<?php

use App\Events\Orders\OrderCreated;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

test('SafeBroadcast swallows connection failures', function () {
    Log::spy();

    config(['broadcasting.default' => 'reverb']);
    config(['broadcasting.connections.reverb.options.host' => '127.0.0.1']);
    config(['broadcasting.connections.reverb.options.port' => 59999]);
    config(['broadcasting.connections.reverb.options.scheme' => 'http']);
    config(['broadcasting.connections.reverb.options.useTLS' => false]);

    SafeBroadcast::event(new OrderCreated([
        'order_id' => 1,
        'order_number' => 'RIDE-TEST',
    ], ['admin']));

    Log::shouldHaveReceived('warning')->once();
});
