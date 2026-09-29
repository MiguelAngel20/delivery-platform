<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\SaveAdminUser;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(): Response
    {
        $admins = User::query()
            ->where('role', UserRole::SystemAdmin)
            ->with('adminPermissions')
            ->orderByDesc('is_platform_owner')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->summary($user))
            ->values()
            ->all();

        return Inertia::render('admin/settings/admins/index', [
            'admins' => $admins,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/settings/admins/create', [
            'sections' => AdminAccess::sectionOptions(),
            'permissions' => AdminAccess::permissionInput(),
        ]);
    }

    public function store(StoreAdminUserRequest $request, SaveAdminUser $save): RedirectResponse
    {
        $save->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Administrador creado.',
        ]);

        return to_route('admin.settings.admins.index');
    }

    public function edit(User $adminUser): Response
    {
        $this->editableStaff($adminUser);
        $adminUser->load('adminPermissions');

        $overrides = [];

        foreach ($adminUser->adminPermissions as $permission) {
            $overrides[$permission->section->value] = [
                'view' => $permission->can_view,
                'create' => $permission->can_create,
                'update' => $permission->can_update,
                'delete' => $permission->can_delete,
            ];
        }

        return Inertia::render('admin/settings/admins/edit', [
            'adminUser' => [
                'id' => $adminUser->id,
                'first_name' => $adminUser->first_name,
                'last_name' => $adminUser->last_name,
                'email' => $adminUser->email,
                'phone' => $adminUser->phone,
            ],
            'sections' => AdminAccess::sectionOptions(),
            'permissions' => AdminAccess::permissionInput($overrides),
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $adminUser, SaveAdminUser $save): RedirectResponse
    {
        $this->editableStaff($adminUser);

        $save->update($adminUser, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Administrador actualizado.',
        ]);

        return to_route('admin.settings.admins.index');
    }

    public function deactivate(Request $request, User $adminUser): RedirectResponse
    {
        $this->editableStaff($adminUser);
        abort_if($adminUser->is($request->user()), 403);

        $adminUser->forceFill(['status' => UserStatus::Inactive])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Administrador desactivado.',
        ]);

        return back();
    }

    public function activate(User $adminUser): RedirectResponse
    {
        $this->editableStaff($adminUser);

        $adminUser->forceFill(['status' => UserStatus::Active])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Administrador reactivado.',
        ]);

        return back();
    }

    private function editableStaff(User $user): void
    {
        abort_unless($user->hasRole(UserRole::SystemAdmin), 404);
        abort_if($user->isPlatformOwner(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        $granted = $user->isPlatformOwner()
            ? ['Acceso completo']
            : $user->adminPermissions
                ->filter(fn ($permission): bool => $permission->can_view || $permission->can_create || $permission->can_update || $permission->can_delete)
                ->map(fn ($permission): string => $permission->section->label())
                ->values()
                ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'is_owner' => $user->isPlatformOwner(),
            'sections' => $granted,
        ];
    }
}
