<?php

namespace Functional\Users\Http\Responses;

use Illuminate\Http\Response;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function toResponse($request): Response
    {
        return response()->noContent();
    }
}
