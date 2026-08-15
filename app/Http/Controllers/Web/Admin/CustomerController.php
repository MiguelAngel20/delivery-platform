<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CustomerTrustLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockCustomerTrustRequest;
use App\Models\Customer;
use App\Services\Reputation\CustomerReputationService;
use App\Support\ReputationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole(UserRole::SystemAdmin), 403);

        $customers = Customer::query()
            ->with(['user', 'metrics'])
            ->when(
                filled($request->input('search')),
                function (Builder $query) use ($request): void {
                    $search = $request->string('search')->toString();
                    $query->whereHas('user', function (Builder $user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                filled($request->input('trust_level')),
                fn (Builder $query) => $query->where('trust_level', $request->string('trust_level')->toString()),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Customer $customer): array => ReputationPresenter::customerForAdmin($customer));

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->input('search', ''),
                'trust_level' => $request->input('trust_level', ''),
            ],
            'trustLevels' => collect(CustomerTrustLevel::cases())->map(fn (CustomerTrustLevel $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
            ])->values()->all(),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $this->authorizeAdmin();
        $customer->loadMissing(['user', 'metrics']);

        return Inertia::render('admin/customers/show', [
            'customer' => ReputationPresenter::customerForAdmin($customer),
        ]);
    }

    public function blockTrust(BlockCustomerTrustRequest $request, Customer $customer, CustomerReputationService $reputation): RedirectResponse
    {
        $reputation->markBlocked($customer);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Nivel de confianza marcado como bloqueado.',
        ]);

        return back();
    }

    public function unblockTrust(BlockCustomerTrustRequest $request, Customer $customer, CustomerReputationService $reputation): RedirectResponse
    {
        $reputation->clearBlocked($customer);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bloqueo de reputación retirado. El nivel se recalculó.',
        ]);

        return back();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(request()->user()?->hasRole(UserRole::SystemAdmin), 403);
    }
}
