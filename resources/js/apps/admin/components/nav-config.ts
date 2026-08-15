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
    },
    {
        title: 'Cobertura',
        href: admin.coverage.index(),
        icon: MapPinned,
    },
    {
        title: 'Repartidores',
        href: admin.drivers.index(),
        icon: Truck,
    },
    {
        title: 'Clientes',
        href: admin.customers.index(),
        icon: Users,
    },
    {
        title: 'Pedidos',
        href: admin.orders.index(),
        icon: Package,
    },
    {
        title: 'Personalizados',
        href: admin.customOrders.index(),
        icon: ClipboardList,
    },
    {
        title: 'Incidencias',
        href: admin.incidents.index(),
        icon: ShieldAlert,
    },
    {
        title: 'Finanzas',
        href: admin.finance.index(),
        icon: CircleDollarSign,
    },
    {
        title: 'Promociones',
        href: admin.promotions.index(),
        icon: Percent,
    },
    {
        title: 'Reportes',
        href: admin.reports.index(),
        icon: BarChart3,
    },
    {
        title: 'Configuración',
        href: admin.settings.index(),
        icon: Settings,
    },
];
