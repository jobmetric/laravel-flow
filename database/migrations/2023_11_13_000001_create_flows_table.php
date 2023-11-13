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
        Schema::create('flows', function (Blueprint $table) {
            $table->id();

            $table->string('driver')->index();
            /**
             * value: driver name
             * use: read file in address: app/Flow/{model}/drivers.php
             */

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flows');
    }
};
