<?php

namespace App\Http\Middleware;

use App\Enums\BranchStatus;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Support\BusinessAccess;
use App\Support\BusinessTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'status' => $user->status->value,
                ],
            ],
            'businessContext' => $this->businessContext($request),
            'realtime' => $this->realtimeContext($request),
            'orderSettings' => [
                'service_fee' => (float) config('business.orders.service_fee', 50),
                'delivery_fee' => (float) config('business.orders.delivery_fee', 0),
                'max_customer_addresses' => (int) config('business.orders.max_customer_addresses', 4),
            ],
            'maps' => [
                'browser_api_key' => (string) config('maps.browser_api_key', ''),
                'default_center' => config('maps.default_center'),
                'default_place_label' => (string) config(
                    'maps.default_place_label',
                    'Comitán de Domínguez, Chiapas',
                ),
            ],
            'storefront' => [
                'categories' => BusinessTypes::categories(),
                'searchSuggestions' => $this->searchSuggestions($request),
            ],
            'notifications' => [
                'unread_count' => $user?->unreadNotifications()->count() ?? 0,
            ],
            'push' => [
                'enabled' => (bool) config('push.enabled', false),
                'vapid_key' => (string) config('push.web.vapid_key', ''),
                'web' => [
                    'apiKey' => (string) config('push.web.api_key', ''),
                    'authDomain' => (string) config('push.web.auth_domain', ''),
                    'projectId' => (string) config('push.web.project_id', ''),
                    'storageBucket' => (string) config('push.web.storage_bucket', ''),
                    'messagingSenderId' => (string) config('push.web.messaging_sender_id', ''),
                    'appId' => (string) config('push.web.app_id', ''),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return list<string>
     */
    private function searchSuggestions(Request $request): array
    {
        if ($request->is(['admin', 'admin/*', 'business', 'business/*', 'driver', 'driver/*'])) {
            return [];
        }

        if (app()->runningUnitTests()) {
            return $this->activeBusinessNames();
        }

        return Cache::remember(
            'storefront.search_suggestions',
            now()->addMinutes(5),
            fn (): array => $this->activeBusinessNames(),
        );
    }

    /**
     * @return list<string>
     */
    private function activeBusinessNames(): array
    {
        return Business::query()
            ->where('status', BusinessStatus::Active)
            ->whereHas('branches', fn ($query) => $query->where('status', BranchStatus::Active))
            ->orderByRaw('case when operation_mode = ? then 0 else 1 end', [
                BusinessOperationMode::Partner->value,
            ])
            ->orderBy('name')
            ->limit(24)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{business: array<string, mixed>, membership_role: string|null, membership_role_label: string|null, branches: list<array<string, mixed>>, current_branch_id: int|null}|null
     */
    private function businessContext(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
            return null;
        }

        /** @var BusinessUser|null $membership */
        $membership = $user->businessMemberships()
            ->where('status', BusinessUserStatus::Active)
            ->with(['business'])
            ->first();

        $business = $membership?->business;

        if ($membership === null || $business === null) {
            return null;
        }

        $branches = app(BusinessAccess::class)->accessibleBranches($user, $business);
        $currentBranch = $branches->first(
            fn ($branch) => $branch->status === BranchStatus::Active,
        ) ?? $branches->first();

        return [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'status' => $business->status->value,
                'status_label' => $business->status->label(),
            ],
            'membership_role' => $membership->role->value,
            'membership_role_label' => $membership->role->label(),
            'branches' => $branches
                ->map(fn ($branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'status' => $branch->status->value,
                    'status_label' => $branch->status->label(),
                ])
                ->values()
                ->all(),
            'current_branch_id' => $currentBranch?->id,
        ];
    }

    /**
     * @return array{
     *     customer_id: int|null,
     *     driver_id: int|null,
     *     business_id: int|null,
     *     branch_ids: list<int>
     * }
     */
    private function realtimeContext(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [
                'customer_id' => null,
                'driver_id' => null,
                'business_id' => null,
                'branch_ids' => [],
            ];
        }

        $user->loadMissing(['customer', 'driver']);

        $branchIds = [];
        $businessId = null;

        if ($user->hasRole(UserRole::BusinessAdmin, UserRole::BusinessEmployee)) {
            $context = $this->businessContext($request);
            $businessId = $context['business']['id'] ?? null;
            $branchIds = collect($context['branches'] ?? [])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        return [
            'customer_id' => $user->customer?->id,
            'driver_id' => $user->driver?->id,
            'business_id' => $businessId,
            'branch_ids' => $branchIds,
        ];
    }
}
