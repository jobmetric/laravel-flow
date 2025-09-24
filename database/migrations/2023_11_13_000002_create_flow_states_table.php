<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jobmetric\Flow\Enums\FlowStateTypeEnum;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('workflow.tables.flow_state'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('flow_id')->index()->constrained(config('workflow.tables.flow'))->cascadeOnDelete()->cascadeOnUpdate();

            $table->string('type')->index();
            /**
             * type of flow state
             *
             * @extends FlowStateTypeEnum
             * expected values: start, state
             */

            $table->json('config')->nullable();
            /**
             * value: json
             * use: {
             *     "color": "#f00",
             *     "icon": "play",
             *     "position": { "x": 0, "y": 0 }
             *     "is_terminal": true|false, // only for 'state' type; if true, meaning end state
             * }
             */

            $table->string('status')->nullable()->index();
            /**
             * status field value in subject model
             *
             * - use status field in subject_type in flow table
             * - can be null for 'state' type (any status)
             * - must not be null for 'start' types (CHECK below; enforce in app if DB ignores CHECK)
             */

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('workflow.tables.flow_state'));
    }
};
