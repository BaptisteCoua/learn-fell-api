<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Rules\DistinctImagePosition;
use Functional\Catalog\Rules\WithinRectoImageLimit;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

/**
 * Read as the `images` relation of a question; written only through its question's mutate
 * (contracts/api.md §3). The uploader is never exposed.
 */
class QuestionImageResource extends Resource
{
    public static $model = QuestionImage::class;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'question_id', 'alt', 'position', 'width', 'height', 'variant_widths'];
    }

    /**
     * The file, its size and its question are set by the upload and by the question's mutate.
     *
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'id' => ['prohibited'],
            'question_id' => ['prohibited'],
            'width' => ['prohibited'],
            'height' => ['prohibited'],
            'variant_widths' => ['prohibited'],
        ];
    }

    /**
     * Each image of a recto is described (FR-004) and placed (FR-005), 4 images at most
     * (FR-003).
     *
     * @return array<string, mixed>
     */
    public function updateRules(RestRequest $request): array
    {
        return [
            'alt' => ['required', 'string', 'max:250'],
            'position' => [
                new WithinRectoImageLimit,
                'required',
                'integer',
                'min:0',
                'max:'.(config('catalog.images.max_per_recto') - 1),
                new DistinctImagePosition,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['position' => 'asc'];
    }

    /**
     * The images of the questions one may read, never a pending one (FR-016, FR-018).
     */
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->whereHas(
            'question',
            fn (Builder $questions): Builder => QuestionResource::readableBy($request->user(), $questions),
        );
    }
}
