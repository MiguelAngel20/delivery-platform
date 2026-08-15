import { Head, Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { deactivateStoredPushDevice } from '@/lib/push/devices';
import { logout } from '@/routes';
import type { Auth } from '@/types';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

type Props = {
    reputation: {
        verified: boolean;
        public_label: string;
        is_frequent: boolean;
        completed_orders: number;
    };
    phone?: string | null;
};

export default function CustomerProfileIndex({ reputation, phone }: Props) {
    const { auth } = usePage().props as { auth: Auth };
    const user = auth.user;

    return (
        <>
            <Head title="Perfil" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-navy">Perfil</h1>
                    <p className="text-sm text-muted-foreground">
                        Información básica de tu cuenta
                    </p>
                </div>

                <ContentCard>
                    <div className="flex items-center gap-3">
                        <Avatar className="size-14">
                            <AvatarFallback className="bg-primary/10 text-primary">
                                {initials(user?.name ?? 'C')}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <p className="text-lg font-semibold text-navy">
                                {user?.name ?? 'Cliente'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {user?.email}
                            </p>
                        </div>
                    </div>

                    <dl className="mt-6 space-y-4 text-sm">
                        <div className="flex justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">Teléfono</dt>
                            <dd className="font-medium text-navy">
                                {phone ?? '—'}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-3 border-b border-border pb-3">
                            <dt className="text-muted-foreground">Cuenta</dt>
                            <dd className="font-medium text-navy">
                                {reputation.public_label}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">
                                Pedidos completados
                            </dt>
                            <dd className="font-medium text-navy">
                                {reputation.completed_orders}
                            </dd>
                        </div>
                    </dl>
                </ContentCard>

                <Button asChild variant="outline" className="min-h-12 w-full">
                    <Link href="/customer/profile/notifications">
                        Notificaciones
                    </Link>
                </Button>

                <Button
                    asChild
                    variant="outline"
                    className="min-h-12 w-full text-destructive hover:bg-destructive/5 hover:text-destructive"
                >
                    <Link
                        href={logout()}
                        as="button"
                        data-test="customer-logout-button"
                        onClick={() => {
                            void deactivateStoredPushDevice();
                            router.flushAll();
                        }}
                    >
                        <LogOut className="size-4" />
                        Cerrar sesión
                    </Link>
                </Button>
            </PageContainer>
        </>
    );
}
