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
        Schema::create(config('workflow.tables.flow_instances'), function (Blueprint $table) {
            $table->id();

            $table->morphs('instanceable');
            /**
             * The entity that this flow instance is associated with.
             * e.g. an Order in shopping, a Card in CRM, a Ticket in support system, an Invoice in billing, etc.
             * This allows tracking the flow state of various entities in the system.
             *
             * Note: instanceable_type must match the subject_type in the flow table.
             */

            $table->foreignId('flow_transition_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            /**
             * The current transition the instance is in.
             *
             * This points to the flow_transition table to determine the current state and possible next states.
             */

            $table->nullableMorphs('actor');
            /**
             * The user or system that initiated or is responsible for the current state of the instance.
             * This can be a User model, System process, or any other entity that can perform actions in the system.
             *
             * Note: actor can be null for system-initiated transitions.
             */

            $table->dateTime('started_at')->useCurrent();
            $table->dateTime('completed_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_instances'));
    }
};
