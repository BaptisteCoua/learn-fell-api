<?php

namespace Functional\Learning\Http\Controllers;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\Learning;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * specs/008-question-import, FR-019 — whether anyone learns a subject, its author included: the
 * questions imported into it go to their box 1. Learning is the only layer that sees both
 * subjects and learners (research R8). Neither a count nor a name is given.
 */
class SubjectLearnersController
{
    public function __invoke(Subject $subject): JsonResponse
    {
        // A subject the user may not read is not found, so a draft never reveals it exists (principle VI).
        if (Gate::denies('view', $subject)) {
            throw new NotFoundHttpException;
        }

        Gate::authorize('update', $subject);

        return new JsonResponse([
            'has_learners' => Learning::query()->where('subject_id', $subject->getKey())->exists(),
        ]);
    }
}
