<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An expected business-rule violation that renders as a JSON error response.
 */
class ApiException extends Exception
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 422,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $payload = ['message' => $this->getMessage()];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->statusCode);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function conflict(string $message, array $errors = []): self
    {
        return new self($message, 409, $errors);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function invalid(string $message, array $errors = []): self
    {
        return new self($message, 422, $errors);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }
}
