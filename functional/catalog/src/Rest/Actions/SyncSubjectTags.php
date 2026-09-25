<?php

namespace Functional\Catalog\Rest\Actions;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Catalog\Support\RetiredSubjectLock;
use Functional\Catalog\Support\TextNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

/**
 * Sets a subject's tags from their names (FR-011): normalized, duplicates ignored, created
 * when new, 10 at most.
 */
class SyncSubjectTags extends Action
{
    public function uriKey(): string
    {
        return 'sync-tags';
    }

    /**
     * @param  array{names?: list<string>}  $fields
     * @param  Collection<int, Subject>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $names = collect($fields['names'] ?? [])
            ->map(fn (string $name): string => TextNormalizer::normalize($name))
            ->filter()
            ->unique()
            ->values();

        foreach ($models as $subject) {
            Gate::authorize('update', $subject);
            RetiredSubjectLock::ensureEditable($subject);

            $tagIds = $names->map(fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->getKey());

            $subject->tags()->sync($tagIds->all());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'names' => ['present', 'array', 'max:'.Subject::MAX_TAGS],
            'names.*' => ['string', 'max:'.Tag::MAX_LENGTH],
        ];
    }
}
