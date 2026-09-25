<?php

namespace Functional\Moderation\Rest\Resources;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Rest\Resources\SubjectResource;
use Functional\Moderation\Enums\ReportReason;
use Functional\Moderation\Enums\ReportStatus;
use Functional\Moderation\Models\Report;
use Functional\Users\Rest\Resources\PublicUserResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\Relation;
use Technical\Osdd\Exceptions\BusinessRuleException;

class ReportResource extends Resource
{
    public static $model = Report::class;

    public int $defaultLimit = 100;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'subject_id', 'reason', 'comment', 'status', 'created_at'];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('subject', SubjectResource::class),
            BelongsTo::make('reporter', PublicUserResource::class),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'subject_id' => ['integer', Rule::exists('subjects', 'id')],
            'reason' => [Rule::enum(ReportReason::class)],
            'comment' => ['nullable', 'string', 'max:500'],
            ...array_fill_keys(['id', 'status', 'created_at'], ['prohibited']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRules(RestRequest $request): array
    {
        return ['subject_id' => ['required'], 'reason' => ['required']];
    }

    /**
     * A reader reports someone else's published subject, once at a time (FR-027, FR-028).
     * The subject stays visible until a moderator decides (FR-029).
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        $reporter = $request->user();
        $subject = Subject::query()->findOrFail($requestBody['attributes']['subject_id']);

        if (! $subject->status->isPublic()) {
            throw new BusinessRuleException('subject_not_published');
        }

        if ($subject->author_id === $reporter->getKey()) {
            throw new AuthorizationException;
        }

        $isAlreadyPending = Report::query()
            ->where('subject_id', $subject->getKey())
            ->where('reporter_id', $reporter->getKey())
            ->where('status', ReportStatus::Pending)
            ->exists();

        if ($isAlreadyPending) {
            throw new BusinessRuleException('report_already_pending', 409);
        }

        $model->reporter_id = $reporter->getKey();
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 50, 100];
    }

    /**
     * The oldest reports first (FR-030).
     *
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['created_at' => 'asc', 'id' => 'asc'];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }
}
