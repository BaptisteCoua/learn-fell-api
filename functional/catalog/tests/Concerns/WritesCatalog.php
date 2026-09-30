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
     * Saves a question with the full list of its recto images, as the editor does: a create
     * without a key, an update with one.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $images  from attachImage() and detachImage()
     */
    protected function mutateQuestionWithImages(User $user, ?int $key, array $attributes, array $images): TestResponse
    {
        $mutation = $key === null
            ? ['operation' => 'create', 'attributes' => $attributes]
            : ['operation' => 'update', 'key' => $key, 'attributes' => $attributes];

        return $this->actingAs($user)->postJson('/api/questions/mutate', [
            'mutate' => [[...$mutation, 'relations' => ['images' => $images]]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attachImage(int $key, ?string $alt, int $position): array
    {
        return ['operation' => 'update', 'key' => $key, 'attributes' => ['alt' => $alt, 'position' => $position]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detachImage(int $key): array
    {
        return ['operation' => 'detach', 'key' => $key];
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
