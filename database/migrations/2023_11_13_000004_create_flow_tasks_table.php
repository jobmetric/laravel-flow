<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('workflow.tables.flow_task'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_transition_id')->index()->constrained(config('workflow.tables.flow_transition'))->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('driver')->index();
            /**
             * Driver class name of task
             *
             * use: read file in address: app/Flow/{model}/{TaskType}/{driver_name}.php
             *
             * TaskType means the type of task, can be:
             * - Restriction
             * - Validation
             * - Action
             */

            $table->json('config')->nullable();
            /**
             * Configuration for the task
             *
             * value: json
             * use: {
             *     "key": "value",
             * }
             */

            $table->unsignedSmallInteger('ordering')->default(0)->index();
            /**
             * ordering of task within the transition
             *
             * - lower numbers run first
             * - allows multiple tasks per transition
             * - used to control sequence of tasks
             */

            $table->boolean('status')->default(true)->index();
            /**
             * status of task
             *
             * - allows soft-disable of tasks without deletion
             * - true = active, false = inactive
             */

            $table->timestamps();

            $table->unique([
                'flow_transition_id',
                'ordering'
            ],'FLOW_TASK_ORDER_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_task'));
    }
};
