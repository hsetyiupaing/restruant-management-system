import { computed, ref, watch } from 'vue';
import type { CartLine, MenuItem } from '@/types/restaurant';

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
            (total, line) => total + Number.parseFloat(line.price) * line.quantity,
            0,
        ),
    );

    function add(item: MenuItem, quantity = 1, note = ''): void {
        const existing = lines.value.find(
            (line) => line.menuItemId === item.id,
        );

        if (existing) {
            existing.quantity = Math.min(existing.quantity + quantity, 99);

            if (note !== '') {
                existing.note = note;
            }

            return;
        }

        lines.value = [
            ...lines.value,
            {
                menuItemId: item.id,
                name: item.name,
                price: item.price,
                quantity: Math.min(quantity, 99),
                note,
                imageUrl: item.image_url,
            },
        ];
    }

    function setQuantity(menuItemId: number, quantity: number): void {
        if (quantity <= 0) {
            remove(menuItemId);

            return;
        }

        const line = lines.value.find((entry) => entry.menuItemId === menuItemId);

        if (line) {
            line.quantity = Math.min(quantity, 99);
        }
    }

    function remove(menuItemId: number): void {
        lines.value = lines.value.filter(
            (line) => line.menuItemId !== menuItemId,
        );
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
    }[] {
        return lines.value.map((line) => ({
            menu_item_id: line.menuItemId,
            quantity: line.quantity,
            ...(line.note ? { note: line.note } : {}),
        }));
    }

    return {
        lines,
        itemCount,
        subtotal,
        add,
        setQuantity,
        remove,
        clear,
        toOrderItems,
    };
}
