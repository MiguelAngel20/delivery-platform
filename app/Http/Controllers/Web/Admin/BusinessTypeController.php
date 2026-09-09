<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\BusinessTypeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBusinessTypeRequest;
use App\Http\Requests\Admin\UpdateBusinessTypeRequest;
use App\Models\Business;
use App\Models\BusinessType;
use App\Support\BusinessTypes;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessTypeController extends Controller
{
    public function index(): Response
    {
        $types = BusinessType::query()
            ->ordered()
            ->get()
            ->map(function (BusinessType $type): array {
                $inUse = Business::query()
                    ->where('business_type', $type->name)
                    ->exists();

                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'description' => $type->description,
                    'status' => $type->status->value,
                    'status_label' => $type->status->label(),
                    'sort_order' => $type->sort_order,
                    'can_delete' => ! $inUse,
                ];
            });

        return Inertia::render('admin/business-types/index', [
            'types' => $types,
            'options' => [
                'statuses' => collect(BusinessTypeStatus::cases())->map(fn (BusinessTypeStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])->values()->all(),
            ],
        ]);
    }

    public function store(StoreBusinessTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        BusinessType::query()->create([
            ...$data,
            'slug' => BusinessTypes::uniqueSlug($data['name']),
            'sort_order' => (int) (BusinessType::query()->max('sort_order') ?? 0) + 1,
        ]);

        BusinessTypes::forgetCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo / giro creado.',
        ]);

        return back();
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $businessType): RedirectResponse
    {
        $data = $request->validated();
        $previousName = $businessType->name;

        $businessType->update([
            ...$data,
            'slug' => BusinessTypes::uniqueSlug($data['name'], $businessType->id),
        ]);

        if ($previousName !== $businessType->name) {
            Business::query()
                ->where('business_type', $previousName)
                ->update(['business_type' => $businessType->name]);
        }

        BusinessTypes::forgetCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo / giro actualizado.',
        ]);

        return back();
    }

    public function destroy(BusinessType $businessType): RedirectResponse
    {
        $inUse = Business::query()
            ->where('business_type', $businessType->name)
            ->exists();

        if ($inUse) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: hay empresas usando este tipo / giro.',
            ]);

            return back();
        }

        $businessType->delete();
        BusinessTypes::forgetCache();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tipo / giro eliminado.',
        ]);

        return back();
    }
}
