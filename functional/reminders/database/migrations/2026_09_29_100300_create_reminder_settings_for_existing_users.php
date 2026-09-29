<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The accounts created before the reminders get their settings, every channel off (research R12).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO reminder_settings (user_id, created_at, updated_at)
            SELECT users.id, NOW(), NOW()
            FROM users
            WHERE NOT EXISTS (SELECT 1 FROM reminder_settings WHERE reminder_settings.user_id = users.id)
            SQL);
    }

    public function down(): void
    {
        //
    }
};
