<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jobmetric\Flow\Enums\FlowStateTypeEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('workflow.tables.flow_state'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_id')->index()->constrained(config('workflow.tables.flow'))->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('type')->index();
            /**
             * type of flow state
             *
             * @extends FlowStateTypeEnum
             * expected values: start, state, end
             */

            $table->json('config')->nullable();
            /**
             * value: json
             * use: {
             *     "color": "#f00",
             *     "position": { "x": 0, "y": 0 }
             * }
             */

            $table->string('status')->nullable()->index();
            /**
             * status field value in subject model
             *
             * - use status field in subject_type in flow table
             * - can be null for 'state' type (any status)
             * - must not be null for 'start' and 'end' types (CHECK below; enforce in app if DB ignores CHECK)
             */

            $table->timestamps();
        });

        // CHECK + uniqueness for single start/end per flow (cross-dialect)
        try {
            $conn   = Schema::getConnection();
            $gram   = $conn->getQueryGrammar();
            $table  = config('workflow.tables.flow_state');

            $wrappedTable = $gram->wrapTable($table);
            $colFlow      = $gram->wrap('flow_id');
            $colType      = $gram->wrap('type');
            $colStatus    = $gram->wrap('status');

            // CHECK: status required for start/end; optional for state
            DB::statement("
                ALTER TABLE {$wrappedTable}
                ADD CONSTRAINT flow_state_status_type_chk
                CHECK (
                    ({$colType} = 'state' AND {$colStatus} IS NULL)
                    OR
                    ({$colType} IN ('start','end') AND {$colStatus} IS NOT NULL)
                )
            ");

            // Single start per flow
            DB::statement("CREATE UNIQUE INDEX flow_state_one_start ON {$wrappedTable} ((CASE WHEN {$colType} = 'start' THEN {$colFlow} ELSE NULL END))");

            // Single end per flow
            DB::statement("CREATE UNIQUE INDEX flow_state_one_end ON {$wrappedTable} ((CASE WHEN {$colType} = 'end' THEN {$colFlow} ELSE NULL END))");
        } catch (\Throwable $e) {
            // If CHECK/functional indexes are unsupported (e.g., old MySQL), enforce in application layer.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_state'));
    }
};
