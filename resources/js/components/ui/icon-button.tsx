import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function IconButton({
    label,
    className,
    children,
    ...props
}: Omit<ComponentProps<typeof Button>, 'size' | 'children'> & {
    label: string;
    children: ReactNode;
}) {
    return (
        <Button
            size="icon"
            aria-label={label}
            title={label}
            className={cn(className)}
            {...props}
        >
            {children}
        </Button>
    );
}

export { IconButton };
