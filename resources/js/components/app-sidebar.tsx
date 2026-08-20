import { Link } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
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
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

type AppSidebarProps = {
    homeHref?: NavItem['href'];
    mainNavItems?: NavItem[];
    footerNavItems?: NavItem[];
    navLabel?: string;
};

export function AppSidebar({
    homeHref = dashboard(),
    mainNavItems = defaultMainNavItems,
    footerNavItems = [],
    navLabel,
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

            {footerNavItems.length > 0 ? (
                <SidebarFooter>
                    <NavFooter items={footerNavItems} className="mt-auto" />
                </SidebarFooter>
            ) : null}
        </Sidebar>
    );
}
