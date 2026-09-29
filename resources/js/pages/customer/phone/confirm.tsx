import { Head, Link } from '@inertiajs/react';
import { PhoneVerificationCard } from '@/apps/customer/components/phone-verification-card';
import { ContentCard, PageContainer } from '@/components/layout/page';
import { Button } from '@/components/ui/button';
import { checkout } from '@/routes/customer';

type DialOption = {
    dial: string;
    label: string;
    national_length: number;
};

type Props = {
    phone_dial_code: string;
    phone_national: string;
    phone_verified: boolean;
    phone_dial_options: DialOption[];
    firebase_phone_auth_ready: boolean;
};

export default function ConfirmCustomerPhone({
    phone_dial_code,
    phone_national,
    phone_verified,
    phone_dial_options,
    firebase_phone_auth_ready,
}: Props) {
    return (
        <>
            <Head title="Confirmar teléfono" />
            <PageContainer className="max-w-lg gap-5 px-4 py-8 md:px-6">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold text-navy">
                        Confirma tu teléfono
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Antes de continuar el pedido, confirma que este número
                        es el correcto. Si no lo es, cámbialo y luego envía el
                        código.
                    </p>
                </div>

                <ContentCard>
                    <PhoneVerificationCard
                        key={`${phone_dial_code}-${phone_national}-${phone_verified ? '1' : '0'}`}
                        phoneDialCode={phone_dial_code}
                        phoneNational={phone_national}
                        phoneVerified={phone_verified}
                        dialOptions={phone_dial_options}
                        firebaseReady={firebase_phone_auth_ready}
                    />
                </ContentCard>

                {phone_verified ? (
                    <Button asChild className="h-11 w-full">
                        <Link href={checkout()}>Continuar con el pedido</Link>
                    </Button>
                ) : null}
            </PageContainer>
        </>
    );
}
