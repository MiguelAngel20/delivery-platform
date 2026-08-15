import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type HighlightKey =
    | 'speed_rating'
    | 'service_rating'
    | 'care_rating'
    | 'respect_rating'
    | 'communication_rating';

const highlights: Array<{ key: HighlightKey; label: string }> = [
    { key: 'speed_rating', label: 'Rapidez' },
    { key: 'service_rating', label: 'Amabilidad' },
    { key: 'care_rating', label: 'Cuidado del pedido' },
    { key: 'respect_rating', label: 'Respeto' },
    { key: 'communication_rating', label: 'Comunicación' },
];

type DriverRatingFormProps = {
    actionUrl: string;
};

function StarPicker({
    value,
    onChange,
}: {
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <div className="flex gap-1" role="radiogroup" aria-label="Calificación">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    aria-label={`${star} estrellas`}
                    className={cn(
                        'text-2xl leading-none',
                        star <= value ? 'text-primary' : 'text-muted-foreground/40',
                    )}
                    onClick={() => onChange(star)}
                >
                    ★
                </button>
            ))}
        </div>
    );
}

export function DriverRatingForm({ actionUrl }: DriverRatingFormProps) {
    const form = useForm({
        overall_rating: 0,
        speed_rating: null as number | null,
        service_rating: null as number | null,
        care_rating: null as number | null,
        respect_rating: null as number | null,
        communication_rating: null as number | null,
        comment: '',
    });

    return (
        <section className="space-y-4 rounded-xl border border-border bg-surface p-4">
            <div>
                <h2 className="font-semibold text-navy">
                    ¿Cómo estuvo tu repartidor?
                </h2>
                <p className="text-sm text-muted-foreground">
                    Calificación general
                </p>
            </div>
            <StarPicker
                value={form.data.overall_rating}
                onChange={(value) => form.setData('overall_rating', value)}
            />
            <div className="space-y-2">
                <p className="text-sm font-medium text-navy">¿Qué destacó?</p>
                <div className="flex flex-wrap gap-2">
                    {highlights.map((item) => {
                        const selected = form.data[item.key] !== null;

                        return (
                            <Button
                                key={item.key}
                                type="button"
                                size="sm"
                                variant={selected ? 'default' : 'outline'}
                                onClick={() =>
                                    form.setData(
                                        item.key,
                                        selected
                                            ? null
                                            : Math.max(form.data.overall_rating, 1),
                                    )
                                }
                            >
                                {item.label}
                            </Button>
                        );
                    })}
                </div>
            </div>
            <FormField label="Comentario (opcional)">
                <Textarea
                    value={form.data.comment}
                    onChange={(event) =>
                        form.setData('comment', event.target.value)
                    }
                    rows={3}
                />
            </FormField>
            <Button
                type="button"
                disabled={form.processing || form.data.overall_rating < 1}
                onClick={() => form.post(actionUrl)}
            >
                Enviar calificación
            </Button>
        </section>
    );
}
