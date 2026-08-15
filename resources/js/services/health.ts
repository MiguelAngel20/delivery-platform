import { apiClient } from '@/services/http';

export type HealthResponse = {
    ok: boolean;
    version: string;
};

export function getHealth(): Promise<HealthResponse> {
    return apiClient<HealthResponse>('/health');
}
