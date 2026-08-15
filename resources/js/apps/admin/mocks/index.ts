/**
 * Temporary development mocks for Admin UI foundation.
 * Replace with real API/Inertia props when modules are implemented.
 */
export const adminDashboardMocks = {
    stats: [
        {
            key: 'orders',
            title: 'Pedidos',
            value: '1,248',
            trend: { value: '12.5%', direction: 'up' as const, label: 'vs. período anterior' },
        },
        {
            key: 'businesses',
            title: 'Empresas',
            value: '86',
            trend: { value: '4.2%', direction: 'up' as const, label: 'vs. período anterior' },
        },
        {
            key: 'drivers',
            title: 'Repartidores',
            value: '142',
            trend: { value: '1.8%', direction: 'down' as const, label: 'vs. período anterior' },
        },
        {
            key: 'customers',
            title: 'Clientes',
            value: '3,910',
            trend: { value: '8.4%', direction: 'up' as const, label: 'vs. período anterior' },
        },
    ],
    dateRangeLabel: '01 May 2026 - 14 May 2026',
    ordersLast7Days: [
        { label: 'Lun', value: 120 },
        { label: 'Mar', value: 156 },
        { label: 'Mié', value: 142 },
        { label: 'Jue', value: 188 },
        { label: 'Vie', value: 210 },
        { label: 'Sáb', value: 176 },
        { label: 'Dom', value: 134 },
    ],
    ordersByStatus: [
        { label: 'Completados', value: 620, color: 'var(--success)' },
        { label: 'En preparación', value: 210, color: 'var(--primary)' },
        { label: 'En camino', value: 280, color: 'var(--info)' },
        { label: 'Cancelados', value: 138, color: 'var(--danger)' },
    ],
    recentOrders: [
        {
            id: 'mock-dash-ord-1',
            code: '#RIDE-1250',
            customer: 'María López',
            business: 'Pizza Roma',
            status: 'En camino' as const,
            total: '$320.00',
            date: '14/05/2026 14:30',
        },
        {
            id: 'mock-dash-ord-2',
            code: '#RIDE-1249',
            customer: 'Carlos Ruiz',
            business: 'Sushi Bar',
            status: 'En preparación' as const,
            total: '$245.50',
            date: '14/05/2026 14:12',
        },
        {
            id: 'mock-dash-ord-3',
            code: '#RIDE-1248',
            customer: 'Ana Gómez',
            business: 'Burger House',
            status: 'Completado' as const,
            total: '$180.00',
            date: '14/05/2026 13:48',
        },
        {
            id: 'mock-dash-ord-4',
            code: '#RIDE-1247',
            customer: 'Luis Pérez',
            business: 'Café Central',
            status: 'Cancelado' as const,
            total: '$95.00',
            date: '14/05/2026 13:20',
        },
        {
            id: 'mock-dash-ord-5',
            code: '#RIDE-1246',
            customer: 'Sofía Díaz',
            business: 'Pizza Roma',
            status: 'Completado' as const,
            total: '$410.00',
            date: '14/05/2026 12:55',
        },
    ],
    activeDrivers: [
        {
            id: 'mock-dash-drv-1',
            name: 'Miguel Torres',
            activity: 'En entrega',
            status: 'Activo' as const,
            initials: 'MT',
        },
        {
            id: 'mock-dash-drv-2',
            name: 'Laura Méndez',
            activity: 'Disponible',
            status: 'Activo' as const,
            initials: 'LM',
        },
        {
            id: 'mock-dash-drv-3',
            name: 'Jorge Salas',
            activity: 'En pausa',
            status: 'Pausa' as const,
            initials: 'JS',
        },
        {
            id: 'mock-dash-drv-4',
            name: 'Elena Vargas',
            activity: 'En entrega',
            status: 'Activo' as const,
            initials: 'EV',
        },
    ],
} as const;

export type MockBusiness = {
    id: string;
    name: string;
    type: string;
    status: 'Activa' | 'Inactiva' | 'Pendiente';
    modality: string;
};

export const mockBusinesses: MockBusiness[] = [
    {
        id: 'mock-biz-1',
        name: 'Empresa Demo 1',
        type: 'Restaurante',
        status: 'Activa',
        modality: 'Delivery',
    },
    {
        id: 'mock-biz-2',
        name: 'Empresa Demo 2',
        type: 'Cafetería',
        status: 'Pendiente',
        modality: 'Pickup',
    },
    {
        id: 'mock-biz-3',
        name: 'Empresa Demo 3',
        type: 'Tienda',
        status: 'Inactiva',
        modality: 'Delivery',
    },
];

export type MockDriver = {
    id: string;
    name: string;
    type: string;
    status: 'Disponible' | 'Ocupado' | 'Inactivo';
    orders: number;
    level: string;
};

export const mockDrivers: MockDriver[] = [
    {
        id: 'mock-drv-1',
        name: 'Repartidor Demo 1',
        type: 'Motocicleta',
        status: 'Disponible',
        orders: 0,
        level: '—',
    },
    {
        id: 'mock-drv-2',
        name: 'Repartidor Demo 2',
        type: 'Bicicleta',
        status: 'Ocupado',
        orders: 0,
        level: '—',
    },
    {
        id: 'mock-drv-3',
        name: 'Repartidor Demo 3',
        type: 'Automóvil',
        status: 'Inactivo',
        orders: 0,
        level: '—',
    },
];

export type MockCustomer = {
    id: string;
    name: string;
    completedOrders: number;
    cancellations: number;
    trustLevel: string;
    status: 'Activo' | 'Restringido' | 'Inactivo';
};

export const mockCustomers: MockCustomer[] = [
    {
        id: 'mock-cus-1',
        name: 'Cliente Demo 1',
        completedOrders: 0,
        cancellations: 0,
        trustLevel: '—',
        status: 'Activo',
    },
    {
        id: 'mock-cus-2',
        name: 'Cliente Demo 2',
        completedOrders: 0,
        cancellations: 0,
        trustLevel: '—',
        status: 'Activo',
    },
    {
        id: 'mock-cus-3',
        name: 'Cliente Demo 3',
        completedOrders: 0,
        cancellations: 0,
        trustLevel: '—',
        status: 'Restringido',
    },
];

export type MockOrder = {
    id: string;
    code: string;
    customer: string;
    business: string;
    driver: string;
    status: 'Nuevo' | 'En camino' | 'Entregado' | 'Cancelado';
    total: string;
    date: string;
};

export const mockOrders: MockOrder[] = [
    {
        id: 'mock-ord-1',
        code: 'ORD-1001',
        customer: 'Cliente Demo 1',
        business: 'Empresa Demo 1',
        driver: 'Repartidor Demo 1',
        status: 'Nuevo',
        total: '—',
        date: '2026-08-12',
    },
    {
        id: 'mock-ord-2',
        code: 'ORD-1002',
        customer: 'Cliente Demo 2',
        business: 'Empresa Demo 2',
        driver: '—',
        status: 'En camino',
        total: '—',
        date: '2026-08-12',
    },
    {
        id: 'mock-ord-3',
        code: 'ORD-1003',
        customer: 'Cliente Demo 3',
        business: 'Empresa Demo 1',
        driver: 'Repartidor Demo 2',
        status: 'Entregado',
        total: '—',
        date: '2026-08-11',
    },
];
