<?php
declare(strict_types=1);

namespace App\Exceptions;

class NotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
