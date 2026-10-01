<?php

namespace Functional\Learning\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-004 — the numbers shown before an author chooses what becomes of their
 * published subjects.
 */
class AuthoredSubjectsSummaryTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    public function test_it_counts_the_published_subjects_and_their_other_learners(): void
    {
        $author = User::factory()->create();
        $verbs = $this->publishedSubjectWithQuestions(2);
        $dates = $this->publishedSubjectWithQuestions(2);
        $verbs->update(['author_id' => $author->id]);
        $dates->update(['author_id' => $author->id]);
        Subject::factory()->for($author, 'author')->create();
        $both = User::factory()->create();
        $this->learn($both, $verbs);
        $this->learn($both, $dates);
        $this->learn(User::factory()->create(), $verbs);
        $this->learn($author, $verbs);

        $this->actingAs($author)->getJson('/api/learning/authored-subjects-summary')
            ->assertOk()
            ->assertExactJson(['published_subjects_count' => 2, 'learners_count' => 2]);
    }

    public function test_an_account_without_subjects_gets_zeros(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/learning/authored-subjects-summary')
            ->assertOk()
            ->assertExactJson(['published_subjects_count' => 0, 'learners_count' => 0]);
    }

    public function test_a_visitor_gets_nothing(): void
    {
        $this->getJson('/api/learning/authored-subjects-summary')->assertUnauthorized();
    }
}
