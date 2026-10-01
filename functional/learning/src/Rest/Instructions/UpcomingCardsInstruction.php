<?php

namespace Functional\Learning\Rest\Instructions;

use Functional\Learning\Queries\DueCardsQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Lomkit\Rest\Instructions\Instruction;

/**
 * The cards a device keeps to review offline (feature 006, research R5): every card due within
 * the next days in a published subject, in the order of a review session.
 */
class UpcomingCardsInstruction extends Instruction
{
    public const HORIZON_DAYS = 7;

    public function uriKey(): string
    {
        return 'upcoming';
    }

    /**
     * @param  array{}  $fields
     */
    public function handle(array $fields, Builder $query): void
    {
        DueCardsQuery::inReviewOrder(DueCardsQuery::constrain($query, Auth::user()->timezone, daysAhead: self::HORIZON_DAYS));
    }
}
