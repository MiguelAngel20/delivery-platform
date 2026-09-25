<?php

namespace App\Support;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Weekly recurring schedule for promotions (independent from branch hours).
 */
final class PromotionSchedule
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

    public static function isAvailable(Promotion $promotion, ?CarbonInterface $at = null): bool
    {
        $at = Carbon::instance($at ?? now());

        if ($promotion->status !== PromotionStatus::Active) {
            return false;
        }

        if ($promotion->is_recurring) {
            if ($promotion->recurrence_starts_on === null) {
                return false;
            }

            $startDate = Carbon::parse($promotion->recurrence_starts_on)->startOfDay();

            if ($at->copy()->startOfDay()->lt($startDate)) {
                return false;
            }

            if ($promotion->recurrence_ends_on !== null) {
                $endDate = Carbon::parse($promotion->recurrence_ends_on)->endOfDay();

                if ($at->gt($endDate)) {
                    return false;
                }
            }

            return self::isWithinWeeklyHours(
                is_array($promotion->recurring_hours) ? $promotion->recurring_hours : null,
                $at,
            );
        }

        if ($promotion->starts_at !== null && $promotion->starts_at->gt($at)) {
            return false;
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->lt($at)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     is_recurring: bool,
     *     starts_at: mixed,
     *     ends_at: mixed,
     *     recurrence_starts_on: mixed,
     *     recurrence_ends_on: mixed,
     *     recurring_hours: list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null
     * }
     */
    public static function attributesFromValidated(array $data): array
    {
        $isRecurring = filter_var($data['is_recurring'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isRecurring) {
            return [
                'is_recurring' => true,
                'starts_at' => null,
                'ends_at' => null,
                'recurrence_starts_on' => $data['recurrence_starts_on'] ?? null,
                'recurrence_ends_on' => $data['recurrence_ends_on'] ?? null,
                'recurring_hours' => self::normalize($data['recurring_hours'] ?? []),
            ];
        }

        return [
            'is_recurring' => false,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'recurrence_starts_on' => null,
            'recurrence_ends_on' => null,
            'recurring_hours' => null,
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
            'recurring_hours' => $hoursRule,
            'recurring_hours.*.day' => ['required_with:recurring_hours', 'string', Rule::in(BusinessHours::dayKeys())],
            'recurring_hours.*.is_open' => ['required_with:recurring_hours', 'boolean'],
            'recurring_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'recurring_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
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
                $isRecurring = filter_var($data['is_recurring'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $isRecurring) {
                    return;
                }

                $hours = $data['recurring_hours'] ?? null;

                if (! is_array($hours)) {
                    return;
                }

                $days = collect($hours)->pluck('day')->filter()->values();

                if ($days->unique()->count() !== $days->count()) {
                    $validator->errors()->add(
                        'recurring_hours',
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
                            "recurring_hours.{$index}.opens_at",
                            'Indica la hora de inicio.',
                        );
                    }

                    if (blank($row['closes_at'] ?? null)) {
                        $validator->errors()->add(
                            "recurring_hours.{$index}.closes_at",
                            'Indica la hora de fin.',
                        );
                    }

                    if (
                        filled($row['opens_at'] ?? null)
                        && filled($row['closes_at'] ?? null)
                        && (string) $row['closes_at'] <= (string) $row['opens_at']
                    ) {
                        $validator->errors()->add(
                            "recurring_hours.{$index}.closes_at",
                            'La hora de fin debe ser posterior a la de inicio.',
                        );
                    }
                }

                if ($openDays === 0) {
                    $validator->errors()->add(
                        'recurring_hours',
                        'Selecciona al menos un día con horario para la promoción recurrente.',
                    );
                }
            },
        ];
    }
}
