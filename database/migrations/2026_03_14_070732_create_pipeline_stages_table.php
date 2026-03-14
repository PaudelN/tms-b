<?php

use App\Models\Pipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Pipeline::class)
                ->index()
                ->constrained()
                ->cascadeOnDelete();

            $table->bigInteger('created_by')->nullable();

            $table->string('name');

            // Slug unique per pipeline — composite unique below
            $table->string('slug');

            // Optional friendlier label shown in the UI
            $table->string('display_name')->nullable();

            // Drag-sort position — lower = leftmost column
            $table->unsignedInteger('display_order')->default(0)->index();

            // 1 = active, 0 = inactive  →  PipelineStageStatus enum
            $table->tinyInteger('status')->default(1)->index();

            // Hex colour for the stage chip/badge (e.g. "#3B82F6")
            $table->string('color', 7)->nullable();

            // Optional WIP limit. NULL = unlimited.
            $table->unsignedSmallInteger('wip_limit')->nullable();

            $table->json('extras')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Slug uniqueness scoped to its pipeline
            $table->unique(['pipeline_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
