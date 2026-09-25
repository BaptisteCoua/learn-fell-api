<?php

namespace Functional\Moderation\Tests\Concerns;

use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;
use Illuminate\Testing\TestResponse;

trait Moderates
{
    protected function moderator(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo(['reports.review', 'subjects.moderate', 'moderation.history.view']));
    }

    protected function report(User $reporter, Subject $subject, string $reason = 'incorrect', ?string $comment = null): TestResponse
    {
        return $this->actingAs($reporter)->postJson('/api/reports/mutate', [
            'mutate' => [['operation' => 'create', 'attributes' => ['subject_id' => $subject->id, 'reason' => $reason, 'comment' => $comment]]],
        ]);
    }

    protected function decide(User $moderator, Subject $subject, string $decision, ?string $reason = null): TestResponse
    {
        return $this->actingAs($moderator)->postJson('/api/moderation-decisions/mutate', [
            'mutate' => [['operation' => 'create', 'attributes' => ['subject_id' => $subject->id, 'decision' => $decision, 'reason' => $reason]]],
        ]);
    }

    /**
     * @param  array<string, mixed>  $search
     */
    protected function search(User $user, string $resource, array $search = []): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/{$resource}/search", ['search' => $search]);
    }
}
