import { Icon } from '@/components/icon';
import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { generatedMainNavItems } from '@/menu/generated';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    ChevronDown,
    Folder,
    Handshake,
    History,
    IdCard,
    Landmark,
    LayoutGrid,
    Shield,
    Tags,
    Users2,
    UserSquare2,
} from 'lucide-react';
import React from 'react';
import AppLogo from './app-logo';

function iconColorClass(title: string): string | undefined {
    return title === 'Dashboard'
        ? 'text-neutral-700 dark:text-neutral-300'
        : title === 'Usuarios'
          ? 'text-sky-600 dark:text-sky-400'
          : title === 'Roles'
            ? 'text-indigo-600 dark:text-indigo-400'
            : title === 'Auditoría'
              ? 'text-orange-600 dark:text-orange-400'
              : title === 'Tipos de local'
                ? 'text-violet-600 dark:text-violet-400'
                : title === 'Estados de local'
                  ? 'text-emerald-600 dark:text-emerald-400'
                  : title === 'Rubros'
                    ? 'text-fuchsia-600 dark:text-fuchsia-400'
                    : title === 'Tipos de concesionario'
                      ? 'text-amber-600 dark:text-amber-400'
                      : title === 'Tipos de documento'
                        ? 'text-cyan-600 dark:text-cyan-400'
                        : title === 'Tipos de contrato'
                          ? 'text-teal-600 dark:text-teal-400'
                          : title === 'Estados de contrato'
                            ? 'text-rose-600 dark:text-rose-400'
                            : title === 'Tipos de gasto'
                              ? 'text-lime-600 dark:text-lime-400'
                              : title === 'Códigos de área'
                                ? 'text-purple-600 dark:text-purple-400'
                                : title === 'Estados de pago'
                                  ? 'text-emerald-600 dark:text-emerald-400'
                                  : title === 'Bancos'
                                    ? 'text-blue-600 dark:text-blue-400'
                                    : title === 'Tipos de pago'
                                      ? 'text-sky-600 dark:text-sky-400'
                                      : undefined;
}

function useNavGroups(): { core: NavItem[]; admin: NavItem[]; catalogs: NavItem[] } {
    const page = usePage<{ auth?: { can?: Record<string, boolean> } }>();
    const can = page.props.auth?.can || {};

    const core: NavItem[] = [{ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid }];

    const admin: NavItem[] = [];
    if (can['users.view']) admin.push({ title: 'Usuarios', url: '/users', icon: Users2 });
    if (can['roles.view']) admin.push({ title: 'Roles', url: '/roles', icon: Shield });
    if (can['auditoria.view']) admin.push({ title: 'Auditoría', url: '/auditoria', icon: History });

    const catalogs: NavItem[] = generatedMainNavItems(can);

    return { core, admin, catalogs };
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
    const { url: currentUrl } = usePage();
    const { core, admin, catalogs } = useNavGroups();
    // Define catalog subgroups by titles (fallback 'Otros')
    const catalogGroupConfigs: Array<{ key: string; title: string; titles: string[] }> = [
        { key: 'locales', title: 'Locales', titles: ['Tipos de local', 'Estados de local'] },
        { key: 'concesionarios', title: 'Concesionarios', titles: ['Tipos de concesionario'] },
        { key: 'contratos', title: 'Contratos', titles: ['Tipos de contrato', 'Estados de contrato', 'Modalidades de contrato'] },
        { key: 'identificacion', title: 'Identificación', titles: ['Tipos de documento', 'Códigos de área'] },
        { key: 'finanzas', title: 'Finanzas', titles: ['Bancos', 'Tipos de pago', 'Estados de pago'] },
        { key: 'comercio', title: 'Comercio', titles: ['Rubros'] },
    ];
    const assigned = new Set<string>();
    const groupedCatalogs = catalogGroupConfigs
        .map((cfg) => ({
            key: cfg.key,
            title: cfg.title,
            items: catalogs.filter((it) => {
                const match = cfg.titles.includes(it.title);
                if (match) assigned.add(it.title);
                return match;
            }),
        }))
        .filter((g) => g.items.length > 0);
    const remaining = catalogs.filter((it) => !assigned.has(it.title));
    if (remaining.length > 0) {
        groupedCatalogs.push({ key: 'otros', title: 'Otros', items: remaining });
    }

    // Persist open state per subgroup in localStorage
    const [openGroups, setOpenGroups] = React.useState<Record<string, boolean>>(() => {
        const init: Record<string, boolean> = {};
        groupedCatalogs.forEach((g) => {
            const raw = typeof window !== 'undefined' ? window.localStorage.getItem(`nav_group_open_${g.key}`) : null;
            init[g.key] = raw === null ? true : raw === 'true';
        });
        return init;
    });
    const setGroupOpen = (key: string, value: boolean) => {
        setOpenGroups((prev) => ({ ...prev, [key]: value }));
        if (typeof window !== 'undefined') window.localStorage.setItem(`nav_group_open_${key}`, String(value));
    };
    // Persist open state for Administración
    const [openAdmin, setOpenAdmin] = React.useState<boolean>(() => {
        const raw = typeof window !== 'undefined' ? window.localStorage.getItem('nav_group_open_admin') : null;
        return raw === null ? true : raw === 'true';
    });
    const saveOpenAdmin = (v: boolean) => {
        setOpenAdmin(v);
        if (typeof window !== 'undefined') window.localStorage.setItem('nav_group_open_admin', String(v));
    };
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
                {/* Core */}
                <SidebarGroup className="px-2 py-0">
                    <SidebarGroupLabel>Inicio</SidebarGroupLabel>
                    <SidebarMenu>
                        {core.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton asChild isActive={item.url === currentUrl}>
                                    <Link href={item.url} prefetch>
                                        {item.icon && <Icon iconNode={item.icon} className={`h-5 w-5 ${iconColorClass(item.title) || ''}`} />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>

                {/* Administración (colapsable) */}
                {admin.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Administración</SidebarGroupLabel>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <Collapsible open={openAdmin} onOpenChange={(v) => saveOpenAdmin(v)}>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton className="justify-between">
                                            <span className="flex items-center gap-2">
                                                <Shield className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                                <span>Administración</span>
                                            </span>
                                            <ChevronDown className="h-4 w-4 transition-transform data-[state=open]:rotate-180" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {admin.map((item) => (
                                                <SidebarMenuSubItem key={`admin-${item.title}`}>
                                                    <SidebarMenuSubButton asChild isActive={item.url === currentUrl}>
                                                        <Link href={item.url} prefetch>
                                                            {item.icon && (
                                                                <Icon
                                                                    iconNode={item.icon}
                                                                    className={`h-4 w-4 ${iconColorClass(item.title) || ''}`}
                                                                />
                                                            )}
                                                            <span>{item.title}</span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </Collapsible>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {/* Catálogos (con subgrupos colapsables) */}
                {groupedCatalogs.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Catálogos</SidebarGroupLabel>
                        <SidebarMenu>
                            {groupedCatalogs.map((group) => {
                                const iconProps =
                                    group.key === 'locales'
                                        ? { icon: Building2, cn: 'text-violet-600 dark:text-violet-400' }
                                        : group.key === 'concesionarios'
                                          ? { icon: UserSquare2, cn: 'text-amber-600 dark:text-amber-400' }
                                          : group.key === 'contratos'
                                            ? { icon: Handshake, cn: 'text-teal-600 dark:text-teal-400' }
                                            : group.key === 'identificacion'
                                              ? { icon: IdCard, cn: 'text-cyan-600 dark:text-cyan-400' }
                                              : group.key === 'finanzas'
                                                ? { icon: Landmark, cn: 'text-blue-600 dark:text-blue-400' }
                                                : group.key === 'comercio'
                                                  ? { icon: Tags, cn: 'text-fuchsia-600 dark:text-fuchsia-400' }
                                                  : { icon: Folder, cn: 'text-slate-600 dark:text-slate-400' };
                                return (
                                    <SidebarMenuItem key={`group-${group.key}`}>
                                        <Collapsible open={!!openGroups[group.key]} onOpenChange={(v) => setGroupOpen(group.key, v)}>
                                            <CollapsibleTrigger asChild>
                                                <SidebarMenuButton className="justify-between">
                                                    <span className="flex items-center gap-2">
                                                        <Icon iconNode={iconProps.icon} className={`h-4 w-4 ${iconProps.cn}`} />
                                                        <span>{group.title}</span>
                                                    </span>
                                                    <ChevronDown className="h-4 w-4 transition-transform data-[state=open]:rotate-180" />
                                                </SidebarMenuButton>
                                            </CollapsibleTrigger>
                                            <CollapsibleContent>
                                                <SidebarMenuSub>
                                                    {group.items.map((item) => (
                                                        <SidebarMenuSubItem key={`item-${group.key}-${item.title}`}>
                                                            <SidebarMenuSubButton asChild isActive={item.url === currentUrl}>
                                                                <Link href={item.url} prefetch>
                                                                    {item.icon && (
                                                                        <Icon
                                                                            iconNode={item.icon}
                                                                            className={`h-4 w-4 ${iconColorClass(item.title) || ''}`}
                                                                        />
                                                                    )}
                                                                    <span>{item.title}</span>
                                                                </Link>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    ))}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        </Collapsible>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
