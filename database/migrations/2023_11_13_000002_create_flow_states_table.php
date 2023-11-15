<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jobmetric\Flow\Enums\TableFlowStateFieldTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * #translatable
         */
        Schema::create(config('workflow.tables.flow_state'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('type')->index();
            /**
             * value: start, end, state
             * use: @extends TableFlowStateFieldTypeEnum
             */

            $table->json('config')->nullable();
            /**
             * value: json
             * use: {
             *     "color": "#f00",
             *     "position": {
             *         "x": 0,
             *         "y": 0
             *     },
             * }
             */

            $table->string(config('workflow.tables.flow_state'))->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_states');
    }
};
