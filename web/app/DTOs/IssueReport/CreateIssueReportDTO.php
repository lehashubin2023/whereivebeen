<?php

namespace App\DTOs\IssueReport;

use App\DTOs\DTOContract;
use App\Enums\IssueReport\IssueReportStatusEnum;
use Illuminate\Http\Request;

class CreateIssueReportDTO implements DTOContract
{
    public function __construct(
        public readonly string $message,
        public readonly IssueReportStatusEnum $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: (string) ($data['message'] ?? ''),
            status: isset($data['status'])
                ? IssueReportStatusEnum::from((string) $data['status'])
                : IssueReportStatusEnum::NEW,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'status' => $this->status,
        ];
    }
}
