<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| One wildcard channel covers every activity-capable entity.
| Pattern: {morphAlias}.{id}  →  task.42, pipeline.7, etc.
|
| To add a new entity, just add it to the $authorizedTypes array —
| no new Broadcast::channel() call needed.
|
*/

$authorizedTypes = [
    'task',
    'pipeline',
    'pipeline_stage',
    'project',
    'workspace',
];

foreach ($authorizedTypes as $type) {
    Broadcast::channel("{$type}.{id}", function (\App\Models\User $user, int $id): bool {
        // $user is injected by Reverb/Pusher after Sanctum auth —
        // it is always an authenticated User here, never null.
        // Intelephense flags auth()->check() because it thinks auth()
        // returns a mixed type; using the injected $user avoids that entirely.
        return true; // swap for policy: $user->can('view', ModelClass::find($id))
    });
}


// When you want per-entity policies later, just swap return true per type inside the loop:
// phpforeach ($authorizedTypes as $type) {
//     $modelClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type);

//     Broadcast::channel("{$type}.{id}", function (\App\Models\User $user, int $id) use ($modelClass): bool {
//         if (! $modelClass) return true;
//         $model = $modelClass::find($id);
//         return $model ? $user->can('view', $model) : false;
//     });
// }
// This uses your existing morph map from AppServiceProvider to resolve the model class automatically — so adding a new entity to the morph map and to $authorizedTypes is the only change ever needed.
