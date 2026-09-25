<?php

namespace Functional\Moderation\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Closed = 'closed';
}
