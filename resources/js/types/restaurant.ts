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
    opening_hours: Record<
        string,
        { open?: string; close?: string } | null
    > | null;
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

export type MenuCourse = 'STARTER' | 'MAIN' | 'DESSERT';

export type DietaryTag = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
};

/**
 * The dietary classifications the menu may filter by. Allergy and
 * intolerance information lives in `MenuItem.allergens`, not here.
 */
export type DietarySlug = 'vegetarian' | 'vegan' | 'keto';

export type Allergen = {
    value: string;
    label: string;
};

export type MenuCustomizationMode = 'addons' | 'radio' | 'checkbox';

export type MenuCustomizationOption = {
    id: string;
    name: string;
    price: string | number;
};

export type MenuCustomizationGroup = {
    id: string;
    name: string;
    type: MenuCustomizationMode;
    options: MenuCustomizationOption[];
};

export type SelectedMenuOption = {
    id: string;
    name: string;
    price: string;
};

export type SelectedMenuCustomization = {
    groupId: string;
    groupName: string;
    options: SelectedMenuOption[];
};

/**
 * The only dietary filters the menu offers. Allergens and medical
 * restrictions are deliberately not filters; they appear on the dish page.
 */
export const dietaryFilters: { value: DietarySlug; label: string }[] = [
    { value: 'vegetarian', label: 'Vegetarian' },
    { value: 'vegan', label: 'Vegan' },
    { value: 'keto', label: 'Keto' },
];

export type MenuItem = {
    id: number;
    restaurant_id: number;
    category_id: number;
    category?: { id: number; name: string };
    name: string;
    slug: string | null;
    course: MenuCourse | null;
    description: string | null;
    /** Null when the kitchen has not recorded the ingredients. */
    ingredients: string | null;
    /**
     * Empty means none of the tracked groups appear in the recorded recipe;
     * null means the kitchen has not recorded allergens. Neither is a claim
     * that the dish is safe for a particular allergy.
     */
    allergens: Allergen[] | null;
    price: string;
    image_url: string | null;
    customization_groups?: MenuCustomizationGroup[];
    is_available: boolean;
    is_featured: boolean;
    dietary_tags?: DietaryTag[];
};

export const menuCourses: { value: MenuCourse; label: string }[] = [
    { value: 'STARTER', label: 'Starter' },
    { value: 'MAIN', label: 'Main' },
    { value: 'DESSERT', label: 'Dessert' },
];

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
    selected_customizations?: {
        group_id: string;
        group_name: string;
        type: MenuCustomizationMode;
        options: SelectedMenuOption[];
    }[];
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
    delivery_status:
        | 'UNASSIGNED'
        | 'ASSIGNED'
        | 'PICKED_UP'
        | 'DELIVERED'
        | 'FAILED';
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

/** The floor-plan canvas every table coordinate is expressed in. */
export const FLOOR_PLAN_WIDTH = 800;
export const FLOOR_PLAN_HEIGHT = 520;

/**
 * `available` can be selected; `taken` and `seated` are already booked;
 * `unavailable` is free but too small for the party.
 */
export type TableStatus = 'available' | 'taken' | 'seated' | 'unavailable';

export type DiningTable = {
    id: number;
    label: string;
    capacity: number;
    min_capacity: number;
    shape: 'ROUND' | 'SQUARE' | 'RECT';
    pos_x: number;
    pos_y: number;
    width: number;
    height: number;
    status?: TableStatus;
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
    table?: {
        id: number;
        label: string;
        capacity: number;
        shape: string;
        pos_x: number;
        pos_y: number;
    };
    customer?: {
        id: number;
        name: string;
        email: string;
        no_show_count: number;
    } | null;
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

/**
 * A saved allergy, dietary restriction or dietary preference. The three kinds
 * are never mixed in the UI: a peanut allergy is not a lifestyle choice.
 */
export type FoodRestrictionKind =
    | 'ALLERGY'
    | 'DIETARY_RESTRICTION'
    | 'DIETARY_PREFERENCE';

export type FoodRestrictionEntry = {
    restriction: string;
    kind: FoodRestrictionKind;
    label: string;
    custom_label: string | null;
    requires_custom_label: boolean;
    /** The allergen group this should be compared with, when there is one. */
    allergen: string | null;
    /** The menu dietary classification this maps to, when there is one. */
    dietary_tag: string | null;
};

export type FoodRestrictionOption = {
    value: string;
    label: string;
    requires_custom_label: boolean;
};

export type FoodRestrictionCatalog = {
    allergies: FoodRestrictionOption[];
    dietary_restrictions: FoodRestrictionOption[];
    dietary_preferences: FoodRestrictionOption[];
};

/** The wire shape the API and Fortify registration accept. */
export type FoodRestrictionInput = {
    restriction: string;
    custom_label: string | null;
};

export type UserRole = 'CUSTOMER' | 'STAFF' | 'ADMIN' | 'SUPER_ADMIN';
export type StaffType = 'CASHIER' | 'KITCHEN' | 'WAITER' | 'DRIVER';

/** A platform account as returned by the Super Admin user API. */
export type PlatformUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    email_verified: boolean;
    role: UserRole;
    staff_type: StaffType | null;
    no_show_count: number;
    created_at: string | null;
};

export const userRoles: { value: UserRole; label: string; hint: string }[] = [
    {
        value: 'CUSTOMER',
        label: 'Customer',
        hint: 'Storefront, own orders and dietary restrictions.',
    },
    {
        value: 'STAFF',
        label: 'Staff',
        hint: 'Operational tasks for a restaurant they belong to.',
    },
    {
        value: 'ADMIN',
        label: 'Administrator',
        hint: 'Manages a restaurant, its menu and its staff.',
    },
    {
        value: 'SUPER_ADMIN',
        label: 'Super Admin',
        hint: 'Full access, including platform accounts and roles.',
    },
];

export function userRoleLabel(role: UserRole): string {
    return userRoles.find((option) => option.value === role)?.label ?? role;
}

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role: UserRole;
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
    selectedOptions?: SelectedMenuCustomization[];
};
