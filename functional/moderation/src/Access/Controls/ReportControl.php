<?php

namespace Functional\Moderation\Access\Controls;

use Functional\Moderation\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * Reviewers read every report; any confirmed account files one (checked in ReportResource).
 * Reports are never edited or deleted through the API.
 */
class ReportControl extends Control
{
    protected string $model = Report::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view' && $user->can('reports.review'))
                ->should(fn (Model $user, Model $report): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query),

            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'create' && $user->hasVerifiedEmail())
                ->should(fn (Model $user, Model $report): bool => false)
                ->query(fn (Builder $query, Model $user): Builder => $query->whereRaw('1 = 0')),
        ];
    }
}
