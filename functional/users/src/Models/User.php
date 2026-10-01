<?php

namespace Functional\Users\Models;

use Carbon\CarbonImmutable;
use Functional\Users\Database\Factories\UserFactory;
use Functional\Users\Notifications\ResetPasswordNotification;
use Functional\Users\Notifications\VerifyEmailNotification;
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
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['display_name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable, Prunable;

    /**
     * Days between a deletion request and the erasure, during which logging in cancels it.
     */
    public const DELETION_GRACE_DAYS = 30;

    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    /**
     * The day the account will be erased, in its own time zone.
     */
    public function eraseOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->deletion_requested_at ?? now())
            ->addDays(self::DELETION_GRACE_DAYS)
            ->setTimezone($this->timezone);
    }

    /**
     * The account's own name, for the emails it receives even while its name is hidden.
     */
    public function ownDisplayName(): string
    {
        return $this->attributes['display_name'];
    }

    /**
     * Accounts never confirmed within 7 days are removed, which frees their address; so are
     * accounts whose deletion was requested more than 30 days ago (feature 004, FR-016).
     *
     * @return Builder<User>
     */
    public function prunable(): Builder
    {
        // Nested, so the `id > ?` that pruning adds by chunk applies to both cases.
        return static::query()->where(fn (Builder $prunable): Builder => $prunable
            ->where(fn (Builder $unconfirmed): Builder => $unconfirmed
                ->whereNull('email_verified_at')
                ->where('created_at', '<', now()->subDays(7)))
            ->orWhere('deletion_requested_at', '<=', now()->subDays(self::DELETION_GRACE_DAYS)));
    }

    /**
     * Every layer erases or detaches its data on `eloquent.deleting`: in one transaction, so a
     * layer that fails leaves the whole account for the next prune (FR-022). Files are only
     * removed once it commits.
     */
    public function prune(): ?bool
    {
        return DB::transaction(function (): ?bool {
            $this->pruning();

            return $this->delete();
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Hidden from everyone once a deletion is requested (feature 004, FR-011): authors become
     * « Auteur supprimé », reporters and moderators « Compte supprimé » on the web.
     *
     * @return Attribute<string|null, string>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (string $displayName, array $attributes): ?string => ($attributes['deletion_requested_at'] ?? null) === null ? $displayName : null,
        );
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
            'deletion_requested_at' => 'datetime',
            'keeps_published_subjects' => 'boolean',
        ];
    }
}
