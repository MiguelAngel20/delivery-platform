import { FilterSelect } from '@/components/forms/filter-select';

export const CATALOG_PER_PAGE_OPTIONS = [25, 50, 75, 100] as const;

export const DEFAULT_CATALOG_PER_PAGE = 25;

type PerPageSelectProps = {
    value: number | string;
    onChange: (perPage: number) => void;
};

export function PerPageSelect({ value, onChange }: PerPageSelectProps) {
    const normalized = Number(value) || DEFAULT_CATALOG_PER_PAGE;

    return (
        <FilterSelect
            label="Mostrar"
            value={String(normalized)}
            onChange={(event) => onChange(Number(event.target.value))}
        >
            {CATALOG_PER_PAGE_OPTIONS.map((option) => (
                <option key={option} value={option}>
                    {option} por página
                </option>
            ))}
        </FilterSelect>
    );
}
