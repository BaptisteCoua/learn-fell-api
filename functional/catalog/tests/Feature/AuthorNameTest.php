<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-010 and FR-011 — the author's name is hidden as soon as a deletion is
 * requested, and the author is null once the account is erased.
 */
class AuthorNameTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    private function authorOf(Subject $subject): mixed
    {
        $response = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $subject->getKey()]],
            'includes' => [['relation' => 'author']],
        ]);

        return $response->assertOk()->json('data.0.author');
    }

    public function test_an_active_author_is_named(): void
    {
        $author = User::factory()->create(['display_name' => 'Inès Martin']);
        $subject = Subject::factory()->for($author, 'author')->published()->create();

        $this->assertSame(['id' => $author->getKey(), 'display_name' => 'Inès Martin'], $this->authorOf($subject));
    }

    public function test_an_author_whose_deletion_is_pending_is_no_longer_named(): void
    {
        $author = User::factory()->pendingDeletion(keepsPublishedSubjects: true)->create(['display_name' => 'Inès Martin']);
        $subject = Subject::factory()->for($author, 'author')->published()->create();

        $this->assertNull($this->authorOf($subject)['display_name']);
    }

    public function test_a_subject_kept_after_its_author_was_erased_has_no_author(): void
    {
        $subject = Subject::factory()->published()->create(['author_id' => null]);

        $this->assertNull($this->authorOf($subject));
    }
}
