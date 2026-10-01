<?php

namespace Functional\Learning\Http\Controllers;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\Learning;
use Functional\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Feature 004, FR-004 — how many published subjects the account wrote, and how many other
 * accounts learn them: what its author weighs before choosing to keep them or not. Learning is
 * the only layer that sees both subjects and learners (research R10).
 */
class AuthoredSubjectsSummaryController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $author */
        $author = $request->user();

        $publishedSubjects = Subject::query()
            ->where('author_id', $author->getKey())
            ->where('status', SubjectStatus::Published);

        return new JsonResponse([
            'published_subjects_count' => $publishedSubjects->count(),
            'learners_count' => Learning::query()
                ->whereIn('subject_id', $publishedSubjects->select('id'))
                ->where('user_id', '!=', $author->getKey())
                ->distinct()
                ->count('user_id'),
        ]);
    }
}
