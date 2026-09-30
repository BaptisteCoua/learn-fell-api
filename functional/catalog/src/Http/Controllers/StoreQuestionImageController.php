<?php

namespace Functional\Catalog\Http\Controllers;

use Functional\Catalog\Http\Requests\StoreQuestionImageRequest;
use Functional\Catalog\Images\QuestionImageProcessor;
use Functional\Catalog\Models\QuestionImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Receives one image and keeps it pending, for its uploader only, until the mutate of a
 * question attaches it (research R5, R6). A file that cannot be processed leaves nothing.
 */
class StoreQuestionImageController
{
    public function __invoke(StoreQuestionImageRequest $request, QuestionImageProcessor $processor): JsonResponse
    {
        $image = new QuestionImage;
        $image->forceFill([
            'uploader_id' => $request->user()->getKey(),
            'width' => 0,
            'height' => 0,
            'variant_widths' => [],
        ]);

        try {
            DB::transaction(function () use ($request, $processor, $image): void {
                $image->save();
                $processor->process($request->file('file'), $image);
                $image->save();
            });
        } catch (Throwable $exception) {
            if ($image->exists) {
                Storage::disk(config('catalog.images.disk'))->deleteDirectory($image->directory());
            }

            throw $exception;
        }

        return new JsonResponse([
            'data' => $image->only(['id', 'width', 'height', 'variant_widths']),
        ], 201);
    }
}
