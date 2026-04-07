<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('causer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('event', 100);
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(
                ['subject_type', 'subject_id', 'created_at'],
                'activities_subject_created_idx'
            );
            $table->index(
                ['subject_type', 'subject_id', 'event'],
                'activities_subject_event_idx'
            );
            $table->index(
                ['causer_id', 'created_at'],
                'activities_causer_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
