export type OrderRealtimePayload = {
    order_id: number;
    order_number: string;
    status: string;
    status_label?: string;
    previous_status?: string;
    branch_id: number;
    business_id?: number | null;
    customer_id: number;
    assigned_driver_id?: number | null;
    assigned_driver_name?: string | null;
    estimated_preparation_minutes?: number | null;
    updated_at?: string | null;
};

export type IncidentRealtimePayload = {
    incident_id: number;
    order_id?: number | null;
    order_number?: string | null;
    type: string;
    severity: string;
    status: string;
};

export type DriverRatedPayload = {
    rating_id: number;
    driver_id: number;
    order_id: number;
    overall_rating: number;
    average_rating?: string | number | null;
};

export const ORDER_REALTIME_EVENTS = [
    '.OrderCreated',
    '.OrderStatusChanged',
    '.DriverAssigned',
    '.OrderAvailable',
    '.IncidentCreated',
    '.DriverRated',
] as const;
