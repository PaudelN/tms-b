<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Project::class)
                ->index()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            // Slug unique per project, not globally.
            // The composite unique constraint is the hard DB guard.
            // spatie/sluggable appends -1, -2 etc. for collisions within the same project.
            $table->string('slug');

            $table->text('description')->nullable();

            // 1 = active, 0 = inactive — maps to PipelineStatus enum
            $table->tinyInteger('status')->default(1)->index();

            $table->json('extras')->nullable();

            $table->bigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Enforce slug uniqueness per project at the DB level
            $table->unique(['project_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
