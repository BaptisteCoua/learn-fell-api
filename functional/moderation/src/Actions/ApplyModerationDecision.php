<?php

namespace Functional\Moderation\Actions;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Moderation\Enums\DecisionType;
use Functional\Moderation\Enums\ReportStatus;
use Functional\Moderation\Models\Report;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * What a decision does to its subject (FR-031, FR-032): ignoring closes the pending reports,
 * retiring hides the subject with its reason and closes them too, restoring turns a retired
 * subject back into a draft of its author.
 */
class ApplyModerationDecision
{
    public function ensureApplicable(DecisionType $decision, Subject $subject, ?string $reason): void
    {
        if ($decision->needsReason() && trim((string) $reason) === '') {
            throw new BusinessRuleException('reason_required');
        }

        if ($decision === DecisionType::Restored && $subject->status !== SubjectStatus::Retired) {
            throw new BusinessRuleException('subject_not_retired');
        }
    }

    public function apply(DecisionType $decision, Subject $subject, ?string $reason): void
    {
        match ($decision) {
            DecisionType::Ignored => $this->closePendingReports($subject),
            DecisionType::Retired => $this->retire($subject, (string) $reason),
            DecisionType::Restored => $subject->forceFill([
                'status' => SubjectStatus::Draft,
                'retired_reason' => null,
                'retired_at' => null,
            ])->save(),
        };
    }

    private function retire(Subject $subject, string $reason): void
    {
        $subject->forceFill([
            'status' => SubjectStatus::Retired,
            'retired_reason' => trim($reason),
            'retired_at' => now(),
        ])->save();

        $this->closePendingReports($subject);
    }

    private function closePendingReports(Subject $subject): void
    {
        Report::query()
            ->where('subject_id', $subject->getKey())
            ->where('status', ReportStatus::Pending)
            ->update(['status' => ReportStatus::Closed, 'closed_at' => now()]);
    }
}
