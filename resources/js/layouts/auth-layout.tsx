import { useEffect } from 'react';
import { forceLightTheme } from '@/hooks/use-appearance';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    useEffect(() => forceLightTheme(), []);

    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
