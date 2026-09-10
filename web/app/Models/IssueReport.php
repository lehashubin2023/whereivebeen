<?php

namespace App\Models;

use App\Enums\IssueReport\IssueReportStatusEnum;
use Database\Factories\IssueReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $message
 * @property IssueReportStatusEnum $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'message', 'status'])]
class IssueReport extends Model
{
    /** @use HasFactory<IssueReportFactory> */
    use HasFactory;

    public $casts = [
        'status' => IssueReportStatusEnum::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
