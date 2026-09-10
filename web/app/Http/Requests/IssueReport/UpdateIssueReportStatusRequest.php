<?php

namespace App\Http\Requests\IssueReport;

use App\Enums\IssueReport\IssueReportStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueReportStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
