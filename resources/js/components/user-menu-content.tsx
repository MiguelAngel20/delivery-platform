import { Link } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logoutAfterPushCleanup } from '@/lib/auth/logout';
import { edit } from '@/routes/profile';

export function UserMenuContent() {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        void logoutAfterPushCleanup(cleanup);
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
                <button
                    type="button"
                    className="block w-full cursor-pointer"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Cerrar sesión
                </button>
            </DropdownMenuItem>
        </DropdownMenuGroup>
    );
}
