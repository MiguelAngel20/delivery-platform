<?php

namespace App\Services\Reputation;

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\CustomerTrustLevel;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerMetric;
use App\Models\Incident;
use App\Models\Order;
use App\Models\OrderCancellation;

final class CustomerReputationService
{
    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config('reputation.customer');
    }

    public function recalculate(Customer $customer): CustomerMetric
    {
        $facts = $this->collectFacts($customer);
        $score = $this->scoreFromFacts($facts);
        $level = $this->levelFromFacts($facts, $score, $customer->trust_level);
        $now = now();

        // Atomic INSERT ... ON DUPLICATE KEY UPDATE — safe under concurrent recalculates.
        CustomerMetric::query()->upsert(
            [
                [
                    'customer_id' => $customer->id,
                    'total_orders' => $facts['total_orders'],
                    'completed_orders' => $facts['completed_orders'],
                    'cancelled_orders' => $facts['cancelled_orders'],
                    'late_cancellations' => $facts['late_cancellations'],
                    'rejected_at_delivery' => $facts['rejected_at_delivery'],
                    'payment_incidents' => $facts['payment_incidents'],
                    'incident_count' => $facts['incident_count'],
                    'responsible_incidents' => $facts['responsible_incidents'],
                    'trust_score' => $score,
                    'trust_level' => $level->value,
                    'last_recalculated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            uniqueBy: ['customer_id'],
            update: [
                'total_orders',
                'completed_orders',
                'cancelled_orders',
                'late_cancellations',
                'rejected_at_delivery',
                'payment_incidents',
                'incident_count',
                'responsible_incidents',
                'trust_score',
                'trust_level',
                'last_recalculated_at',
                'updated_at',
            ],
        );

        $customer->forceFill([
            'trust_level' => $level,
        ])->save();

        return CustomerMetric::query()
            ->where('customer_id', $customer->id)
            ->firstOrFail();
    }

    public function calculateScore(Customer $customer): float
    {
        return $this->scoreFromFacts($this->collectFacts($customer));
    }

    public function determineLevel(Customer $customer): CustomerTrustLevel
    {
        $facts = $this->collectFacts($customer);

        return $this->levelFromFacts($facts, $this->scoreFromFacts($facts), $customer->trust_level);
    }

    public function markBlocked(Customer $customer): CustomerMetric
    {
        $metrics = $this->recalculate($customer);
        $customer->forceFill([
            'trust_level' => CustomerTrustLevel::Blocked,
        ])->save();

        $metrics->forceFill([
            'trust_level' => CustomerTrustLevel::Blocked,
        ])->save();

        return $metrics->fresh();
    }

    public function clearBlocked(Customer $customer): CustomerMetric
    {
        $customer->forceFill([
            'trust_level' => CustomerTrustLevel::New,
        ])->save();

        return $this->recalculate($customer->fresh());
    }

    /**
     * @return array{
     *     total_orders: int,
     *     completed_orders: int,
     *     cancelled_orders: int,
     *     late_cancellations: int,
     *     early_cancellations: int,
     *     rejected_at_delivery: int,
     *     payment_incidents: int,
     *     incident_count: int,
     *     responsible_incidents: int
     * }
     */
    public function collectFacts(Customer $customer): array
    {
        $orders = Order::query()
            ->with(['cancellation', 'incidents', 'payment'])
            ->where('customer_id', $customer->id)
            ->get();

        $completed = $orders->where('order_status', OrderStatus::Delivered)->count();
        $cancelled = $orders->where('order_status', OrderStatus::Cancelled)->count();
        $late = 0;
        $early = 0;
        $rejectedAtDelivery = 0;
        $responsibleIncidents = 0;
        $paymentIncidents = 0;

        foreach ($orders as $order) {
            $cancellation = $order->cancellation;

            if ($cancellation !== null && $this->isCustomerResponsible($cancellation)) {
                if ($this->isDeliveryRefusal($cancellation)) {
                    $rejectedAtDelivery++;
                } elseif ($this->isLateCancellation($cancellation)) {
                    $late++;
                } elseif ($cancellation->previous_order_status->isEarlyCustomerCancelWindow()) {
                    $early++;
                }
            }

            foreach ($order->incidents as $incident) {
                if ($this->isCustomerPaymentIncident($incident)) {
                    $paymentIncidents++;
                }

                if ($this->isCustomerResponsibleIncident($incident)) {
                    $responsibleIncidents++;
                }
            }
        }

        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $completed,
            'cancelled_orders' => $cancelled,
            'late_cancellations' => $late,
            'early_cancellations' => $early,
            'rejected_at_delivery' => $rejectedAtDelivery,
            'payment_incidents' => $paymentIncidents,
            'incident_count' => $orders->sum(fn (Order $order): int => $order->incidents->count()),
            'responsible_incidents' => $responsibleIncidents,
        ];
    }

    /**
     * @param  array<string, int>  $facts
     */
    public function scoreFromFacts(array $facts): float
    {
        $cfg = $this->config();
        $points = $cfg['points'];

        $score = (float) $cfg['base_score']
            + ($facts['completed_orders'] * (float) $points['completed_order'])
            + (($facts['early_cancellations'] ?? 0) * (float) $points['early_cancellation'])
            + ($facts['late_cancellations'] * (float) $points['late_cancellation'])
            + ($facts['rejected_at_delivery'] * (float) $points['rejected_at_delivery'])
            + ($facts['payment_incidents'] * (float) $points['payment_incident'])
            + ($facts['responsible_incidents'] * (float) $points['responsible_incident']);

        return round(
            max((float) $cfg['min_score'], min((float) $cfg['max_score'], $score)),
            2,
        );
    }

    /**
     * @param  array<string, int>  $facts
     */
    public function levelFromFacts(array $facts, float $score, CustomerTrustLevel $current): CustomerTrustLevel
    {
        if ($current === CustomerTrustLevel::Blocked) {
            return CustomerTrustLevel::Blocked;
        }

        $levels = $this->config()['levels'];

        if ($facts['completed_orders'] <= (int) $levels['new_max_completed_orders']
            && $facts['late_cancellations'] === 0
            && $facts['rejected_at_delivery'] === 0
            && $facts['payment_incidents'] === 0
            && $facts['responsible_incidents'] === 0) {
            return CustomerTrustLevel::New;
        }

        $restricted = $facts['late_cancellations'] >= (int) $levels['restricted_late_cancellations']
            || $facts['rejected_at_delivery'] >= (int) $levels['restricted_rejected_at_delivery']
            || $facts['payment_incidents'] >= (int) $levels['restricted_payment_incidents']
            || $facts['responsible_incidents'] >= (int) $levels['restricted_responsible_incidents']
            || $score <= (float) $levels['restricted_max_score'];

        if ($restricted) {
            return CustomerTrustLevel::Restricted;
        }

        $trusted = $facts['completed_orders'] >= (int) $levels['trusted_min_completed_orders']
            && $facts['late_cancellations'] <= (int) $levels['trusted_max_late_cancellations']
            && $facts['rejected_at_delivery'] <= (int) $levels['trusted_max_rejected_at_delivery']
            && $facts['payment_incidents'] <= (int) $levels['trusted_max_payment_incidents']
            && $score >= (float) $levels['trusted_min_score'];

        if ($trusted) {
            return CustomerTrustLevel::Trusted;
        }

        return CustomerTrustLevel::Good;
    }

    private function isCustomerResponsible(OrderCancellation $cancellation): bool
    {
        return $cancellation->responsibility === CancellationResponsibility::Customer;
    }

    private function isLateCancellation(OrderCancellation $cancellation): bool
    {
        return in_array(
            $cancellation->previous_order_status->value,
            $this->config()['late_statuses'],
            true,
        );
    }

    private function isDeliveryRefusal(OrderCancellation $cancellation): bool
    {
        if ($cancellation->reason_code === CancellationReasonCode::CustomerRefusedDelivery) {
            return true;
        }

        return in_array(
            $cancellation->previous_order_status->value,
            $this->config()['delivery_refusal_statuses'],
            true,
        );
    }

    private function isCustomerPaymentIncident(Incident $incident): bool
    {
        return $incident->type === IncidentType::PaymentProblem
            && $incident->status === IncidentStatus::Resolved;
    }

    private function isCustomerResponsibleIncident(Incident $incident): bool
    {
        if ($incident->status !== IncidentStatus::Resolved) {
            return false;
        }

        return $incident->type === IncidentType::CustomerRefusedOrder;
    }
}
