import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';

type BackButtonProps = {
    href: NonNullable<InertiaLinkProps['href']>;
    label?: string;
    size?: 'default' | 'sm' | 'lg' | 'icon';
};

export function BackButton({
    href,
    label = 'Volver',
    size = 'default',
}: BackButtonProps) {
    return (
        <Button variant="outline" size={size} asChild>
            <Link href={href}>
                <ArrowLeft aria-hidden="true" />
                <span>{label}</span>
            </Link>
        </Button>
    );
}
