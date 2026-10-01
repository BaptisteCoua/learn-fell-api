<?php

namespace Functional\Catalog\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 004, FR-004 and FR-009 — « Tout effacer » withholds the author's published subjects
 * as soon as the deletion is requested: they reach nobody but moderation.
 */
class WithheldSubjectsTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog, WritesCatalog;

    private function requestDeletion(User $author, ?bool $keepsPublishedSubjects): TestResponse
    {
        $response = $this->actingAs($author, 'web')->postJson('/api/account/deletion', [
            'password' => 'password',
            'keep_published_subjects' => $keepsPublishedSubjects,
        ]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    public function test_an_author_with_published_subjects_must_choose(): void
    {
        $author = User::factory()->create();
        $subject = Subject::factory()->for($author, 'author')->published()->create();

        $this->requestDeletion($author, null)
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_choice_required']);

        $this->assertFalse($author->fresh()->isPendingDeletion());
        $this->assertSame(SubjectStatus::Published, $subject->fresh()->status);
    }

    public function test_the_choice_is_recorded(): void
    {
        $author = User::factory()->create();
        Subject::factory()->for($author, 'author')->published()->create();

        $this->requestDeletion($author, true)->assertOk();

        $this->assertTrue($author->fresh()->keeps_published_subjects);
    }

    public function test_an_author_without_published_subject_needs_no_choice(): void
    {
        $author = User::factory()->create();
        $draft = Subject::factory()->for($author, 'author')->create();

        $this->requestDeletion($author, null)->assertOk();
        $this->assertSame(SubjectStatus::Draft, $draft->fresh()->status);

        $other = User::factory()->create();
        $this->requestDeletion($other, false)->assertOk();
        $this->assertTrue($other->fresh()->isPendingDeletion());
    }

    public function test_everything_erased_withholds_the_published_subjects_only(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00'));
        $author = User::factory()->create();
        $published = Subject::factory()->for($author, 'author')->published()->create(['published_at' => '2026-09-01 08:00:00']);
        $draft = Subject::factory()->for($author, 'author')->create();
        $retired = Subject::factory()->for($author, 'author')->retired()->create();
        $othersSubject = Subject::factory()->published()->create();

        $this->requestDeletion($author, false)->assertOk();

        $this->assertSame(SubjectStatus::Withheld, $published->fresh()->status);
        $this->assertEquals(CarbonImmutable::parse('2026-09-01 08:00:00'), $published->fresh()->published_at);
        $this->assertSame(SubjectStatus::Draft, $draft->fresh()->status);
        $this->assertSame(SubjectStatus::Retired, $retired->fresh()->status);
        $this->assertSame(SubjectStatus::Published, $othersSubject->fresh()->status);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function viewers(): array
    {
        return [
            'visitor' => ['visitor', false],
            'other member' => ['member', false],
            'moderator' => ['moderator', true],
        ];
    }

    #[DataProvider('viewers')]
    public function test_who_still_sees_a_withheld_subject_and_its_questions(string $viewer, bool $isVisible): void
    {
        $author = User::factory()->pendingDeletion(keepsPublishedSubjects: false)->create();
        $subject = Subject::factory()->for($author, 'author')->create(['title' => 'Verbes irréguliers anglais', 'status' => SubjectStatus::Withheld]);
        $question = Question::factory()->for($subject)->create(['position' => 1]);
        $user = match ($viewer) {
            'visitor' => null,
            'moderator' => tap(User::factory()->create(), fn (User $moderator) => $moderator->givePermissionTo('subjects.moderate')),
            default => User::factory()->create(),
        };

        $listed = $this->searchResource('subjects', [], $user);
        $searched = $this->searchResource('subjects', [
            'instructions' => [['name' => 'search', 'fields' => [['name' => 'q', 'value' => 'irreguliers']]]],
        ], $user);
        $direct = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $subject->getKey()]],
        ], $user);
        $questions = $this->searchResource('questions', [
            'filters' => [['field' => 'subject_id', 'value' => $subject->getKey()]],
        ], $user);

        foreach ([$listed, $searched, $direct] as $response) {
            $response->assertOk();
            $this->assertSame($isVisible ? [$subject->getKey()] : [], $this->returnedIds($response));
        }
        $questions->assertOk();
        $this->assertSame($isVisible ? [$question->getKey()] : [], $this->returnedIds($questions));
    }

    public function test_a_withheld_subject_is_read_only_but_for_moderation(): void
    {
        $subject = Subject::factory()->create(['title' => 'Avant', 'status' => SubjectStatus::Withheld]);
        $author = $subject->author;
        $author->forceFill(['deletion_requested_at' => null])->save();

        $this->updateResource($author, 'subjects', $subject->getKey(), ['title' => 'Après'])
            ->assertUnprocessable()
            ->assertJson(['code' => 'subject_withheld']);
        $this->assertSame('Avant', $subject->fresh()->title);
    }
}
