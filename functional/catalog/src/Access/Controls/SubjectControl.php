<?php

namespace Functional\Catalog\Access\Controls;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * Signed-in access to subjects. Moderators reach every subject. An account writes only its
 * own subjects, and reads the published ones and its own. Visitors are handled by
 * SubjectPolicy (published only). The retired lock is a business rule (SubjectResource).
 */
class SubjectControl extends Control
{
    public const WRITE_METHODS = ['create', 'update', 'delete'];

    protected string $model = Subject::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $user->can('subjects.moderate'))
                ->should(fn (Model $user, Model $subject): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query),

            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => in_array($method, self::WRITE_METHODS, true)
                    && $user->hasVerifiedEmail())
                ->should(fn (Model $user, Model $subject): bool => $subject->author_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->where('author_id', $user->getKey())),

            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view')
                ->should(fn (Model $user, Model $subject): bool => $subject->status->isPublic()
                    || $subject->author_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->where('status', SubjectStatus::Published)
                        ->orWhere('author_id', $user->getKey()),
                )),
        ];
    }
}
