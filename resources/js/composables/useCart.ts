import { computed, ref, watch } from 'vue';
import type {
    CartLine,
    MenuItem,
    SelectedMenuCustomization,
} from '@/types/restaurant';

const STORAGE_KEY = 'mori.cart';

/**
 * A module-level cart so it survives Inertia page visits. Lines are keyed by
 * menu item because the API rejects duplicate `menu_item_id` rows in one order.
 */
const lines = ref<CartLine[]>(load());
const hydrated = ref(false);

function load(): CartLine[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? (parsed as CartLine[]) : [];
    } catch {
        return [];
    }
}

if (typeof window !== 'undefined') {
    watch(
        lines,
        (value) => {
            if (!hydrated.value) {
                return;
            }

            try {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
            } catch {
                // Storage can be unavailable (private mode); the cart still works in memory.
            }
        },
        { deep: true },
    );

    hydrated.value = true;
}

export function useCart() {
    const itemCount = computed(() =>
        lines.value.reduce((total, line) => total + line.quantity, 0),
    );

    const subtotal = computed(() =>
        lines.value.reduce(
            (total, line) =>
                total + Number.parseFloat(line.price) * line.quantity,
            0,
        ),
    );

    function lineKey(line: CartLine): string {
        const optionKey = (line.selectedOptions ?? [])
            .map(
                (group) =>
                    `${group.groupId}:${group.options.map((option) => option.id).join(',')}`,
            )
            .join('|');

        return `${line.menuItemId}:${optionKey}`;
    }

    function add(
        item: MenuItem,
        quantity = 1,
        note = '',
        selectedOptions: SelectedMenuCustomization[] = [],
    ): void {
        const customizationPrice = selectedOptions
            .flatMap((group) => group.options)
            .reduce((total, option) => total + Number(option.price), 0);
        const incomingLine: CartLine = {
            menuItemId: item.id,
            name: item.name,
            price: (Number(item.price) + customizationPrice).toFixed(2),
            quantity: Math.min(quantity, 99),
            note,
            imageUrl: item.image_url,
            selectedOptions,
        };
        const existing = lines.value.find(
            (line) => lineKey(line) === lineKey(incomingLine),
        );

        if (existing) {
            existing.quantity = Math.min(existing.quantity + quantity, 99);

            if (note !== '') {
                existing.note = note;
            }

            return;
        }

        lines.value = [...lines.value, incomingLine];
    }

    function setQuantity(key: string, quantity: number): void {
        if (quantity <= 0) {
            remove(key);

            return;
        }

        const line = lines.value.find((entry) => lineKey(entry) === key);

        if (line) {
            line.quantity = Math.min(quantity, 99);
        }
    }

    function remove(key: string): void {
        lines.value = lines.value.filter((line) => lineKey(line) !== key);
    }

    function clear(): void {
        lines.value = [];
    }

    /**
     * Payload shape accepted by POST /api/v1/orders. Prices are deliberately
     * omitted: Laravel recomputes every amount from the menu table.
     */
    function toOrderItems(): {
        menu_item_id: number;
        quantity: number;
        note?: string;
        customizations?: { group_id: string; option_ids: string[] }[];
    }[] {
        return lines.value.map((line) => ({
            menu_item_id: line.menuItemId,
            quantity: line.quantity,
            ...(line.note ? { note: line.note } : {}),
            ...(line.selectedOptions?.length
                ? {
                      customizations: line.selectedOptions.map((group) => ({
                          group_id: group.groupId,
                          option_ids: group.options.map((option) => option.id),
                      })),
                  }
                : {}),
        }));
    }

    return {
        lines,
        itemCount,
        subtotal,
        add,
        setQuantity,
        remove,
        lineKey,
        clear,
        toOrderItems,
    };
}
