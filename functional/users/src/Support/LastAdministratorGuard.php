<?php

namespace Functional\Users\Support;

use Functional\Users\Models\User;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Feature 004, FR-005 — CINQ keeps someone for every administration permission: no screen names
 * an administrator, so the last account holding one cannot be deleted. An account whose own
 * deletion is pending does not count. Decided on permissions, never on a role (principle III).
 */
class LastAdministratorGuard
{
    public const ADMINISTRATION_PERMISSIONS = [
        'categories.manage',
        'subjects.moderate',
        'reports.review',
        'moderation.history.view',
    ];

    public function isLastAdministrator(User $user): bool
    {
        foreach (self::ADMINISTRATION_PERMISSIONS as $permission) {
            if ($user->can($permission) && ! $this->anotherActiveAccountHolds($permission, $user)) {
                return true;
            }
        }

        return false;
    }

    public function ensureCanLeave(User $user): void
    {
        if ($this->isLastAdministrator($user)) {
            throw new BusinessRuleException('last_admin');
        }
    }

    private function anotherActiveAccountHolds(string $permission, User $user): bool
    {
        return User::permission($permission)
            ->whereKeyNot($user->getKey())
            ->whereNull('deletion_requested_at')
            ->exists();
    }
}
