import { Head, Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import {
    LoyaltyProgressCard
    
} from '@/apps/customer/components/loyalty-progress-card';
import type {LoyaltyProgress} from '@/apps/customer/components/loyalty-progress-card';
import { PhoneVerificationCard } from '@/apps/customer/components/phone-verification-card';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { logoutAfterPushCleanup } from '@/lib/auth/logout';
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
    loyalty?: LoyaltyProgress | null;
    phone?: string | null;
    phone_verified?: boolean;
    phone_dial_code?: string;
    phone_national?: string;
    phone_dial_options?: Array<{
        dial: string;
        label: string;
        national_length: number;
    }>;
    firebase_phone_auth_ready?: boolean;
};

export default function CustomerProfileIndex({
    reputation,
    loyalty,
    phone,
    phone_verified = false,
    phone_dial_code = '+52',
    phone_national = '',
    phone_dial_options = [],
    firebase_phone_auth_ready = false,
}: Props) {
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
                    <PhoneVerificationCard
                        key={`${phone_dial_code}-${phone_national}-${phone_verified ? '1' : '0'}`}
                        phoneDialCode={phone_dial_code}
                        phoneNational={phone_national}
                        phoneVerified={phone_verified}
                        dialOptions={phone_dial_options}
                        firebaseReady={firebase_phone_auth_ready}
                    />
                </ContentCard>

                {loyalty ? <LoyaltyProgressCard loyalty={loyalty} /> : null}
                <Button asChild variant="outline" className="min-h-12 w-full">
                    <Link href="/customer/profile/notifications">
                        Notificaciones
                    </Link>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    className="min-h-12 w-full text-destructive hover:bg-destructive/5 hover:text-destructive"
                    data-test="customer-logout-button"
                    onClick={() => {
                        void logoutAfterPushCleanup();
                    }}
                >
                    <LogOut className="size-4" />
                    Cerrar sesión
                </Button>
            </PageContainer>
        </>
    );
}
