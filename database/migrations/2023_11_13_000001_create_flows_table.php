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
        Schema::create(config('workflow.tables.flow'), function (Blueprint $table) {
            $table->id();

            $table->string('subject_type')->index();
            /**
             * Model class name of subject
             * model must be use string `status` field
             *
             * e.g. App\Models\Order
             */

            $table->string('subject_scope')->nullable()->index();
            /**
             * optional scoping of subject
             *
             * e.g. tenant ID, user ID, organization ID
             * validation: regex:/^[a-zA-Z0-9_\-]+$/
             */

            $table->unsignedBigInteger('subject_key')->nullable()->index();
            /**
             * optional primary key of subject
             *
             * e.g. 12345
             * validation: integer > 0
             */

            $table->unsignedInteger('version')->default(1)->index();
            /**
             * version of flow for subject
             * start with 1, increment for new versions
             * picking order: higher = more recent
             */

            $table->boolean('is_default')->default(false)->index();
            /**
             * default flow for subject (per version)
             *
             * - when true: preferred choice if multiple candidates match
             * - only one per subject_type+subject_scope+subject_key+version (enforce in app layer)
             * - fallback to the highest version without is_default if none marked
             */

            $table->boolean('status')->default(true)->index();
            /**
             * active status of this flow version
             *
             * - true = active, false = inactive
             */

            $table->dateTime('active_from')->nullable();
            $table->dateTime('active_to')->nullable();
            /**
             * optional active time window (UTC)
             *
             * - compare against UTC "now" in application layer
             * - ensure active_from <= active_to (DB CHECK if supported, otherwise validate)
             */

            // Optional selectors for fast SQL pre-filtering
            $table->string('channel')->nullable()->index();
            /**
             * optional channel of flow
             *
             * e.g. web, pos, api
             * validation: regex:/^[a-zA-Z0-9_\-\/]+$/
             */

            $table->smallInteger('ordering')->default(0)->index();
            /**
             * relative ordering of flow for subject
             *
             * higher = more preferred
             */

            $table->unsignedTinyInteger('rollout_pct')->nullable()->index();
            /**
             * optional rollout percentage (0-100)
             *
             * null = 100% (no canary)
             * use stable hashing in app layer to route a percentage of instances
             */

            $table->string('environment')->nullable()->index();
            /**
             * optional environment of flow
             * useful for testing flows in non-prod environments
             *
             * e.g. dev, test, staging, prod
             * validation: regex:/^[a-zA-Z0-9_\-]+$/
             */

            $table->softDeletes();
            $table->timestamps();

            $table->unique([
                'subject_type',
                'subject_scope',
                'subject_key',
                'version'
            ], 'FLOW_UNIQUE');

            $table->index([
                'subject_type',
                'subject_scope',
                'subject_key',
                'status',
                'is_default',
                'active_from',
                'active_to'
            ], 'FLOW_PICK_IDX');
        });

        // Optional: add CHECK constraints (where supported).
        try {
            $conn   = Schema::getConnection();
            $table  = config('workflow.tables.flow');

            // Use Laravel grammar to properly quote the table for the current driver.
            $wrappedTable = $conn->getQueryGrammar()->wrapTable($table);

            DB::statement("ALTER TABLE {$wrappedTable} ADD CONSTRAINT flow_rollout_pct_chk CHECK (rollout_pct IS NULL OR (rollout_pct >= 0 AND rollout_pct <= 100))");
            DB::statement("ALTER TABLE {$wrappedTable} ADD CONSTRAINT flow_active_window_chk CHECK (active_from IS NULL OR active_to IS NULL OR active_from <= active_to)");
        } catch (\Throwable $e) {
            // Databases without CHECK support: enforce in application layer.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow'));
    }
};
