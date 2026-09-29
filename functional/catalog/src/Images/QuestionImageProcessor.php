<?php

namespace Functional\Catalog\Images;

use Functional\Catalog\Models\QuestionImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Turns an uploaded photo into the WebP variants readers get (research R3): upright, never
 * enlarged, re-encoded from the pixels alone so that no metadata survives (FR-013, FR-014).
 * The uploaded file itself is never stored.
 */
class QuestionImageProcessor
{
    /**
     * Pixel cache ImageMagick may keep in memory before it spills to disk: an 8,000 px image
     * decodes to about 512 MB.
     */
    private const MEMORY_LIMIT_BYTES = 256 * 1024 * 1024;

    private const MAP_LIMIT_BYTES = 512 * 1024 * 1024;

    public function process(UploadedFile $file, QuestionImage $image): void
    {
        $source = $this->decode($file);
        $variantWidths = $this->variantWidths($source->width());
        $source->scaleDown(width: max($variantWidths));
        $disk = Storage::disk(config('catalog.images.disk'));
        $widest = $source;

        foreach ($variantWidths as $slot => $width) {
            $variant = (clone $source)->scaleDown(width: $width);
            $encoded = $variant->encode(new WebpEncoder(quality: config('catalog.images.webp_quality'), strip: true));
            $disk->put("{$image->directory()}/{$slot}.webp", (string) $encoded);
            $widest = $variant;
        }

        $image->forceFill([
            'width' => $widest->width(),
            'height' => $widest->height(),
            'variant_widths' => array_keys($variantWidths),
        ]);
    }

    private function decode(UploadedFile $file): ImageInterface
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, self::MEMORY_LIMIT_BYTES);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, self::MAP_LIMIT_BYTES);

        try {
            $source = (new ImageManager(new Driver, autoOrientation: true, strip: true))->decodePath($file->getRealPath());
        } catch (DecoderException) {
            throw new BusinessRuleException('image_invalid_format');
        }

        if ($source->isAnimated()) {
            throw new BusinessRuleException('image_invalid_format');
        }

        return $source->removeProfile();
    }

    /**
     * The width of each variant, keyed by the width it is served under: every configured width
     * the source reaches, and the source's own width in the next slot when it is narrower than
     * the widest one. A 700 px photo gives [480 => 480, 960 => 700].
     *
     * @return array<int, int>
     */
    private function variantWidths(int $sourceWidth): array
    {
        $slots = config('catalog.images.variant_widths');
        sort($slots);
        $variantWidths = [];

        foreach ($slots as $slot) {
            if ($slot <= $sourceWidth) {
                $variantWidths[$slot] = $slot;
            } elseif (! in_array($sourceWidth, $variantWidths, true)) {
                $variantWidths[$slot] = $sourceWidth;
            }
        }

        return $variantWidths;
    }
}
