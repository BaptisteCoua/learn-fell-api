<?php

namespace Functional\Learning\Tests\Concerns;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;
use Illuminate\Testing\TestResponse;

trait ReviewsCards
{
    protected function publishedSubjectWithQuestions(int $count): Subject
    {
        $subject = Subject::factory()->published()->create();
        Question::factory()->count($count)->for($subject)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();

        return $subject;
    }

    protected function learn(User $user, Subject $subject): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/learnings/mutate', [
            'mutate' => [['operation' => 'create', 'attributes' => ['subject_id' => $subject->id]]],
        ]);
    }

    /**
     * @param  list<int>  $subjectIds
     */
    protected function dueCards(User $user, array $subjectIds): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/card-progress/search', [
            'search' => [
                'instructions' => [['name' => 'due', 'fields' => [['name' => 'subject_ids', 'value' => $subjectIds]]]],
                'limit' => 100,
            ],
        ]);
    }

    protected function answer(User $user, int $cardId, bool $known): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/card-progress/actions/answer', [
            'fields' => [['name' => 'card_progress_id', 'value' => $cardId], ['name' => 'known', 'value' => $known]],
        ]);
    }
}
