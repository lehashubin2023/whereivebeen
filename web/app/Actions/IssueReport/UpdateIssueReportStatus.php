<?php

namespace App\Actions\IssueReport;

use App\Enums\IssueReport\IssueReportStatusEnum;
use App\Models\IssueReport;

class UpdateIssueReportStatus
{
    public function exec(IssueReport $report, IssueReportStatusEnum $status): IssueReport
    {
        $report->status = $status;
        $report->save();

        return $report;
    }
}
