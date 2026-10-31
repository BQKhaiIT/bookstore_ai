<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class CloudinaryServiceException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        int $httpStatus = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function httpStatus(): int
    {
        $status = $this->getCode();

        return is_int($status) && $status >= 400 && $status < 600 ? $status : 502;
    }
}
