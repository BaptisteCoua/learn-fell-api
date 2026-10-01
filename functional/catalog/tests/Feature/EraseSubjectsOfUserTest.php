<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature 004, FR-018 and FR-019 — what the erasure of an author does to their subjects and
 * images. The learners' progress is covered in the learning layer, the reports in moderation.
 */
class EraseSubjectsOfUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
    }

    private function prune(): void
    {
        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();
    }

    private function leavingAuthor(bool $keepsPublishedSubjects): User
    {
        return User::factory()->pendingDeletion(keepsPublishedSubjects: $keepsPublishedSubjects, daysAgo: 31)->create();
    }

    private function subjectWithImage(User $author, SubjectStatus $status): QuestionImage
    {
        $subject = Subject::factory()->for($author, 'author')->create(['status' => $status]);
        $question = Question::factory()->for($subject)->create(['position' => 1]);

        return QuestionImage::factory()->attachedTo($question, 0)->create(['uploader_id' => $author->id]);
    }

    public function test_everything_erased_takes_every_subject_its_questions_and_its_image_files(): void
    {
        $author = $this->leavingAuthor(false);
        $withheld = $this->subjectWithImage($author, SubjectStatus::Withheld);
        $question = $withheld->question;
        $subject = $question->subject;
        $draft = Subject::factory()->for($author, 'author')->create();
        $othersSubject = Subject::factory()->published()->create();

        $this->prune();

        $this->assertModelMissing($author);
        $this->assertModelMissing($subject);
        $this->assertModelMissing($question);
        $this->assertModelMissing($withheld);
        $this->assertFalse(Storage::disk(config('catalog.images.disk'))->directoryExists($withheld->directory()));
        $this->assertModelMissing($draft);
        $this->assertModelExists($othersSubject);
    }

    public function test_kept_subjects_stay_published_without_author_with_their_images(): void
    {
        $author = $this->leavingAuthor(true);
        $kept = $this->subjectWithImage($author, SubjectStatus::Published);
        $draft = Subject::factory()->for($author, 'author')->create();
        $retired = Subject::factory()->for($author, 'author')->retired()->create();
        $pendingImage = QuestionImage::factory()->pending()->create(['uploader_id' => $author->id]);

        $this->prune();

        $this->assertModelMissing($author);
        $subject = $kept->question->subject->fresh();
        $this->assertSame(SubjectStatus::Published, $subject->status);
        $this->assertNull($subject->author_id);
        $this->assertNull($kept->fresh()->uploader_id);
        Storage::disk(config('catalog.images.disk'))->assertExists("{$kept->directory()}/960.webp");
        $this->assertModelMissing($draft);
        $this->assertModelMissing($retired);
        $this->assertModelMissing($pendingImage);
    }

    public function test_images_an_erased_moderator_put_on_other_subjects_stay(): void
    {
        $moderator = $this->leavingAuthor(true);
        $othersSubject = Subject::factory()->published()->create();
        $image = QuestionImage::factory()
            ->attachedTo(Question::factory()->for($othersSubject)->create(['position' => 1]), 0)
            ->create(['uploader_id' => $moderator->id]);

        $this->prune();

        $this->assertModelMissing($moderator);
        $this->assertNull($image->fresh()->uploader_id);
    }
}
