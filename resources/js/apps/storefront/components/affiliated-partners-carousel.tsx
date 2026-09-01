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
                'relative min-w-0 w-full overflow-hidden rounded-2xl bg-secondary',
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
                            <div className="absolute bottom-2 right-2 z-10 md:bottom-4 md:right-4">
                                <Button
                                    asChild
                                    size="sm"
                                    className="h-6 rounded-md bg-white/95 px-2 text-[10px] font-medium text-navy shadow-sm hover:bg-white md:h-8 md:px-3 md:text-xs"
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
