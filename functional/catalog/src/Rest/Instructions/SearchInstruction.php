<?php

namespace Functional\Catalog\Rest\Instructions;

use Functional\Catalog\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

/**
 * Case- and accent-insensitive search on the title, description and tags of a subject,
 * through the trigram-indexed search document (FR-025).
 */
class SearchInstruction extends Instruction
{
    /**
     * @param  array{q: string}  $fields
     */
    public function handle(array $fields, Builder $query): void
    {
        $needle = addcslashes(TextNormalizer::normalize($fields['q']), '\\%_');

        $query->where('search_document', 'like', "%{$needle}%");
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(RestRequest $request): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }
}
