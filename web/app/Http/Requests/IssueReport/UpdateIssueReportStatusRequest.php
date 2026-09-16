<?php

namespace App\Http\Requests\IssueReport;

use App\Enums\IssueReport\IssueReportStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(IssueReportStatusEnum::class)],
        ];
    }

    public function status(): IssueReportStatusEnum
    {
        return IssueReportStatusEnum::from((string) $this->validated('status'));
    }
}
