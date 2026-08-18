import { Head } from '@inertiajs/react';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import type { MockPromotion } from '@/apps/storefront/mocks';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';

type Props = {
    promotions?: MockPromotion[];
};

export default function PromotionsIndex({ promotions = [] }: Props) {
    return (
        <>
            <Head title="Promociones" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">
                        Promociones
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Ofertas activas
                    </p>
                </div>
                {promotions.length === 0 ? (
                    <EmptyState title="No hay promociones" />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {promotions.map((promotion) => (
                            <PromotionCard
                                key={promotion.id}
                                promotion={promotion}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>
        </>
    );
}
