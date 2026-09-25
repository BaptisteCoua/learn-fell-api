<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-023, SC-006 — drafts and retired subjects never reach anyone not allowed to see them,
 * whether listed, searched or asked for directly.
 */
class SubjectVisibilityTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function viewers(): array
    {
        return [
            'visitor, draft' => ['visitor', 'draft', false],
            'visitor, retired' => ['visitor', 'retired', false],
            'other member, draft' => ['member', 'draft', false],
            'other member, retired' => ['member', 'retired', false],
            'author, draft' => ['author', 'draft', true],
            'author, retired' => ['author', 'retired', true],
            'admin, draft' => ['admin', 'draft', true],
            'admin, retired' => ['admin', 'retired', true],
            'visitor, published' => ['visitor', 'published', true],
        ];
    }

    #[DataProvider('viewers')]
    public function test_who_sees_a_subject_in_the_list_the_search_and_directly(string $viewer, string $status, bool $isVisible): void
    {
        $author = User::factory()->create();
        $factory = Subject::factory()->for($author, 'author');
        $subject = ($status === 'draft' ? $factory : $factory->{$status}())->create(['title' => 'Verbes irréguliers anglais']);
        $user = $this->viewer($viewer, $author);

        $listed = $this->searchResource('subjects', [], $user);
        $searched = $this->searchResource('subjects', [
            'instructions' => [['name' => 'search', 'fields' => [['name' => 'q', 'value' => 'irreguliers']]]],
        ], $user);
        $direct = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $subject->getKey()]],
        ], $user);

        foreach ([$listed, $searched, $direct] as $response) {
            $response->assertOk();
            $this->assertSame($isVisible ? [$subject->getKey()] : [], $this->returnedIds($response));
        }
    }

    #[DataProvider('viewers')]
    public function test_the_questions_of_a_subject_follow_its_visibility(string $viewer, string $status, bool $isVisible): void
    {
        $author = User::factory()->create();
        $factory = Subject::factory()->for($author, 'author');
        $subject = ($status === 'draft' ? $factory : $factory->{$status}())->create();
        $question = Question::factory()->for($subject)->create(['position' => 1]);

        $response = $this->searchResource('questions', [
            'filters' => [['field' => 'subject_id', 'value' => $subject->getKey()]],
        ], $this->viewer($viewer, $author));

        $response->assertOk();
        $this->assertSame($isVisible ? [$question->getKey()] : [], $this->returnedIds($response));
    }

    private function viewer(string $viewer, User $author): ?User
    {
        return match ($viewer) {
            'visitor' => null,
            'author' => $author,
            'admin' => User::factory()->create()->assignRole('admin'),
            default => User::factory()->create(),
        };
    }
}
