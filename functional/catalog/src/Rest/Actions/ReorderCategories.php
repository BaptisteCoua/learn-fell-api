<?php

namespace Functional\Catalog\Rest\Actions;

use Functional\Catalog\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

/**
 * Puts the categories in the given display order (FR-008).
 */
class ReorderCategories extends Action
{
    public function uriKey(): string
    {
        return 'reorder';
    }

    /**
     * @param  array{ids: list<int>}  $fields
     * @param  Collection<int, Category>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        Gate::authorize('update', new Category);

        $orderedIds = array_map('intval', $fields['ids']);
        $currentIds = Category::query()->pluck('id')->all();

        if (count($orderedIds) !== count($currentIds) || array_diff($currentIds, $orderedIds) !== []) {
            throw ValidationException::withMessages(['ids' => __('validation.in', ['attribute' => 'ids'])]);
        }

        DB::transaction(function () use ($orderedIds): void {
            // Positions are unique: park them out of the way before renumbering.
            Category::query()->update(['position' => DB::raw('-position')]);

            foreach ($orderedIds as $index => $categoryId) {
                Category::query()->whereKey($categoryId)->update(['position' => $index + 1]);
            }
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
