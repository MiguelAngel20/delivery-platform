import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type MobileNavigationProps = {
    items: NavItem[];
    className?: string;
    persistOnDesktop?: boolean;
};

export function MobileNavigation({
    items,
    className,
    persistOnDesktop = false,
}: MobileNavigationProps) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav
            aria-label="Navegación principal"
            className={cn(
                'fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/95 backdrop-blur-sm',
                !persistOnDesktop && 'md:hidden',
                className,
            )}
        >
            <ul className="mx-auto flex max-w-3xl items-stretch justify-around gap-1 px-2 py-2">
                {items.map((item) => {
                    const active = isCurrentUrl(item.href);

                    return (
                        <li key={toUrl(item.href)} className="flex-1">
                            <Link
                                href={item.href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-12 flex-col items-center justify-center gap-1 rounded-md px-1 py-1 text-[11px] font-medium sm:text-xs',
                                    active
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {item.icon ? (
                                    <item.icon
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                ) : null}
                                <span className="truncate">{item.title}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
