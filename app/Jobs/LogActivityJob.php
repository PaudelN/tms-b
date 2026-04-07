<?php

namespace App\Jobs;

use App\Events\ActivityLogged;
use App\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $event,
        public readonly array $properties,
        public readonly ?int $causerId,
    ) {}

    public function handle(): void
    {
        $morphAlias = Relation::getMorphAlias($this->subjectType);

        $activity = Activity::create([
            'subject_type' => $morphAlias,
            'subject_id' => $this->subjectId,
            'event' => $this->event,
            'properties' => $this->properties ?: null,
            'causer_id' => $this->causerId,
        ]);

        // Real-time broadcast — fires synchronously inside the worker
        ActivityLogged::dispatch($activity);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[LogActivityJob] failed', [
            'subject' => "{$this->subjectType}#{$this->subjectId}",
            'event' => $this->event,
            'error' => $exception->getMessage(),
        ]);
    }
}
