<?php

namespace Functional\Catalog\Tests\Unit;

use Functional\Catalog\Images\QuestionImageProcessor;
use Functional\Catalog\Models\QuestionImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use Technical\Osdd\Exceptions\BusinessRuleException;
use Tests\TestCase;

/**
 * FR-013, FR-014, SC-005 — images are served upright, resized, and without their metadata.
 */
class QuestionImageProcessorTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../fixtures/images';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('catalog.images.disk'));
    }

    private function fixture(string $name): UploadedFile
    {
        return new UploadedFile(self::FIXTURES."/{$name}", $name, test: true);
    }

    private function process(UploadedFile $file): QuestionImage
    {
        $image = new QuestionImage;
        $image->id = 42;

        app(QuestionImageProcessor::class)->process($file, $image);

        return $image;
    }

    private function variant(QuestionImage $image, int $width): string
    {
        return Storage::disk(config('catalog.images.disk'))->get("{$image->directory()}/{$width}.webp");
    }

    /**
     * @return list<string>
     */
    private function riffChunks(string $webp): array
    {
        $chunks = [];

        for ($offset = 12; $offset + 8 <= strlen($webp);) {
            $chunks[] = substr($webp, $offset, 4);
            $size = unpack('V', substr($webp, $offset + 4, 4))[1];
            $offset += 8 + $size + ($size % 2);
        }

        return $chunks;
    }

    public function test_a_phone_photo_is_turned_upright(): void
    {
        $image = $this->process($this->fixture('rotated-with-gps.jpg'));

        $this->assertSame([200, 320], [$image->width, $image->height]);
        $variant = new Imagick;
        $variant->readImageBlob($this->variant($image, 480));
        $this->assertSame([200, 320], [$variant->getImageWidth(), $variant->getImageHeight()]);
    }

    public function test_the_variants_are_webp_files_without_metadata(): void
    {
        $image = $this->process($this->fixture('rotated-with-gps.jpg'));

        foreach ($image->variant_widths as $width) {
            $webp = $this->variant($image, $width);

            $this->assertSame('RIFF', substr($webp, 0, 4));
            $this->assertSame('WEBP', substr($webp, 8, 4));
            $this->assertEmpty(array_intersect(['EXIF', 'XMP ', 'ICCP'], $this->riffChunks($webp)));
            $this->assertStringNotContainsString('CinqCam', $webp);
            $this->assertStringNotContainsString('Phone X1', $webp);
            $this->assertStringNotContainsString('2026:09:29', $webp);
        }
    }

    public function test_a_small_image_is_never_enlarged(): void
    {
        $image = $this->process(UploadedFile::fake()->image('small.jpg', 700, 466));

        $this->assertSame([480, 960], $image->variant_widths);
        $this->assertSame([700, 466], [$image->width, $image->height]);
        $widest = new Imagick;
        $widest->readImageBlob($this->variant($image, 960));
        $this->assertSame(700, $widest->getImageWidth());
        $narrowest = new Imagick;
        $narrowest->readImageBlob($this->variant($image, 480));
        $this->assertSame(480, $narrowest->getImageWidth());
        Storage::disk(config('catalog.images.disk'))->assertMissing("{$image->directory()}/1600.webp");
    }

    public function test_a_large_image_gets_every_variant_and_the_size_of_the_widest(): void
    {
        $image = $this->process(UploadedFile::fake()->image('large.png', 2400, 1600));

        $this->assertSame([480, 960, 1600], $image->variant_widths);
        $this->assertSame([1600, 1067], [$image->width, $image->height]);
    }

    public function test_the_original_file_is_never_kept(): void
    {
        $image = $this->process($this->fixture('transparent.png'));

        $this->assertSame(
            ["{$image->directory()}/480.webp"],
            Storage::disk(config('catalog.images.disk'))->allFiles($image->directory()),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function animations(): array
    {
        return [
            'animated gif' => ['animated.gif'],
            'animated webp' => ['animated.webp'],
        ];
    }

    #[DataProvider('animations')]
    public function test_an_animated_image_is_refused(string $fixture): void
    {
        try {
            $this->process($this->fixture($fixture));
            $this->fail('An animated image must be refused.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('image_invalid_format', $exception->errorCode);
        }

        $this->assertSame([], Storage::disk(config('catalog.images.disk'))->allFiles());
    }
}
