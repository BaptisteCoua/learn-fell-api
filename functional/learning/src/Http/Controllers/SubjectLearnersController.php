<?php

namespace Functional\Learning\Http\Controllers;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\Learning;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * specs/008-question-import, FR-019 — whether anyone learns a subject, its author included: the
 * questions imported into it go to their box 1. Learning is the only layer that sees both
 * subjects and learners (research R8). Neither a count nor a name is given.
 */
class SubjectLearnersController
{
    public function __invoke(Subject $subject): JsonResponse
    {
        Gate::authorize('update', $subject);

        return new JsonResponse([
            'has_learners' => Learning::query()->where('subject_id', $subject->getKey())->exists(),
        ]);
    }
}
