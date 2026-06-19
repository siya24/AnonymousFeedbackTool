<?php
declare(strict_types=1);

namespace App\Exceptions;

class ConflictException extends \RuntimeException
{
    public function __construct(string $message = 'Conflict', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

