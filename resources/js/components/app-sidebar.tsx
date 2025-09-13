import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { generatedMainNavItems } from '@/menu/generated';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, History, LayoutGrid, Shield, Users2 } from 'lucide-react';
import AppLogo from './app-logo';

function useMainNavItems(): NavItem[] {
    const page = usePage<{ auth?: { can?: Record<string, boolean> } }>();
    const can = page.props.auth?.can || {};

    const items: NavItem[] = [{ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid }];

    // Conditionally show Users if permission exists
    if (can['users.view']) {
        items.push({ title: 'Usuarios', url: '/users', icon: Users2 });
    }

    // Conditionally show Roles and Auditoría only if user has view permission
    if (can['roles.view']) {
        items.push({ title: 'Roles', url: '/roles', icon: Shield });
    }
    if (can['auditoria.view']) {
        items.push({ title: 'Auditoría', url: '/auditoria', icon: History });
    }

    // Merge generated catalog items (idempotent, permission-aware)
    items.push(...generatedMainNavItems(can));

    return items;
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repositorio',
        url: 'https://github.com/MarcoVegaR/mercach',
        icon: Folder,
    },
    {
        title: 'Documentación',
        url: 'https://marcovegar.github.io/mercach',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const mainNavItems = useMainNavItems();
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
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
