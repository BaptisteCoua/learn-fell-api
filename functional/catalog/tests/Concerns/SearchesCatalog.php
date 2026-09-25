<?php

namespace Functional\Catalog\Tests\Concerns;

use Functional\Users\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Calls the lomkit search endpoints the way the web app does, as a visitor or as a user.
 */
trait SearchesCatalog
{
    /**
     * @param  array<string, mixed>  $search
     */
    protected function searchResource(string $resource, array $search = [], ?User $user = null): TestResponse
    {
        $request = $user === null ? $this : $this->actingAs($user);

        return $request->postJson("/api/{$resource}/search", ['search' => $search]);
    }

    /**
     * @return list<int>
     */
    protected function returnedIds(TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('id')->all();
    }
}
