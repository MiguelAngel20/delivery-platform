<?php

use App\Support\BusinessHours;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('business hours detects open and closed windows', function () {
    $hours = collect(BusinessHours::dayKeys())
        ->map(fn (string $day): array => [
            'day' => $day,
            'is_open' => $day === 'monday',
            'opens_at' => $day === 'monday' ? '09:00' : null,
            'closes_at' => $day === 'monday' ? '18:00' : null,
        ])
        ->all();

    $openAt = Carbon::parse('2026-08-17 12:00:00', BusinessHours::timezone());
    $beforeOpen = Carbon::parse('2026-08-17 08:59:00', BusinessHours::timezone());
    $afterClose = Carbon::parse('2026-08-17 18:01:00', BusinessHours::timezone());
    $closedDay = Carbon::parse('2026-08-18 12:00:00', BusinessHours::timezone());

    expect(BusinessHours::isOpenNow($hours, $openAt))->toBeTrue()
        ->and(BusinessHours::isOpenNow($hours, $beforeOpen))->toBeFalse()
        ->and(BusinessHours::isOpenNow($hours, $afterClose))->toBeFalse()
        ->and(BusinessHours::isOpenNow($hours, $closedDay))->toBeFalse()
        ->and(BusinessHours::todayLabel($hours, $openAt))->toBe('Hoy 09:00 – 18:00')
        ->and(BusinessHours::todayLabel($hours, $beforeOpen))->toBe('Abre hoy a las 09:00')
        ->and(BusinessHours::todayLabel($hours, $closedDay))->toBe('Cerrado hoy');
});

test('null opening hours are treated as closed', function () {
    expect(BusinessHours::isOpenNow(null))->toBeFalse()
        ->and(BusinessHours::isOpenNow([]))->toBeFalse()
        ->and(BusinessHours::todayLabel(null))->toBe('Horario no configurado');
});

test('business hours summarizes consecutive days with the same window', function () {
    $summary = BusinessHours::summarize(BusinessHours::defaults());

    expect($summary)->toHaveCount(2)
        ->and($summary[0]['days_label'])->toBe('Lunes a Viernes')
        ->and($summary[0]['is_open'])->toBeTrue()
        ->and($summary[0]['hours_label'])->toBe('09:00 – 21:00')
        ->and($summary[1]['days_label'])->toBe('Sábado a Domingo')
        ->and($summary[1]['is_open'])->toBeFalse()
        ->and($summary[1]['hours_label'])->toBe('Cerrado')
        ->and(BusinessHours::workingDayLabels(BusinessHours::defaults()))->toBe([
            'Lunes',
            'Martes',
            'Miércoles',
            'Jueves',
            'Viernes',
        ])
        ->and(BusinessHours::summarize(null))->toBe([])
        ->and(BusinessHours::workingDayLabels(null))->toBe([]);
});
