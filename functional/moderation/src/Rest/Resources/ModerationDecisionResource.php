<?php

namespace Functional\Moderation\Rest\Resources;

use Functional\Catalog\Models\Subject;
use Functional\Moderation\Actions\ApplyModerationDecision;
use Functional\Moderation\Enums\DecisionType;
use Functional\Moderation\Models\ModerationDecision;
use Functional\Users\Rest\Resources\PublicUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\Relation;

/**
 * Creating a decision is the moderation act itself: ignore, retire or restore a subject.
 */
class ModerationDecisionResource extends Resource
{
    public static $model = ModerationDecision::class;

    public int $defaultLimit = 50;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'subject_id', 'subject_title', 'decision', 'reason', 'created_at'];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('admin', PublicUserResource::class)];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'subject_id' => ['integer', Rule::exists('subjects', 'id')],
            'decision' => [Rule::enum(DecisionType::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
            ...array_fill_keys(['id', 'subject_title', 'created_at'], ['prohibited']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRules(RestRequest $request): array
    {
        return ['subject_id' => ['required'], 'decision' => ['required']];
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        $attributes = $requestBody['attributes'];
        $subject = Subject::query()->findOrFail($attributes['subject_id']);

        app(ApplyModerationDecision::class)->ensureApplicable(DecisionType::from($attributes['decision']), $subject, $attributes['reason'] ?? null);

        $model->admin_id = $request->user()->getKey();
        $model->subject_title = $subject->title;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     */
    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        app(ApplyModerationDecision::class)->apply(
            $model->decision,
            Subject::query()->findOrFail($model->subject_id),
            $model->reason,
        );
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 50, 100];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['created_at' => 'desc', 'id' => 'desc'];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }
}
