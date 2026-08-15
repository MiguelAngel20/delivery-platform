import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, Home, Search, UserRound } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { home, login, search } from '@/routes';
import customer from '@/routes/customer';
import type { Auth, NavItem } from '@/types';

function guestItems(): NavItem[] {
    return [
        { title: 'Inicio', href: home(), icon: Home },
        { title: 'Buscar', href: search(), icon: Search },
        { title: 'Entrar', href: login(), icon: UserRound },
    ];
}

function customerItems(): NavItem[] {
    return [
        { title: 'Inicio', href: home(), icon: Home },
        { title: 'Buscar', href: search(), icon: Search },
        { title: 'Pedidos', href: customer.orders.index(), icon: ClipboardList },
        { title: 'Perfil', href: customer.profile.index(), icon: UserRound },
    ];
}

export function StorefrontBottomNav() {
    const { auth } = usePage().props as { auth: Auth };
    const { isCurrentUrl } = useCurrentUrl();
    const items =
        auth.user?.role === 'customer' ? customerItems() : guestItems();

    return (
        <nav
            aria-label="Navegación principal"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/95 backdrop-blur-sm md:hidden"
        >
            <ul className="mx-auto flex max-w-6xl items-stretch justify-around gap-1 px-2 py-2">
                {items.map((item) => {
                    const active = isCurrentUrl(item.href);

                    return (
                        <li key={toUrl(item.href)} className="flex-1">
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
