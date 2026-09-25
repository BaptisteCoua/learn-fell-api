<?php

namespace Functional\Moderation\Enums;

/**
 * Why a subject is reported (FR-027): a closed list.
 */
enum ReportReason: string
{
    case Inappropriate = 'inappropriate';
    case Incorrect = 'incorrect';
    case Spam = 'spam';
    case Copyright = 'copyright';
    case Other = 'other';
}
