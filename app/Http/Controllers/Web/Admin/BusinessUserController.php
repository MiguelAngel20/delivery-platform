<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Businesses\CreateBusinessEmployee;
use App\Actions\Businesses\UpdateBusinessEmployee;
use App\Enums\BusinessUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessUserRequest;
use App\Http\Requests\Admin\UpdateBusinessUserRequest;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Support\BusinessMembershipData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessUserController extends Controller
{
    public function index(Business $business): Response
    {
        $this->authorize('view', $business);
        $this->authorize('viewAny', BusinessUser::class);

        $business->load([
            'memberships' => fn ($query) => $query
                ->with([
                    'user:id,first_name,last_name,name,email,phone',
                    'branches:id,name',
                ])
                ->latest(),
        ]);

        return Inertia::render('admin/businesses/users/index', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'users' => $business->memberships
                ->map(fn (BusinessUser $membership): array => BusinessMembershipData::transform($membership))
                ->values()
                ->all(),
            'options' => BusinessMembershipData::formOptions($business),
        ]);
    }

    public function create(Business $business): Response
    {
        $this->authorize('create', [BusinessUser::class, $business]);

        return Inertia::render('admin/businesses/users/create', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'options' => BusinessMembershipData::formOptions($business),
        ]);
    }

    public function store(
        StoreBusinessUserRequest $request,
        Business $business,
        CreateBusinessEmployee $action,
    ): RedirectResponse {
        $membership = $action->handle($business, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuario asociado a la empresa correctamente.',
        ]);

        return to_route('admin.businesses.users.edit', [$business, $membership]);
    }

    public function edit(Business $business, BusinessUser $businessUser): Response
    {
        $this->ensureSameBusiness($business, $businessUser);
        $this->authorize('update', $businessUser);

        $businessUser->load(['user', 'branches:id,name']);

        return Inertia::render('admin/businesses/users/edit', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
            'userMembership' => BusinessMembershipData::transform($businessUser),
            'options' => BusinessMembershipData::formOptions($business),
        ]);
    }

    public function update(
        UpdateBusinessUserRequest $request,
        Business $business,
        BusinessUser $businessUser,
        UpdateBusinessEmployee $action,
    ): RedirectResponse {
        $this->ensureSameBusiness($business, $businessUser);

        $action->handle($businessUser, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuario empresarial actualizado.',
        ]);

        return to_route('admin.businesses.users.edit', [$business, $businessUser]);
    }

    public function deactivate(
        Request $request,
        Business $business,
        BusinessUser $businessUser,
    ): RedirectResponse {
        $this->ensureSameBusiness($business, $businessUser);
        $this->authorize('deactivate', $businessUser);

        $businessUser->update([
            'status' => BusinessUserStatus::Inactive,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Membresía desactivada.',
        ]);

        return back();
    }

    public function activate(
        Business $business,
        BusinessUser $businessUser,
    ): RedirectResponse {
        $this->ensureSameBusiness($business, $businessUser);
        $this->authorize('activate', $businessUser);

        $businessUser->update([
            'status' => BusinessUserStatus::Active,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Membresía reactivada.',
        ]);

        return back();
    }

    private function ensureSameBusiness(Business $business, BusinessUser $businessUser): void
    {
        abort_unless($businessUser->business_id === $business->id, 404);
    }
}
