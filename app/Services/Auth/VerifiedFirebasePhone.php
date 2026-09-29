<?php

namespace App\Services\Auth;

final class VerifiedFirebasePhone
{
    public function __construct(
        public readonly string $phone,
        public readonly string $uid,
    ) {}
}
