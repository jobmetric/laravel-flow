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
        /**
         * #translatable
         */
        Schema::create('flow_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('driver')->index();
            /**
             * value: driver name
             * use: read file in address: app/Flow/{model}/Tasks/{driver_name}.php
             */

            $table->json('config')->nullable();
            /**
             * value: json
             * use: {
             *     // any config
             * }
             */

            $table->integer('ordering')->default(0);

            $table->boolean('status')->default(true)->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_tasks');
    }
};
