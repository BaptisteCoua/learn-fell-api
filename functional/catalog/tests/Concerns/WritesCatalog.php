<?php

namespace Functional\Catalog\Tests\Concerns;

use Functional\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Writes through the lomkit endpoints, as the web app does.
 */
trait WritesCatalog
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createResource(User $user, string $resource, array $attributes): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/{$resource}/mutate", [
            'mutate' => [['operation' => 'create', 'attributes' => $attributes]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function updateResource(User $user, string $resource, int $key, array $attributes): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/{$resource}/mutate", [
            'mutate' => [['operation' => 'update', 'key' => $key, 'attributes' => $attributes]],
        ]);
    }

    /**
     * @param  list<int>  $keys
     */
    protected function deleteResource(User $user, string $resource, array $keys): TestResponse
    {
        return $this->actingAs($user)->deleteJson("/api/{$resource}", ['resources' => $keys]);
    }

    /**
     * Runs an action on one subject, found by its key.
     *
     * @param  array<string, mixed>  $fields
     */
    protected function subjectAction(User $user, string $action, int $subjectId, array $fields = []): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/subjects/actions/{$action}", [
            'search' => ['filters' => [['field' => 'id', 'value' => $subjectId]]],
            'fields' => collect($fields)->map(fn ($value, $name) => ['name' => $name, 'value' => $value])->values()->all(),
        ]);
    }

    /**
     * @param  list<int>  $ids
     */
    protected function reorderQuestions(User $user, int $subjectId, array $ids): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/questions/actions/reorder', [
            'fields' => [['name' => 'subject_id', 'value' => $subjectId], ['name' => 'ids', 'value' => $ids]],
        ]);
    }
}
