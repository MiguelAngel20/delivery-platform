<?php

namespace App\Http\Controllers\Web\Business;

use App\Enums\BusinessOperationMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Business\Concerns\ResolvesBusinessCatalog;
use App\Http\Requests\Business\UpdateBusinessProfileRequest;
use App\Models\Business;
use App\Support\BusinessBannerStorage;
use App\Support\BusinessLogoStorage;
use App\Support\BusinessTypes;
use App\Support\UniqueSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProfileController extends Controller
{
    use ResolvesBusinessCatalog;

    public function __construct(
        private readonly BusinessLogoStorage $logoStorage,
        private readonly BusinessBannerStorage $bannerStorage,
    ) {}

    public function edit(Request $request): Response
    {
        $business = $this->currentBusiness($request);
        $this->authorize('update', $business);

        return Inertia::render('business/settings/business', [
            'business' => $this->transformBusiness($business),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateBusinessProfileRequest $request): RedirectResponse
    {
        $business = $this->currentBusiness($request);
        $data = collect($request->validated())->except(['logo', 'banner'])->all();
        $nameChanged = $business->name !== $data['name'];

        $business->update([
            ...$data,
            'slug' => $nameChanged
                ? UniqueSlug::forBusiness($data['name'], $business->id)
                : $business->slug,
        ]);

        if ($request->hasFile('logo')) {
            $this->logoStorage->replace($business, $request->file('logo'));
        }

        if ($request->hasFile('banner')) {
            $this->bannerStorage->replace($business, $request->file('banner'));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Información del negocio actualizada.',
        ]);

        return to_route('business.settings.business.edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'business_types' => BusinessTypes::options(),
            'operation_modes' => collect(BusinessOperationMode::cases())
                ->map(fn (BusinessOperationMode $mode): array => [
                    'value' => $mode->value,
                    'label' => $mode->label(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBusiness(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'description' => $business->description,
            'business_type' => $business->business_type,
            'operation_mode' => $business->operation_mode->value,
            'operation_mode_label' => $business->operation_mode->label(),
            'delivery_mode' => $business->delivery_mode->value,
            'delivery_mode_label' => $business->delivery_mode->label(),
            'status' => $business->status->value,
            'status_label' => $business->status->label(),
            'phone' => $business->phone,
            'email' => $business->email,
            'logo_url' => $this->logoStorage->url($business->logo_path),
            'banner_url' => $this->bannerStorage->url($business->banner_path),
        ];
    }
}
