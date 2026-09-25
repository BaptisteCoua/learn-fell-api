<?php

namespace Functional\Users\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * An unknown, expired or already used reset token (FR-005).
 */
class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public function toResponse($request): JsonResponse
    {
        return (new BusinessRuleException('link_expired'))->render();
    }
}
