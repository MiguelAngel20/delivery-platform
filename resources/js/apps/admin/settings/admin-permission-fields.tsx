export type AdminSectionOption = {
    value: string;
    label: string;
    hint: string | null;
};

export type PermissionFlags = {
    view: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
};

const flags: Array<{ key: keyof PermissionFlags; label: string }> = [
    { key: 'view', label: 'Ver' },
    { key: 'create', label: 'Crear' },
    { key: 'update', label: 'Editar' },
    { key: 'delete', label: 'Eliminar' },
];

type Props = {
    sections: AdminSectionOption[];
    permissions: Record<string, PermissionFlags>;
    onChange: (permissions: Record<string, PermissionFlags>) => void;
};

export function AdminPermissionFields({
    sections,
    permissions,
    onChange,
}: Props) {
    const setFlag = (
        section: string,
        key: keyof PermissionFlags,
        checked: boolean,
    ) => {
        const current = permissions[section] ?? {
            view: false,
            create: false,
            update: false,
            delete: false,
        };
        const next = { ...current, [key]: checked };

        if (key !== 'view' && checked) {
            next.view = true;
        }

        if (key === 'view' && !checked) {
            next.create = false;
            next.update = false;
            next.delete = false;
        }

        onChange({
            ...permissions,
            [section]: next,
        });
    };

    return (
        <div className="space-y-3">
            {sections.map((section) => (
                <fieldset
                    key={section.value}
                    className="rounded-lg border border-border p-3"
                >
                    <legend className="px-1 text-sm font-medium text-navy">
                        {section.label}
                    </legend>
                    <div className="mt-2 flex flex-wrap gap-4">
                        {flags.map((flag) => (
                            <label
                                key={flag.key}
                                className="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    className="size-4 rounded border-input"
                                    checked={
                                        permissions[section.value]?.[flag.key] ??
                                        false
                                    }
                                    onChange={(event) =>
                                        setFlag(
                                            section.value,
                                            flag.key,
                                            event.target.checked,
                                        )
                                    }
                                />
                                {flag.label}
                            </label>
                        ))}
                    </div>
                    {section.hint ? (
                        <p className="mt-2 text-xs text-muted-foreground">
                            {section.hint}
                        </p>
                    ) : null}
                </fieldset>
            ))}
        </div>
    );
}
