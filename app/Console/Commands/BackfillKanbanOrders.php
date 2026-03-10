<?php

namespace App\Console\Commands;

use App\Models\KanbanOrder;
use Illuminate\Console\Command;

/**
 * One-time command to create kanban_orders rows for existing data.
 *
 * Run once after migration on existing data:
 *   php artisan kanban:backfill "App\Models\Workspace"
 *
 * Safe to run multiple times — uses upsert so existing rows are not overwritten
 * (only new rows are created for entities that don't have an order yet).
 */
class BackfillKanbanOrders extends Command
{
    protected $signature = 'kanban:backfill {model : Fully qualified model class (e.g. "App\\Models\\Workspace")}';

    protected $description = 'Create kanban_orders rows for all existing records of a kanban-enabled model';

    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Class [{$modelClass}] not found.");

            return self::FAILURE;
        }

        $instance = new $modelClass;

        if (! method_exists($instance, 'kanbanColumnField')) {
            $this->error("[{$modelClass}] does not use the HasKanban trait.");

            return self::FAILURE;
        }

        $field = $instance->kanbanColumnField();
        $table = $instance->getTable();

        $total = $modelClass::count();
        $this->info("Backfilling kanban_orders for [{$modelClass}] ({$total} records)...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $skipped = 0;

        // Process in chunks to avoid memory exhaustion on large tables
        $modelClass::orderBy('created_at')->chunk(500, function ($records) use (
            $modelClass, $field, &$processed, &$skipped, $bar
        ) {
            // Find which entity_ids already have kanban_orders rows
            $existingIds = KanbanOrder::where('entity_type', $modelClass)
                ->whereIn('entity_id', $records->pluck('id'))
                ->pluck('entity_id')
                ->toArray();

            // Only backfill records that don't already have a row
            $toBackfill = $records->filter(
                fn ($r) => ! in_array($r->id, $existingIds)
            );

            if ($toBackfill->isEmpty()) {
                $skipped += $records->count();
                $bar->advance($records->count());

                return;
            }

            // Group by stage value so we assign sequential sort_orders per stage
            $byStage = $toBackfill->groupBy(function ($item) use ($field) {
                $value = $item->{$field};

                return $value instanceof \BackedEnum ? $value->value : (string) $value;
            });

            foreach ($byStage as $stageValue => $items) {
                if (! $stageValue) {
                    continue;
                }

                // Get the current max for this stage — we append, not overwrite
                $currentMax = KanbanOrder::getMaxOrder($modelClass, $stageValue);

                $rows = $items->values()->map(function ($item, $index) use (
                    $modelClass, $stageValue, $currentMax
                ) {
                    return [
                        'entity_type' => $modelClass,
                        'entity_id' => $item->id,
                        'stage_value' => $stageValue,
                        'sort_order' => $currentMax + 1 + $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                \DB::table('kanban_orders')->upsert(
                    $rows,
                    ['entity_type', 'entity_id'],
                    ['stage_value', 'sort_order', 'updated_at']
                );
            }

            $processed += $toBackfill->count();
            $skipped += $records->count() - $toBackfill->count();
            $bar->advance($records->count());
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Done. Created: {$processed} rows. Skipped (already existed): {$skipped}.");

        return self::SUCCESS;
    }
}
