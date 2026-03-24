<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Project::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->constrained('users')->restrictOnDelete();

            // Display / alias name (editable by the user after upload).
            $table->string('name');

            // Original filename as it was on the user's machine.
            $table->string('original_name');

            // Relative storage path within the disk (e.g. "projects/1/media/abc123.jpg").
            $table->string('path');

            // MIME type (e.g. "image/jpeg", "application/pdf").
            $table->string('mime_type');

            // File size in bytes.
            $table->unsignedBigInteger('size')->default(0);

            // Filesystem disk identifier (e.g. "public", "s3").
            $table->string('disk')->default('public');

            // Arbitrary extra metadata (alt text, caption, dimensions, etc.).
            $table->json('extra')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('mime_type');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
