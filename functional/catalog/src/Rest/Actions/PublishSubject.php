<?php

namespace Functional\Catalog\Rest\Actions;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Draft → published (FR-016): needs at least one question; a retired subject stays retired
 * until a moderator restores it (FR-033).
 */
class PublishSubject extends Action
{
    public function uriKey(): string
    {
        return 'publish';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Subject>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        foreach ($models as $subject) {
            Gate::authorize('update', $subject);

            if ($subject->status === SubjectStatus::Retired) {
                throw new BusinessRuleException('subject_retired');
            }

            if (! $subject->questions()->exists()) {
                throw new BusinessRuleException('subject_has_no_question');
            }

            if ($subject->status === SubjectStatus::Draft) {
                $subject->forceFill(['status' => SubjectStatus::Published, 'published_at' => now()])->save();
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
