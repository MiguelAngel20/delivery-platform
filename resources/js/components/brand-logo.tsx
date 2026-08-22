import { useState } from 'react';
import { brandAssets } from '@/constants/brand';
import { cn } from '@/lib/utils';

type BrandLogoProps = {
    variant?: 'isotipo' | 'horizontal' | 'full' | 'responsive' | 'onDark';
    className?: string;
    alt?: string;
};

export function BrandLogo({
    variant = 'horizontal',
    className,
    alt = 'RIDE',
}: BrandLogoProps) {
    if (variant === 'responsive') {
        return (
            <>
                <BrandImage
                    src={brandAssets.isotipo}
                    fallback={brandAssets.isotipoFallback}
                    alt={alt}
                    className={cn('h-14 w-auto md:hidden', className)}
                />
                <BrandImage
                    src={brandAssets.logoHorizontal}
                    fallback={brandAssets.logoHorizontalFallback}
                    alt=""
                    className={cn('hidden h-16 w-auto md:block', className)}
                />
            </>
        );
    }

    const source =
        variant === 'isotipo'
            ? {
                  src: brandAssets.isotipo,
                  fallback: brandAssets.isotipoFallback,
              }
            : variant === 'full'
              ? { src: brandAssets.logo, fallback: brandAssets.logo }
              : variant === 'onDark'
                ? {
                      src: brandAssets.logoOnDark,
                      fallback: brandAssets.logoHorizontalFallback,
                  }
                : {
                      src: brandAssets.logoHorizontal,
                      fallback: brandAssets.logoHorizontalFallback,
                  };

    return (
        <BrandImage
            src={source.src}
            fallback={source.fallback}
            alt={alt}
            className={cn(
                variant === 'onDark' ? 'h-16 w-auto md:h-16' : 'h-8 w-auto',
                className,
            )}
        />
    );
}

function BrandImage({
    src,
    fallback,
    alt,
    className,
}: {
    src: string;
    fallback: string;
    alt: string;
    className?: string;
}) {
    const [currentSrc, setCurrentSrc] = useState(src);

    return (
        <img
            src={currentSrc}
            alt={alt}
            className={className}
            onError={() => {
                if (currentSrc !== fallback) {
                    setCurrentSrc(fallback);
                }
            }}
        />
    );
}
