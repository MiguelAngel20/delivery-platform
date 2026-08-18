export function isGooglePlacesDropdownTarget(
    target: EventTarget | null,
): boolean {
    return target instanceof Element && Boolean(target.closest('.pac-container'));
}
