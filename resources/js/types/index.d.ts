import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

// Game State Types (matching Eloquent models and middleware sharing)
export interface GameStateShared {
    cash: number;
    xp: number;
    day: number;
    level: number;
    reputation: number;
    strikes: number;
}

export interface LocationModel {
    id: string;
    name: string;
    address: string;
    max_storage: number;
}

export interface ProductModel {
    id: string;
    name: string;
    category: string;
    is_perishable: boolean;
    storage_cost: number;
    vendors?: VendorModel[];
}

export interface VendorModel {
    id: string;
    name: string;
    reliability_score: number;
    metrics: Record<string, unknown> | null;
}

export interface AlertModel {
    id: string;
    type: string;
    message: string;
    severity: 'info' | 'warning' | 'critical';
    is_read: boolean;
    location_id?: string;
    product_id?: string;
    created_at: string;
}

export interface SpikeEventModel {
    id: string;
    type: string;
    name: string;
    description: string;
    is_active: boolean;
    meta: Record<string, unknown> | null;
    created_at: string;
}

export interface RouteModel {
    id: number;
    source_id: string;
    target_id: string;
    transport_mode: string;
    cost: number;
    transit_days: number;
    capacity: number;
    is_active: boolean;
    weather_vulnerability: boolean;
    source?: LocationModel;
    target?: LocationModel;
}

export interface GameShared {
    state: GameStateShared;
    locations: LocationModel[];
    products: ProductModel[];
    vendors: VendorModel[];
    alerts: AlertModel[];
    currentSpike: SpikeEventModel | null;
}

export interface SharedData {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    game: GameShared | null;
    [key: string]: unknown;
}

// Inventory Types (for page props)
export interface InventoryModel {
    id: string;
    location_id: string;
    product_id: string;
    quantity: number;
    last_restocked_at: string | null;
    location?: LocationModel;
    product?: ProductModel;
}

// Order Types (for page props)
export interface OrderModel {
    id: string;
    vendor_id: string;
    status: string;
    total_cost: number;
    delivery_date: string | null;
    delivery_day: number | null;
    created_at: string;
    vendor?: VendorModel;
    items?: OrderItemModel[];
}

export interface OrderItemModel {
    id: string;
    order_id: string;
    product_id: string;
    quantity: number;
    unit_price: number;
    product?: ProductModel;
}

// Transfer Types (for page props)
export interface TransferModel {
    id: string;
    source_location_id: string;
    target_location_id: string;
    status: string;
    delivery_day: number | null;
    created_at: string;
    sourceLocation?: LocationModel;
    targetLocation?: LocationModel;
}

// KPI Types (for dashboard)
export interface DashboardKPI {
    label: string;
    value: string | number;
    trend?: 'up' | 'down' | 'neutral';
    trendValue?: string;
}

// Quest Types (for dashboard)
export interface QuestModel {
    id: string;
    type: string;
    title: string;
    description: string;
    reward: {
        xp: number;
        cash?: number;
    };
    targetValue: number;
    currentValue: number;
    isCompleted: boolean;
}
