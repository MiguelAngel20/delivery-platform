import { Link } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const defaultMainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

type AppSidebarProps = {
    homeHref?: NavItem['href'];
    mainNavItems?: NavItem[];
    footerNavItems?: NavItem[];
    navLabel?: string;
    userRole?: string;
};

export function AppSidebar({
    homeHref = dashboard(),
    mainNavItems = defaultMainNavItems,
    footerNavItems = [],
    navLabel,
    userRole,
}: AppSidebarProps) {
    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label={navLabel} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 ? (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                ) : null}
                <NavUser role={userRole} />
            </SidebarFooter>
        </Sidebar>
    );
}
