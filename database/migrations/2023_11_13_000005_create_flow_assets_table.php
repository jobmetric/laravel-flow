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
        Schema::create('flow_assets', function (Blueprint $table) {
            $table->id();

            $table->morphs('assetable');

            $table->foreignId('flow_state_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();

            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flow_assets');
    }
};
