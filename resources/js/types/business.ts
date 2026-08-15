export type BusinessContextBranch = {
    id: number;
    name: string;
    status: string;
    status_label: string;
};

export type BusinessContext = {
    business: {
        id: number;
        name: string;
        slug: string;
        status: string;
        status_label: string;
    };
    membership_role: string | null;
    membership_role_label: string | null;
    branches: BusinessContextBranch[];
    current_branch_id: number | null;
};
