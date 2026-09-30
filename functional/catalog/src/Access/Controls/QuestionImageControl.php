<?php

namespace Functional\Catalog\Access\Controls;

use Functional\Catalog\Models\QuestionImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * An image is read through its question, with QuestionControl's own perimeters: every image
 * for a moderator, those of published subjects and of one's own subjects otherwise. A pending
 * image is never listed. Images are written through the mutate of their question only.
 */
class QuestionImageControl extends Control
{
    protected string $model = QuestionImage::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view')
                ->should(fn (Model $user, Model $image): bool => ! $image->isPending()
                    && Gate::forUser($user)->allows('view', $image->question))
                ->query(fn (Builder $query, Model $user): Builder => $query->whereHas(
                    'question',
                    fn (Builder $questions): Builder => (new QuestionControl)->queried($questions, $user),
                )),
        ];
    }
}
