import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { deactivateStoredPushDevice } from '@/lib/push/devices';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

export function UserMenuContent() {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        void deactivateStoredPushDevice();
        cleanup();
        router.flushAll();
    };

    return (
        <DropdownMenuGroup>
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={edit()}
                    prefetch
                    onClick={cleanup}
                >
                    <Settings className="mr-2" />
                    Configuración
                </Link>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Cerrar sesión
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
    );
}
