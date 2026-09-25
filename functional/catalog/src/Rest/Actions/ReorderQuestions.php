<?php

namespace Functional\Catalog\Rest\Actions;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Support\RetiredSubjectLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

/**
 * Puts a subject's questions in the given order (FR-014); readers see that order at once.
 */
class ReorderQuestions extends Action
{
    public function uriKey(): string
    {
        return 'reorder';
    }

    /**
     * @param  array{subject_id: int, ids: list<int>}  $fields
     * @param  Collection<int, Question>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $subject = Subject::query()->findOrFail($fields['subject_id']);
        Gate::authorize('update', $subject);
        RetiredSubjectLock::ensureEditable($subject);

        $orderedIds = array_map('intval', $fields['ids']);
        $currentIds = $subject->questions()->pluck('id')->all();

        if (count($orderedIds) !== count($currentIds) || array_diff($currentIds, $orderedIds) !== []) {
            throw ValidationException::withMessages(['ids' => __('validation.in', ['attribute' => 'ids'])]);
        }

        DB::transaction(function () use ($subject, $orderedIds): void {
            // Positions are unique per subject: park them out of the way before renumbering.
            $subject->questions()->update(['position' => DB::raw('-position')]);

            foreach ($orderedIds as $index => $questionId) {
                Question::query()->whereKey($questionId)->update(['position' => $index + 1]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'ids' => ['required', 'array', 'max:'.Subject::MAX_QUESTIONS],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
