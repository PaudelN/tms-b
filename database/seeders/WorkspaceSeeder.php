<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding Workspaces...');

        $admin = User::where('email', 'admin@example.com')->first();
        $testUser = User::where('email', 'test@example.com')->first();

        if (! $admin || ! $testUser) {
            $this->command->error('Please run UserSeeder first!');

            return;
        }

        // ── 1. Admin workspaces ────────────────────────────────────────────────
        $adminWorkspaceNames = [
            'Marketing Campaign',
            'Website Redesign Project',
            'Mobile App Development',
            'Q1 Product Launch',
            'Team Building Activities',
        ];

        foreach ($adminWorkspaceNames as $baseName) {
            for ($i = 1; $i <= 10; $i++) {
                $this->createWorkspace($baseName, $i, $admin->id, 'active');
            }
        }

        // ── 2. Archived workspaces for admin ──────────────────────────────────
        for ($i = 1; $i <= 2; $i++) {
            $this->createWorkspace('Archived Workspace', $i, $admin->id, 'archived');
        }

        // ── 3. Test user workspaces ───────────────────────────────────────────
        $testWorkspaceNames = [
            'Personal Projects',
            'Learning Resources',
            'Side Hustle Ideas',
        ];

        foreach ($testWorkspaceNames as $baseName) {
            for ($i = 1; $i <= 5; $i++) {
                $this->createWorkspace($baseName, $i, $testUser->id, 'active');
            }
        }

        // ── 4. Random workspaces for other users ──────────────────────────────
        $otherUsers = User::whereNotIn('email', ['admin@example.com', 'test@example.com'])->get();

        foreach ($otherUsers as $user) {
            $count = rand(5, 15);
            for ($i = 1; $i <= $count; $i++) {
                $this->createWorkspace('Workspace', $i, $user->id, 'active');
            }
        }

        $this->command->info('✓ Workspaces seeded successfully!');
    }

    /**
     * Create a workspace with a guaranteed-unique slug.
     * Slug = slugified-name + '-' + userId + '-' + iterator
     * e.g. "marketing-campaign-1-3" — can never collide across users or loops.
     */
    private function createWorkspace(string $baseName, int $i, int $userId, string $status): void
    {
        $name = "{$baseName} #{$i}";
        $slug = Str::slug($baseName).'-'.$userId.'-'.$i;

        Workspace::factory()->create([
            'name' => $name,
            'slug' => $slug,
            'user_id' => $userId,
            'status' => $status,
        ]);
    }
}
