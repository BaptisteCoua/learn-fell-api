<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-001, FR-003 to FR-005, FR-008, FR-017 — user story 1: the images of a recto are saved
 * with the question, in one mutate.
 */
class QuestionImageAttachTest extends TestCase
{
    use RefreshDatabase, WritesCatalog;

    private User $author;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
        $this->author = User::factory()->create();
        $this->subject = Subject::factory()->for($this->author, 'author')->create();
    }

    private function pendingImageOf(User $user): QuestionImage
    {
        return QuestionImage::factory()->pending()->for($user, 'uploader')->create();
    }

    private function questionWithImages(int $count, string $recto = '<p>Quel oiseau ?</p>'): Question
    {
        $question = Question::factory()->for($this->subject)->create(['position' => 1, 'recto_html' => $recto]);

        for ($position = 0; $position < $count; $position++) {
            QuestionImage::factory()->attachedTo($question, $position)->for($this->author, 'uploader')->create();
        }

        return $question;
    }

    private function assertImageDeleted(QuestionImage $image): void
    {
        $this->assertModelMissing($image);
        $this->assertSame([], Storage::disk(config('catalog.images.disk'))->allFiles($image->directory()));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function imageCounts(): array
    {
        return ['1 image' => [1], '4 images' => [4]];
    }

    #[DataProvider('imageCounts')]
    public function test_the_author_creates_a_question_with_described_images(int $count): void
    {
        $images = collect(range(0, $count - 1))->map(fn (): QuestionImage => $this->pendingImageOf($this->author));

        $this->mutateQuestionWithImages($this->author, null, [
            'subject_id' => $this->subject->id,
            'recto_html' => '<p>Quel oiseau ?</p>',
            'verso_html' => '<p>Le Grand Duc</p>',
        ], $images->map(fn (QuestionImage $image, int $position): array => $this->attachImage($image->id, "Hibou numéro {$position}", $position))->all())
            ->assertOk();

        $question = $this->subject->questions()->sole();
        $this->assertSame($images->pluck('id')->all(), $question->images()->pluck('id')->all());
        $this->assertSame(range(0, $count - 1), $question->images()->pluck('position')->all());
        $this->assertSame('Hibou numéro 0', $question->images()->first()->alt);
    }

    public function test_the_author_adds_images_to_an_existing_question(): void
    {
        $question = $this->questionWithImages(1);
        $kept = $question->images()->sole();
        $added = $this->pendingImageOf($this->author);

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($kept->id, 'Hibou de face', 0),
            $this->attachImage($added->id, 'Hibou en vol', 1),
        ])->assertOk();

        $this->assertSame([$kept->id, $added->id], $question->images()->pluck('id')->all());
        $this->assertSame(['Hibou de face', 'Hibou en vol'], $question->images()->pluck('alt')->all());
    }

    public function test_a_recto_holds_4_images_at_most(): void
    {
        $question = $this->questionWithImages(0);
        $images = collect(range(0, 4))->map(fn (): QuestionImage => $this->pendingImageOf($this->author));

        $this->mutateQuestionWithImages($this->author, $question->id, [], $images->map(
            fn (QuestionImage $image, int $index): array => $this->attachImage($image->id, 'Une image', min($index, 3)),
        )->all())
            ->assertUnprocessable()
            ->assertJson(['code' => 'recto_image_limit', 'message' => 'Un recto porte au plus 4 images.']);

        $this->assertSame(0, $question->images()->count());
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function invalidDescriptions(): array
    {
        return [
            'missing' => [null, 'Décrivez cette image.'],
            'blank' => ['   ', 'Décrivez cette image.'],
            '251 characters' => [str_repeat('a', 251), 'La description ne doit pas dépasser 250 caractères.'],
        ];
    }

    #[DataProvider('invalidDescriptions')]
    public function test_every_image_needs_a_description_of_250_characters_at_most(?string $alt, string $message): void
    {
        $image = $this->pendingImageOf($this->author);

        $this->mutateQuestionWithImages($this->author, null, [
            'subject_id' => $this->subject->id,
            'recto_html' => '<p>Quel oiseau ?</p>',
            'verso_html' => '<p>Le Grand Duc</p>',
        ], [$this->attachImage($image->id, $alt, 0)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mutate.0.relations.images.0.attributes.alt' => $message]);

        $this->assertDatabaseCount('questions', 0);
        $this->assertTrue($image->fresh()->isPending());
    }

    public function test_positions_are_distinct_and_between_0_and_3(): void
    {
        $question = $this->questionWithImages(0);
        [$first, $second] = [$this->pendingImageOf($this->author), $this->pendingImageOf($this->author)];

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($first->id, 'Hibou de face', 1),
            $this->attachImage($second->id, 'Hibou en vol', 1),
        ])->assertUnprocessable()->assertJsonValidationErrors(['mutate.0.relations.images.1.attributes.position']);

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($first->id, 'Hibou de face', 4),
        ])->assertUnprocessable()->assertJsonValidationErrors(['mutate.0.relations.images.0.attributes.position']);

        $this->assertSame(0, $question->images()->count());
    }

    public function test_a_recto_may_hold_images_only(): void
    {
        $image = $this->pendingImageOf($this->author);

        $this->mutateQuestionWithImages($this->author, null, [
            'subject_id' => $this->subject->id,
            'recto_html' => '',
            'verso_html' => '<p>Le Grand Duc</p>',
        ], [$this->attachImage($image->id, 'Hibou aux aigrettes', 0)])->assertOk();

        $question = $this->subject->questions()->sole();
        $this->assertSame('', $question->recto_html);
        $this->assertSame([$image->id], $question->images()->pluck('id')->all());
    }

    public function test_a_recto_without_text_nor_image_is_refused(): void
    {
        $this->createResource($this->author, 'questions', [
            'subject_id' => $this->subject->id,
            'recto_html' => '<p> </p>',
            'verso_html' => '<p>Le Grand Duc</p>',
        ])->assertUnprocessable()->assertJson(['code' => 'recto_empty', 'message' => 'Ajoutez un texte ou une image au recto.']);

        $question = $this->questionWithImages(0);
        $this->updateResource($this->author, 'questions', $question->id, ['recto_html' => ''])
            ->assertUnprocessable()
            ->assertJson(['code' => 'recto_empty']);

        $this->assertSame('<p>Quel oiseau ?</p>', $question->fresh()->recto_html);
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_the_author_reorders_then_removes_an_image(): void
    {
        $question = $this->questionWithImages(3);
        [$first, $second, $third] = $question->images()->get();

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($third->id, 'Troisième', 0),
            $this->attachImage($first->id, 'Première', 1),
            $this->attachImage($second->id, 'Deuxième', 2),
        ])->assertOk();
        $this->assertSame([$third->id, $first->id, $second->id], $question->images()->pluck('id')->all());

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($third->id, 'Troisième', 0),
            $this->attachImage($second->id, 'Deuxième', 1),
        ])->assertOk();

        $this->assertSame([$third->id, $second->id], $question->images()->pluck('id')->all());
        $this->assertImageDeleted($first);
    }

    public function test_the_author_replaces_an_image(): void
    {
        $question = $this->questionWithImages(1);
        $old = $question->images()->sole();
        $new = $this->pendingImageOf($this->author);

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($new->id, 'Nouvelle photo', 0),
            $this->detachImage($old->id),
        ])->assertOk();

        $this->assertSame([$new->id], $question->images()->pluck('id')->all());
        $this->assertImageDeleted($old);
    }

    public function test_a_lone_detach_removes_the_last_image_of_a_question_with_text(): void
    {
        $question = $this->questionWithImages(1);
        $image = $question->images()->sole();

        $this->mutateQuestionWithImages($this->author, $question->id, [], [$this->detachImage($image->id)])->assertOk();

        $this->assertSame(0, $question->images()->count());
        $this->assertImageDeleted($image);
    }

    public function test_a_detach_keeps_the_images_left_out_when_no_image_is_listed(): void
    {
        $question = $this->questionWithImages(2);
        [$first, $second] = $question->images()->get();

        $this->mutateQuestionWithImages($this->author, $question->id, [], [$this->detachImage($first->id)])->assertOk();

        $this->assertSame([$second->id], $question->images()->pluck('id')->all());
        $this->assertImageDeleted($first);
    }

    public function test_a_lone_detach_of_the_last_image_of_an_empty_recto_is_refused(): void
    {
        $question = $this->questionWithImages(1, '');
        $image = $question->images()->sole();

        $this->mutateQuestionWithImages($this->author, $question->id, [], [$this->detachImage($image->id)])
            ->assertUnprocessable()
            ->assertJson(['code' => 'recto_empty']);

        $this->assertModelExists($image);
        $this->assertSame($question->id, $image->fresh()->question_id);
    }

    public function test_a_mutate_without_images_keeps_them(): void
    {
        $question = $this->questionWithImages(2, '');

        $this->updateResource($this->author, 'questions', $question->id, ['verso_html' => '<p>Corrigé</p>'])->assertOk();
        $this->updateResource($this->author, 'questions', $question->id, ['recto_html' => ''])->assertOk();

        $this->assertSame(2, $question->images()->count());
        $this->assertSame('<p>Corrigé</p>', $question->fresh()->verso_html);
    }

    public function test_the_pending_image_of_another_account_cannot_be_attached(): void
    {
        $image = $this->pendingImageOf(User::factory()->create());

        $this->mutateQuestionWithImages($this->author, null, [
            'subject_id' => $this->subject->id,
            'recto_html' => '<p>Quel oiseau ?</p>',
            'verso_html' => '<p>Le Grand Duc</p>',
        ], [$this->attachImage($image->id, 'Hibou', 0)])->assertForbidden();

        $this->assertDatabaseCount('questions', 0);
        $this->assertTrue($image->fresh()->isPending());
    }

    public function test_the_image_of_another_question_can_be_neither_attached_nor_detached(): void
    {
        $question = $this->questionWithImages(1);
        $otherImage = QuestionImage::factory()->attachedTo(Question::factory()->for($this->subject)->create(['position' => 2]), 0)->create();

        $this->mutateQuestionWithImages($this->author, $question->id, ['verso_html' => '<p>Changé</p>'], [
            $this->attachImage($otherImage->id, 'Volée', 0),
        ])->assertForbidden();
        $this->mutateQuestionWithImages($this->author, $question->id, [], [$this->detachImage($otherImage->id)])->assertForbidden();

        $this->assertNotSame('<p>Changé</p>', $question->fresh()->verso_html);
        $this->assertSame(1, $question->images()->count());
        $this->assertNotSame($question->id, $otherImage->fresh()->question_id);
        $this->assertModelExists($otherImage);
    }

    public function test_an_image_cannot_be_created_through_the_question(): void
    {
        $question = $this->questionWithImages(0);

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            ['operation' => 'create', 'attributes' => ['alt' => 'Hibou', 'position' => 0]],
        ])->assertForbidden();

        $this->assertDatabaseCount('question_images', 0);
    }

    public function test_an_image_is_never_written_directly(): void
    {
        $question = $this->questionWithImages(1);
        $image = $question->images()->sole();

        $this->updateResource($this->author, 'question-images', $image->id, ['alt' => 'Changée', 'position' => 0])->assertForbidden();
        $this->deleteResource($this->author, 'question-images', [$image->id])->assertForbidden();

        $this->assertModelExists($image);
        $this->assertNotSame('Changée', $image->fresh()->alt);
    }

    public function test_an_administrator_adds_replaces_and_removes_the_images_of_someone_else(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $question = $this->questionWithImages(1);
        $old = $question->images()->sole();
        $added = $this->pendingImageOf($admin);
        $replacement = $this->pendingImageOf($admin);

        $this->mutateQuestionWithImages($admin, $question->id, [], [
            $this->attachImage($old->id, 'Ancienne', 0),
            $this->attachImage($added->id, 'Ajoutée', 1),
        ])->assertOk();
        $this->mutateQuestionWithImages($admin, $question->id, [], [
            $this->attachImage($replacement->id, 'Remplaçante', 0),
            $this->detachImage($old->id),
            $this->detachImage($added->id),
        ])->assertOk();

        $this->assertSame([$replacement->id], $question->images()->pluck('id')->all());
        $this->assertImageDeleted($old);
        $this->assertImageDeleted($added);
    }

    public function test_the_author_of_a_retired_subject_cannot_change_its_images(): void
    {
        $this->subject->forceFill(['status' => 'retired', 'retired_reason' => 'Droits d’auteur', 'retired_at' => now()])->save();
        $question = $this->questionWithImages(1);
        $image = $question->images()->sole();

        $this->mutateQuestionWithImages($this->author, $question->id, [], [
            $this->attachImage($this->pendingImageOf($this->author)->id, 'Nouvelle', 0),
        ])->assertUnprocessable()->assertJson(['code' => 'subject_retired']);

        $this->assertSame([$image->id], $question->images()->pluck('id')->all());
    }
}
