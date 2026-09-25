<?php

namespace App\Support;

use App\Models\ProductCategory;
use Carbon\CarbonInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Optional weekly recurring schedule for principal product categories.
 */
final class CategorySchedule
{
    /**
     * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    public static function defaultHours(): array
    {
        return collect(BusinessHours::dayKeys())
            ->map(fn (string $day): array => [
                'day' => $day,
                'is_open' => false,
                'opens_at' => '09:00',
                'closes_at' => '12:00',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    public static function normalize(mixed $hours): array
    {
        return BusinessHours::normalize(
            is_array($hours) ? $hours : self::prepareInput($hours),
        );
    }

    /**
     * @return list<mixed>
     */
    public static function prepareInput(mixed $hours): array
    {
        return BusinessHours::prepareInput($hours);
    }

    /**
     * @param  list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null  $hours
     */
    public static function isWithinWeeklyHours(?array $hours, ?CarbonInterface $at = null): bool
    {
        return BusinessHours::isOpenNow($hours, $at);
    }

    public static function isVisible(ProductCategory $category, ?CarbonInterface $at = null): bool
    {
        return $category->isVisibleNow($at);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     has_schedule: bool,
     *     schedule_hours: list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null
     * }
     */
    public static function attributesFromValidated(array $data, bool $allowSchedule = true): array
    {
        if (! $allowSchedule) {
            return [
                'has_schedule' => false,
                'schedule_hours' => null,
            ];
        }

        $hasSchedule = filter_var($data['has_schedule'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $hasSchedule) {
            return [
                'has_schedule' => false,
                'schedule_hours' => null,
            ];
        }

        return [
            'has_schedule' => true,
            'schedule_hours' => self::normalize($data['schedule_hours'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(bool $required = true): array
    {
        $hoursRule = $required
            ? ['required', 'array', 'size:7']
            : ['nullable', 'array', 'size:7'];

        return [
            'has_schedule' => ['sometimes', 'boolean'],
            'schedule_hours' => $hoursRule,
            'schedule_hours.*.day' => ['required_with:schedule_hours', 'string', Rule::in(BusinessHours::dayKeys())],
            'schedule_hours.*.is_open' => ['required_with:schedule_hours', 'boolean'],
            'schedule_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'schedule_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public static function afterValidation(): array
    {
        return [
            function (Validator $validator): void {
                $data = $validator->getData();
                $hasSchedule = filter_var($data['has_schedule'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $hasSchedule) {
                    return;
                }

                $hours = $data['schedule_hours'] ?? null;

                if (! is_array($hours)) {
                    return;
                }

                $days = collect($hours)->pluck('day')->filter()->values();

                if ($days->unique()->count() !== $days->count()) {
                    $validator->errors()->add(
                        'schedule_hours',
                        'Cada día de la semana debe aparecer una sola vez.',
                    );
                }

                $openDays = 0;

                foreach ($hours as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $isOpen = filter_var($row['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);

                    if (! $isOpen) {
                        continue;
                    }

                    $openDays++;

                    if (blank($row['opens_at'] ?? null)) {
                        $validator->errors()->add(
                            "schedule_hours.{$index}.opens_at",
                            'Indica la hora de inicio.',
                        );
                    }

                    if (blank($row['closes_at'] ?? null)) {
                        $validator->errors()->add(
                            "schedule_hours.{$index}.closes_at",
                            'Indica la hora de fin.',
                        );
                    }

                    if (
                        filled($row['opens_at'] ?? null)
                        && filled($row['closes_at'] ?? null)
                        && (string) $row['closes_at'] <= (string) $row['opens_at']
                    ) {
                        $validator->errors()->add(
                            "schedule_hours.{$index}.closes_at",
                            'La hora de fin debe ser posterior a la de inicio.',
                        );
                    }
                }

                if ($openDays === 0) {
                    $validator->errors()->add(
                        'schedule_hours',
                        'Selecciona al menos un día con horario para la categoría.',
                    );
                }
            },
        ];
    }
}
