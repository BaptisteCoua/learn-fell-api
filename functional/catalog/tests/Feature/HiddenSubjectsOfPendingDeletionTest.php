<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 004, FR-008 and FR-011 — the drafts, unpublished and retired subjects of an author
 * whose deletion is pending reach nobody but moderation, and never carry the author's name.
 */
class HiddenSubjectsOfPendingDeletionTest extends TestCase
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
            'admin, draft' => ['admin', 'draft', true],
            'admin, retired' => ['admin', 'retired', true],
        ];
    }

    #[DataProvider('viewers')]
    public function test_who_still_sees_an_unpublished_subject_of_a_leaving_author(string $viewer, string $status, bool $isVisible): void
    {
        $author = User::factory()->pendingDeletion(keepsPublishedSubjects: true)->create();
        $factory = Subject::factory()->for($author, 'author');
        $subject = ($status === 'draft' ? $factory : $factory->{$status}())->create(['title' => 'Verbes irréguliers anglais']);
        $user = match ($viewer) {
            'visitor' => null,
            'admin' => User::factory()->create()->assignRole('admin'),
            default => User::factory()->create(),
        };

        $listed = $this->searchResource('subjects', [], $user);
        $searched = $this->searchResource('subjects', [
            'instructions' => [['name' => 'search', 'fields' => [['name' => 'q', 'value' => 'irreguliers']]]],
        ], $user);
        $direct = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $subject->getKey()]],
            'includes' => [['relation' => 'author']],
        ], $user);

        foreach ([$listed, $searched, $direct] as $response) {
            $response->assertOk();
            $this->assertSame($isVisible ? [$subject->getKey()] : [], $this->returnedIds($response));
        }

        if ($isVisible) {
            $this->assertNull($direct->json('data.0.author.display_name'));
        }
    }
}
