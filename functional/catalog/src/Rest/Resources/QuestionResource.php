<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Rest\Actions\ReorderQuestions;
use Functional\Catalog\Rules\VisibleTextLength;
use Functional\Catalog\Support\RetiredSubjectLock;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\Relation;
use Technical\Osdd\Exceptions\BusinessRuleException;

class QuestionResource extends Resource
{
    public static $model = Question::class;

    public int $defaultLimit = 50;

    /**
     * Markup allowance around the 5,000 visible characters.
     */
    public const MAX_HTML_LENGTH = 20000;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'subject_id', 'recto_html', 'verso_html', 'position'];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('subject', SubjectResource::class)];
    }

    /**
     * Recto and verso hold rich text (FR-014); the position is set by the API and by the
     * `reorder` action, and a question never moves to another subject.
     *
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'recto_html' => ['string', 'max:'.self::MAX_HTML_LENGTH, new VisibleTextLength],
            'verso_html' => ['string', 'max:'.self::MAX_HTML_LENGTH, new VisibleTextLength],
            'id' => ['prohibited'],
            'position' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRules(RestRequest $request): array
    {
        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'recto_html' => ['required'],
            'verso_html' => ['required'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateRules(RestRequest $request): array
    {
        return ['subject_id' => ['prohibited']];
    }

    /**
     * A new question goes last in a subject its author may edit, up to 500 (FR-014).
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        if ($model->exists) {
            RetiredSubjectLock::ensureEditable($model->subject);

            return;
        }

        $subject = Subject::query()->findOrFail($requestBody['attributes']['subject_id']);
        Gate::authorize('update', $subject);
        RetiredSubjectLock::ensureEditable($subject);

        if ($subject->questions()->count() >= Subject::MAX_QUESTIONS) {
            throw new BusinessRuleException('question_limit_reached', replace: ['max' => Subject::MAX_QUESTIONS]);
        }

        $model->position = (int) $subject->questions()->max('position') + 1;
    }

    /**
     * A published subject keeps at least one question (FR-018).
     */
    public function destroying(DestroyRequest $request, Model $model): void
    {
        $subject = $model->subject;
        RetiredSubjectLock::ensureEditable($subject);

        if ($subject->status->isPublic() && $subject->questions()->count() === 1) {
            throw new BusinessRuleException('last_question_of_published_subject');
        }
    }

    /**
     * @return list<Action>
     */
    public function actions(RestRequest $request): array
    {
        return [ReorderQuestions::make()->standalone()];
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 25, 50, 100];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['position' => 'asc'];
    }

    /**
     * A question follows its subject: published for visitors, QuestionControl otherwise.
     */
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $request->user() === null
            ? $query->whereHas('subject', fn (Builder $subjects): Builder => $subjects->where('status', SubjectStatus::Published))
            : $query->controlled();
    }
}
