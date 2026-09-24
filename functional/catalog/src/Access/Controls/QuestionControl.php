<?php

namespace Functional\Catalog\Access\Controls;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * A question is reachable exactly when its subject is (see SubjectControl).
 */
class QuestionControl extends Control
{
    protected string $model = Question::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $user->can('subjects.moderate'))
                ->should(fn (Model $user, Model $question): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query),

            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view')
                ->should(fn (Model $user, Model $question): bool => $question->subject->status->isPublic()
                    || $question->subject->author_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->whereHas(
                    'subject',
                    fn (Builder $subjects): Builder => $subjects->where(
                        fn (Builder $subjects): Builder => $subjects
                            ->where('status', SubjectStatus::Published)
                            ->orWhere('author_id', $user->getKey()),
                    ),
                )),
        ];
    }
}
