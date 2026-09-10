<?php

namespace App\Actions\IssueReport;

use App\DTOs\IssueReport\CreateIssueReportDTO;
use App\Models\IssueReport;
use App\Models\User;

class CreateIssueReport
{
    public function exec(CreateIssueReportDTO $dto, User $user): IssueReport
    {
        return IssueReport::create([
            ...$dto->toArray(),
            'user_id' => $user->id,
        ]);
    }
}
