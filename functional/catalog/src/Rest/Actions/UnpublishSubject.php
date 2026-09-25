<?php

namespace Functional\Catalog\Rest\Actions;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

/**
 * Published → draft (FR-017): the subject disappears from the catalogue, its author keeps it.
 */
class UnpublishSubject extends Action
{
    public function uriKey(): string
    {
        return 'unpublish';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Subject>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $subject) {
            Gate::authorize('update', $subject);

            if ($subject->status === SubjectStatus::Published) {
                $subject->forceFill(['status' => SubjectStatus::Draft])->save();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(RestRequest $request): array
    {
        return [];
    }
}
