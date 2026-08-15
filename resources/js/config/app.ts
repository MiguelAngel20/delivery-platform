export const appInterfaces = [
    'public',
    'customer',
    'business',
    'driver',
    'admin',
] as const;

export type AppInterface = (typeof appInterfaces)[number];

export const apiVersion = 'v1' as const;
