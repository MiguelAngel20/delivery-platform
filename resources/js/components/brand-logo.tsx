import { brandAssets } from '@/constants/brand';
import { cn } from '@/lib/utils';

type BrandLogoProps = {
    variant?: 'isotipo' | 'horizontal' | 'full';
    className?: string;
    alt?: string;
};

const sources = {
    isotipo: brandAssets.isotipo,
    horizontal: brandAssets.logoHorizontal,
    full: brandAssets.logo,
} as const;

export function BrandLogo({
    variant = 'horizontal',
    className,
    alt = 'RIDE',
}: BrandLogoProps) {
    return (
        <img
            src={sources[variant]}
            alt={alt}
            className={cn('h-8 w-auto', className)}
        />
    );
}
