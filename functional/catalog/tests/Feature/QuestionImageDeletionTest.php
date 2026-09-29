<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * FR-017, FR-018 — a removed image is gone for good, rows and files, and a pending image does
 * not outlive 24 hours.
 */
class QuestionImageDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
    }

    private function assertImageDeleted(QuestionImage $image): void
    {
        $this->assertModelMissing($image);
        $this->assertFalse(Storage::disk(config('catalog.images.disk'))->directoryExists($image->directory()));
    }

    private function assertImageKept(QuestionImage $image): void
    {
        $this->assertModelExists($image);
        Storage::disk(config('catalog.images.disk'))->assertExists("{$image->directory()}/960.webp");
    }

    public function test_deleting_a_question_deletes_its_images(): void
    {
        $question = Question::factory()->create(['position' => 1]);
        $images = collect([0, 1])->map(fn (int $position): QuestionImage => QuestionImage::factory()->attachedTo($question, $position)->create());
        $otherImage = QuestionImage::factory()->create();

        $question->delete();

        $images->each(fn (QuestionImage $image) => $this->assertImageDeleted($image));
        $this->assertImageKept($otherImage);
    }

    public function test_deleting_a_subject_deletes_the_images_of_its_questions(): void
    {
        $subject = Subject::factory()->published()->create();
        $images = collect([1, 2])->map(fn (int $position): QuestionImage => QuestionImage::factory()
            ->attachedTo(Question::factory()->for($subject)->create(['position' => $position]), 0)
            ->create());

        $subject->delete();

        $images->each(fn (QuestionImage $image) => $this->assertImageDeleted($image));
    }

    public function test_a_pending_image_expires_after_24_hours(): void
    {
        $uploader = User::factory()->create();
        $expired = QuestionImage::factory()->pending()->for($uploader, 'uploader')->create();
        $this->travel(1)->hours();
        $recent = QuestionImage::factory()->pending()->for($uploader, 'uploader')->create();
        $attached = QuestionImage::factory()->create();

        $this->travel(23)->hours();
        $this->travel(1)->minutes();
        $this->artisan('model:prune', ['--model' => [QuestionImage::class]])->assertSuccessful();

        $this->assertImageDeleted($expired);
        $this->assertImageKept($recent);
        $this->assertImageKept($attached);
    }

    public function test_a_rolled_back_deletion_keeps_the_files(): void
    {
        $image = QuestionImage::factory()->create();

        try {
            DB::transaction(function () use ($image): void {
                $image->question->delete();

                throw new RuntimeException('The save failed.');
            });
        } catch (RuntimeException) {
        }

        $this->assertImageKept($image);
    }
}
