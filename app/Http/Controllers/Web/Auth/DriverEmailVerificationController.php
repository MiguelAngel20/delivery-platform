<?php

namespace App\Http\Controllers\Web\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverEmailVerificationController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        /** @var User|null $user */
        $user = User::query()->find($id);

        abort_if($user === null, 404);
        abort_unless($user->role === UserRole::Driver, 404);
        abort_unless(hash_equals($hash, sha1((string) $user->email)), 403);

        if ($user->email_verified_at === null) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        return Inertia::render('auth/driver-email-verified', [
            'firstName' => $user->first_name,
            'loginUrl' => route('driver.login'),
        ]);
    }
}
