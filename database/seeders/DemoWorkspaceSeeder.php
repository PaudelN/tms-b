<?php

namespace Database\Seeders;

use App\Enums\PipelineStageStatus;
use App\Enums\PipelineStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Enums\WorkspaceStatus;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Demo Workspace...');

        // ── Admin user (created by UserSeeder) ────────────────────────────────
        $admin = User::where('email', 'admin@example.com')->first();

        if (! $admin) {
            $this->command->error('Admin user not found. UserSeeder must run first.');
            return;
        }

        // ── Workspace ─────────────────────────────────────────────────────────
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'demo-workspace'],
            [
                'name'        => 'Demo Workspace',
                'description' => 'A demo workspace created for client handover.',
                'user_id'     => $admin->id,
                'status'      => WorkspaceStatus::ACTIVE,
                'extra'       => [
                    'color' => '#6366f1',
                    'icon'  => 'briefcase',
                ],
            ]
        );

        // Attach admin as workspace owner-member if not already attached
        if (! $workspace->members()->where('user_id', $admin->id)->exists()) {
            $workspace->members()->attach($admin->id, [
                'is_owner' => true,
                'status'   => 'active',
            ]);
        }

        $this->command->info("  ✓ Workspace : {$workspace->name}");

        // ── Project ───────────────────────────────────────────────────────────
        $project = Project::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'slug'         => 'demo-project',
            ],
            [
                'created_by'  => $admin->id,
                'name'        => 'Demo Project',
                'description' => 'A demo project showcasing the pipeline & task workflow.',
                'status'      => ProjectStatus::IN_PROGRESS,
                'visibility'  => ProjectVisibility::PRIVATE,
                'start_date'  => now()->toDateString(),
                'end_date'    => now()->addMonths(3)->toDateString(),
                'extra'       => [
                    'color' => '#10b981',
                    'icon'  => 'folder',
                ],
            ]
        );

        $this->command->info("  ✓ Project   : {$project->name}");

        // ── Pipeline ──────────────────────────────────────────────────────────
        $pipeline = Pipeline::firstOrCreate(
            [
                'project_id' => $project->id,
                'slug'       => 'demo-pipeline',
            ],
            [
                'created_by'  => $admin->id,
                'name'        => 'Demo Pipeline',
                'description' => 'Default task pipeline for the demo project.',
                'status'      => PipelineStatus::ACTIVE,
                'extras'      => [
                    'default' => true,
                ],
            ]
        );

        $this->command->info("  ✓ Pipeline  : {$pipeline->name}");

        // ── Pipeline Stages ───────────────────────────────────────────────────
        $stages = [
            [
                'name'          => 'Backlog',
                'display_name'  => 'Backlog',
                'color'         => '#94a3b8',  // slate-400
                'wip_limit'     => null,
                'display_order' => 1,
            ],
            [
                'name'          => 'In Progress',
                'display_name'  => 'In Progress',
                'color'         => '#3b82f6',  // blue-500
                'wip_limit'     => 5,
                'display_order' => 2,
            ],
            [
                'name'          => 'In Review',
                'display_name'  => 'In Review',
                'color'         => '#f59e0b',  // amber-500
                'wip_limit'     => 3,
                'display_order' => 3,
            ],
            [
                'name'          => 'QA',
                'display_name'  => 'QA / Testing',
                'color'         => '#8b5cf6',  // violet-500
                'wip_limit'     => 3,
                'display_order' => 4,
            ],
            [
                'name'          => 'Done',
                'display_name'  => 'Done ✓',
                'color'         => '#22c55e',  // green-500
                'wip_limit'     => null,
                'display_order' => 5,
            ],
        ];

        foreach ($stages as $stageData) {
            $stage = PipelineStage::firstOrCreate(
                [
                    'pipeline_id' => $pipeline->id,
                    'slug'        => Str::slug($stageData['name']),
                ],
                [
                    'created_by'    => $admin->id,
                    'name'          => $stageData['name'],
                    'display_name'  => $stageData['display_name'],
                    'display_order' => $stageData['display_order'],
                    'color'         => $stageData['color'],
                    'wip_limit'     => $stageData['wip_limit'],
                    'status'        => PipelineStageStatus::ACTIVE,
                    'extras'        => [],
                ]
            );

            $wip = $stage->wip_limit ? "WIP limit: {$stage->wip_limit}" : 'No WIP limit';
            $this->command->info(
                "    [{$stage->display_order}] {$stage->display_name} — {$wip}"
            );
        }

        $this->command->info('✓ Demo workspace seeded successfully!');
    }
}
