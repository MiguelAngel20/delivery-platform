import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

type Option = { value: string; label: string };

type OrderActionDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    actionUrl: string;
    codeField: 'reason_code' | 'type';
    options: Option[];
    selectLabel: string;
    notesLabel: string;
    notesName?: 'reason' | 'description';
    notesRequired?: boolean;
    submitLabel: string;
};

export function OrderActionDialog({
    open,
    onOpenChange,
    title,
    description,
    actionUrl,
    codeField,
    options,
    selectLabel,
    notesLabel,
    notesName = 'description',
    notesRequired = true,
    submitLabel,
}: OrderActionDialogProps) {
    const [code, setCode] = useState(options[0]?.value ?? '');
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (open) {
            setCode(options[0]?.value ?? '');
            setNotes('');
        }
    }, [open, options]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                <div className="space-y-3">
                    <FormField label={selectLabel} required>
                        <select
                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                        >
                            {options.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField label={notesLabel} required={notesRequired}>
                        <Textarea
                            value={notes}
                            onChange={(event) => setNotes(event.target.value)}
                            rows={4}
                        />
                    </FormField>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cerrar
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || !code}
                        onClick={() => {
                            setProcessing(true);
                            router.post(
                                actionUrl,
                                {
                                    [codeField]: code,
                                    [notesName]: notes,
                                },
                                {
                                    onFinish: () => setProcessing(false),
                                    onSuccess: () => onOpenChange(false),
                                },
                            );
                        }}
                    >
                        {submitLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
