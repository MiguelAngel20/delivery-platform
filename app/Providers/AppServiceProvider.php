<?php

namespace App\Providers;

use App\Contracts\MapsClient;
use App\Contracts\PushProvider;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Push\FcmHttpV1PushProvider;
use App\Services\Push\LogPushProvider;
use App\Services\Push\NullPushProvider;
use App\Support\ApplicationPassword;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MapsClient::class, GoogleMapsClient::class);
        $this->app->bind(PushProvider::class, function (): PushProvider {
            if (! (bool) config('push.enabled', false)) {
                return config('push.driver') === 'log'
                    ? new LogPushProvider
                    : new NullPushProvider;
            }

            return match ((string) config('push.driver', 'fcm')) {
                'log' => new LogPushProvider,
                'null' => new NullPushProvider,
                default => $this->resolveFcmProvider(),
            };
        });
    }

    private function resolveFcmProvider(): PushProvider
    {
        $projectId = (string) config('push.fcm.project_id', '');
        $hasJson = (string) config('push.fcm.credentials_json', '') !== '';
        $path = (string) config('push.fcm.credentials', '');
        $hasFile = $path !== '' && is_readable($path);

        if ($projectId === '' || (! $hasJson && ! $hasFile)) {
            return new NullPushProvider;
        }

        return new FcmHttpV1PushProvider;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => ApplicationPassword::rule());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('maps-geo', function (Request $request) {
            $limit = (int) config('maps.geocode_rate_limit_per_minute', 30);

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('maps-distance', function (Request $request) {
            $limit = (int) config('maps.distance_rate_limit_per_minute', 30);

            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('customer-register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('customer-verify-email', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip());
        });
    }
}
