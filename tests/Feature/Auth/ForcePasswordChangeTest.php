<?php

use App\Actions\Businesses\CreateBusinessEmployee;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function seedBusinessUserForAccess(): User
{
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();

    $membership = app(CreateBusinessEmployee::class)->handle($business, [
        'first_name' => 'Ana',
        'last_name' => 'Sucursal',
        'email' => 'ana.sucursal@ride.test',
        'phone' => '+50255559901',
        'role' => BusinessUserRole::BusinessEmployee,
        'status' => BusinessUserStatus::Active,
        'branch_ids' => [$branch->id],
    ]);

    return $membership->user;
}

test('new business user receives the temporary password and must change it', function () {
    $user = seedBusinessUserForAccess();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check((string) config('business.users.temporary_password'), $user->password))->toBeTrue();
});

test('business user with temporary password is redirected to change it after login', function () {
    $user = seedBusinessUserForAccess();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => (string) config('business.users.temporary_password'),
    ])
        ->assertRedirect(route('password.force.edit'));

    $this->assertAuthenticated();
});

test('business user cannot open the portal until the temporary password is changed', function () {
    $user = seedBusinessUserForAccess();

    $this->actingAs($user)
        ->get(route('business.home'))
        ->assertRedirect(route('password.force.edit'));
});

test('business user can change the temporary password and then enter the portal', function () {
    $user = seedBusinessUserForAccess();

    $this->actingAs($user)
        ->put(route('password.force.update'), [
            'password' => 'Nueva1!Clave',
            'password_confirmation' => 'Nueva1!Clave',
        ])
        ->assertRedirect(route('business.home'));

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('Nueva1!Clave', $user->password))->toBeTrue();

    $this->actingAs($user)
        ->get(route('business.home'))
        ->assertOk();
});

test('business user cannot keep the temporary password', function () {
    $user = seedBusinessUserForAccess();
    $temporary = (string) config('business.users.temporary_password');

    $this->actingAs($user)
        ->put(route('password.force.update'), [
            'password' => $temporary,
            'password_confirmation' => $temporary,
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});
