/**
 * Shapes returned by the v1 API resources. Money is emitted as decimal
 * strings, so it is typed as string rather than number.
 */

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        path: string;
        to: number | null;
        total: number;
    };
};

export type RestaurantSettings = {
    slot_minutes: number;
    auto_confirm_reservations: boolean;
    delivery_fee: string;
    min_delivery_amount: string;
    tax_rate: string;
};

export type Restaurant = {
    id: number;
    name: string;
    slug: string;
    phone: string | null;
    address: string | null;
    opening_hours: Record<string, { open?: string; close?: string } | null> | null;
    is_active: boolean;
    settings: RestaurantSettings;
    created_at?: string | null;
};

export type Category = {
    id: number;
    name: string;
    sort_order: number;
    menu_items_count?: number;
};

export type MenuItem = {
    id: number;
    restaurant_id: number;
    category_id: number;
    category?: { id: number; name: string };
    name: string;
    description: string | null;
    price: string;
    image_url: string | null;
    is_available: boolean;
    is_featured: boolean;
};

export type OrderType = 'PICKUP' | 'DELIVERY';

export type OrderStatus =
    | 'PLACED'
    | 'CONFIRMED'
    | 'PREPARING'
    | 'READY'
    | 'OUT_FOR_DELIVERY'
    | 'COMPLETED'
    | 'CANCELLED'
    | 'REJECTED'
    | 'DELIVERY_FAILED'
    | 'REFUNDED';

export type PaymentStatus = 'UNPAID' | 'PAID' | 'FAILED' | 'REFUNDED';

export type ReservationStatus =
    | 'PENDING'
    | 'CONFIRMED'
    | 'SEATED'
    | 'COMPLETED'
    | 'CANCELLED'
    | 'NO_SHOW'
    | 'EXPIRED';

export type OrderItem = {
    id: number;
    menu_item_id: number;
    name: string;
    unit_price: string;
    quantity: number;
    line_total: string;
    note: string | null;
};

export type OrderPayment = {
    id: number;
    order_id: number;
    method: 'CASH' | 'CARD' | 'ONLINE';
    amount: string;
    status: PaymentStatus;
    provider_ref: string | null;
    paid_at: string | null;
    created_at: string | null;
};

export type OrderStatusHistory = {
    id: number;
    from_status: string | null;
    to_status: string;
    changed_by: { id: number; name: string } | null;
    changed_at: string | null;
    reason: string | null;
};

export type DeliveryDetail = {
    id: number;
    order_id: number;
    driver_id: number | null;
    driver?: { id: number; name: string } | null;
    recipient_name: string;
    recipient_phone: string;
    address_line: string;
    latitude: string | null;
    longitude: string | null;
    delivery_status: 'UNASSIGNED' | 'ASSIGNED' | 'PICKED_UP' | 'DELIVERED' | 'FAILED';
    assigned_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    failure_reason: string | null;
};

export type Order = {
    id: number;
    order_number: string;
    order_type: OrderType;
    status: OrderStatus;
    payment_status: PaymentStatus;
    restaurant?: { id: number; name: string; slug: string };
    customer?: { id: number; name: string; email: string } | null;
    reservation?: {
        id: number;
        status: ReservationStatus;
        start_time: string;
        end_time: string;
        party_size: number;
    } | null;
    items?: OrderItem[];
    delivery?: DeliveryDetail | null;
    payments?: OrderPayment[];
    status_history?: OrderStatusHistory[];
    totals: {
        subtotal: string;
        delivery_fee: string;
        tax: string;
        discount: string;
        total: string;
    };
    scheduled_for: string | null;
    customer_note: string | null;
    created_at: string;
    updated_at: string;
};

export type DiningTable = {
    id: number;
    label: string;
    capacity: number;
    min_capacity: number;
    shape: 'ROUND' | 'SQUARE' | 'RECT';
    pos_x: number;
    pos_y: number;
    status?: 'AVAILABLE' | 'RESERVED' | 'OCCUPIED' | 'UNAVAILABLE';
    fits_party?: boolean;
    is_active?: boolean;
};

export type Availability = {
    data: {
        restaurant: { id: number; slug: string; slot_minutes: number };
        start_time: string;
        end_time: string;
        tables: DiningTable[];
    };
    meta: { available_count: number };
};

export type Reservation = {
    id: number;
    status: ReservationStatus;
    party_size: number;
    start_time: string;
    end_time: string;
    hold_expires_at: string | null;
    special_request: string | null;
    restaurant?: { id: number; name: string; slug: string };
    table?: { id: number; label: string; capacity: number; shape: string; pos_x: number; pos_y: number };
    customer?: { id: number; name: string; email: string; no_show_count: number } | null;
    created_at: string;
};

export type Kpis = {
    range: { from: string; to: string };
    revenue: number;
    revenue_today: number;
    orders_today: number;
    order_count: number;
    completed_order_count: number;
    average_order_value: number;
    pending_orders: number;
    pickup_orders: number;
    delivery_orders: number;
    reservation_count: number;
    completed_reservations: number;
    no_show_reservations: number;
    no_show_rate: number;
    active_deliveries: number;
    revenue_series: { date: string; revenue: number }[];
    order_series: { date: string; count: number }[];
    recent_orders: {
        id: number;
        order_number: string;
        order_type: OrderType;
        status: OrderStatus;
        payment_status: PaymentStatus;
        total: string;
        created_at: string;
    }[];
};

export type RestaurantMember = {
    id: number;
    user_id: number;
    user?: { id: number; name: string; email: string };
    role: 'STAFF' | 'ADMIN';
    staff_type: 'CASHIER' | 'KITCHEN' | 'WAITER' | 'DRIVER' | null;
    created_at: string | null;
};

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: 'CUSTOMER' | 'STAFF' | 'ADMIN' | 'SUPER_ADMIN';
    memberships?: {
        restaurant_id: number;
        restaurant_name: string | null;
        role: 'STAFF' | 'ADMIN';
        staff_type: string | null;
    }[];
};

export type CartLine = {
    menuItemId: number;
    name: string;
    price: string;
    quantity: number;
    note: string;
    imageUrl: string | null;
};
