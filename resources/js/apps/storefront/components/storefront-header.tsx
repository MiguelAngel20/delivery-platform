import { Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    ClipboardList,
    LogOut,
    MapPin,
    MapPinned,
    Search,
    ShoppingBag,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { useStorefrontCart } from '@/apps/storefront/cart/use-storefront-cart';
import { DeliveryLocationDialog } from '@/apps/storefront/components/delivery-location-dialog';
import { useDeliveryLocation } from '@/apps/storefront/hooks/use-delivery-location';
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
import { cart, home, login, logout, search } from '@/routes';
import customer from '@/routes/customer';
import promotions from '@/routes/promotions';
import restaurants from '@/routes/restaurants';
import type { Auth } from '@/types';

export function StorefrontHeader() {
    const { auth } = usePage().props as { auth: Auth };
    const { itemCount } = useStorefrontCart();
    const { location, hasCoordinates } = useDeliveryLocation();
    const [locationOpen, setLocationOpen] = useState(false);
    const authenticated = auth.user?.role === 'customer';

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
                            <Link href={restaurants.index()}>Restaurantes</Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={promotions.index()}>Promociones</Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={search()}>
                                <Search className="size-4" />
                                Buscar
                            </Link>
                        </Button>
                    </nav>

                    <div className="flex items-center gap-1.5">
                        {authenticated ? <NotificationBell compact /> : null}
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="relative"
                            aria-label="Carrito"
                        >
                            <Link href={cart()}>
                                <ShoppingBag className="size-5" />
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
                                        <Link href={customer.profile.notifications.edit()}>
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
                            <Button asChild size="sm" className="min-h-9">
                                <Link href={login()}>Iniciar sesión</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <button
                    type="button"
                    onClick={() => setLocationOpen(true)}
                    className="flex min-h-11 items-center gap-2 rounded-lg border border-border bg-background px-3 text-left"
                >
                    <MapPin className="size-4 shrink-0 text-primary" />
                    <span className="min-w-0">
                        <span className="block text-[11px] text-muted-foreground">
                            Entregar en
                        </span>
                        <span className="block truncate text-sm font-medium text-navy">
                            {hasCoordinates ? location.detail : location.label}
                        </span>
                    </span>
                </button>

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

            <DeliveryLocationDialog
                open={locationOpen}
                onOpenChange={setLocationOpen}
            />
        </header>
    );
}
