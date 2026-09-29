import {
    BarChart3,
    Building2,
    CircleDollarSign,
    ClipboardList,
    LayoutDashboard,
    MapPinned,
    Package,
    Percent,
    Settings,
    ShieldAlert,
    Tags,
    Truck,
    Users,
} from 'lucide-react';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';

export const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: admin.home(),
        icon: LayoutDashboard,
    },
    {
        title: 'Empresas',
        href: admin.businesses.index(),
        icon: Building2,
        access: 'businesses',
    },
    {
        title: 'Tipos / giros',
        href: admin.businessTypes.index(),
        icon: Tags,
        access: 'business_types',
    },
    {
        title: 'Cobertura',
        href: admin.coverage.index(),
        icon: MapPinned,
        access: 'coverage',
    },
    {
        title: 'Repartidores',
        href: admin.drivers.index(),
        icon: Truck,
        access: 'drivers',
    },
    {
        title: 'Clientes',
        href: admin.customers.index(),
        icon: Users,
        access: 'customers',
    },
    {
        title: 'Pedidos',
        href: admin.orders.index(),
        icon: Package,
        access: 'orders',
    },
    {
        title: 'Personalizados',
        href: admin.customOrders.index(),
        icon: ClipboardList,
        access: 'custom_orders',
    },
    {
        title: 'Incidencias',
        href: admin.incidents.index(),
        icon: ShieldAlert,
        access: 'incidents',
    },
    {
        title: 'Finanzas',
        href: admin.finance.index(),
        icon: CircleDollarSign,
        access: 'finance',
    },
    {
        title: 'Promociones',
        href: admin.promotions.index(),
        icon: Percent,
        access: 'promotions',
    },
    {
        title: 'Reportes',
        href: admin.reports.index(),
        icon: BarChart3,
        access: 'reports',
    },
    {
        title: 'Configuración',
        href: admin.settings.index(),
        icon: Settings,
        access: 'settings',
    },
];
