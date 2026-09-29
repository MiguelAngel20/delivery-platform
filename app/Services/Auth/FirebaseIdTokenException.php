<?php

namespace App\Services\Auth;

use RuntimeException;

final class FirebaseIdTokenException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
