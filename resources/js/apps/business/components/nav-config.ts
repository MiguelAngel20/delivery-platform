import {
    ArrowUpRight,
    CircleDollarSign,
    LayoutDashboard,
    Package,
    Percent,
    Settings,
    ShoppingBag,
    Tag,
    Users,
} from 'lucide-react';
import business from '@/routes/business';
import type { NavItem } from '@/types';
import type { UserRole } from '@/types/auth';

export const businessNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: business.home(),
        icon: LayoutDashboard,
        roles: ['business_admin', 'business_employee'],
    },
    {
        title: 'Pedidos',
        href: business.orders.index(),
        icon: ShoppingBag,
        roles: ['business_admin', 'business_employee'],
    },
    {
        title: 'Finanzas',
        href: business.finance.index(),
        icon: CircleDollarSign,
        roles: ['business_admin'],
    },
    {
        title: 'Productos',
        href: business.products.index(),
        icon: Package,
        roles: ['business_admin'],
    },
    {
        title: 'Categorías',
        href: business.categories.index(),
        icon: Tag,
        roles: ['business_admin'],
    },
    {
        title: 'Promociones',
        href: business.promotions.index(),
        icon: Percent,
        roles: ['business_admin'],
    },
    {
        title: 'Empleados',
        href: business.employees.index(),
        icon: Users,
        roles: ['business_admin'],
    },
    {
        title: 'Solicitudes',
        href: business.upgradeRequests.index(),
        icon: ArrowUpRight,
        roles: ['business_admin'],
    },
    {
        title: 'Configuración',
        href: business.settings.index(),
        icon: Settings,
        roles: ['business_admin'],
    },
];

export function getBusinessNavItems(role: UserRole | null | undefined): NavItem[] {
    if (role === null || role === undefined) {
        return [];
    }

    return businessNavItems.filter(
        (item) => item.roles === undefined || item.roles.includes(role),
    );
}

export const businessPortalRoleLabels: Partial<Record<UserRole, string>> = {
    business_admin: 'Administrador',
    business_employee: 'Empleado',
};
