<?php

namespace Functional\Moderation\Enums;

/**
 * What a moderator decided about a subject (FR-031, FR-032).
 */
enum DecisionType: string
{
    case Ignored = 'ignored';
    case Retired = 'retired';
    case Restored = 'restored';

    public function needsReason(): bool
    {
        return $this === self::Retired;
    }
}
