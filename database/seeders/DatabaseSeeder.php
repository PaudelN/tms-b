<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        $this->command->newLine();

        $this->call([
            UserSeeder::class,           // Creates admin@example.com, test@example.com + 20 random users
            WorkspaceSeeder::class,      // Creates bulk random workspaces for all users
            DemoWorkspaceSeeder::class,  // Creates: Demo Workspace → Demo Project → Demo Pipeline → 5 stages
            // Future seeders go here:
            // ProjectSeeder::class,
            // TaskSeeder::class,
            // CommentSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 Database seeding completed successfully!');
    }
}
