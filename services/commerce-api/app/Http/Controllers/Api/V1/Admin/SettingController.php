<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SettingMutationRequest;
use App\Http\Responses\ProblemDetails;
use App\Models\ApplicationSetting;
use App\Services\AdminAuditLogger;
use App\Services\ApplicationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function __construct(
        private readonly ApplicationSettings $settings,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(array_map(
            fn (string $key): array => $this->settings->effective($key),
            $this->settings->keys(),
        ));
    }

    public function update(SettingMutationRequest $request, string $key): JsonResponse
    {
        if (! $this->settings->supports($key)) {
            return $this->unknownKey($request);
        }

        $setting = DB::transaction(function () use ($request, $key): ApplicationSetting {
            $setting = ApplicationSetting::query()->where('key', $key)->lockForUpdate()->first();
            $before = $this->settings->effective($key);

            if ($setting instanceof ApplicationSetting) {
                $setting->forceFill([
                    'value' => (string) $request->integer('value'),
                    'type' => $this->settings->type($key),
                    'description' => $this->settings->description($key),
                    'version' => $setting->version + 1,
                ])->save();
            } else {
                $setting = ApplicationSetting::query()->create([
                    'key' => $key,
                    'value' => (string) $request->integer('value'),
                    'type' => $this->settings->type($key),
                    'description' => $this->settings->description($key),
                    'version' => 1,
                ]);
            }

            $this->audit->record($request, 'setting.updated', $setting, $before, $this->settings->effective($key));

            return $setting;
        }, 3);

        return response()->json($this->settings->effective($setting->key));
    }

    public function destroy(Request $request, string $key): JsonResponse
    {
        if (! $this->settings->supports($key)) {
            return $this->unknownKey($request);
        }

        DB::transaction(function () use ($request, $key): void {
            $setting = ApplicationSetting::query()->where('key', $key)->lockForUpdate()->first();

            if (! $setting instanceof ApplicationSetting) {
                return;
            }

            $before = $this->settings->effective($key);
            $setting->delete();
            $this->audit->record($request, 'setting.override_removed', $setting, $before, $this->settings->effective($key));
        }, 3);

        return response()->json($this->settings->effective($key));
    }

    private function unknownKey(Request $request): JsonResponse
    {
        return ProblemDetails::response(
            $request,
            404,
            'Setting not found',
            'The requested application setting is not allowlisted.',
            'setting_not_found',
        );
    }
}
