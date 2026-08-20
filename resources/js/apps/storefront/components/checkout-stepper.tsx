import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export const CHECKOUT_STEPS = [
    { step: 1, label: 'Pedido' },
    { step: 2, label: 'Dirección' },
    { step: 3, label: 'Pago' },
    { step: 4, label: 'Confirmar' },
] as const;

type CheckoutStepperProps = {
    currentStep: 1 | 2 | 3 | 4;
    className?: string;
};

export function CheckoutStepper({
    currentStep,
    className,
}: CheckoutStepperProps) {
    return (
        <nav
            aria-label="Progreso del pedido"
            className={cn('w-full', className)}
        >
            <ol className="flex items-center justify-between gap-1">
                {CHECKOUT_STEPS.map(({ step, label }, index) => {
                    const completed = step < currentStep;
                    const active = step === currentStep;

                    return (
                        <li
                            key={step}
                            className="flex min-w-0 flex-1 items-center"
                        >
                            <div className="flex min-w-0 flex-1 flex-col items-center gap-1.5">
                                <div
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold transition-colors',
                                        completed &&
                                            'border-primary bg-primary text-primary-foreground',
                                        active &&
                                            'border-primary bg-primary/10 text-primary',
                                        !completed &&
                                            !active &&
                                            'border-border bg-background text-muted-foreground',
                                    )}
                                    aria-current={active ? 'step' : undefined}
                                >
                                    {completed ? (
                                        <Check className="size-4" />
                                    ) : (
                                        step
                                    )}
                                </div>
                                <span
                                    className={cn(
                                        'hidden truncate text-center text-xs font-medium sm:block',
                                        active
                                            ? 'text-navy'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {label}
                                </span>
                            </div>
                            {index < CHECKOUT_STEPS.length - 1 ? (
                                <div
                                    className={cn(
                                        'mx-1 mb-5 h-0.5 flex-1 rounded-full sm:mb-6',
                                        step < currentStep
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                    aria-hidden
                                />
                            ) : null}
                        </li>
                    );
                })}
            </ol>
            <p className="mt-3 text-center text-sm font-medium text-navy sm:hidden">
                Paso {currentStep} de {CHECKOUT_STEPS.length}:{' '}
                {CHECKOUT_STEPS[currentStep - 1]?.label}
            </p>
        </nav>
    );
}
