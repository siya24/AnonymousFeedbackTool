<?php
declare(strict_types=1);

namespace App\Exceptions;

class ServerException extends \RuntimeException
{
    public function __construct(string $message = 'Internal server error', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

