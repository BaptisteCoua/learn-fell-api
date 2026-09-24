<?php

namespace Technical\Osdd\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A request the domain refuses on purpose (a subject without questions, a second pending
 * report, ...). Rendered as `{ "code", "message" }` so the web app can react to the code
 * and show the translated message.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace  values for the translation placeholders
     * @param  array<string, mixed>  $context  extra fields returned next to the code
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $replace = [],
        public readonly array $context = [],
    ) {
        parent::__construct(__('errors.'.$errorCode, $replace));
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            ['code' => $this->errorCode, 'message' => $this->getMessage(), ...$this->context],
            $this->status,
            options: JSON_UNESCAPED_UNICODE,
        );
    }
}
