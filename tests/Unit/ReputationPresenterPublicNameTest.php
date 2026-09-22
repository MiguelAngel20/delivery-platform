<?php

use App\Support\ReputationPresenter;

test('driver public name uses second given name initial when present', function () {
    expect(ReputationPresenter::driverPublicName('Miguel Angel', 'Santiz Rodriguez'))
        ->toBe('Miguel A.');
});

test('driver public name uses first surname initial when only one given name', function () {
    expect(ReputationPresenter::driverPublicName('Miguel', 'Santiz Rodriguez'))
        ->toBe('Miguel S.');
});

test('driver public name falls back to first name alone when surname is missing', function () {
    expect(ReputationPresenter::driverPublicName('Ana', ''))->toBe('Ana')
        ->and(ReputationPresenter::driverPublicName('', 'Lopez'))->toBe('Repartidor');
});
