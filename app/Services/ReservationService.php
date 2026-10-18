<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\ApiException;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\OpeningHours;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    private const MIN_LEAD_HOURS = 1;

    private const MAX_LEAD_DAYS = 30;

    private const HOLD_MINUTES = 15;

    /**
     * Return the visual table map for a slot: every active table with the
     * status the frontend should paint it in.
     *
     * A slot is always the restaurant's `slot_minutes` long (90 by default). A
     * table is "taken" when a PENDING or CONFIRMED reservation overlaps the
     * window and "seated" when a SEATED one does; overlap is the usual
     * half-open test, so a booking ending exactly as the next begins does not
     * block it. A table too small for the party is never selectable.
     *
     * @return Collection<int, array{
     *     id: int,
     *     label: string,
     *     capacity: int,
     *     min_capacity: int,
     *     shape: 'ROUND'|'SQUARE'|'RECT',
     *     pos_x: int,
     *     pos_y: int,
     *     width: int,
     *     height: int,
     *     status: 'available'|'taken'|'seated'|'unavailable',
     *     fits_party: bool
     * }>
     */
    public function availability(Restaurant $restaurant, CarbonInterface $start, int $partySize): Collection
    {
        $end = $start->copy()->addMinutes($restaurant->slot_minutes);

        // Natural order so table "10" follows "9" rather than "1" — the floor
        // plan and the table list are read together.
        $tables = $restaurant->diningTables()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (DiningTable $table): string => $table->label, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $bookings = Reservation::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('status', ReservationStatus::capacityHolding())
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->get()
            ->groupBy('table_id');

        return $tables->map(function (DiningTable $table) use ($bookings, $partySize): array {
            /** @var Collection<int, Reservation> $tableBookings */
            $tableBookings = $bookings->get($table->id, collect());

            $seated = $tableBookings->contains(
                fn (Reservation $reservation): bool => $reservation->status === ReservationStatus::Seated
            );

            $status = match (true) {
                $seated => 'seated',
                $tableBookings->isNotEmpty() => 'taken',
                ! $table->canHost($partySize) => 'unavailable',
                default => 'available',
            };

            return [
                'id' => $table->id,
                'label' => $table->label,
                'capacity' => $table->capacity,
                'min_capacity' => $table->min_capacity,
                'shape' => $table->shape->value,
                'pos_x' => $table->pos_x,
                'pos_y' => $table->pos_y,
                'width' => $table->width,
                'height' => $table->height,
                'status' => $status,
                'fits_party' => $table->canHost($partySize),
            ];
        });
    }

    /**
     * Book a table. The slot check and insert run in one transaction holding a
     * row lock on the dining table so concurrent requests cannot double-book.
     */
    public function create(User $customer, Restaurant $restaurant, int $tableId, CarbonInterface $start, int $partySize, ?string $specialRequest): Reservation
    {
        $this->assertOrderingRules($restaurant, $start, $partySize);

        return DB::transaction(function () use ($customer, $restaurant, $tableId, $start, $partySize, $specialRequest): Reservation {
            /** @var DiningTable $table */
            $table = $restaurant->diningTables()
                ->whereKey($tableId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $table->is_active) {
                throw ApiException::invalid('That table is not available for booking.', ['table_id' => ['Table is inactive.']]);
            }

            if (! $table->canHost($partySize)) {
                throw ApiException::invalid(
                    'The party size does not fit this table.',
                    ['party_size' => ["This table seats between {$table->min_capacity} and {$table->capacity} guests."]],
                );
            }

            $end = $start->copy()->addMinutes($restaurant->slot_minutes);

            $conflict = Reservation::query()
                ->where('table_id', $table->id)
                ->whereIn('status', ReservationStatus::capacityHolding())
                ->where('start_time', '<', $end)
                ->where('end_time', '>', $start)
                ->exists();

            if ($conflict) {
                throw ApiException::conflict('That table was just booked for this slot.', ['table_id' => ['Please choose another table or time.']]);
            }

            $autoConfirm = $restaurant->auto_confirm_reservations;

            $reservation = Reservation::query()->create([
                'restaurant_id' => $restaurant->id,
                'customer_id' => $customer->id,
                'table_id' => $table->id,
                'party_size' => $partySize,
                'start_time' => $start,
                'end_time' => $end,
                'status' => $autoConfirm ? ReservationStatus::Confirmed : ReservationStatus::Pending,
                'special_request' => $specialRequest,
                'hold_expires_at' => $autoConfirm
                    ? null
                    : now()->addMinutes(self::HOLD_MINUTES),
            ]);

            $reservation->statusHistory()->create([
                'from_status' => null,
                'to_status' => $reservation->status->value,
                'changed_by' => $customer->id,
                'changed_at' => now(),
            ]);

            return $reservation->load(['table', 'restaurant', 'statusHistory']);
        });
    }

    /**
     * Move a reservation through its allowed lifecycle and record the historical status change.
     */
    public function transition(Reservation $reservation, ReservationStatus $to, ?User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $to, $actor): Reservation {
            $reservation->refresh();

            if (! $reservation->status->canTransitionTo($to)) {
                throw ApiException::invalid(
                    "Cannot move reservation from {$reservation->status->value} to {$to->value}.",
                    ['status' => ['This status transition is not allowed.']],
                );
            }

            $from = $reservation->status;

            $reservation->status = $to;

            if ($to !== ReservationStatus::Pending) {
                $reservation->hold_expires_at = null;
            }

            $reservation->save();

            if ($to === ReservationStatus::NoShow) {
                $reservation->customer()->increment('no_show_count');
            }

            $reservation->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $actor?->id,
                'changed_at' => now(),
            ]);

            return $reservation->load(['table', 'restaurant', 'customer', 'statusHistory']);
        });
    }

    /**
     * Validate the restaurant, time window, party size, and availability constraints before booking.
     */
    private function assertOrderingRules(Restaurant $restaurant, CarbonInterface $start, int $partySize): void
    {
        if (! $restaurant->is_active) {
            throw ApiException::invalid('This restaurant is not accepting reservations.');
        }

        if ($start->lessThan(now()->addHours(self::MIN_LEAD_HOURS))) {
            throw ApiException::invalid('Reservations must be made at least one hour in advance.', [
                'start_time' => ['Choose a time at least one hour from now.'],
            ]);
        }

        if ($start->greaterThan(now()->addDays(self::MAX_LEAD_DAYS))) {
            throw ApiException::invalid('Reservations can be made at most 30 days in advance.', [
                'start_time' => ['Choose a time within the next 30 days.'],
            ]);
        }

        if ($partySize < 1) {
            throw ApiException::invalid('Party size must be at least one.', ['party_size' => ['Invalid party size.']]);
        }

        if (! (new OpeningHours($restaurant->opening_hours))->applies($start)) {
            throw ApiException::invalid('The restaurant is closed at that time.', [
                'start_time' => ['Chosen time is outside opening hours.'],
            ]);
        }

        $this->expireHolds($restaurant);
    }

    /**
     * Release stale pending holds so they stop blocking a table after their grace period expires.
     */
    private function expireHolds(Restaurant $restaurant): void
    {
        Reservation::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('status', ReservationStatus::Pending)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->update([
                'status' => ReservationStatus::Expired->value,
                'hold_expires_at' => null,
            ]);
    }
}
