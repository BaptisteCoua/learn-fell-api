<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-010 — « Laisser mes sujets publiés »: the subjects stay readable and
 * learnable from the request on, without their author's name.
 */
class KeptSubjectsOfPendingDeletionTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    public function test_kept_subjects_stay_in_the_catalogue_without_the_name(): void
    {
        $author = User::factory()->create(['display_name' => 'Inès Martin']);
        $subject = Subject::factory()->for($author, 'author')->published()->create();
        $question = Question::factory()->for($subject)->create(['position' => 1]);

        $this->actingAs($author, 'web')->postJson('/api/account/deletion', [
            'password' => 'password',
            'keep_published_subjects' => true,
        ])->assertOk();

        $this->assertSame(SubjectStatus::Published, $subject->fresh()->status);
        $direct = $this->searchResource('subjects', [
            'filters' => [['field' => 'id', 'value' => $subject->getKey()]],
            'includes' => [['relation' => 'author']],
        ]);
        $this->assertSame([$subject->getKey()], $this->returnedIds($direct));
        $this->assertNull($direct->json('data.0.author.display_name'));
        $this->assertStringNotContainsString('Inès Martin', $direct->content());

        $questions = $this->searchResource('questions', [
            'filters' => [['field' => 'subject_id', 'value' => $subject->getKey()]],
        ], User::factory()->create());
        $this->assertSame([$question->getKey()], $this->returnedIds($questions));
    }
}
