import {
    CircleDollarSign,
    ClipboardList,
    Home,
    ShoppingBag,
    UserRound,
} from 'lucide-react';
import driver from '@/routes/driver';
import type { NavItem } from '@/types';

export const driverNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: driver.home(),
        icon: Home,
    },
    {
        title: 'Pedidos',
        href: driver.orders.index(),
        icon: ShoppingBag,
    },
    {
        title: 'Ganancias',
        href: driver.earnings.index(),
        icon: CircleDollarSign,
    },
    {
        title: 'Historial',
        href: driver.history.index(),
        icon: ClipboardList,
    },
    {
        title: 'Perfil',
        href: driver.profile.index(),
        icon: UserRound,
    },
];
