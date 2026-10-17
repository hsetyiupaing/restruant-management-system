import { ref } from 'vue';
import type { Ref } from 'vue';
import { ApiError, errorMessage } from '@/lib/api';

type State<T> = {
    data: Ref<T | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    validation: Ref<Record<string, string[]>>;
    reload: () => Promise<void>;
};

/**
 * Small read helper for API-backed screens. It tracks loading and error state
 * so pages can render skeleton, empty and failure states consistently.
 */
export function useApi<T>(
    loader: () => Promise<T>,
    initial: T | null = null,
): State<T> {
    const data = ref<T | null>(initial) as Ref<T | null>;
    const loading = ref(false);
    const error = ref<string | null>(null);
    const validation = ref<Record<string, string[]>>({});

    async function reload(): Promise<void> {
        loading.value = true;
        error.value = null;
        validation.value = {};

        try {
            data.value = await loader();
        } catch (caught) {
            if (caught instanceof ApiError) {
                validation.value = caught.errors;
            }

            error.value = errorMessage(caught);
        } finally {
            loading.value = false;
        }
    }

    return { data, loading, error, validation, reload };
}
