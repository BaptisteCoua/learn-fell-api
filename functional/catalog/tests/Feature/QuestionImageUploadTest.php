<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\QuestionImage;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-002, FR-018 — user story 1: sending an image before attaching it to a question.
 */
class QuestionImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURES = __DIR__.'/../fixtures/images';

    private const FORMAT_MESSAGE = 'Choisissez une image au format JPEG, PNG ou WebP.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
    }

    private function fixture(string $name): UploadedFile
    {
        return new UploadedFile(self::FIXTURES."/{$name}", $name, test: true);
    }

    private function upload(?User $user, ?UploadedFile $file): TestResponse
    {
        if ($user !== null) {
            $this->actingAs($user);
        }

        return $this->postJson('/api/question-images', $file === null ? [] : ['file' => $file]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedImages(): array
    {
        return [
            'jpeg' => ['rotated-with-gps.jpg'],
            'png' => ['transparent.png'],
            'webp' => ['photo.webp'],
        ];
    }

    #[DataProvider('acceptedImages')]
    public function test_an_image_is_kept_pending_for_its_uploader(string $fixture): void
    {
        $user = User::factory()->create();

        $response = $this->upload($user, $this->fixture($fixture));

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'width', 'height', 'variant_widths']]);
        $image = QuestionImage::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($image->isPending());
        $this->assertSame($user->id, $image->uploader_id);
        $this->assertSame($image->variant_widths, $response->json('data.variant_widths'));
        $this->assertSame([$image->width, $image->height], [$response->json('data.width'), $response->json('data.height')]);
        foreach ($image->variant_widths as $width) {
            Storage::disk(config('catalog.images.disk'))->assertExists("{$image->directory()}/{$width}.webp");
        }
    }

    /**
     * @return array<string, array{\Closure(): UploadedFile, string}>
     */
    public static function refusedFiles(): array
    {
        return [
            'more than 5 MB' => [fn (): UploadedFile => UploadedFile::fake()->create('big.jpg', 7168, 'image/jpeg'), 'L’image ne doit pas dépasser 5 Mo.'],
            'more than 8,000 px' => [fn (): UploadedFile => UploadedFile::fake()->image('wide.jpg', 9000, 10), 'L’image ne doit pas dépasser 8 000 pixels de côté.'],
            'svg' => [fn (): UploadedFile => new UploadedFile(self::FIXTURES.'/vector.svg', 'vector.svg', test: true), self::FORMAT_MESSAGE],
            'animated gif' => [fn (): UploadedFile => new UploadedFile(self::FIXTURES.'/animated.gif', 'animated.gif', test: true), self::FORMAT_MESSAGE],
            'text renamed to jpg' => [fn (): UploadedFile => new UploadedFile(self::FIXTURES.'/text-renamed.jpg', 'text-renamed.jpg', test: true), self::FORMAT_MESSAGE],
        ];
    }

    /**
     * @param  \Closure(): UploadedFile  $file
     */
    #[DataProvider('refusedFiles')]
    public function test_a_file_outside_the_limits_is_refused(\Closure $file, string $message): void
    {
        $this->upload(User::factory()->create(), $file())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => $message]);

        $this->assertDatabaseCount('question_images', 0);
        $this->assertSame([], Storage::disk(config('catalog.images.disk'))->allFiles());
    }

    public function test_a_missing_file_is_refused(): void
    {
        $this->upload(User::factory()->create(), null)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => self::FORMAT_MESSAGE]);
    }

    public function test_an_animated_webp_is_refused_and_leaves_nothing(): void
    {
        $this->upload(User::factory()->create(), $this->fixture('animated.webp'))
            ->assertUnprocessable()
            ->assertExactJson(['code' => 'image_invalid_format', 'message' => self::FORMAT_MESSAGE]);

        $this->assertDatabaseCount('question_images', 0);
        $this->assertSame([], Storage::disk(config('catalog.images.disk'))->allFiles());
    }

    public function test_a_visitor_cannot_send_an_image(): void
    {
        $this->upload(null, $this->fixture('photo.webp'))->assertUnauthorized();
    }

    public function test_an_unconfirmed_address_cannot_send_an_image(): void
    {
        $this->upload(User::factory()->unverified()->create(), $this->fixture('photo.webp'))->assertForbidden();

        $this->assertDatabaseCount('question_images', 0);
    }

    public function test_an_account_sends_30_images_a_minute_at_most(): void
    {
        $user = User::factory()->create();

        for ($upload = 1; $upload <= 30; $upload++) {
            $this->upload($user, UploadedFile::fake()->image("photo-{$upload}.jpg", 10, 10))->assertCreated();
        }

        $this->upload($user, UploadedFile::fake()->image('photo-31.jpg', 10, 10))->assertTooManyRequests();
    }
}
