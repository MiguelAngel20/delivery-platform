<?php

namespace App\Http\Controllers\Web\Business;

use App\Actions\Businesses\CreateBusinessEmployee;
use App\Actions\Businesses\UpdateBusinessEmployee;
use App\Enums\BusinessUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Business\IndexBusinessEmployeeRequest;
use App\Http\Requests\Business\StoreBusinessEmployeeRequest;
use App\Http\Requests\Business\UpdateBusinessEmployeeRequest;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use App\Services\BusinessLimitService;
use App\Support\BusinessMembershipData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly BusinessLimitService $limitService,
    ) {}

    public function index(IndexBusinessEmployeeRequest $request): Response
    {
        $business = $this->currentBusiness($request);
        $filters = $request->validated();
        $limits = $this->limitService->summary($business);

        $employees = BusinessUser::query()
            ->where('business_id', $business->id)
            ->with([
                'user:id,first_name,last_name,name,email,phone',
                'branches:id,name',
            ])
            ->when(
                filled($filters['search'] ?? null),
                function ($query) use ($filters): void {
                    $search = $filters['search'];
                    $query->whereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                filled($filters['role'] ?? null),
                fn ($query) => $query->where('role', $filters['role']),
            )
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (BusinessUser $membership): array => BusinessMembershipData::transform($membership));

        return Inertia::render('business/employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'options' => BusinessMembershipData::formOptions($business),
            'limits' => $limits,
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('create', [BusinessUser::class, $business]);

        return Inertia::render('business/employees/create', [
            'options' => BusinessMembershipData::formOptions($business),
            'limits' => $this->limitService->summary($business),
        ]);
    }

    public function store(
        StoreBusinessEmployeeRequest $request,
        CreateBusinessEmployee $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);

        $membership = $action->handle($business, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado creado correctamente.',
        ]);

        return to_route('business.employees.edit', $membership);
    }

    public function edit(Request $request, BusinessUser $businessUser): Response
    {
        $business = $this->currentBusiness($request);
        $this->ensureSameBusiness($business, $businessUser);
        $this->authorize('update', $businessUser);

        $businessUser->load(['user', 'branches:id,name']);

        return Inertia::render('business/employees/edit', [
            'employee' => BusinessMembershipData::transform($businessUser),
            'options' => BusinessMembershipData::formOptions($business),
        ]);
    }

    public function update(
        UpdateBusinessEmployeeRequest $request,
        BusinessUser $businessUser,
        UpdateBusinessEmployee $action,
    ): RedirectResponse {
        $business = $this->currentBusiness($request);
        $this->ensureSameBusiness($business, $businessUser);

        $action->handle($businessUser, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado actualizado correctamente.',
        ]);

        return to_route('business.employees.edit', $businessUser);
    }

    public function deactivate(Request $request, BusinessUser $businessUser): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureSameBusiness($business, $businessUser);
        $this->authorize('deactivate', $businessUser);

        if ($businessUser->user_id === $request->user()?->id) {
            throw ValidationException::withMessages([
                'status' => 'No puedes desactivarte a ti mismo.',
            ]);
        }

        $businessUser->update([
            'status' => BusinessUserStatus::Inactive,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado desactivado.',
        ]);

        return back();
    }

    public function activate(Request $request, BusinessUser $businessUser): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $this->ensureSameBusiness($business, $businessUser);
        $this->authorize('activate', $businessUser);

        $businessUser->update([
            'status' => BusinessUserStatus::Active,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado reactivado.',
        ]);

        return back();
    }

    private function currentBusiness(Request $request): Business
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $user->activeBusinessMembership();

        abort_unless(
            $membership !== null && $membership->isAdmin() && $membership->business !== null,
            403,
        );

        return $membership->business;
    }

    private function ensureSameBusiness(Business $business, BusinessUser $businessUser): void
    {
        abort_unless($businessUser->business_id === $business->id, 404);
    }
}
