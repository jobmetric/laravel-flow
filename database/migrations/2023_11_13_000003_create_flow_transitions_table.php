<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('workflow.tables.flow_transition'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_id')->index()->constrained(config('workflow.tables.flow'))->cascadeOnDelete()->cascadeOnUpdate();

            $table->foreignId('from')->nullable()->index()->constrained(config('workflow.tables.flow_state'))->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('to')->nullable()->index()->constrained(config('workflow.tables.flow_state'))->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('slug')->nullable()->index();
            /**
             * transition identifier
             *
             *  - optional slug to identify transition
             *  - must be unique per (flow_id, slug)
             *  - can be used in code to trigger specific transition
             *  - can be used in UI to show available transitions
             *  - can be null for multiple transitions between same states
             *  - should be descriptive, short, and use only [a-z0-9-] characters
             *  - recommended to use format: {subject_type: in flow table}-{from_state}-{to_state}
             */

            $table->timestamps();

            $table->unique(['flow_id', 'from', 'to'], 'FLOW_TRANSITION_UNIQUE');
            $table->unique(['flow_id', 'slug'], 'FLOW_TRANSITION_SLUG_UNIQUE');
        });

        // CHECK constraints + cross-dialect uniqueness for start/end edges (single path)
        try {
            $conn = Schema::getConnection();
            $gram = $conn->getQueryGrammar();
            $table = config('workflow.tables.flow_transition');

            // Properly quote table/columns for current driver (MySQL/Postgres)
            $wrappedTable = $gram->wrapTable($table);
            $colFlow = $gram->wrap('flow_id');
            $colFrom = $gram->wrap('from');
            $colTo = $gram->wrap('to');

            // Base checks (MySQL < 8.0.16 parses but ignores CHECK; enforce in app if needed)
            DB::statement("ALTER TABLE {$wrappedTable} ADD CONSTRAINT flow_transition_not_both_null_chk CHECK (({$colFrom} IS NOT NULL) OR ({$colTo} IS NOT NULL))");
            DB::statement("ALTER TABLE {$wrappedTable} ADD CONSTRAINT flow_transition_not_same_chk CHECK (({$colFrom} IS NULL OR {$colTo} IS NULL OR {$colFrom} <> {$colTo}))");

            // One start-edge and one end-edge per flow, via functional unique indexes (works on PG & MySQL 8+)
            DB::statement("CREATE UNIQUE INDEX flow_transition_one_start ON {$wrappedTable} ((CASE WHEN {$colFrom} IS NULL THEN {$colFlow} ELSE NULL END))");
            DB::statement("CREATE UNIQUE INDEX flow_transition_one_end ON {$wrappedTable} ((CASE WHEN {$colTo} IS NULL THEN {$colFlow} ELSE NULL END))");
        } catch (\Throwable $e) {
            // If CHECK/functional indexes are unsupported on this engine/version, enforce in application layer.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_transition'));
    }
};
