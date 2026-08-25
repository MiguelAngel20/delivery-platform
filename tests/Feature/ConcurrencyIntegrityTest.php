<?php

use App\Actions\Businesses\ApproveBusiness;
use App\Actions\Businesses\RejectBusiness;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use App\Models\Business;
use App\Models\BusinessBranch;
use App\Models\BusinessLimit;
use App\Models\BusinessUpgradeRequest;
use App\Models\BusinessUser;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderFinancial;
use App\Models\Payment;
use App\Models\User;
use App\Services\BusinessLimitService;
use App\Services\Finance\OrderFinancialService;
use App\Services\Incidents\IncidentService;
use Illuminate\Validation\ValidationException;

test('approving the same upgrade request twice does not inflate limits', function () {
    $adminA = User::factory()->systemAdmin()->create();
    $adminB = User::factory()->systemAdmin()->create();
    $businessAdmin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();
    $branch = BusinessBranch::factory()->for($business)->create();
    $business->limits()->update(['max_employees_per_branch' => 3]);

    BusinessUser::query()->create([
        'business_id' => $business->id,
        'user_id' => $businessAdmin->id,
        'role' => BusinessUserRole::BusinessAdmin,
        'status' => BusinessUserStatus::Active,
    ])->branches()->sync([$branch->id]);

    $request = BusinessUpgradeRequest::query()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $businessAdmin->id,
        'type' => UpgradeRequestType::AdditionalEmployees,
        'requested_quantity' => 2,
        'status' => UpgradeRequestStatus::Pending,
    ]);

    $this->actingAs($adminA)
        ->post(route('admin.businesses.upgrade-requests.approve', [$business, $request]), [
            'apply_limit_increase' => true,
            'quantity' => 2,
        ])
        ->assertRedirect();

    $this->actingAs($adminB)
        ->post(route('admin.businesses.upgrade-requests.approve', [$business, $request->fresh()]), [
            'apply_limit_increase' => true,
            'quantity' => 2,
        ])
        ->assertSessionHasErrors('status');

    expect($request->fresh()->status)->toBe(UpgradeRequestStatus::Approved)
        ->and($business->fresh()->limits->max_employees_per_branch)->toBe(5)
        ->and(BusinessUpgradeRequest::query()->whereKey($request->id)->count())->toBe(1);
});

test('rejecting an already approved upgrade request is rejected safely', function () {
    $admin = User::factory()->systemAdmin()->create();
    $businessAdmin = User::factory()->businessAdmin()->create();
    $business = Business::factory()->create();

    $request = BusinessUpgradeRequest::query()->create([
        'business_id' => $business->id,
        'requested_by_user_id' => $businessAdmin->id,
        'type' => UpgradeRequestType::AdditionalBranch,
        'requested_quantity' => 1,
        'status' => UpgradeRequestStatus::Approved,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.businesses.upgrade-requests.reject', [$business, $request]), [
            'notes' => 'tarde',
        ])
        ->assertSessionHasErrors('status');

    expect($request->fresh()->status)->toBe(UpgradeRequestStatus::Approved);
});

test('createSnapshot twice keeps a single financial and payment row', function () {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Accepted,
    ]);
    $service = app(OrderFinancialService::class);

    $first = $service->createSnapshot($order);
    $second = $service->createSnapshot($order->fresh());

    expect(OrderFinancial::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

test('ensureLimits concurrent-style create keeps a single business_limits row', function () {
    $business = Business::factory()->create();
    BusinessLimit::query()->where('business_id', $business->id)->delete();
    $business->unsetRelation('limits');

    $service = app(BusinessLimitService::class);
    $first = $service->ensureLimits($business);
    $second = $service->ensureLimits($business->fresh());

    expect(BusinessLimit::query()->where('business_id', $business->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

test('approve then reject of the same business cannot both succeed', function () {
    $admin = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'status' => BusinessStatus::PendingApproval,
    ]);

    app(ApproveBusiness::class)->handle($business, $admin);

    expect(fn () => app(RejectBusiness::class)->handle($business->fresh(), 'no'))
        ->toThrow(ValidationException::class);

    expect($business->fresh()->status)->toBe(BusinessStatus::Active);
});

test('double approve of the same pending business is safe', function () {
    $adminA = User::factory()->systemAdmin()->create();
    $adminB = User::factory()->systemAdmin()->create();
    $business = Business::factory()->create([
        'status' => BusinessStatus::PendingApproval,
    ]);

    app(ApproveBusiness::class)->handle($business, $adminA);

    expect(fn () => app(ApproveBusiness::class)->handle($business->fresh(), $adminB))
        ->toThrow(ValidationException::class);

    expect($business->fresh()->status)->toBe(BusinessStatus::Active);
});

test('reporting the same open incident twice returns one row', function () {
    $user = User::factory()->customer()->create();
    $order = Order::factory()->create([
        'order_status' => OrderStatus::OnTheWay,
    ]);
    $service = app(IncidentService::class);

    $first = $service->report($order, $user, [
        'type' => IncidentType::CustomerRefusedOrder,
        'description' => 'No estaba en domicilio',
        'idempotency_key' => "order:{$order->id}:customer-refused",
    ]);

    $second = $service->report($order->fresh(), $user, [
        'type' => IncidentType::CustomerRefusedOrder,
        'description' => 'No estaba en domicilio (reintento)',
        'idempotency_key' => "order:{$order->id}:customer-refused",
    ]);

    expect(Incident::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

test('reporting open incident without key still collapses duplicates under lock', function () {
    $user = User::factory()->customer()->create();
    $order = Order::factory()->create([
        'order_status' => OrderStatus::OnTheWay,
    ]);
    $service = app(IncidentService::class);

    $first = $service->report($order, $user, [
        'type' => IncidentType::PaymentProblem,
        'description' => 'Cliente sin efectivo',
    ]);

    $second = $service->report($order->fresh(), $user, [
        'type' => IncidentType::PaymentProblem,
        'description' => 'Cliente sin efectivo otra vez',
    ]);

    expect(Incident::query()
        ->where('order_id', $order->id)
        ->where('type', IncidentType::PaymentProblem)
        ->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});
