import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import { MobileNavigation } from '@/components/navigation/mobile-navigation';
import { cn } from '@/lib/utils';
import { home as publicHome } from '@/routes';
import type { NavItem } from '@/types';

type MobileShellProps = {
    children: ReactNode;
    title?: string;
    navItems: NavItem[];
    homeHref?: NonNullable<InertiaLinkProps['href']>;
    topbarEnd?: ReactNode;
    /** Keep bottom navigation visible on tablet/desktop (driver apps). */
    persistBottomNav?: boolean;
    className?: string;
};

export function MobileShell({
    children,
    title,
    navItems,
    homeHref,
    topbarEnd,
    persistBottomNav = false,
    className,
}: MobileShellProps) {
    return (
        <div
            className={cn(
                'flex min-h-screen flex-col bg-background text-foreground',
                className,
            )}
        >
            <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-border bg-surface px-4">
                <Link
                    href={homeHref ?? publicHome()}
                    className="flex min-w-0 items-center gap-2"
                >
                    <BrandLogo variant="isotipo" className="size-8 shrink-0" />
                    {title ? (
                        <span className="truncate text-sm font-semibold text-navy">
                            {title}
                        </span>
                    ) : null}
                </Link>
                <div className="flex items-center gap-2">{topbarEnd}</div>
            </header>
            <main
                className={cn(
                    'mx-auto w-full flex-1',
                    persistBottomNav ? 'max-w-3xl pb-24' : 'pb-24 md:pb-8',
                )}
            >
                {children}
            </main>
            <MobileNavigation
                items={navItems}
                persistOnDesktop={persistBottomNav}
            />
        </div>
    );
}
