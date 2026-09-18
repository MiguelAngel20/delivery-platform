<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCoverageZoneRequest;
use App\Http\Requests\Admin\UpdateCoverageZoneRequest;
use App\Http\Requests\Admin\UpdateServiceFeeDistanceSettingsRequest;
use App\Models\BusinessBranch;
use App\Models\CoverageZone;
use App\Models\ServiceFeeDistanceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoverageZoneController extends Controller
{
    public function index(Request $request): Response
    {
        $zones = CoverageZone::query()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CoverageZone $zone): array => $this->zonePayload($zone));

        $branches = BusinessBranch::query()
            ->with('business:id,name')
            ->orderBy('name')
            ->get(['id', 'business_id', 'name', 'latitude', 'longitude'])
            ->map(fn (BusinessBranch $branch): array => [
                'id' => $branch->id,
                'name' => ($branch->business?->name ? $branch->business->name.' · ' : '').$branch->name,
                'latitude' => (string) $branch->latitude,
                'longitude' => (string) $branch->longitude,
            ]);

        $tariff = ServiceFeeDistanceSetting::current();

        return Inertia::render('admin/coverage/index', [
            'zones' => $zones,
            'branches' => $branches,
            'serviceFeeTariff' => [
                'base_meters' => (int) $tariff->base_meters,
                'base_fee' => number_format((float) $tariff->base_fee, 2, '.', ''),
                'step_meters' => (int) $tariff->step_meters,
                'step_fee' => number_format((float) $tariff->step_fee, 2, '.', ''),
            ],
            'options' => [
                'scope_types' => collect(CoverageScopeType::cases())->map(fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])->values()->all(),
                'zone_types' => [
                    ['value' => CoverageZoneType::Radius->value, 'label' => CoverageZoneType::Radius->label()],
                ],
                'radius_presets_meters' => [1000, 3000, 5000, 8000, 10000],
            ],
            'maps' => [
                'browser_api_key' => (string) config('maps.browser_api_key', ''),
                'default_center' => config('maps.default_center'),
                'default_place_label' => (string) config(
                    'maps.default_place_label',
                    'Comitán de Domínguez, Chiapas',
                ),
            ],
        ]);
    }

    public function store(StoreCoverageZoneRequest $request): RedirectResponse
    {
        CoverageZone::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Zona de cobertura creada.',
        ]);

        return back();
    }

    public function update(UpdateCoverageZoneRequest $request, CoverageZone $coverage): RedirectResponse
    {
        $coverage->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Zona de cobertura actualizada.',
        ]);

        return back();
    }

    public function updateTariff(UpdateServiceFeeDistanceSettingsRequest $request): RedirectResponse
    {
        $tariff = ServiceFeeDistanceSetting::current();
        $tariff->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarifa de servicio actualizada.',
        ]);

        return back();
    }

    public function deactivate(CoverageZone $coverage): RedirectResponse
    {
        $coverage->update(['is_active' => false]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Zona desactivada.',
        ]);

        return back();
    }

    public function activate(CoverageZone $coverage): RedirectResponse
    {
        $coverage->update(['is_active' => true]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Zona activada.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function zonePayload(CoverageZone $zone): array
    {
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'scope_type' => $zone->scope_type->value,
            'scope_type_label' => $zone->scope_type->label(),
            'scope_id' => $zone->scope_id,
            'zone_type' => $zone->zone_type->value,
            'zone_type_label' => $zone->zone_type->label(),
            'center_latitude' => $zone->center_latitude !== null ? (string) $zone->center_latitude : null,
            'center_longitude' => $zone->center_longitude !== null ? (string) $zone->center_longitude : null,
            'radius_meters' => $zone->radius_meters,
            'is_active' => $zone->is_active,
        ];
    }
}
