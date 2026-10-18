<script setup lang="ts">
import { computed } from 'vue';
import { FLOOR_PLAN_HEIGHT, FLOOR_PLAN_WIDTH } from '@/types/restaurant';
import type { DiningTable, TableStatus } from '@/types/restaurant';

const props = defineProps<{
    tables: DiningTable[];
    selectedId: number | null;
}>();

const emit = defineEmits<{
    (e: 'select', table: DiningTable): void;
}>();

/**
 * Colour lives in `.reserve-page` (resources/css/mori.css) so the floor plan,
 * the legend and the Reserve button all read from one palette. This component
 * only names a state; it never invents a colour.
 */
const classes: Record<TableStatus, string> = {
    available: 'is-available',
    taken: 'is-taken',
    seated: 'is-seated',
    unavailable: 'is-unavailable',
};

/**
 * Worst-case clearance kept between a table and anything else on the plan.
 * Table sizes are stored data, so the canvas grows to fit them rather than
 * tables being nudged around: this is the breathing room every table gets.
 */
const TABLE_GUTTER = 20;

const viewBoxWidth = computed(
    () =>
        props.tables.reduce(
            (widest, table) =>
                Math.max(widest, table.pos_x + table.width + TABLE_GUTTER),
            FLOOR_PLAN_WIDTH,
        ) + TABLE_GUTTER,
);

const viewBoxHeight = computed(
    () =>
        props.tables.reduce(
            (tallest, table) =>
                Math.max(tallest, table.pos_y + table.height + TABLE_GUTTER),
            FLOOR_PLAN_HEIGHT,
        ) + TABLE_GUTTER,
);

function statusOf(table: DiningTable): TableStatus {
    return table.status ?? 'available';
}

const isSelectable = (table: DiningTable): boolean =>
    statusOf(table) === 'available';

const isSelected = (table: DiningTable): boolean =>
    props.selectedId === table.id;

/**
 * A selected table keeps the selected styling even while the pointer is on it,
 * so hover can never make the current choice look like some other state.
 */
function stateClass(table: DiningTable): string {
    if (isSelected(table)) {
        return 'is-selected';
    }

    return classes[statusOf(table)];
}

function stateLabelFor(table: DiningTable): string {
    switch (statusOf(table)) {
        case 'seated':
            return 'occupied';
        case 'taken':
            return 'already booked';
        default:
            return 'too small for your party';
    }
}

/**
 * Only free tables that fit the party can be picked. Taken, seated and
 * too-small tables stay plain labels — no pointer, no tab stop.
 */
function activate(table: DiningTable): void {
    if (isSelectable(table)) {
        emit('select', table);
    }
}

function onKeydown(event: KeyboardEvent, table: DiningTable): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        activate(table);
    }
}
</script>

<template>
    <svg
        :viewBox="`0 0 ${viewBoxWidth} ${viewBoxHeight}`"
        class="floor-plan mt-4"
        role="group"
        aria-label="Restaurant floor plan"
        preserveAspectRatio="xMidYMid meet"
    >
        <!-- Decorative areas: they describe the room, never something to pick. -->
        <g aria-hidden="true">
            <rect
                x="620"
                y="436"
                width="160"
                height="64"
                rx="6"
                class="floor-plan-room"
            />
            <text x="700" y="468" class="floor-plan-room-label">ENTRANCE</text>

            <!-- The canvas is padded rather than the tables moved, so the room
                 labels get their clearance without touching stored positions. -->
            <rect
                x="620"
                y="14"
                width="160"
                height="44"
                rx="6"
                class="floor-plan-room"
            />
            <text x="700" y="36" class="floor-plan-room-label">KITCHEN</text>
        </g>

        <g
            v-for="table in tables"
            :key="table.id"
            :role="isSelectable(table) ? 'button' : undefined"
            :tabindex="isSelectable(table) ? 0 : undefined"
            :aria-label="
                isSelectable(table)
                    ? `Table ${table.label}, seats ${table.capacity}, available. Press Enter to select.`
                    : `Table ${table.label}, seats ${table.capacity}, ${stateLabelFor(table)}`
            "
            :aria-pressed="isSelectable(table) ? isSelected(table) : undefined"
            class="floor-plan-table"
            :class="[
                stateClass(table),
                isSelectable(table) ? 'is-selectable' : 'is-static',
            ]"
            @click="activate(table)"
            @keydown="onKeydown($event, table)"
        >
            <title>
                Table {{ table.label }} · seats {{ table.capacity }} ·
                {{ isSelected(table) ? 'selected' : stateLabelFor(table) }}
            </title>

            <rect
                :x="table.pos_x"
                :y="table.pos_y"
                :width="table.width"
                :height="table.height"
                rx="8"
            />

            <text
                :x="table.pos_x + table.width / 2"
                :y="table.pos_y + table.height / 2 - 10"
                class="floor-plan-label is-number"
                aria-hidden="true"
            >
                {{ table.label }}
            </text>

            <text
                :x="table.pos_x + table.width / 2"
                :y="table.pos_y + table.height / 2 + 14"
                class="floor-plan-label is-seats"
                aria-hidden="true"
            >
                {{ table.capacity }} seats
            </text>
        </g>
    </svg>
</template>
