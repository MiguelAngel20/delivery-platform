export type AdminAbility = 'view' | 'create' | 'update' | 'delete';

export type AdminAccess = {
    is_owner: boolean;
    sections: Record<string, Record<AdminAbility, boolean>>;
};

export function canAdmin(
    access: AdminAccess | null | undefined,
    section: string,
    ability: AdminAbility = 'view',
): boolean {
    if (access == null) {
        return false;
    }

    if (access.is_owner) {
        return true;
    }

    return access.sections[section]?.[ability] === true;
}
