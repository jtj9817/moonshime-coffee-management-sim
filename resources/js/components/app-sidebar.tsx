import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRightLeft,
    BarChart3,
    BookOpen,
    Folder,
    Layers,
    LayoutDashboard,
    Package,
    PieChart,
    ShoppingCart,
    Users,
} from 'lucide-react';

import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import game from '@/routes/game';
import { type NavItem } from '@/types';

import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Mission Control',
        href: game.dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Strategy Deck',
        href: game.strategy(),
        icon: Layers,
    },
    {
        title: 'Pantry',
        href: game.inventory(),
        icon: Package,
    },
    {
        title: 'Procurement',
        href: game.ordering(),
        icon: ShoppingCart,
    },
    {
        title: 'Logistics',
        href: game.transfers(),
        icon: ArrowRightLeft,
    },
    {
        title: 'Suppliers',
        href: game.vendors(),
        icon: Users,
    },
    {
        title: 'Analytics',
        href: game.analytics(),
        icon: BarChart3,
    },
    {
        title: 'War Room',
        href: game.spikeHistory(),
        icon: Activity,
    },
    {
        title: 'Wastage',
        href: game.reports(),
        icon: PieChart,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={game.dashboard.url()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
