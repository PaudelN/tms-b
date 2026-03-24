<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | media
        |----------------------------------------------------------------------
        | One row = one physical file on a storage disk.
        | The path is built as:  {directory}/{filename}.{extension}
        |
        | aggregate_type  → "image" | "video" | "audio" | "document" | "other"
        |   Lets you group files without parsing MIME types everywhere.
        |
        | original_filename → what the user's browser sent (nice for UX display)
        | alt               → accessibility / caption text for images
        | uploaded_by       → nullable FK so media can survive user deletion
        */
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Storage location
            $table->string('disk', 32)->default('public');
            $table->string('directory')->default('');
            $table->string('filename');
            $table->string('extension', 32);

            // MIME / type metadata
            $table->string('mime_type', 128);
            $table->string('aggregate_type', 32)->index();   // image|video|audio|document|other
            $table->unsignedBigInteger('size');              // bytes

            // Optional UX metadata
            $table->string('original_filename')->nullable(); // browser filename
            $table->string('alt')->nullable();               // alt text / caption

            // Ownership (nullable → media survives user deletion)
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // A file path must be unique per disk
            $table->unique(['disk', 'directory', 'filename', 'extension']);
        });

        /*
        |----------------------------------------------------------------------
        | mediables  (polymorphic pivot)
        |----------------------------------------------------------------------
        | Links one media record to ANY model (User, Task, Project, …)
        | via morph columns.
        |
        | tag   → named slot on the owning model, e.g. "avatar", "attachments"
        |          Allows a model to have multiple distinct media groups.
        |
        | order → position within a tag group (for ordered galleries / lists).
        |
        | Composite PK: (media_id, mediable_type, mediable_id, tag)
        |   Prevents duplicate attachments of the same file under the same tag.
        */
        Schema::create('mediables', function (Blueprint $table) {
            $table->foreignId('media_id')
                ->constrained('media')
                ->cascadeOnDelete();

            // Polymorphic target
            $table->string('mediable_type');
            $table->unsignedBigInteger('mediable_id');

            // Slot name on the owning model
            $table->string('tag')->default('default')->index();

            // Sort order within a tag group
            $table->unsignedInteger('order')->default(0)->index();

            // Composite primary key — prevents duplicate attach under same tag
            $table->primary(['media_id', 'mediable_type', 'mediable_id', 'tag']);

            // Index for "give me all media for model X"
            $table->index(['mediable_type', 'mediable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('media');
    }
};
