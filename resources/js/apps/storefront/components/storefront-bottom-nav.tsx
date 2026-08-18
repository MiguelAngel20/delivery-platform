import { Link } from '@inertiajs/react';
import { Home, Store, Tag } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { home } from '@/routes';
import promotions from '@/routes/promotions';
import restaurants from '@/routes/restaurants';
import type { NavItem } from '@/types';

function storefrontItems(): NavItem[] {
    return [
        { title: 'Inicio', href: home(), icon: Home },
        { title: 'Negocios', href: restaurants.index(), icon: Store },
        { title: 'Promociones', href: promotions.index(), icon: Tag },
    ];
}

export function StorefrontBottomNav() {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const items = storefrontItems();

    return (
        <nav
            aria-label="Navegación principal"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/95 backdrop-blur-sm md:hidden"
        >
            <ul className="mx-auto flex max-w-6xl items-stretch justify-around gap-1 px-2 py-2">
                {items.map((item) => {
                    const href = toUrl(item.href);
                    const active =
                        href === toUrl(home())
                            ? isCurrentUrl(item.href)
                            : isCurrentOrParentUrl(item.href);

                    return (
                        <li key={href} className="flex-1">
                            <Link
                                href={item.href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-12 flex-col items-center justify-center gap-1 rounded-md px-1 py-1 text-[11px] font-medium',
                                    active
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {item.icon ? (
                                    <item.icon
                                        aria-hidden
                                        className="size-5"
                                    />
                                ) : null}
                                <span>{item.title}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
