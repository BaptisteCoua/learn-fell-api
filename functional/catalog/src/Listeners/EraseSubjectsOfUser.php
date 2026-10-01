<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Users\Models\User;

/**
 * Feature 004, FR-018 and FR-019 — an erased account takes its subjects along, except the
 * published ones it left to the community, which stay without author. Each subject is deleted
 * on its own, so the existing chain removes its questions, images, files, learnings and reports.
 */
class EraseSubjectsOfUser
{
    public function handle(User $user): void
    {
        $keepsPublishedSubjects = $user->keeps_published_subjects === true;

        Subject::query()
            ->where('author_id', $user->getKey())
            ->get()
            ->each(function (Subject $subject) use ($keepsPublishedSubjects): void {
                if ($keepsPublishedSubjects && $subject->status === SubjectStatus::Published) {
                    $subject->forceFill(['author_id' => null])->save();

                    return;
                }

                $subject->delete();
            });

        QuestionImage::query()
            ->where('uploader_id', $user->getKey())
            ->whereNull('question_id')
            ->get()
            ->each(fn (QuestionImage $image): ?bool => $image->delete());

        // What is left was attached to subjects that stay, its own or others' it edited.
        QuestionImage::query()->where('uploader_id', $user->getKey())->update(['uploader_id' => null]);
    }
}
