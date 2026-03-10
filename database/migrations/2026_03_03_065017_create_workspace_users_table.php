<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable() // null until user accepts invite
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->string('status')->default('pending');
            $table->uuid('invite_token')->nullable()->unique();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_users');
    }
};
