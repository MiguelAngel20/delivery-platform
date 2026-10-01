let audioContext: AudioContext | null = null;

function audioContextOrNull(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (audioContext !== null) {
        return audioContext;
    }

    const AudioCtx =
        window.AudioContext ||
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext;

    if (!AudioCtx) {
        return null;
    }

    audioContext = new AudioCtx();

    return audioContext;
}

function scheduleTones(ctx: AudioContext): void {
    const tones = [740, 880, 1046];

    tones.forEach((frequency, index) => {
        const start = ctx.currentTime + index * 0.22;
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.2, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.18);
        oscillator.connect(gain);
        gain.connect(ctx.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.2);
    });
}

export function unlockAdminOrderChime(): Promise<boolean> {
    const ctx = audioContextOrNull();

    if (ctx === null) {
        return Promise.resolve(false);
    }

    if (ctx.state === 'running') {
        return Promise.resolve(true);
    }

    return ctx
        .resume()
        .then(() => ctx.state === 'running')
        .catch(() => false);
}

export function playAdminPendingChime(): boolean {
    if (audioContext === null || audioContext.state !== 'running') {
        return false;
    }

    scheduleTones(audioContext);

    return true;
}
