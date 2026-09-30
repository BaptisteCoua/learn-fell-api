<?php

namespace Functional\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The real content is checked, not the file name, and the dimensions are read from the header
 * before anything is decoded (FR-002, research R4).
 */
class StoreQuestionImageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSide = config('catalog.images.max_side');

        return [
            'file' => [
                'bail',
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.config('catalog.images.max_kilobytes'),
                "dimensions:max_width={$maxSide},max_height={$maxSide}",
            ],
        ];
    }
}
