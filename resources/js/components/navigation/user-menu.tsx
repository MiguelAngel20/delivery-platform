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
                    <Avatar className="size-8 overflow-hidden rounded-full">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="bg-navy text-xs font-semibold text-navy-foreground">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden min-w-0 flex-col items-start text-left md:flex">
                        <span className="max-w-32 truncate text-sm font-medium text-navy">
                            {auth.user.name}
                        </span>
                        {role ? (
                            <span className="max-w-32 truncate text-xs text-muted-foreground">
                                {role}
                            </span>
                        ) : null}
                    </span>
                    <ChevronsUpDown className="hidden size-4 text-muted-foreground md:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-56 rounded-lg">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
