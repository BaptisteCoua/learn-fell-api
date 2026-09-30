<?php

namespace Functional\Catalog\Support;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Rest\Resources\QuestionResource;
use Functional\Users\Models\User;

/**
 * Who may load the file of an image (FR-016, FR-018): its uploader while it is pending, then
 * whoever may read its question, with the very query that lists questions.
 */
class QuestionImageAccess
{
    public static function canView(?User $user, QuestionImage $image): bool
    {
        if ($image->isPending()) {
            return $user !== null && $image->uploader_id === $user->getKey();
        }

        return QuestionResource::readableBy($user, Question::query()->whereKey($image->question_id))->exists();
    }
}
