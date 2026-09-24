<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data: the code checks these permissions by name, so they ship with the schema.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const ROLES = ['member', 'admin'];

    private const ADMIN_PERMISSIONS = [
        'categories.manage',
        'subjects.moderate',
        'reports.review',
        'moderation.history.view',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROLES as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role, 'guard_name' => self::GUARD],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $adminRoleId = DB::table('roles')->where(['name' => 'admin', 'guard_name' => self::GUARD])->value('id');

        foreach (self::ADMIN_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => self::GUARD],
                ['created_at' => $now, 'updated_at' => $now],
            );

            $permissionId = DB::table('permissions')->where(['name' => $permission, 'guard_name' => self::GUARD])->value('id');

            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $adminRoleId],
            );
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::ADMIN_PERMISSIONS)->where('guard_name', self::GUARD)->delete();
        DB::table('roles')->whereIn('name', self::ROLES)->where('guard_name', self::GUARD)->delete();
    }
};
