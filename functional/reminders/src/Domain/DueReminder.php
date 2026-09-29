<?php

namespace Functional\Reminders\Domain;

/**
 * What a reminder announces (FR-009): the cards due today and the subjects of its session link.
 */
final readonly class DueReminder
{
    /**
     * @param  list<int>  $subjectIds
     */
    public function __construct(
        public int $cardsCount,
        public array $subjectIds,
    ) {}
}
