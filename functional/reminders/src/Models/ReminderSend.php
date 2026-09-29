<?php

namespace Functional\Reminders\Models;

use Functional\Reminders\Database\Factories\ReminderSendFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * The reminder of one account on one local day (FR-020): it keeps the day to a single reminder
 * and spaces the next ones. Only the latest matters, so the log keeps 90 days (research R13).
 */
#[Fillable(['user_id', 'local_date', 'cards_count', 'subject_ids', 'channels', 'sent_at'])]
#[UseFactory(ReminderSendFactory::class)]
class ReminderSend extends Model
{
    use HasFactory, Prunable;

    public const RETENTION_DAYS = 90;

    public $timestamps = false;

    /**
     * @return Builder<ReminderSend>
     */
    public function prunable(): Builder
    {
        return static::query()->where('sent_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'local_date' => 'date',
            'cards_count' => 'integer',
            'subject_ids' => 'array',
            'channels' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
