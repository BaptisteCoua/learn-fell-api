<?php

namespace Functional\Learning\Tests\Concerns;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\CardProgress;
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
     * The card of a new one-question subject the user learns, put in a box and a due date.
     */
    protected function learnedCard(User $user, int $box, string $nextReviewOn): CardProgress
    {
        $subject = $this->publishedSubjectWithQuestions(1);
        $this->learn($user, $subject)->assertOk();
        $card = CardProgress::query()->where('user_id', $user->id)->where('subject_id', $subject->id)->sole();
        $card->update(['box' => $box, 'next_review_on' => $nextReviewOn]);

        return $card;
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

    /**
     * @param  array{answer_id?: mixed, answered_at?: mixed, due_on?: mixed}  $offlineFields  what a device sends for an answer given offline
     */
    protected function answer(User $user, int|string $cardId, bool|string $known, array $offlineFields = []): TestResponse
    {
        $fields = [['name' => 'card_progress_id', 'value' => $cardId], ['name' => 'known', 'value' => $known]];

        foreach ($offlineFields as $name => $value) {
            $fields[] = ['name' => $name, 'value' => $value];
        }

        return $this->actingAs($user)->postJson('/api/card-progress/actions/answer', ['fields' => $fields]);
    }
}
