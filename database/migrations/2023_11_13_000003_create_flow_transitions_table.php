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

            // Unique constraint for (flow_id, from, to) - allows self-loops (from = to) and null values
            // Note: NULL values are considered distinct in unique constraints, so we can have multiple transitions with null from or to
            $table->unique(['flow_id', 'from', 'to'], 'FLOW_TRANSITION_UNIQUE');
            $table->unique(['flow_id', 'slug'], 'FLOW_TRANSITION_SLUG_UNIQUE');
        });

        // CHECK constraints + cross-dialect uniqueness for start edges (single path)
        try {
            $conn = Schema::getConnection();
            $gram = $conn->getQueryGrammar();
            $table = config('workflow.tables.flow_transition');

            // Properly quote table/columns for current driver (MySQL/Postgres)
            $wrappedTable = $gram->wrapTable($table);
            $colFlow = $gram->wrap('flow_id');
            $colFrom = $gram->wrap('from');
            $colTo = $gram->wrap('to');

            // Base check: at least one of from or to must not be null
            // (we can have from=null for generic input, to=null for generic output, but not both null)
            DB::statement("ALTER TABLE {$wrappedTable} ADD CONSTRAINT flow_transition_not_both_null_chk CHECK (({$colFrom} IS NOT NULL) OR ({$colTo} IS NOT NULL))");

            // Note: We removed the constraint that prevented from == to to allow self-loops
            // Self-loops are now allowed (except for start states, which is enforced in application layer)

            // One start-edge per flow, via functional unique indexes (works on PG & MySQL 8+)
            // This ensures only one transition from start state exists per flow
            DB::statement("CREATE UNIQUE INDEX flow_transition_one_start ON {$wrappedTable} ((CASE WHEN {$colFrom} IS NULL THEN {$colFlow} ELSE NULL END))");
        } catch (Throwable $e) {
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
