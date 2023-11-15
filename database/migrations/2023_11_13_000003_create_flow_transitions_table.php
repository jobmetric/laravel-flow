<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JobMetric\Flow\Models\FlowState;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $_flow_state = (new FlowState)->getTable();

        /**
         * #translatable
         */
        Schema::create(config('workflow.tables.flow_transition'), function (Blueprint $table) use ($_flow_state) {
            $table->id();

            $table->foreignId('flow_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->foreignId('from')->index()->constrained($_flow_state)->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('to')->index()->constrained($_flow_state)->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('slug')->nullable()->index();

            $table->unsignedBigInteger('roll_id')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_transition'));
    }
};
