<?php

namespace App\Contracts;

use App\Services\Auth\VerifiedFirebasePhone;

interface FirebaseIdTokenVerifier
{
    public function verify(string $idToken): VerifiedFirebasePhone;
}
