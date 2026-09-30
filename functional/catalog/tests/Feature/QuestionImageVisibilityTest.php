<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-016, FR-018, SC-004 — an image is seen exactly by those who may read its question, and is
 * otherwise the same 404 as an image that does not exist.
 */
class QuestionImageVisibilityTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
        $this->author = User::factory()->create();

        // The response a browser gets in production, without the debug trace of the test.
        config(['app.debug' => false]);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function viewers(): array
    {
        $cases = [];
        $visibleTo = [
            'draft' => ['author', 'admin'],
            'published' => ['visitor', 'member', 'author', 'admin'],
            'unpublished' => ['author', 'admin'],
            'retired' => ['author', 'admin'],
            'deleted' => [],
        ];

        foreach ($visibleTo as $status => $viewers) {
            foreach (['visitor', 'member', 'author', 'admin'] as $viewer) {
                $cases["{$viewer}, {$status}"] = [$viewer, $status, in_array($viewer, $viewers, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('viewers')]
    public function test_who_loads_an_image_by_its_address(string $viewer, string $status, bool $isVisible): void
    {
        $image = $this->imageOnSubject($status === 'published' ? 'published' : ($status === 'retired' ? 'retired' : 'draft'));
        $subject = $image->question->subject;

        if ($status === 'unpublished') {
            $subject->forceFill(['status' => 'published', 'published_at' => now()])->save();
            $subject->forceFill(['status' => 'draft'])->save();
        } elseif ($status === 'deleted') {
            $subject->delete();
        }

        $response = $this->loadImage($this->viewer($viewer), $image->id);

        if ($isVisible) {
            $response->assertOk()->assertHeader('Content-Type', 'image/webp');
            $this->assertSame("variant 960 of image {$image->id}", $response->streamedContent());
        } else {
            $this->assertIsTheMissingImageResponse($response);
        }
    }

    public function test_a_pending_image_is_seen_by_its_uploader_only(): void
    {
        $image = QuestionImage::factory()->pending()->for($this->author, 'uploader')->create();

        $this->loadImage($this->author, $image->id)->assertOk();
        $this->assertIsTheMissingImageResponse($this->loadImage(null, $image->id));
        $this->assertIsTheMissingImageResponse($this->loadImage(User::factory()->create(), $image->id));
        $this->assertIsTheMissingImageResponse($this->loadImage($this->viewer('admin'), $image->id));
    }

    public function test_a_retired_subject_restored_and_published_again_shows_its_images_again(): void
    {
        $image = $this->imageOnSubject('published');
        $subject = $image->question->subject;

        $subject->forceFill(['status' => 'retired', 'retired_reason' => 'Droits d’auteur', 'retired_at' => now()])->save();
        $this->assertIsTheMissingImageResponse($this->loadImage(null, $image->id));

        $subject->forceFill(['status' => 'draft', 'retired_reason' => null, 'retired_at' => null])->save();
        $subject->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->loadImage(null, $image->id)->assertOk();
    }

    public function test_the_images_of_a_published_subject_are_cached_privately_for_an_hour(): void
    {
        $response = $this->loadImage(null, $this->imageOnSubject('published')->id);

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertCacheControl($response, ['max-age=3600', 'private']);
    }

    public function test_the_images_of_a_hidden_subject_are_never_stored(): void
    {
        $response = $this->loadImage($this->author, $this->imageOnSubject('draft')->id);

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertCacheControl($response, ['no-store', 'private']);
    }

    public function test_a_width_outside_the_variants_is_missing(): void
    {
        $image = $this->imageOnSubject('published');

        $this->assertIsTheMissingImageResponse($this->loadImage(null, $image->id, 700));
    }

    public function test_a_small_image_is_served_in_its_widest_variant(): void
    {
        $question = Question::factory()->for(Subject::factory()->published()->for($this->author, 'author'))->create(['position' => 1]);
        $image = QuestionImage::factory()->small()->attachedTo($question, 0)->create();

        $response = $this->loadImage(null, $image->id, 1600);

        $response->assertOk();
        $this->assertSame("variant 960 of image {$image->id}", $response->streamedContent());
    }

    public function test_search_and_includes_never_return_a_pending_image_nor_one_of_a_hidden_subject(): void
    {
        $member = User::factory()->create();
        $visible = $this->imageOnSubject('published');
        $this->imageOnSubject('draft');
        $this->imageOnSubject('retired');
        QuestionImage::factory()->pending()->for($member, 'uploader')->create();

        foreach ([null, $member] as $user) {
            $this->assertSame([$visible->id], $this->returnedIds($this->searchResource('question-images', [], $user)->assertOk()));

            $questions = $this->searchResource('questions', ['includes' => [['relation' => 'images']]], $user)->assertOk();
            $this->assertSame([$visible->id], collect($questions->json('data'))->pluck('images')->flatten(1)->pluck('id')->all());
        }
    }

    private function imageOnSubject(string $status): QuestionImage
    {
        $factory = Subject::factory()->for($this->author, 'author');
        $subject = ($status === 'draft' ? $factory : $factory->{$status}())->create();
        $question = Question::factory()->for($subject)->create(['position' => 1]);

        return QuestionImage::factory()->attachedTo($question, 0)->for($this->author, 'uploader')->create();
    }

    private function viewer(string $viewer): ?User
    {
        return match ($viewer) {
            'visitor' => null,
            'author' => $this->author,
            'admin' => User::factory()->create()->assignRole('admin'),
            default => User::factory()->create(),
        };
    }

    private function loadImage(?User $user, int $id, int $width = 960): TestResponse
    {
        if ($user === null) {
            $this->app['auth']->forgetGuards();
        } else {
            $this->actingAs($user);
        }

        return $this->get("/api/question-images/{$id}/{$width}");
    }

    private function assertIsTheMissingImageResponse(TestResponse $response): void
    {
        $response->assertNotFound();
        $missing = $this->loadImage(null, 999_999);

        $this->assertSame($missing->getContent(), $response->getContent());
        $this->assertSame($missing->headers->get('Content-Type'), $response->headers->get('Content-Type'));
    }

    /**
     * @param  list<string>  $directives
     */
    private function assertCacheControl(TestResponse $response, array $directives): void
    {
        $sent = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
        sort($sent);

        $this->assertSame($directives, $sent);
    }
}
