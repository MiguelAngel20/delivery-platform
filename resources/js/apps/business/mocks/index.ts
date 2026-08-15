/**
 * Temporary development mocks for Business UI foundation.
 * Replace with real API/Inertia props when modules are implemented.
 */

export const businessDashboardMocks = {
    stats: [
        {
            key: 'ordersToday',
            title: 'Pedidos hoy',
            value: '18',
            trend: { value: '+3 vs ayer', direction: 'up' as const },
        },
        {
            key: 'preparing',
            title: 'Pedidos en preparación',
            value: '5',
            trend: { value: 'En cocina', direction: 'neutral' as const },
        },
        {
            key: 'completed',
            title: 'Pedidos completados',
            value: '11',
            trend: { value: 'Hoy', direction: 'neutral' as const },
        },
        {
            key: 'sales',
            title: 'Ventas del día',
            value: '$4,280',
            trend: { value: '+12%', direction: 'up' as const },
        },
    ],
} as const;

export type MockActiveOrderStatus =
    | 'Nuevo'
    | 'Aceptado'
    | 'Preparando'
    | 'Listo para recoger';

export type MockActiveOrderItem = {
    qty: number;
    name: string;
};

export type MockActiveOrder = {
    id: string;
    code: string;
    customer: string;
    items: MockActiveOrderItem[];
    note?: string;
    total: string;
    status: MockActiveOrderStatus;
    time: string;
};

export const mockActiveOrders: MockActiveOrder[] = [
    {
        id: 'mock-active-1',
        code: 'RIDE-1052',
        customer: 'Juan Pérez',
        items: [
            { qty: 2, name: 'Hamburguesa clásica' },
            { qty: 1, name: 'Papas' },
            { qty: 2, name: 'Refresco' },
        ],
        note: 'Sin tomate',
        total: '$285',
        status: 'Nuevo',
        time: '12:14',
    },
    {
        id: 'mock-active-2',
        code: 'RIDE-1053',
        customer: 'María López',
        items: [
            { qty: 1, name: 'Tacos al pastor' },
            { qty: 1, name: 'Agua de horchata' },
        ],
        total: '$160',
        status: 'Aceptado',
        time: '12:08',
    },
    {
        id: 'mock-active-3',
        code: 'RIDE-1049',
        customer: 'Carlos Ruiz',
        items: [
            { qty: 1, name: 'Pizza pepperoni' },
            { qty: 1, name: 'Ensalada' },
        ],
        note: 'Extra queso',
        total: '$320',
        status: 'Preparando',
        time: '11:55',
    },
    {
        id: 'mock-active-4',
        code: 'RIDE-1047',
        customer: 'Ana Torres',
        items: [{ qty: 3, name: 'Burrito de res' }],
        total: '$240',
        status: 'Listo para recoger',
        time: '11:40',
    },
];

export type MockBusinessOrder = {
    id: string;
    code: string;
    customer: string;
    status:
        | 'Nuevo'
        | 'Preparando'
        | 'Listo'
        | 'Completado'
        | 'Cancelado';
    total: string;
    time: string;
    driver: string;
};

export const mockBusinessOrders: MockBusinessOrder[] = [
    {
        id: 'mock-bord-1',
        code: 'RIDE-1052',
        customer: 'Juan Pérez',
        status: 'Nuevo',
        total: '$285',
        time: '12:14',
        driver: '—',
    },
    {
        id: 'mock-bord-2',
        code: 'RIDE-1053',
        customer: 'María López',
        status: 'Preparando',
        total: '$160',
        time: '12:08',
        driver: 'Luis M.',
    },
    {
        id: 'mock-bord-3',
        code: 'RIDE-1049',
        customer: 'Carlos Ruiz',
        status: 'Listo',
        total: '$320',
        time: '11:55',
        driver: 'Sofía R.',
    },
    {
        id: 'mock-bord-4',
        code: 'RIDE-1041',
        customer: 'Ana Torres',
        status: 'Completado',
        total: '$240',
        time: '11:10',
        driver: 'Luis M.',
    },
    {
        id: 'mock-bord-5',
        code: 'RIDE-1038',
        customer: 'Pedro Díaz',
        status: 'Cancelado',
        total: '$95',
        time: '10:42',
        driver: '—',
    },
];

export type MockProduct = {
    id: string;
    name: string;
    category: string;
    price: string;
    availability: 'Disponible' | 'Agotado';
    status: 'Activo' | 'Inactivo';
};

export const mockProducts: MockProduct[] = [
    {
        id: 'mock-prod-1',
        name: 'Hamburguesa clásica',
        category: 'Comidas',
        price: '$120',
        availability: 'Disponible',
        status: 'Activo',
    },
    {
        id: 'mock-prod-2',
        name: 'Papas fritas',
        category: 'Acompañamientos',
        price: '$45',
        availability: 'Disponible',
        status: 'Activo',
    },
    {
        id: 'mock-prod-3',
        name: 'Refresco 600 ml',
        category: 'Bebidas',
        price: '$35',
        availability: 'Agotado',
        status: 'Activo',
    },
    {
        id: 'mock-prod-4',
        name: 'Ensalada César',
        category: 'Comidas',
        price: '$95',
        availability: 'Disponible',
        status: 'Inactivo',
    },
];

export type MockCategory = {
    id: string;
    name: string;
    products: number;
    status: 'Activa' | 'Inactiva';
    order: number;
};

export const mockCategories: MockCategory[] = [
    {
        id: 'mock-cat-1',
        name: 'Comidas',
        products: 12,
        status: 'Activa',
        order: 1,
    },
    {
        id: 'mock-cat-2',
        name: 'Bebidas',
        products: 8,
        status: 'Activa',
        order: 2,
    },
    {
        id: 'mock-cat-3',
        name: 'Acompañamientos',
        products: 5,
        status: 'Activa',
        order: 3,
    },
    {
        id: 'mock-cat-4',
        name: 'Postres',
        products: 0,
        status: 'Inactiva',
        order: 4,
    },
];

export type MockPromotion = {
    id: string;
    name: string;
    price: string;
    validity: string;
    status: 'Activa' | 'Programada' | 'Finalizada';
    composition: string;
};

export const mockPromotions: MockPromotion[] = [
    {
        id: 'mock-promo-1',
        name: 'Combo clásico',
        price: '$160',
        validity: '12 ago – 31 ago',
        status: 'Activa',
        composition: 'Hamburguesa + Papas + Refresco',
    },
    {
        id: 'mock-promo-2',
        name: 'Hamburguesa + Jugo',
        price: '$60',
        validity: 'Hoy',
        status: 'Activa',
        composition: 'Producto del menú + ítem externo',
    },
    {
        id: 'mock-promo-3',
        name: '2x1 tacos',
        price: '$90',
        validity: '01 sep – 07 sep',
        status: 'Programada',
        composition: 'Tacos al pastor x2',
    },
];

export type MockEmployee = {
    id: string;
    name: string;
    email: string;
    role: 'Administrador' | 'Empleado';
    status: 'Activo' | 'Inactivo';
};

export const mockEmployees: MockEmployee[] = [
    {
        id: 'mock-emp-1',
        name: 'Laura Gómez',
        email: 'laura@negocio.test',
        role: 'Administrador',
        status: 'Activo',
    },
    {
        id: 'mock-emp-2',
        name: 'Miguel Santos',
        email: 'miguel@negocio.test',
        role: 'Empleado',
        status: 'Activo',
    },
    {
        id: 'mock-emp-3',
        name: 'Diana Vega',
        email: 'diana@negocio.test',
        role: 'Empleado',
        status: 'Inactivo',
    },
];

export const mockSettingsSections = [
    {
        key: 'business',
        title: 'Información del negocio',
        description: 'Nombre, logo y datos de contacto.',
    },
    {
        key: 'branch',
        title: 'Sucursal',
        description: 'Dirección y zona de cobertura.',
    },
    {
        key: 'schedule',
        title: 'Horario',
        description: 'Días y horas de operación.',
    },
    {
        key: 'delivery',
        title: 'Método de entrega',
        description: 'Recoger en local o envío con repartidor.',
    },
    {
        key: 'operations',
        title: 'Configuración operativa',
        description: 'Tiempos, alertas y preferencias de cocina.',
    },
] as const;
