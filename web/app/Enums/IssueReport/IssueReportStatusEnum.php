<?php

namespace App\Enums\IssueReport;

enum IssueReportStatusEnum: string
{
    case NEW = 'new';
    case RESOLVED = 'resolved';
}
