<?php

namespace Functional\Catalog\Policies;

use Functional\Catalog\Access\Controls\QuestionImageControl;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Images change only through the mutate of their question (research R6). lomkit authorizes
 * each image of `relations.images` with `update` (or `view` for a detach), after
 * QuestionResource has checked attach() and detach() against the question itself.
 */
class QuestionImagePolicy extends PubliclyReadablePolicy
{
    protected string $control = QuestionImageControl::class;

    protected function isPubliclyReadable(Model $model): bool
    {
        return ! $model->isPending() && $model->question->subject->status->isPublic();
    }

    /**
     * An image is created by the upload route, never by lomkit.
     */
    public function create(Model $user): bool
    {
        return false;
    }

    public function update(Model $user, Model $model): bool
    {
        return $model->isPending()
            ? $model->uploader_id === $user->getKey()
            : Gate::forUser($user)->allows('update', $model->question);
    }

    /**
     * An image is deleted with its question, or by leaving it out of its question's mutate.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * One's own pending image, or an image this question already carries (FR-001, FR-005).
     */
    public function attach(User $user, Question $question, QuestionImage $image): bool
    {
        return $image->isPending()
            ? $image->uploader_id === $user->getKey()
            : $question->exists && $image->question_id === $question->getKey();
    }

    /**
     * Only an image of this very question can be taken off it, and doing so deletes it.
     */
    public function detach(User $user, Question $question, QuestionImage $image): bool
    {
        return $question->exists && ! $image->isPending() && $image->question_id === $question->getKey();
    }
}
