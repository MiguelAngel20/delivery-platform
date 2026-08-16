export type EnumOption = {
    value: string;
    label: string;
};

export type BusinessOpeningHour = {
    day: string;
    day_label?: string;
    is_open: boolean;
    opens_at: string | null;
    closes_at: string | null;
    label?: string;
};

export type BusinessFormOptions = {
    business_types: string[];
    operation_modes: EnumOption[];
    delivery_modes: EnumOption[];
    statuses: EnumOption[];
    weekdays: EnumOption[];
    default_opening_hours: BusinessOpeningHour[];
};

export type BusinessListItem = {
    id: number;
    name: string;
    slug: string;
    operation_mode: string;
    operation_mode_label: string;
    delivery_mode: string;
    delivery_mode_label: string;
    status: string;
    status_label: string;
    branches_count: number;
    created_at: string | null;
};

export type BusinessBranchItem = {
    id: number;
    name: string;
    phone: string | null;
    address_text: string;
    reference: string | null;
    latitude: string;
    longitude: string;
    google_maps_url: string | null;
    status: string;
    status_label: string;
};

export type BusinessMembershipItem = {
    id: number;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    user: {
        id: number;
        name: string;
        email: string;
        phone?: string | null;
        first_name?: string;
        last_name?: string;
    } | null;
    branches: Array<{ id: number; name: string }>;
    branch_ids?: number[];
};

export type BusinessDetail = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    business_type: string | null;
    operation_mode: string;
    operation_mode_label: string;
    delivery_mode: string;
    delivery_mode_label: string;
    status: string;
    status_label: string;
    phone: string | null;
    email: string | null;
    opening_hours: BusinessOpeningHour[];
    logo_path: string | null;
    logo_url: string | null;
    banner_path: string | null;
    banner_url: string | null;
    rejection_reason: string | null;
    suspension_reason: string | null;
    approved_at: string | null;
    created_at: string | null;
    created_by: { id: number; name: string } | null;
    approved_by: { id: number; name: string } | null;
    branches: BusinessBranchItem[];
    memberships: BusinessMembershipItem[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type BusinessStatusTone =
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'primary';

export function businessStatusTone(status: string): BusinessStatusTone {
    switch (status) {
        case 'active':
            return 'success';
        case 'pending_approval':
            return 'warning';
        case 'rejected':
        case 'suspended':
            return 'danger';
        case 'inactive':
        default:
            return 'neutral';
    }
}

export function branchStatusTone(status: string): BusinessStatusTone {
    switch (status) {
        case 'active':
            return 'success';
        case 'suspended':
            return 'danger';
        case 'inactive':
        default:
            return 'neutral';
    }
}
