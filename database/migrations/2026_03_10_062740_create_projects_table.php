<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->constrained('users')->restrictOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            $table->string('status')->default(ProjectStatus::DRAFT->value);

            $table->string('visibility')->default(ProjectVisibility::PRIVATE->value);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->json('extra')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // slug unique per workspace, not globally
            $table->unique(['workspace_id', 'slug']);

            $table->index('status');
            $table->index('created_by');
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
