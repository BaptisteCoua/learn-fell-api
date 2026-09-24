<?php

namespace Functional\Users\Models;

use Functional\Users\Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['display_name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable, Prunable;

    /**
     * Accounts never confirmed within 7 days are removed, which frees their address.
     *
     * @return Builder<User>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays(7));
    }

    /**
     * Emails are stored lower-cased so their uniqueness ignores case.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $email): string => mb_strtolower(trim($email)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
