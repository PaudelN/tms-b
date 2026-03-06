<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_orders', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('stage_value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            
            // One row per entity — a workspace is in exactly one stage at a time.
            // NOTE: If you later need multi-stage entities (e.g. task in multiple pipelines),
            // change this to unique(['entity_type', 'entity_id', 'stage_value'])
            $table->unique(['entity_type', 'entity_id'], 'kanban_orders_entity_unique');

            // Hot-path index: "give me all Workspace IDs in stage=active, ordered"
            // This is an index-only scan — never touches actual row data
            $table->index(
                ['entity_type', 'stage_value', 'sort_order'],
                'kanban_orders_stage_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_orders');
    }
};
