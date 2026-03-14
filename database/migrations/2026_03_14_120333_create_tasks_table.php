<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // ── Hierarchy FKs ─────────────────────────────────────────────────
            $table->foreignId('pipeline_id')
                ->constrained('pipelines')
                ->cascadeOnDelete();

            $table->foreignId('pipeline_stage_id')
                ->constrained('pipeline_stages')
                ->restrictOnDelete(); // prevent stage delete while tasks exist

            // Optional direct project FK — avoids JOIN when querying by project.
            // Always mirrors pipeline.project_id; kept in sync via observer.
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            // ── Identity ──────────────────────────────────────────────────────
            // Human-readable task number, unique per pipeline (T-0001, T-0002…)
            // Generated in Task::boot() — never null after creation.
            $table->string('task_number', 20)->unique();

            // ── Core fields ───────────────────────────────────────────────────
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // TaskPriority enum

            $table->date('due_date')->nullable();
            $table->json('extra')->nullable();

            // ── Ownership ─────────────────────────────────────────────────────
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── Kanban ordering ───────────────────────────────────────────────
            // sort_order within a stage — mirrored in kanban_orders table.
            // Kept here as a fast fallback when kanban_orders has no entry yet.
            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────────
            // Hot-path: "all tasks for pipeline X in stage Y, ordered"
            $table->index(['pipeline_id', 'pipeline_stage_id', 'sort_order'], 'tasks_pipeline_stage_order');
            $table->index(['project_id'],  'tasks_project_id');
            $table->index(['created_by'],       'tasks_created_by');
            $table->index(['due_date'],         'tasks_due_date');
            $table->index(['priority'],         'tasks_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
