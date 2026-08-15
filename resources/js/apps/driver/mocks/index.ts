/**
 * Temporary development mocks for Driver UI foundation.
 * Replace with real API/Inertia props when modules are implemented.
 */

export type DriverAvailability =
    | 'offline'
    | 'available'
    | 'paused'
    | 'busy';

export const driverAvailabilityLabels: Record<DriverAvailability, string> = {
    offline: 'Desconectado',
    available: 'Disponible',
    paused: 'En pausa',
    busy: 'En servicio',
};

export const driverAvailabilityCycle: DriverAvailability[] = [
    'available',
    'paused',
    'busy',
    'offline',
];

export type MockDriverOrderStatus =
    | 'Disponible'
    | 'Aceptado'
    | 'En establecimiento'
    | 'Recogido'
    | 'En camino'
    | 'Entregado'
    | 'Cancelado';

export type MockActiveDriverOrder = {
    id: string;
    code: string;
    business: string;
    status: Extract<
        MockDriverOrderStatus,
        'Aceptado' | 'En establecimiento' | 'Recogido' | 'En camino'
    >;
    pickupAddress: string;
    dropoffAddress: string;
    customer: string;
    earnings: string;
    mapsUrl: string;
};

export type MockAvailableOrder = {
    id: string;
    code: string;
    business: string;
    pickupDistance: string;
    dropoffArea: string;
    earnings: string;
    compatibleWithActiveRoute?: boolean;
};

export type MockRouteSummary = {
    business: string;
    orderCodes: string[];
};

export type MockEarningMovement = {
    id: string;
    label: string;
    amount: string;
    tone: 'credit' | 'debit';
};

export type MockHistoryOrder = {
    id: string;
    code: string;
    business: string;
    date: string;
    earnings: string;
    status: 'Entregado' | 'Cancelado';
};

export type MockDriverProfile = {
    name: string;
    email: string;
    phone: string;
    type: 'PLATFORM' | 'BUSINESS_ONLY';
    typeLabel: string;
    accountStatus: string;
    rating: string;
    completedOrders: number;
};

export const driverDashboardMocks = {
    greetingName: 'Miguel',
    earningsToday: '$480.00',
    completedToday: '12',
    activeOrders: '1',
    rating: '4.9',
} as const;

export const mockActiveDriverOrder: MockActiveDriverOrder = {
    id: 'mock-driver-active-1',
    code: 'RIDE-1052',
    business: 'Pizza Roma',
    status: 'Aceptado',
    pickupAddress: 'Av. Central 120, Zona 1',
    dropoffAddress: 'Calle 5 #42, Barrio Centro',
    customer: 'Miguel R.',
    earnings: '$50',
    mapsUrl: 'https://maps.google.com/?q=Ciudad+de+Guatemala',
};

export const mockDriverRoute: MockRouteSummary = {
    business: 'Pizza Roma',
    orderCodes: ['RIDE-1001', 'RIDE-1002', 'RIDE-1003'],
};

export const mockAvailableOrders: MockAvailableOrder[] = [
    {
        id: 'mock-avail-1',
        code: 'RIDE-1060',
        business: 'Burger House',
        pickupDistance: '0.8 km',
        dropoffArea: 'Zona 10',
        earnings: '$55',
    },
    {
        id: 'mock-avail-2',
        code: 'RIDE-1061',
        business: 'Sushi Go',
        pickupDistance: '1.5 km',
        dropoffArea: 'Zona 4',
        earnings: '$62',
    },
    {
        id: 'mock-avail-3',
        code: 'RIDE-1062',
        business: 'Pizza Roma',
        pickupDistance: '1.2 km',
        dropoffArea: 'Barrio Centro',
        earnings: '$45',
        compatibleWithActiveRoute: true,
    },
];

export const mockCompatibleOrder: MockAvailableOrder = {
    id: 'mock-compat-1',
    code: 'RIDE-1058',
    business: 'Pizza Roma',
    pickupDistance: '1.1 km',
    dropoffArea: 'Barrio Centro',
    earnings: '$45',
    compatibleWithActiveRoute: true,
};

export const driverEarningsMocks = {
    today: '$480.00',
    week: '$2,150.00',
    completedOrders: '47',
    movements: [
        {
            id: 'mock-earn-1',
            label: 'Pedido #1052',
            amount: '+$50',
            tone: 'credit',
        },
        {
            id: 'mock-earn-2',
            label: 'Pedido #1053',
            amount: '+$45',
            tone: 'credit',
        },
        {
            id: 'mock-earn-3',
            label: 'Pedido #1050',
            amount: '+$50',
            tone: 'credit',
        },
        {
            id: 'mock-earn-4',
            label: 'Ajuste',
            amount: '-$10',
            tone: 'debit',
        },
    ] satisfies MockEarningMovement[],
} as const;

export const mockHistoryOrders: MockHistoryOrder[] = [
    {
        id: 'mock-hist-1',
        code: 'RIDE-1050',
        business: 'Pizza Roma',
        date: '12 Ago 2026',
        earnings: '$50',
        status: 'Entregado',
    },
    {
        id: 'mock-hist-2',
        code: 'RIDE-1048',
        business: 'Burger House',
        date: '12 Ago 2026',
        earnings: '$55',
        status: 'Entregado',
    },
    {
        id: 'mock-hist-3',
        code: 'RIDE-1044',
        business: 'Sushi Go',
        date: '11 Ago 2026',
        earnings: '$0',
        status: 'Cancelado',
    },
    {
        id: 'mock-hist-4',
        code: 'RIDE-1040',
        business: 'Pizza Roma',
        date: '11 Ago 2026',
        earnings: '$48',
        status: 'Entregado',
    },
];

export const mockDriverProfile: MockDriverProfile = {
    name: 'Miguel Rivera',
    email: 'driver@ride.test',
    phone: '+502 5555-0102',
    type: 'PLATFORM',
    typeLabel: 'Plataforma',
    accountStatus: 'Activo',
    rating: '4.9',
    completedOrders: 312,
};
