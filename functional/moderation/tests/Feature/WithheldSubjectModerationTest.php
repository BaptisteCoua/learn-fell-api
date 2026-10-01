<?php

namespace Functional\Moderation\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Moderation\Tests\Concerns\Moderates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004 — moderation still retires a subject withheld by its leaving author, or one its
 * erased author left to the community.
 */
class WithheldSubjectModerationTest extends TestCase
{
    use Moderates, RefreshDatabase;

    public function test_a_withheld_subject_can_be_retired(): void
    {
        $subject = Subject::factory()->create(['status' => SubjectStatus::Withheld]);

        $this->decide($this->moderator(), $subject, 'retired', 'Contenu recopié.')->assertOk();

        $this->assertSame(SubjectStatus::Retired, $subject->fresh()->status);
    }

    public function test_a_subject_without_author_can_be_retired(): void
    {
        $subject = Subject::factory()->published()->create(['author_id' => null]);

        $this->decide($this->moderator(), $subject, 'retired', 'Contenu recopié.')->assertOk();

        $this->assertSame(SubjectStatus::Retired, $subject->fresh()->status);
    }
}
