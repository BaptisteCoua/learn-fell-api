<?php

namespace Functional\Catalog\Http\Controllers;

use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Support\QuestionImageAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves one variant of an image, checked on every request with the reading rules of its
 * question (research R8). Whatever the reason, a refusal is the very 404 of an image that does
 * not exist (FR-016).
 */
class ShowQuestionImageController
{
    public function __invoke(Request $request, int $id, int $width): StreamedResponse
    {
        $image = QuestionImage::query()->with('question.subject')->find($id);

        if ($image === null
            || ! in_array($width, config('catalog.images.variant_widths'), true)
            || ! QuestionImageAccess::canView($request->user(), $image)) {
            throw new NotFoundHttpException;
        }

        $servedWidth = in_array($width, $image->variant_widths, true) ? $width : max($image->variant_widths);

        return Storage::disk(config('catalog.images.disk'))->response(
            "{$image->directory()}/{$servedWidth}.webp",
            headers: [
                'Content-Type' => 'image/webp',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => $image->question?->subject->status->isPublic() ? 'private, max-age=3600' : 'private, no-store',
            ],
        );
    }
}
