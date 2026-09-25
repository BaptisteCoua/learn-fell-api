<?php

namespace Functional\Learning\Rest\Resources;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Rest\Resources\SubjectResource;
use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Models\Learning;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\Relation;
use Technical\Osdd\Exceptions\BusinessRuleException;

class LearningResource extends Resource
{
    public static $model = Learning::class;

    public int $defaultLimit = 50;

    /**
     * Counted by the API for "Mes révisions": cards due today and cards in each box.
     */
    public const COMPUTED_FIELDS = [
        'due_today_count', 'box_1_count', 'box_2_count', 'box_3_count', 'box_4_count', 'box_5_count', 'next_review_on',
    ];

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'subject_id', 'created_at', ...self::COMPUTED_FIELDS];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('subject', SubjectResource::class)];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'subject_id' => ['integer', Rule::exists('subjects', 'id')],
            ...array_fill_keys(['id', 'created_at', ...self::COMPUTED_FIELDS], ['prohibited']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRules(RestRequest $request): array
    {
        return ['subject_id' => ['required']];
    }

    /**
     * Only a published subject can be learned, once (FR-041).
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        $learner = $request->user();
        $subject = Subject::query()->findOrFail($requestBody['attributes']['subject_id']);

        if (! $subject->status->isPublic()) {
            throw new BusinessRuleException('subject_not_published');
        }

        if (Learning::query()->where('user_id', $learner->getKey())->where('subject_id', $subject->getKey())->exists()) {
            throw new BusinessRuleException('already_learning', 409);
        }

        $model->user_id = $learner->getKey();
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 25, 50];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['id' => 'desc'];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }

    /**
     * The counts go on the final query: lomkit runs searchQuery() inside a where() group,
     * where selected columns would be dropped.
     */
    public function paginate($query, RestRequest $request)
    {
        $today = LeitnerSchedule::todayFor($request->user()->timezone)->toDateString();
        $boxCounts = collect(range(LeitnerSchedule::FIRST_BOX, LeitnerSchedule::LAST_BOX))
            ->mapWithKeys(fn (int $box): array => [
                "cards as box_{$box}_count" => fn (Builder $cards): Builder => $cards->where('box', $box),
            ])
            ->all();

        $query
            ->withCount([
                'cards as due_today_count' => fn (Builder $cards): Builder => $cards
                    ->where('next_review_on', '<=', $today)
                    ->whereHas('subject', fn (Builder $subjects): Builder => $subjects->where('status', SubjectStatus::Published)),
                ...$boxCounts,
            ])
            ->withMin('cards as next_review_on', 'next_review_on');

        return parent::paginate($query, $request);
    }
}
