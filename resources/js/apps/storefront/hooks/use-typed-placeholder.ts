import { useEffect, useMemo, useState } from 'react';

const FALLBACK_PLACEHOLDER = 'Buscar negocios, comida...';
const TYPE_MS = 85;
const DELETE_MS = 45;
const HOLD_MS = 1800;
const GAP_MS = 420;

function uniqueNames(suggestions: readonly string[]): string[] {
    return [...new Set(suggestions.map((name) => name.trim()).filter(Boolean))];
}

export function useTypedPlaceholder(
    suggestions: readonly string[],
    enabled = true,
): string {
    const namesKey = uniqueNames(suggestions).join('\u0000');
    const names = useMemo(() => uniqueNames(suggestions), [namesKey]);
    const [text, setText] = useState(
        names.length === 0 ? FALLBACK_PLACEHOLDER : '',
    );

    useEffect(() => {
        if (!enabled || names.length === 0) {
            setText(FALLBACK_PLACEHOLDER);

            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setText(names[0] ?? FALLBACK_PLACEHOLDER);

            return;
        }

        let nameIndex = 0;
        let charCount = 0;
        let deleting = false;
        let timeoutId = 0;

        const tick = () => {
            const current = names[nameIndex] ?? '';

            if (!deleting) {
                charCount += 1;
                setText(current.slice(0, charCount));

                if (charCount >= current.length) {
                    deleting = true;
                    timeoutId = window.setTimeout(tick, HOLD_MS);

                    return;
                }

                timeoutId = window.setTimeout(tick, TYPE_MS);

                return;
            }

            charCount -= 1;
            setText(current.slice(0, Math.max(charCount, 0)));

            if (charCount <= 0) {
                deleting = false;
                nameIndex = (nameIndex + 1) % names.length;
                timeoutId = window.setTimeout(tick, GAP_MS);

                return;
            }

            timeoutId = window.setTimeout(tick, DELETE_MS);
        };

        timeoutId = window.setTimeout(tick, GAP_MS);

        return () => window.clearTimeout(timeoutId);
    }, [enabled, names]);

    return text;
}
