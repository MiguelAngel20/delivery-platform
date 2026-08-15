export type UserRole =
    | 'system_admin'
    | 'business_admin'
    | 'business_employee'
    | 'driver'
    | 'customer';

export type UserStatus = 'active' | 'suspended' | 'inactive';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    status: UserStatus;
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
};

export type Auth = {
    user: User | null;
};

export const userRoleLabels: Record<UserRole, string> = {
    system_admin: 'Administrador del sistema',
    business_admin: 'Administrador de negocio',
    business_employee: 'Empleado de negocio',
    driver: 'Repartidor',
    customer: 'Cliente',
};
