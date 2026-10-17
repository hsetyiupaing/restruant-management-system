/**
 * Money arrives from the API as a decimal string ("42.00"). It is formatted
 * for display only; totals are always recomputed by Laravel.
 */
const moneyFormatter = new Intl.NumberFormat('th-TH', {
    style: 'currency',
    currency: 'THB',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatMoney(value: string | number): string {
    const amount = typeof value === 'number' ? value : Number.parseFloat(value);

    return moneyFormatter.format(Number.isFinite(amount) ? amount : 0);
}

/**
 * Timestamps from this API are stored and emitted in UTC but represent the
 * restaurant's wall clock. They are formatted in UTC so "19:00" stays "19:00"
 * instead of shifting to the browser's timezone.
 */
const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'UTC',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'UTC',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

const timeFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'UTC',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return dateFormatter.format(new Date(value));
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return dateTimeFormatter.format(new Date(value));
}

export function formatTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return timeFormatter.format(new Date(value));
}

/**
 * Build the `YYYY-MM-DD` and `HH:mm` pair the availability endpoint expects.
 * The values are read from the UTC components so they match the stored slot.
 */
export function toSlotParts(value: string): { date: string; time: string } {
    const date = new Date(value);

    return {
        date: date.toISOString().slice(0, 10),
        time: date.toISOString().slice(11, 16),
    };
}

export function statusLabel(status: string): string {
    return status
        .toLowerCase()
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
