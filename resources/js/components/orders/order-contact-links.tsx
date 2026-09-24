import { MessageCircle, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { telHref, whatsappHref } from '@/lib/phone';
import { cn } from '@/lib/utils';

type OrderContactLinksProps = {
    phone?: string | null;
    whatsappText?: string;
    className?: string;
};

export function OrderContactLinks({
    phone = null,
    whatsappText,
    className,
}: OrderContactLinksProps) {
    const callUrl = telHref(phone);
    const chatUrl = whatsappHref(phone, whatsappText);

    if (!callUrl && !chatUrl) {
        return null;
    }

    return (
        <div className={cn('flex shrink-0 items-center gap-1.5', className)}>
            {callUrl ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    asChild
                >
                    <a href={callUrl} aria-label="Llamar" title="Llamar">
                        <Phone className="size-4" />
                    </a>
                </Button>
            ) : null}
            {chatUrl ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    asChild
                >
                    <a
                        href={chatUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="WhatsApp"
                        title="WhatsApp"
                    >
                        <MessageCircle className="size-4" />
                    </a>
                </Button>
            ) : null}
        </div>
    );
}
