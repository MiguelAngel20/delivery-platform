import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

type UserMenuProps = {
    role?: string;
};

export function UserMenu({ role }: UserMenuProps) {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className="h-10 gap-2 rounded-lg px-2"
                    aria-label="Menú de usuario"
                >
                    <Avatar className="size-9 shrink-0 overflow-hidden rounded-full">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="bg-navy text-xs font-semibold text-navy-foreground">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden min-w-0 flex-col items-start text-left sm:flex">
                        <span className="max-w-36 truncate text-sm font-medium text-foreground">
                            {auth.user.name}
                        </span>
                        {role ? (
                            <span className="max-w-36 truncate text-xs text-muted-foreground">
                                {role}
                            </span>
                        ) : null}
                    </span>
                    <ChevronsUpDown className="hidden size-4 shrink-0 text-muted-foreground sm:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48 rounded-lg">
                <UserMenuContent />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
