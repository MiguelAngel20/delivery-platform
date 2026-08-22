import { router } from '@inertiajs/react';
import {
    Clock3,
    ExternalLink,
    MapPin,
    Phone,
    Store,
    type LucideIcon,
} from 'lucide-react';
import { LocationPreviewMap } from '@/components/maps/location-preview-map';
import { StatusBadge } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import restaurants from '@/routes/restaurants';

export type RestaurantScheduleRow = {
    days_label: string;
    is_open: boolean;
    hours_label: string;
};

export type RestaurantProfileData = {
    slug: string;
    name: string;
    category: string;
    open: boolean;
    branchName: string;
    schedule: string;
    eta: string | null;
    mode: string;
    modeLabel: string;
    canOrder: boolean;
    description?: string | null;
    logo_url?: string | null;
    phone?: string | null;
    address?: string | null;
    reference?: string | null;
    latitude: number;
    longitude: number;
    google_maps_url?: string | null;
    schedule_summary: RestaurantScheduleRow[];
    working_days: string[];
    branches: Array<{ id: number; name: string }>;
};

type RestaurantProfileProps = {
    restaurant: RestaurantProfileData;
    branchId: number;
};

function telHref(phone: string): string {
    return `tel:${phone.replace(/\s+/g, '')}`;
}

export function RestaurantProfile({
    restaurant,
    branchId,
}: RestaurantProfileProps) {
    const mapsUrl = restaurant.google_maps_url;
    const latitude = Number(restaurant.latitude);
    const longitude = Number(restaurant.longitude);
    const hasMap = Number.isFinite(latitude) && Number.isFinite(longitude);

    return (
        <section className="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
            <div className="relative z-0 h-52 bg-secondary md:h-64">
                {hasMap ? (
                    <LocationPreviewMap
                        latitude={latitude}
                        longitude={longitude}
                        title={restaurant.name}
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-muted-foreground">
                        <MapPin className="size-8 text-primary" />
                    </div>
                )}
                {mapsUrl ? (
                    <a
                        href={mapsUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="absolute right-3 bottom-3 inline-flex min-h-10 items-center gap-1.5 rounded-full bg-white/95 px-3 text-sm font-semibold text-navy shadow-md ring-1 ring-black/5"
                    >
                        Cómo llegar
                        <ExternalLink className="size-3.5" />
                    </a>
                ) : null}
            </div>

            <div className="relative z-10 px-4 pb-5 md:px-6">
                <div className="-mt-10 flex items-end gap-3 md:gap-4">
                    <div className="relative size-20 shrink-0 overflow-hidden rounded-2xl border-4 border-surface bg-secondary shadow-lg md:size-24">
                        {restaurant.logo_url ? (
                            <img
                                src={restaurant.logo_url}
                                alt={`Logo de ${restaurant.name}`}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center text-2xl font-semibold text-navy">
                                {restaurant.name.slice(0, 1)}
                            </div>
                        )}
                    </div>
                    <div className="mb-1 flex min-w-0 flex-1 flex-wrap items-center gap-2 pb-1">
                        <StatusBadge
                            tone={restaurant.open ? 'success' : 'neutral'}
                        >
                            {restaurant.open ? 'Abierto ahora' : 'Cerrado ahora'}
                        </StatusBadge>
                        {restaurant.canOrder ? (
                            <StatusBadge tone="primary">
                                {restaurant.modeLabel}
                            </StatusBadge>
                        ) : (
                            <StatusBadge>{restaurant.modeLabel}</StatusBadge>
                        )}
                    </div>
                </div>

                <div className="mt-3 space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight text-navy md:text-3xl">
                        {restaurant.name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {restaurant.category} · {restaurant.branchName}
                        {restaurant.eta ? ` · ${restaurant.eta}` : ''}
                    </p>
                </div>

                {restaurant.description ? (
                    <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                        {restaurant.description}
                    </p>
                ) : null}

                {!restaurant.open ? (
                    <p className="mt-3 rounded-xl border border-border bg-secondary px-3 py-2 text-sm text-navy">
                        Este negocio está cerrado ahora. Puedes ver el menú,
                        pero no agregar productos al carrito hasta que abra.
                    </p>
                ) : null}

                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <InfoTile
                        icon={Clock3}
                        label="Horario de hoy"
                        value={restaurant.schedule}
                    />
                    {restaurant.phone ? (
                        <a href={telHref(restaurant.phone)} className="block">
                            <InfoTile
                                icon={Phone}
                                label="Teléfono"
                                value={restaurant.phone}
                                interactive
                            />
                        </a>
                    ) : (
                        <InfoTile
                            icon={Phone}
                            label="Teléfono"
                            value="No publicado"
                        />
                    )}
                    <div className="sm:col-span-2">
                        <InfoTile
                            icon={MapPin}
                            label="Dirección"
                            value={
                                restaurant.reference
                                    ? `${restaurant.address ?? 'Sin dirección'} · ${restaurant.reference}`
                                    : (restaurant.address ?? 'Sin dirección')
                            }
                        />
                    </div>
                </div>

                {restaurant.working_days.length > 0 ? (
                    <div className="mt-4 space-y-2">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Días de trabajo
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {restaurant.working_days.map((day) => (
                                <span
                                    key={day}
                                    className="rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-navy"
                                >
                                    {day}
                                </span>
                            ))}
                        </div>
                    </div>
                ) : null}

                {restaurant.schedule_summary.length > 0 ? (
                    <div className="mt-4 overflow-hidden rounded-xl border border-border">
                        {restaurant.schedule_summary.map((row) => (
                            <div
                                key={row.days_label}
                                className="flex items-center justify-between gap-3 border-b border-border px-3 py-2 last:border-b-0"
                            >
                                <span className="text-sm font-medium text-navy">
                                    {row.days_label}
                                </span>
                                <span
                                    className={
                                        row.is_open
                                            ? 'text-sm font-medium text-navy'
                                            : 'text-sm text-muted-foreground'
                                    }
                                >
                                    {row.hours_label}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : null}

                <div className="mt-4 flex flex-wrap gap-2">
                    {mapsUrl ? (
                        <Button variant="outline" size="sm" asChild>
                            <a href={mapsUrl} target="_blank" rel="noreferrer">
                                <MapPin className="size-4" />
                                Ver en Maps
                            </a>
                        </Button>
                    ) : null}
                    {restaurant.phone ? (
                        <Button variant="outline" size="sm" asChild>
                            <a href={telHref(restaurant.phone)}>
                                <Phone className="size-4" />
                                Llamar
                            </a>
                        </Button>
                    ) : null}
                </div>

                {restaurant.branches.length > 1 ? (
                    <label className="mt-4 block space-y-1.5">
                        <span className="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            <Store className="size-3.5" />
                            Sucursal
                        </span>
                        <select
                            className="flex h-10 w-full max-w-sm rounded-md border border-input bg-background px-3 text-sm"
                            value={branchId}
                            onChange={(event) =>
                                router.get(
                                    restaurants.show.url(restaurant.slug),
                                    { branch: event.target.value },
                                    { preserveState: true },
                                )
                            }
                        >
                            {restaurant.branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                    </label>
                ) : null}
            </div>
        </section>
    );
}

function InfoTile({
    icon: Icon,
    label,
    value,
    interactive = false,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    interactive?: boolean;
}) {
    return (
        <div
            className={`rounded-xl border border-border bg-secondary/60 p-3 ${
                interactive ? 'transition-colors hover:border-primary/40' : ''
            }`}
        >
            <div className="flex items-start gap-2.5">
                <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-primary shadow-sm">
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="text-xs font-medium text-muted-foreground">
                        {label}
                    </p>
                    <p className="mt-0.5 text-sm font-semibold text-navy">
                        {value}
                    </p>
                </div>
            </div>
        </div>
    );
}
