import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    ChevronDown,
    ClipboardList,
    LogOut,
    MapPinned,
    Search,
    ShoppingCart,
    UserRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useStorefrontCart } from '@/apps/storefront/cart/use-storefront-cart';
import { applyStorefrontCategoryFilter } from '@/apps/storefront/components/category-card';
import { DeliveryLocationCue } from '@/apps/storefront/components/delivery-location-cue';
import { SearchBar } from '@/apps/storefront/components/search-bar';
import type { MockCategory } from '@/apps/storefront/mocks';
import { BrandLogo } from '@/components/brand-logo';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { deactivateStoredPushDevice } from '@/lib/push/devices';
import { cart, home, login, logout } from '@/routes';
import customer from '@/routes/customer';
import promotions from '@/routes/promotions';
import restaurants from '@/routes/restaurants';
import type { Auth } from '@/types';

export function StorefrontHeader() {
    const page = usePage();
    const { auth, q: searchQuery = '', storefront } = page.props as {
        auth: Auth;
        q?: string;
        storefront?: { categories?: MockCategory[] };
    };
    const categories = storefront?.categories ?? [];
    const { itemCount } = useStorefrontCart();
    const [searchOpen, setSearchOpen] = useState(false);
    const authenticated = auth.user?.role === 'customer';
    const onSearchPage = page.component === 'public/search/index';
    const selectedCategory = String(
        (page.props as { filters?: { category?: string | null } }).filters
            ?.category ?? '',
    );

    useEffect(() => {
        if (onSearchPage) {
            setSearchOpen(true);

            return;
        }

        setSearchOpen(false);
    }, [onSearchPage, page.url]);

    useEffect(() => {
        if (!searchOpen) {
            return;
        }

        const isInsideSearchUi = (target: EventTarget | null): boolean => {
            if (!(target instanceof Element)) {
                return false;
            }

            return Boolean(
                target.closest('#storefront-search') ||
                    target.closest('[aria-controls="storefront-search"]'),
            );
        };

        const searchInputValue = (): string => {
            const input = document.querySelector(
                '#storefront-search input',
            ) as HTMLInputElement | null;

            return input?.value.trim() ?? '';
        };

        const onPointerDown = (event: PointerEvent) => {
            if (isInsideSearchUi(event.target)) {
                return;
            }

            setSearchOpen(false);

            // Empty query on search page: leave /search and return home.
            if (searchInputValue() === '' && onSearchPage) {
                router.get(
                    home.url(),
                    {},
                    { replace: true, preserveScroll: true },
                );
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') {
                return;
            }

            setSearchOpen(false);

            if (searchInputValue() === '' && onSearchPage) {
                router.get(
                    home.url(),
                    {},
                    { replace: true, preserveScroll: true },
                );
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [searchOpen, onSearchPage]);

    return (
        <header className="sticky top-0 z-30 border-b border-border bg-surface">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-3 md:px-6">
                <div className="flex items-center justify-between gap-3">
                    <Link href={home()} className="shrink-0">
                        <BrandLogo
                            variant="horizontal"
                            className="h-7 md:h-8"
                        />
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={home()}>Inicio</Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    aria-label="Categorías"
                                >
                                    Categorías
                                    <ChevronDown className="size-4 opacity-70" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="min-w-48">
                                <DropdownMenuLabel>
                                    Tipo / giro
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {categories.map((category) => (
                                    <DropdownMenuItem
                                        key={category.id}
                                        onSelect={() =>
                                            applyStorefrontCategoryFilter(
                                                selectedCategory ===
                                                    category.slug
                                                    ? null
                                                    : category.slug,
                                            )
                                        }
                                        className={
                                            selectedCategory === category.slug
                                                ? 'bg-accent'
                                                : undefined
                                        }
                                    >
                                        {category.name}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={restaurants.index()}>Negocios</Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={promotions.index()}>Promociones</Link>
                        </Button>
                        <Button
                            type="button"
                            variant={searchOpen ? 'secondary' : 'ghost'}
                            size="sm"
                            aria-expanded={searchOpen}
                            aria-controls="storefront-search"
                            onClick={() => setSearchOpen((open) => !open)}
                        >
                            <Search className="size-4" />
                            Buscar
                        </Button>
                    </nav>

                    <div className="flex items-center gap-1.5">
                        <Button
                            type="button"
                            variant={searchOpen ? 'secondary' : 'ghost'}
                            size="icon"
                            className="md:hidden"
                            aria-label={
                                searchOpen ? 'Cerrar búsqueda' : 'Buscar'
                            }
                            aria-expanded={searchOpen}
                            aria-controls="storefront-search"
                            onClick={() => setSearchOpen((open) => !open)}
                        >
                            <Search className="size-5" />
                        </Button>
                        {authenticated ? <NotificationBell compact /> : null}
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="relative"
                            aria-label="Carrito"
                        >
                            <Link href={cart()}>
                                <ShoppingCart className="size-5" />
                                {itemCount > 0 ? (
                                    <span className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground">
                                        {itemCount}
                                    </span>
                                ) : null}
                            </Link>
                        </Button>

                        {authenticated ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        aria-label="Menú de cuenta"
                                    >
                                        <UserRound className="size-4" />
                                        <span className="hidden sm:inline">
                                            {auth.user?.name.split(' ')[0]}
                                        </span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="min-w-52"
                                >
                                    <DropdownMenuLabel className="font-normal">
                                        <p className="truncate text-sm font-medium text-navy">
                                            {auth.user?.name}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {auth.user?.email}
                                        </p>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link href={customer.orders.index()}>
                                            <ClipboardList className="size-4" />
                                            Mis pedidos
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={customer.addresses.index()}>
                                            <MapPinned className="size-4" />
                                            Direcciones
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={customer.profile.index()}>
                                            <UserRound className="size-4" />
                                            Perfil
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={customer.profile.notifications.edit()}
                                        >
                                            <Bell className="size-4" />
                                            Notificaciones
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={logout()}
                                            as="button"
                                            className="w-full text-destructive focus:text-destructive"
                                            data-test="customer-logout-button"
                                            onClick={() => {
                                                void deactivateStoredPushDevice();
                                                router.flushAll();
                                            }}
                                        >
                                            <LogOut className="size-4" />
                                            Cerrar sesión
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : (
                            <Button
                                asChild
                                aria-label="Iniciar sesión"
                                className="group h-9 w-9 gap-0 overflow-hidden px-0 transition-[width,padding,gap] duration-300 ease-out hover:w-auto hover:gap-2 hover:px-3 focus-visible:w-auto focus-visible:gap-2 focus-visible:px-3"
                            >
                                <Link href={login()}>
                                    <UserRound className="size-4 shrink-0" />
                                    <span className="max-w-0 overflow-hidden whitespace-nowrap opacity-0 transition-all duration-300 ease-out group-hover:max-w-36 group-hover:opacity-100 group-focus-visible:max-w-36 group-focus-visible:opacity-100">
                                        Iniciar sesión
                                    </span>
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {searchOpen ? (
                    <div id="storefront-search">
                        <SearchBar
                            key="storefront-search-bar"
                            defaultValue={searchQuery}
                            autoFocus
                            onDismiss={() => setSearchOpen(false)}
                        />
                    </div>
                ) : null}

                <DeliveryLocationCue />

                {authenticated ? (
                    <div className="hidden gap-2 md:flex">
                        <Button asChild variant="outline" size="sm">
                            <Link href={customer.orders.index()}>
                                Mis pedidos
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={customer.addresses.index()}>
                                Direcciones
                            </Link>
                        </Button>
                    </div>
                ) : null}
            </div>
        </header>
    );
}
