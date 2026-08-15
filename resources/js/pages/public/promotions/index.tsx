import { Head } from '@inertiajs/react';
import { PromotionCard } from '@/apps/storefront/components/promotion-card';
import { mockPromotions } from '@/apps/storefront/mocks';
import { EmptyState } from '@/components/feedback/empty-state';
import { PageContainer } from '@/components/layout/page';

export default function PromotionsIndex() {
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
                {mockPromotions.length === 0 ? (
                    <EmptyState title="No hay promociones" />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {mockPromotions.map((promotion) => (
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
