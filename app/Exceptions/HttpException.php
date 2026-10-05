<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class HttpException extends Exception
{
    public function __construct(
        string $message = 'Error',
        private readonly int $statusCode = 400,
        private readonly array $errors = []
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
