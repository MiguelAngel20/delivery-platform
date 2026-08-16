import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import restaurants from '@/routes/restaurants';

export type AffiliatedPartner = {
    id: string;
    slug: string;
    name: string;
    banner_url: string;
};

type AffiliatedPartnersCarouselProps = {
    partners: AffiliatedPartner[];
    className?: string;
};

export function AffiliatedPartnersCarousel({
    partners,
    className,
}: AffiliatedPartnersCarouselProps) {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        setIndex(0);
    }, [partners]);

    useEffect(() => {
        if (partners.length <= 1) {
            return;
        }

        const timer = window.setInterval(() => {
            setIndex((current) => (current + 1) % partners.length);
        }, 5500);

        return () => window.clearInterval(timer);
    }, [partners]);

    if (partners.length === 0) {
        return null;
    }

    const active = partners[index] ?? partners[0];

    return (
        <section
            aria-label="Empresas afiliadas a RIDE"
            aria-roledescription="carrusel"
            className={cn(
                'relative -mx-4 overflow-hidden bg-secondary md:mx-0 md:rounded-2xl',
                className,
            )}
        >
            <div className="relative w-full md:aspect-[3/1] md:min-h-56">
                {partners.map((partner, partnerIndex) => {
                    const isActive = partnerIndex === index;

                    return (
                        <div
                            key={partner.id}
                            aria-hidden={!isActive}
                            className={cn(
                                'transition-opacity duration-500',
                                isActive
                                    ? 'relative opacity-100'
                                    : 'hidden opacity-0',
                                'md:absolute md:inset-0 md:block',
                                isActive
                                    ? 'md:opacity-100'
                                    : 'md:pointer-events-none md:opacity-0',
                            )}
                        >
                            <img
                                src={partner.banner_url}
                                alt={partner.name}
                                className="block h-auto w-full object-contain object-center md:absolute md:inset-0 md:size-full md:object-cover"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-navy/55 via-transparent to-transparent md:from-navy/75 md:via-navy/20" />
                            <div className="absolute inset-x-0 bottom-0 flex flex-col items-center gap-1.5 px-3 pb-7 text-center md:flex-row md:items-end md:justify-between md:gap-3 md:p-6 md:pb-11 md:text-left">
                                <div className="max-w-xl space-y-0.5 text-white">
                                    <p className="text-[10px] font-medium uppercase tracking-wide text-white/80 md:text-xs">
                                        Empresa afiliada
                                    </p>
                                    <h2 className="line-clamp-2 text-sm font-semibold drop-shadow-sm md:text-2xl">
                                        {partner.name}
                                    </h2>
                                </div>
                                <Button
                                    asChild
                                    size="sm"
                                    className="h-8 bg-white px-3 text-xs text-navy hover:bg-white/90 md:h-10 md:px-4 md:text-sm"
                                >
                                    <Link href={restaurants.show(partner.slug)}>
                                        Ver negocio
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>

            {partners.length > 1 ? (
                <div className="absolute bottom-2 left-1/2 z-10 flex -translate-x-1/2 gap-1.5 md:bottom-3 md:gap-2">
                    {partners.map((partner, partnerIndex) => (
                        <button
                            key={partner.id}
                            type="button"
                            aria-label={`Ir a ${partner.name}`}
                            aria-current={
                                partnerIndex === index ? 'true' : undefined
                            }
                            className={cn(
                                'size-2 rounded-full border border-white/80 transition-colors md:size-2.5',
                                partnerIndex === index
                                    ? 'bg-white'
                                    : 'bg-white/30 hover:bg-white/60',
                            )}
                            onClick={() => setIndex(partnerIndex)}
                        />
                    ))}
                </div>
            ) : null}

            <span className="sr-only">
                Mostrando {active.name} ({index + 1} de {partners.length})
            </span>
        </section>
    );
}
