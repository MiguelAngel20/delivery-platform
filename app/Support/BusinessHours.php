<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class BusinessHours
{
    /**
     * @return array<string, string>
     */
    public static function dayLabels(): array
    {
        return [
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
        ];
    }

    /**
     * @return list<string>
     */
    public static function dayKeys(): array
    {
        return array_keys(self::dayLabels());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function dayOptions(): array
    {
        return collect(self::dayLabels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    public static function defaults(): array
    {
        return collect(self::dayKeys())
            ->map(function (string $day): array {
                $isWeekend = in_array($day, ['saturday', 'sunday'], true);

                return [
                    'day' => $day,
                    'is_open' => ! $isWeekend,
                    'opens_at' => $isWeekend ? null : '09:00',
                    'closes_at' => $isWeekend ? null : '21:00',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    public static function alwaysOpen(): array
    {
        return collect(self::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => true,
                'opens_at' => '00:00',
                'closes_at' => '23:59',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    public static function normalize(mixed $hours): array
    {
        $indexed = collect(is_array($hours) ? $hours : [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['day'] ?? null))
            ->keyBy(fn (array $row): string => (string) $row['day']);

        return collect(self::dayKeys())
            ->map(function (string $day) use ($indexed): array {
                $row = $indexed->get($day, []);
                $isOpen = filter_var($row['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $opensAt = self::normalizeTime($row['opens_at'] ?? null);
                $closesAt = self::normalizeTime($row['closes_at'] ?? null);

                if (! $isOpen) {
                    return [
                        'day' => $day,
                        'is_open' => false,
                        'opens_at' => null,
                        'closes_at' => null,
                    ];
                }

                return [
                    'day' => $day,
                    'is_open' => true,
                    'opens_at' => $opensAt,
                    'closes_at' => $closesAt,
                ];
            })
            ->values()
            ->all();
    }

    public static function timezone(): string
    {
        return (string) config('business.hours_timezone', config('app.timezone', 'UTC'));
    }

    /**
     * @param  list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null  $hours
     */
    public static function isOpenNow(?array $hours, ?CarbonInterface $at = null): bool
    {
        if ($hours === null || $hours === []) {
            return false;
        }

        $at = Carbon::instance($at ?? now())->timezone(self::timezone());
        $day = strtolower($at->englishDayOfWeek);
        $row = collect(self::normalize($hours))->firstWhere('day', $day);

        if (! is_array($row) || ! ($row['is_open'] ?? false)) {
            return false;
        }

        $opensAt = self::normalizeTime($row['opens_at'] ?? null);
        $closesAt = self::normalizeTime($row['closes_at'] ?? null);

        if ($opensAt === null || $closesAt === null) {
            return false;
        }

        $current = $at->format('H:i');

        return $current >= $opensAt && $current <= $closesAt;
    }

    /**
     * @param  list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null  $hours
     */
    public static function todayLabel(?array $hours, ?CarbonInterface $at = null): string
    {
        if ($hours === null || $hours === []) {
            return 'Horario no configurado';
        }

        $at = Carbon::instance($at ?? now())->timezone(self::timezone());
        $day = strtolower($at->englishDayOfWeek);
        $row = collect(self::normalize($hours))->firstWhere('day', $day);

        if (! is_array($row) || ! ($row['is_open'] ?? false)) {
            return 'Cerrado hoy';
        }

        $opensAt = self::normalizeTime($row['opens_at'] ?? null);
        $closesAt = self::normalizeTime($row['closes_at'] ?? null);

        if ($opensAt === null || $closesAt === null) {
            return 'Cerrado hoy';
        }

        if (self::isOpenNow($hours, $at)) {
            return sprintf('Hoy %s – %s', $opensAt, $closesAt);
        }

        $current = $at->format('H:i');

        if ($current < $opensAt) {
            return sprintf('Abre hoy a las %s', $opensAt);
        }

        return sprintf('Cerrado · Horario de hoy %s – %s', $opensAt, $closesAt);
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'opening_hours' => ['required', 'array', 'size:7'],
            'opening_hours.*.day' => ['required', 'string', Rule::in(self::dayKeys())],
            'opening_hours.*.is_open' => ['required', 'boolean'],
            'opening_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public static function afterValidation(): array
    {
        return [
            function (Validator $validator): void {
                $hours = $validator->getData()['opening_hours'] ?? null;

                if (! is_array($hours)) {
                    return;
                }

                $days = collect($hours)->pluck('day')->filter()->values();

                if ($days->unique()->count() !== $days->count()) {
                    $validator->errors()->add(
                        'opening_hours',
                        'Cada día de la semana debe aparecer una sola vez.',
                    );
                }

                foreach ($hours as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $isOpen = filter_var($row['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if (! $isOpen) {
                        continue;
                    }

                    if (blank($row['opens_at'] ?? null)) {
                        $validator->errors()->add(
                            "opening_hours.{$index}.opens_at",
                            'Indica la hora de apertura.',
                        );
                    }

                    if (blank($row['closes_at'] ?? null)) {
                        $validator->errors()->add(
                            "opening_hours.{$index}.closes_at",
                            'Indica la hora de cierre.',
                        );
                    }

                    if (
                        filled($row['opens_at'] ?? null)
                        && filled($row['closes_at'] ?? null)
                        && (string) $row['closes_at'] <= (string) $row['opens_at']
                    ) {
                        $validator->errors()->add(
                            "opening_hours.{$index}.closes_at",
                            'La hora de cierre debe ser posterior a la de apertura.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * @param  list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null  $hours
     * @return list<array{day: string, day_label: string, is_open: bool, opens_at: string|null, closes_at: string|null, label: string}>
     */
    public static function present(?array $hours): array
    {
        return collect(self::normalize($hours ?? self::defaults()))
            ->map(function (array $row): array {
                $dayLabel = self::dayLabels()[$row['day']] ?? $row['day'];

                return [
                    ...$row,
                    'day_label' => $dayLabel,
                    'label' => $row['is_open']
                        ? sprintf('%s – %s', $row['opens_at'], $row['closes_at'])
                        : 'Cerrado',
                ];
            })
            ->values()
            ->all();
    }

    private static function normalizeTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, 5);
    }
}
