export async function releasePushTokenBeforeLogout(
    release: () => Promise<void>,
): Promise<void> {
    try {
        await release();
    } catch {
        // A network failure must not block Laravel logout.
        // The next registration on this origin reassigns the token.
    }
}
