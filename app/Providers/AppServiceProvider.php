<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Relation::enforceMorphMap([
            'task'     => \App\Models\Task::class,
            'project'  => \App\Models\Project::class,
            'pipeline' => \App\Models\Pipeline::class,
            'pipeline_stage' => \App\Models\PipelineStage::class,
            'workspace' => \App\Models\Workspace::class,
            'user'=> \App\Models\User::class,

        ]);
    }
}
