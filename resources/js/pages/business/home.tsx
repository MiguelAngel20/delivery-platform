import { Head, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ChefHat,
    DollarSign,
    ShoppingBag,
} from 'lucide-react';
import { ActiveOrderCard } from '@/apps/business/components/active-order-card';
import {
    businessDashboardMocks,
    mockActiveOrders,
} from '@/apps/business/mocks';
import { StatCard } from '@/components/data-display/stat-card';
import {
    ContentCard,
    PageContainer,
    PageHeader,
} from '@/components/layout/page';
import { home } from '@/routes/business';
import type { BusinessContext } from '@/types/business';

const statIcons = {
    ordersToday: ShoppingBag,
    preparing: ChefHat,
    completed: CheckCircle2,
    sales: DollarSign,
} as const;

export default function BusinessHome() {
    const { businessContext } = usePage().props as {
        businessContext: BusinessContext | null;
    };
    const currentBranch =
        businessContext?.branches.find(
            (branch) => branch.id === businessContext.current_branch_id,
        ) ?? businessContext?.branches[0];
    const description = businessContext
        ? `${businessContext.business.name}${currentBranch ? ` · ${currentBranch.name}` : ''}`
        : 'Resumen del establecimiento';

    return (
        <>
            <Head title="Dashboard" />
            <PageContainer>
                <PageHeader title="Dashboard" description={description} />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {businessDashboardMocks.stats.map((stat) => {
                        const Icon =
                            statIcons[stat.key as keyof typeof statIcons];

                        return (
                            <StatCard
                                key={stat.key}
                                title={stat.title}
                                value={stat.value}
                                trend={stat.trend}
                                icon={<Icon />}
                            />
                        );
                    })}
                </div>

                <ContentCard
                    title="Pedidos activos"
                    description="Comandas en curso"
                    bodyClassName="p-4 md:p-5"
                >
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-3">
                        {mockActiveOrders.map((order) => (
                            <ActiveOrderCard key={order.id} order={order} />
                        ))}
                    </div>
                </ContentCard>
            </PageContainer>
        </>
    );
}

BusinessHome.layout = {
    title: 'Dashboard',
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: home(),
        },
    ],
};
