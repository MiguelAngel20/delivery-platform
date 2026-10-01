const VOLUME_KEY = 'ride.admin.pending_order_volume';
const DEFAULT_VOLUME = 100;

let audioContext: AudioContext | null = null;
let masterGain: GainNode | null = null;

function clampVolume(value: number): number {
    if (!Number.isFinite(value)) {
        return DEFAULT_VOLUME;
    }

    return Math.min(100, Math.max(0, Math.round(value)));
}

export function readAdminChimeVolume(): number {
    if (typeof window === 'undefined') {
        return DEFAULT_VOLUME;
    }

    const raw = window.localStorage.getItem(VOLUME_KEY);

    if (raw === null || raw === '') {
        return DEFAULT_VOLUME;
    }

    return clampVolume(Number(raw));
}

export function setAdminChimeVolume(volume: number): void {
    const next = clampVolume(volume);

    window.localStorage.setItem(VOLUME_KEY, String(next));

    if (masterGain !== null) {
        masterGain.gain.value = next / 100;
    }
}

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

function masterGainNode(ctx: AudioContext): GainNode {
    if (masterGain !== null) {
        return masterGain;
    }

    masterGain = ctx.createGain();
    masterGain.gain.value = readAdminChimeVolume() / 100;
    masterGain.connect(ctx.destination);

    return masterGain;
}

function scheduleTones(ctx: AudioContext): void {
    const output = masterGainNode(ctx);
    const tones = [740, 880, 1046];

    tones.forEach((frequency, index) => {
        const start = ctx.currentTime + index * 0.28;
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(1, start + 0.02);
        gain.gain.setValueAtTime(1, start + 0.16);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.26);
        oscillator.connect(gain);
        gain.connect(output);
        oscillator.start(start);
        oscillator.stop(start + 0.28);
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
