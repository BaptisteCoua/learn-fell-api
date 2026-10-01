<?php

namespace Functional\Catalog\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-015 — a cancelled deletion gives the author their subjects back as they were.
 */
class RestoreWithheldSubjectsTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    private function logIn(User $user): void
    {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->app['auth']->forgetGuards();
    }

    public function test_withheld_subjects_are_published_again_under_the_author_name(): void
    {
        $author = User::factory()->pendingDeletion(keepsPublishedSubjects: false)->create(['display_name' => 'Inès Martin']);
        $withheld = Subject::factory()->for($author, 'author')->create([
            'status' => SubjectStatus::Withheld,
            'published_at' => '2026-09-01 08:00:00',
        ]);
        $retiredMeanwhile = Subject::factory()->for($author, 'author')->retired()->create();
        $othersWithheld = Subject::factory()->create(['status' => SubjectStatus::Withheld]);

        $this->logIn($author);

        $this->assertSame(SubjectStatus::Published, $withheld->fresh()->status);
        $this->assertEquals(CarbonImmutable::parse('2026-09-01 08:00:00'), $withheld->fresh()->published_at);
        $this->assertSame(SubjectStatus::Retired, $retiredMeanwhile->fresh()->status);
        $this->assertSame(SubjectStatus::Withheld, $othersWithheld->fresh()->status);

        $direct = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $withheld->getKey()]],
            'includes' => [['relation' => 'author']],
        ]);
        $this->assertSame('Inès Martin', $direct->json('data.0.author.display_name'));
    }

    public function test_kept_subjects_get_their_author_name_back(): void
    {
        $author = User::factory()->pendingDeletion(keepsPublishedSubjects: true)->create(['display_name' => 'Inès Martin']);
        $subject = Subject::factory()->for($author, 'author')->published()->create();

        $this->logIn($author);

        $direct = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $subject->getKey()]],
            'includes' => [['relation' => 'author']],
        ]);
        $this->assertSame('Inès Martin', $direct->json('data.0.author.display_name'));
    }
}
