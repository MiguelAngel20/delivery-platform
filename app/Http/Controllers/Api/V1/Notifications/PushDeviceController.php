<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\RegisterPushDeviceRequest;
use App\Services\Push\DeviceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(
        RegisterPushDeviceRequest $request,
        DeviceRegistrationService $devices,
    ): JsonResponse {
        $device = $devices->register($request->user(), $request->validated());

        return response()->json([
            'id' => $device->id,
            'token' => $device->token,
            'is_active' => $device->is_active,
        ], 201);
    }

    public function destroy(Request $request, DeviceRegistrationService $devices): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $devices->deactivateToken($request->user(), $validated['token']);

        return response()->json(['ok' => true]);
    }
}
