<?php

namespace Functional\Learning\Enums;

/**
 * What the replay of its card made of an answer (feature 006, research R3): applied to the card,
 * or discarded because the due date it answered had already been answered, or was not reached.
 */
enum AnswerStatus: string
{
    case Applied = 'applied';
    case Discarded = 'discarded';
}
