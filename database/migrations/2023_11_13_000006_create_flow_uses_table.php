<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('workflow.tables.flow_uses'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_id')->constrained(config('workflow.tables.flow'))->cascadeOnUpdate()->cascadeOnDelete();

            $table->morphs('flowable');
            /**
             * Polymorphic relation to the model that is part of the flow
             *
             * e.g. App\Models\Order, App\Models\Invoice
             * e.g. 12345
             */

            $table->dateTime('used_at')->useCurrent();

            $table->unique([
                'flow_id',
                'flowable_type',
                'flowable_id'
            ], 'FLOW_USES_UNIQUE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_uses'));
    }
};
