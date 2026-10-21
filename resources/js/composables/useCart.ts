import { computed, ref, watch } from 'vue';
import type {
    CartLine,
    MenuItem,
    SelectedMenuCustomization,
} from '@/types/restaurant';

/**
 * Carts are browser-held, but they must never be shared between accounts or
 * between guest sessions on the same browser. Every cart is therefore stored
 * under a namespace owned by the server-issued cart token:
 *
 *   mori.cart:user:<id>      an authenticated customer's own cart
 *   mori.cart:guest:<token>  a guest session's cart (token is derived from the
 *                            server-managed session id in HandleInertiaRequests)
 *
 * The legacy `mori.cart` key had no owner, so it cannot be attributed to
 * anyone. It is discarded instead of being shown to the next visitor.
 */
const STORAGE_PREFIX = 'mori.cart:';
const LEGACY_STORAGE_KEY = 'mori.cart';
const LAST_GUEST_KEY = 'mori.cart.last-guest';

/**
 * Lines are keyed by menu item because the API rejects duplicate
 * `menu_item_id` rows in one order.
 */
const lines = ref<CartLine[]>([]);

let currentOwner: string | null = null;
let currentStorageKey: string | null = null;
let initialized = false;

/**
 * Explicit guest-to-account merge policy: identical configured lines are
 * combined by summing quantities (capped at 99). The authenticated cart wins
 * for price/image metadata; a non-empty guest note replaces an empty one.
 * Nothing is merged in the other direction (account -> guest).
 */
function mergeLines(base: CartLine[], incoming: CartLine[]): CartLine[] {
    const merged = base.map((line) => ({ ...line }));

    incoming.forEach((guestLine) => {
        const existing = merged.find(
            (line) => lineKey(line) === lineKey(guestLine),
        );

        if (existing) {
            existing.quantity = Math.min(
                existing.quantity + guestLine.quantity,
                99,
            );
            existing.note = guestLine.note || existing.note;

            return;
        }

        merged.push({ ...guestLine });
    });

    return merged;
}

function storageKeyFor(owner: string): string {
    return `${STORAGE_PREFIX}${owner}`;
}

function isGuestOwner(owner: string | null): boolean {
    return owner !== null && owner.startsWith('guest:');
}

function safeRead(key: string | null): CartLine[] {
    if (key === null || typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(key);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? (parsed as CartLine[]) : [];
    } catch {
        return [];
    }
}

function safeWrite(key: string | null, value: CartLine[]): void {
    if (key === null || typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Storage can be unavailable (private mode); the cart still works in memory.
    }
}

function safeRemove(key: string | null): void {
    if (key === null || typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.removeItem(key);
    } catch {
        // Nothing to do; the key simply remains unavailable.
    }
}

function readSessionValue(key: string): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeSessionValue(key: string, value: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(key, value);
    } catch {
        // Session storage is optional; the merge then only works in-memory.
    }
}

function clearSessionValue(key: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Session storage is optional.
    }
}

/**
 * Remember the guest cart key so a full page reload during login can still
 * merge the guest cart into the account that just signed in.
 */
function rememberGuestCart(): void {
    if (isGuestOwner(currentOwner) && currentStorageKey !== null) {
        writeSessionValue(LAST_GUEST_KEY, currentStorageKey);
    }
}

/**
 * Switch the active cart when authentication changes. Callers pass the
 * server-issued owner from the shared Inertia props; `null` means no owner is
 * available, in which case the in-memory cart is emptied.
 */
export function syncCartOwner(owner: string | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (initialized && owner === currentOwner) {
        return;
    }

    const previousOwner = currentOwner;
    const previousKey = currentStorageKey;
    const previousLines = lines.value;
    const nextKey = owner === null ? null : storageKeyFor(owner);
    const signingIn = owner !== null && !isGuestOwner(owner);
    const wasGuest = isGuestOwner(previousOwner);

    let nextLines: CartLine[];

    if (signingIn && (wasGuest || !initialized)) {
        // Guest -> account. The guest cart is read from memory during an
        // Inertia visit and from the key parked in session storage after a
        // full page load. Exactly one of them is used, so quantities are not
        // merged twice.
        const pendingGuestKey = wasGuest
            ? previousKey
            : readSessionValue(LAST_GUEST_KEY);
        const guestLines = wasGuest ? previousLines : safeRead(pendingGuestKey);

        nextLines = mergeLines(safeRead(nextKey), guestLines);

        safeWrite(nextKey, nextLines);

        if (pendingGuestKey !== null && pendingGuestKey !== nextKey) {
            safeRemove(pendingGuestKey);
        }

        clearSessionValue(LAST_GUEST_KEY);
    } else if (
        isGuestOwner(previousOwner) &&
        isGuestOwner(owner) &&
        previousKey !== nextKey
    ) {
        // The guest session rotated (e.g. the session cookie was renewed). A
        // guest cart belongs to this browser, not to another person, so move
        // it to the new session namespace rather than silently losing it.
        nextLines = mergeLines(safeRead(nextKey), previousLines);

        safeWrite(nextKey, nextLines);
        safeRemove(previousKey);
        clearSessionValue(LAST_GUEST_KEY);
    } else {
        // Login page, sign out, or account switch: load only the new owner's
        // own namespace. A signed-in account's stored cart is left in place
        // but is unreachable from any other owner, so neither the next
        // visitor nor the next account can inherit it.
        nextLines = safeRead(nextKey);
    }

    lines.value = nextLines;
    currentOwner = owner;
    currentStorageKey = nextKey;
    initialized = true;
    rememberGuestCart();
}

if (typeof window !== 'undefined') {
    // A cart with no owner cannot be safely attributed to the current visitor.
    safeRemove(LEGACY_STORAGE_KEY);

    watch(
        lines,
        (value) => {
            if (!initialized || currentStorageKey === null) {
                return;
            }

            safeWrite(currentStorageKey, value);
            rememberGuestCart();
        },
        { deep: true },
    );
}

function lineKey(line: CartLine): string {
    const optionKey = (line.selectedOptions ?? [])
        .map(
            (group) =>
                `${group.groupId}:${group.options.map((option) => option.id).join(',')}`,
        )
        .join('|');

    return `${line.menuItemId}:${optionKey}`;
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
