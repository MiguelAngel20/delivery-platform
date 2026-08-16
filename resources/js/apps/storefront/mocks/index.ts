/**
 * Temporary development mocks for Storefront + Customer UI foundation.
 * Replace with real API/Inertia props when catalog modules are implemented.
 */

export type OperationMode = 'partner' | 'platform_operated' | 'directory';

export type MockCategory = {
    id: string;
    name: string;
    slug: string;
};

export type MockRestaurant = {
    id: string;
    slug: string;
    name: string;
    category: string;
    eta: string;
    open: boolean;
    mode: OperationMode;
    branchName: string;
    schedule: string;
    canOrder: boolean;
    modeLabel: string;
    logo_url?: string | null;
    is_affiliated?: boolean;
};

export type MockProductOption = {
    id: string;
    name: string;
    price: number;
};

export type MockProduct = {
    id: string;
    restaurantSlug: string;
    category: string;
    name: string;
    description: string;
    price: number;
    ingredients: string[];
    extras: MockProductOption[];
};

export type MockPromotion = {
    id: string;
    restaurantSlug?: string;
    restaurant_name?: string | null;
    business_type?: string | null;
    name: string;
    description: string;
    price: number;
    composition: string;
    image_url?: string | null;
    is_affiliated?: boolean;
};

export type MockAddress = {
    id: string;
    label: string;
    line: string;
    isDefault: boolean;
};

export type MockCustomerOrderStatus =
    | 'received'
    | 'managing'
    | 'confirmed'
    | 'picking_up'
    | 'picked_up'
    | 'on_the_way'
    | 'delivered'
    | 'preparing'
    | 'driver_assigned';

export type MockCustomerOrder = {
    id: string;
    code: string;
    restaurant: string;
    status: MockCustomerOrderStatus;
    statusLabel: string;
    eta?: string;
    total: string;
    mode: OperationMode;
    timeline: Array<{ key: string; label: string; done: boolean; current?: boolean }>;
    items: Array<{ name: string; qty: number; price: string }>;
    address: string;
    payment: string;
};

export const mockCategories: MockCategory[] = [
    { id: 'cat-1', name: 'Comida rápida', slug: 'comida-rapida' },
    { id: 'cat-2', name: 'Restaurantes', slug: 'restaurantes' },
    { id: 'cat-3', name: 'Pizza', slug: 'pizza' },
    { id: 'cat-4', name: 'Tacos', slug: 'tacos' },
    { id: 'cat-5', name: 'Postres', slug: 'postres' },
    { id: 'cat-6', name: 'Cafeterías', slug: 'cafeterias' },
];

export const mockRestaurants: MockRestaurant[] = [
    {
        id: 'rest-1',
        slug: 'pollo-guero',
        name: 'Pollo Güero',
        category: 'Comida rápida',
        eta: '25-35 min',
        open: true,
        mode: 'partner',
        branchName: 'Sucursal Centro',
        schedule: 'Hoy 10:00 – 22:00',
        canOrder: true,
        modeLabel: 'Entrega disponible',
    },
    {
        id: 'rest-2',
        slug: 'pizza-roma',
        name: 'Pizza Roma',
        category: 'Pizza',
        eta: '30-40 min',
        open: true,
        mode: 'platform_operated',
        branchName: 'Sucursal Norte',
        schedule: 'Hoy 11:00 – 23:00',
        canOrder: true,
        modeLabel: 'Entrega disponible',
    },
    {
        id: 'rest-3',
        slug: 'cafe-aurora',
        name: 'Café Aurora',
        category: 'Cafeterías',
        eta: '—',
        open: true,
        mode: 'directory',
        branchName: 'Sucursal Centro',
        schedule: 'Hoy 08:00 – 20:00',
        canOrder: false,
        modeLabel: 'Solo información',
    },
    {
        id: 'rest-4',
        slug: 'tacos-el-paso',
        name: 'Tacos El Paso',
        category: 'Tacos',
        eta: '20-30 min',
        open: false,
        mode: 'partner',
        branchName: 'Sucursal Sur',
        schedule: 'Cerrado · Abre mañana 11:00',
        canOrder: true,
        modeLabel: 'Entrega disponible',
    },
];

export const mockProducts: MockProduct[] = [
    {
        id: 'prod-1',
        restaurantSlug: 'pollo-guero',
        category: 'Hamburguesas',
        name: 'Hamburguesa clásica',
        description: 'Carne, queso y vegetales',
        price: 105,
        ingredients: ['Lechuga', 'Tomate', 'Cebolla', 'Queso'],
        extras: [
            { id: 'ex-1', name: 'Queso extra', price: 15 },
            { id: 'ex-2', name: 'Tocino', price: 20 },
        ],
    },
    {
        id: 'prod-2',
        restaurantSlug: 'pollo-guero',
        category: 'Hamburguesas',
        name: 'Hamburguesa doble',
        description: 'Doble carne y queso',
        price: 135,
        ingredients: ['Lechuga', 'Tomate', 'Cebolla', 'Queso'],
        extras: [{ id: 'ex-1', name: 'Queso extra', price: 15 }],
    },
    {
        id: 'prod-3',
        restaurantSlug: 'pollo-guero',
        category: 'Acompañamientos',
        name: 'Papas fritas',
        description: 'Porción mediana',
        price: 45,
        ingredients: [],
        extras: [],
    },
    {
        id: 'prod-4',
        restaurantSlug: 'pizza-roma',
        category: 'Pizzas',
        name: 'Pepperoni',
        description: 'Clásica de pepperoni',
        price: 120,
        ingredients: ['Queso', 'Pepperoni'],
        extras: [{ id: 'ex-3', name: 'Borde de queso', price: 25 }],
    },
    {
        id: 'prod-5',
        restaurantSlug: 'pizza-roma',
        category: 'Pizzas',
        name: 'Hawaiana',
        description: 'Jamón y piña',
        price: 125,
        ingredients: ['Queso', 'Jamón', 'Piña'],
        extras: [],
    },
    {
        id: 'prod-6',
        restaurantSlug: 'cafe-aurora',
        category: 'Bebidas',
        name: 'Café americano',
        description: 'Taza 12 oz',
        price: 30,
        ingredients: [],
        extras: [],
    },
];

export const mockPromotions: MockPromotion[] = [
    {
        id: 'promo-1',
        restaurantSlug: 'pollo-guero',
        name: 'Hamburguesa + Jugo',
        description: 'Incluye jugo externo al menú habitual',
        price: 60,
        composition: 'Hamburguesa clásica + Jugo natural',
    },
    {
        id: 'promo-2',
        restaurantSlug: 'pizza-roma',
        name: '2x1 personal',
        description: 'Dos pizzas personales',
        price: 140,
        composition: 'Pepperoni + Hawaiana',
    },
    {
        id: 'promo-3',
        name: 'Envío bonificado',
        description: 'Promoción de plataforma',
        price: 0,
        composition: 'Servicio con descuento en pedidos seleccionados',
    },
];

export const mockAddresses: MockAddress[] = [
    {
        id: 'addr-1',
        label: 'Casa',
        line: 'Calle 5 #42, Barrio Centro',
        isDefault: true,
    },
    {
        id: 'addr-2',
        label: 'Trabajo',
        line: 'Av. Reforma 120, Zona 9',
        isDefault: false,
    },
    {
        id: 'addr-3',
        label: 'Universidad',
        line: 'Campus Norte, Edificio B',
        isDefault: false,
    },
];

export const mockCustomerOrders: MockCustomerOrder[] = [
    {
        id: 'ord-1',
        code: 'RIDE-1052',
        restaurant: 'Pizza Roma',
        status: 'preparing',
        statusLabel: 'Preparando',
        eta: '20 min',
        total: '$285',
        mode: 'partner',
        address: 'Casa · Calle 5 #42',
        payment: 'Efectivo',
        items: [
            { name: 'Pepperoni', qty: 1, price: '$120' },
            { name: 'Hawaiana', qty: 1, price: '$125' },
        ],
        timeline: [
            { key: 'received', label: 'Pedido recibido', done: true },
            { key: 'preparing', label: 'Preparando', done: true, current: true },
            { key: 'driver', label: 'Repartidor asignado', done: false },
            { key: 'picked', label: 'Pedido recogido', done: false },
            { key: 'way', label: 'En camino', done: false },
            { key: 'done', label: 'Entregado', done: false },
        ],
    },
    {
        id: 'ord-2',
        code: 'RIDE-1040',
        restaurant: 'Pollo Güero',
        status: 'managing',
        statusLabel: 'Estamos gestionando tu pedido',
        eta: '35 min',
        total: '$150',
        mode: 'platform_operated',
        address: 'Trabajo · Av. Reforma 120',
        payment: 'Efectivo',
        items: [{ name: 'Hamburguesa clásica', qty: 1, price: '$105' }],
        timeline: [
            { key: 'received', label: 'Pedido recibido', done: true },
            {
                key: 'managing',
                label: 'Estamos gestionando tu pedido',
                done: true,
                current: true,
            },
            { key: 'confirmed', label: 'Pedido confirmado', done: false },
            { key: 'picking', label: 'Recogiendo pedido', done: false },
            { key: 'picked', label: 'Pedido recogido', done: false },
            { key: 'way', label: 'En camino', done: false },
            { key: 'done', label: 'Entregado', done: false },
        ],
    },
    {
        id: 'ord-3',
        code: 'RIDE-1030',
        restaurant: 'Pizza Roma',
        status: 'delivered',
        statusLabel: 'Entregado',
        total: '$240',
        mode: 'partner',
        address: 'Casa · Calle 5 #42',
        payment: 'Efectivo',
        items: [{ name: 'Pepperoni', qty: 2, price: '$240' }],
        timeline: [
            { key: 'received', label: 'Pedido recibido', done: true },
            { key: 'preparing', label: 'Preparando', done: true },
            { key: 'driver', label: 'Repartidor asignado', done: true },
            { key: 'picked', label: 'Pedido recogido', done: true },
            { key: 'way', label: 'En camino', done: true },
            { key: 'done', label: 'Entregado', done: true, current: true },
        ],
    },
];

export const SERVICE_FEE = 50;

export function getRestaurantBySlug(slug: string): MockRestaurant | undefined {
    return mockRestaurants.find((restaurant) => restaurant.slug === slug);
}

export function getProductsByRestaurant(slug: string): MockProduct[] {
    return mockProducts.filter((product) => product.restaurantSlug === slug);
}

export function getPromotionsByRestaurant(slug: string): MockPromotion[] {
    return mockPromotions.filter(
        (promotion) => promotion.restaurantSlug === slug,
    );
}

export { formatMoney } from '@/lib/money';

export function findOrder(id: string): MockCustomerOrder | undefined {
    return mockCustomerOrders.find((order) => order.id === id);
}
