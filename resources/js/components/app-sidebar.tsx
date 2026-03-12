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
    useSidebar,
} from '@/components/ui/sidebar';
import { generatedMainNavItems } from '@/menu/generated';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    CalendarDays,
    ChevronDown,
    Coins,
    FileBarChart,
    FileText,
    Folder,
    Handshake,
    History,
    Landmark,
    LayoutGrid,
    ListChecks,
    Shield,
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
            : title === 'Rubros'
              ? 'text-fuchsia-600 dark:text-fuchsia-400'
              : title === 'Cesionarios'
                ? 'text-amber-600 dark:text-amber-400'
                : title === 'Tipos de concesionario'
                  ? 'text-amber-600 dark:text-amber-400'
                  : title === 'Tipos de documento'
                    ? 'text-cyan-600 dark:text-cyan-400'
                    : title === 'Tipos de contrato'
                      ? 'text-teal-600 dark:text-teal-400'
                      : title === 'Estados de contrato'
                        ? 'text-rose-600 dark:text-rose-400'
                        : title === 'Contratos'
                          ? 'text-teal-600 dark:text-teal-400'
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
                                    : title === 'Pagos'
                                      ? 'text-emerald-600 dark:text-emerald-400'
                                      : title === 'Cargos'
                                        ? 'text-violet-600 dark:text-violet-400'
                                        : title === 'Locales'
                                          ? 'text-green-600 dark:text-green-400'
                                          : title === 'Ubicaciones de local'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : title === 'Mercados'
                                              ? 'text-orange-600 dark:text-orange-400'
                                              : undefined;
}

function useNavGroups(): {
    core: NavItem[];
    main: NavItem[];
    operation: NavItem[];
    reports: NavItem[];
    tools: NavItem[];
    config: NavItem[];
    catalogs: NavItem[];
} {
    const page = usePage<{ auth?: { can?: Record<string, boolean>; portalAvailable?: boolean } }>();
    const can = page.props.auth?.can || {};
    const portalAvailable = !!page.props.auth?.portalAvailable;

    const core: NavItem[] = [];
    if (can['dashboard.view']) core.push({ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid });
    if (portalAvailable) core.push({ title: 'Portal de Servicios', url: '/portal', icon: UserSquare2 });

    // Main entities (promoted from catalogs)
    const main: NavItem[] = [];
    const allCatalogs = generatedMainNavItems(can);
    const mainTitles = ['Mercados', 'Locales', 'Cesionarios', 'Contratos'];
    mainTitles.forEach((title) => {
        const item = allCatalogs.find((it) => it.title === title);
        if (item) main.push(item);
    });

    // Operation (Cargos + Pagos + Períodos)
    const operation: NavItem[] = [];
    if (can['charges.view']) operation.push({ title: 'Cargos', url: '/charges', icon: ListChecks });
    const pagosItem = allCatalogs.find((it) => it.title === 'Pagos');
    if (pagosItem) operation.push(pagosItem);
    if (can['condo_period.view']) operation.push({ title: 'Períodos', url: '/condo/periods', icon: CalendarDays });

    // Reports
    const reports: NavItem[] = [];
    if (can['reports.bank_validations.view']) {
        reports.push({ title: 'Validaciones Bancarias', url: '/reports/bank-validations', icon: FileBarChart });
    }
    if (can['reports.daily_bank_reconciliation.view']) {
        reports.push({ title: 'Conciliación diaria por banco', url: '/reports/daily-bank-reconciliation', icon: Landmark });
    }
    if (can['reports.contracts_unsigned.view']) {
        reports.push({ title: 'Contratos sin firma', url: '/reports/contracts-unsigned', icon: FileText });
    }
    if (can['reports.concessionaire_changes.view']) {
        reports.push({ title: 'Cambios de cesionario', url: '/reports/concessionaire-changes', icon: Handshake });
    }
    if (can['reports.locals_recovered.view']) {
        reports.push({ title: 'Locales recuperados', url: '/reports/locals-recovered', icon: Building2 });
    }
    if (can['reports.locals_financial_status.view']) {
        reports.push({ title: 'Estado financiero de locales', url: '/reports/locals-financial-status', icon: Coins });
    }

    // Tools (analysis & queries)
    const tools: NavItem[] = [];
    if (can['dashboard.view.finance']) tools.push({ title: 'Análisis de Deudas', url: '/dashboard/debt-analysis', icon: FileBarChart });
    if (can['admin.economic_profile.view']) tools.push({ title: 'Perfil Económico', url: '/admin/economic-profile', icon: Coins });

    // Configuration (security & admin)
    const config: NavItem[] = [];
    if (can['users.view']) config.push({ title: 'Usuarios', url: '/users', icon: Users2 });
    if (can['roles.view']) config.push({ title: 'Roles', url: '/roles', icon: Shield });
    if (can['auditoria.view']) config.push({ title: 'Auditoría', url: '/auditoria', icon: History });

    // Remaining catalogs (exclude main entities and Pagos)
    const excludeTitles = [...mainTitles, 'Pagos'];
    const catalogs = allCatalogs.filter((it) => !excludeTitles.includes(it.title));

    return { core, main, operation, reports, tools, config, catalogs };
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
    const { core, main, operation, reports, tools, config, catalogs } = useNavGroups();
    const { state, setOpen } = useSidebar();
    // Simplified catalog grouping
    const catalogGroupConfigs: Array<{ key: string; title: string; titles: string[] }> = [
        { key: 'infraestructura', title: 'Infraestructura', titles: ['Tipos de local', 'Estados de local', 'Ubicaciones de local'] },
        { key: 'personas', title: 'Personas', titles: ['Tipos de concesionario', 'Tipos de documento', 'Códigos de área'] },
        {
            key: 'finanzas',
            title: 'Finanzas',
            titles: [
                'Bancos',
                'Cuentas receptoras',
                'Tipos de pago',
                'Estados de pago',
                'Estados de cargo',
                'Motivos de traspaso de deuda',
                'Tipos de gasto',
                'Tasas de cambio',
            ],
        },
        { key: 'contratos', title: 'Contratos', titles: ['Tipos de contrato', 'Modalidades de contrato', 'Estados de contrato'] },
        { key: 'otros', title: 'Otros', titles: ['Rubros', 'Tarifas de mercado'] },
    ];
    const assigned = new Set<string>();
    const groupedCatalogs = catalogGroupConfigs
        .map((cfg) => ({
            key: cfg.key,
            title: cfg.title,
            items: catalogs.filter((it: NavItem) => {
                const match = cfg.titles.includes(it.title);
                if (match) assigned.add(it.title);
                return match;
            }),
        }))
        .filter((g) => g.items.length > 0);
    const remaining = catalogs.filter((it: NavItem) => !assigned.has(it.title));
    if (remaining.length > 0) {
        groupedCatalogs.push({ key: 'otros', title: 'Otros', items: remaining });
    }

    // Persist collapsible states
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

    const [openReports, setOpenReports] = React.useState(() => {
        const raw = typeof window !== 'undefined' ? window.localStorage.getItem('nav_group_open_reports') : null;
        return raw === null ? true : raw === 'true';
    });
    const saveOpenReports = (v: boolean) => {
        setOpenReports(v);
        if (typeof window !== 'undefined') window.localStorage.setItem('nav_group_open_reports', String(v));
    };

    const [openTools, setOpenTools] = React.useState(() => {
        const raw = typeof window !== 'undefined' ? window.localStorage.getItem('nav_group_open_tools') : null;
        return raw === null ? true : raw === 'true';
    });
    const saveOpenTools = (v: boolean) => {
        setOpenTools(v);
        if (typeof window !== 'undefined') window.localStorage.setItem('nav_group_open_tools', String(v));
    };

    const [openConfig, setOpenConfig] = React.useState(() => {
        const raw = typeof window !== 'undefined' ? window.localStorage.getItem('nav_group_open_config') : null;
        return raw === null ? false : raw === 'true'; // Closed by default
    });
    const saveOpenConfig = (v: boolean) => {
        setOpenConfig(v);
        if (typeof window !== 'undefined') window.localStorage.setItem('nav_group_open_config', String(v));
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
                                        <span data-sidebar-label>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>

                {/* Main Entities */}
                {main.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Gestión Principal</SidebarGroupLabel>
                        <SidebarMenu>
                            {main.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={item.url === currentUrl}>
                                        <Link href={item.url} prefetch>
                                            {item.icon && <Icon iconNode={item.icon} className={`h-5 w-5 ${iconColorClass(item.title) || ''}`} />}
                                            <span data-sidebar-label>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {/* Operations */}
                {operation.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Operaciones</SidebarGroupLabel>
                        <SidebarMenu>
                            {operation.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={item.url === currentUrl}>
                                        <Link href={item.url} prefetch>
                                            {item.icon && <Icon iconNode={item.icon} className={`h-5 w-5 ${iconColorClass(item.title) || ''}`} />}
                                            <span data-sidebar-label>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {/* Reports */}
                {reports.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Reportes</SidebarGroupLabel>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <Collapsible open={openReports} onOpenChange={saveOpenReports}>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            className="justify-between"
                                            tooltip={state === 'collapsed' ? 'Reportes' : undefined}
                                            onClick={(e) => {
                                                if (state === 'collapsed') {
                                                    e.preventDefault();
                                                    setOpen(true);
                                                }
                                            }}
                                        >
                                            <span className="flex items-center gap-2">
                                                <FileBarChart className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                                <span data-sidebar-label>Reportes</span>
                                            </span>
                                            <ChevronDown className="h-4 w-4 transition-transform data-[state=open]:rotate-180" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {reports.map((item) => (
                                                <SidebarMenuSubItem key={item.title}>
                                                    <SidebarMenuSubButton asChild isActive={item.url === currentUrl}>
                                                        <Link href={item.url} prefetch>
                                                            {item.icon && (
                                                                <Icon
                                                                    iconNode={item.icon}
                                                                    className={`h-4 w-4 ${iconColorClass(item.title) || ''}`}
                                                                />
                                                            )}
                                                            <span data-sidebar-label>{item.title}</span>
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

                {/* Tools */}
                {tools.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Herramientas</SidebarGroupLabel>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <Collapsible open={openTools} onOpenChange={saveOpenTools}>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            className="justify-between"
                                            tooltip={state === 'collapsed' ? 'Herramientas' : undefined}
                                            onClick={(e) => {
                                                if (state === 'collapsed') {
                                                    e.preventDefault();
                                                    setOpen(true);
                                                }
                                            }}
                                        >
                                            <span className="flex items-center gap-2">
                                                <Coins className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                                <span data-sidebar-label>Herramientas</span>
                                            </span>
                                            <ChevronDown className="h-4 w-4 transition-transform data-[state=open]:rotate-180" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {tools.map((item) => (
                                                <SidebarMenuSubItem key={item.title}>
                                                    <SidebarMenuSubButton asChild isActive={item.url === currentUrl}>
                                                        <Link href={item.url} prefetch>
                                                            {item.icon && (
                                                                <Icon
                                                                    iconNode={item.icon}
                                                                    className={`h-4 w-4 ${iconColorClass(item.title) || ''}`}
                                                                />
                                                            )}
                                                            <span data-sidebar-label>{item.title}</span>
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

                {/* Configuration */}
                {config.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Configuración</SidebarGroupLabel>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <Collapsible open={openConfig} onOpenChange={saveOpenConfig}>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            className="justify-between"
                                            tooltip={state === 'collapsed' ? 'Configuración' : undefined}
                                            onClick={(e) => {
                                                if (state === 'collapsed') {
                                                    e.preventDefault();
                                                    setOpen(true);
                                                }
                                            }}
                                        >
                                            <span className="flex items-center gap-2">
                                                <Shield className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                                <span data-sidebar-label>Configuración</span>
                                            </span>
                                            <ChevronDown className="h-4 w-4 transition-transform data-[state=open]:rotate-180" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {config.map((item) => (
                                                <SidebarMenuSubItem key={item.title}>
                                                    <SidebarMenuSubButton asChild isActive={item.url === currentUrl}>
                                                        <Link href={item.url} prefetch>
                                                            {item.icon && (
                                                                <Icon
                                                                    iconNode={item.icon}
                                                                    className={`h-4 w-4 ${iconColorClass(item.title) || ''}`}
                                                                />
                                                            )}
                                                            <span data-sidebar-label>{item.title}</span>
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
                                    group.key === 'infraestructura'
                                        ? { icon: Building2, cn: 'text-emerald-600 dark:text-emerald-400' }
                                        : group.key === 'personas'
                                          ? { icon: UserSquare2, cn: 'text-amber-600 dark:text-amber-400' }
                                          : group.key === 'finanzas'
                                            ? { icon: Landmark, cn: 'text-blue-600 dark:text-blue-400' }
                                            : group.key === 'contratos'
                                              ? { icon: Handshake, cn: 'text-teal-600 dark:text-teal-400' }
                                              : { icon: Folder, cn: 'text-slate-600 dark:text-slate-400' };
                                return (
                                    <SidebarMenuItem key={`group-${group.key}`}>
                                        <Collapsible open={!!openGroups[group.key]} onOpenChange={(v) => setGroupOpen(group.key, v)}>
                                            <CollapsibleTrigger asChild>
                                                <SidebarMenuButton
                                                    className="justify-between"
                                                    tooltip={state === 'collapsed' ? group.title : undefined}
                                                    onClick={(e) => {
                                                        if (state === 'collapsed') {
                                                            e.preventDefault();
                                                            setOpen(true);
                                                        }
                                                    }}
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <Icon iconNode={iconProps.icon} className={`h-4 w-4 ${iconProps.cn}`} />
                                                        <span data-sidebar-label>{group.title}</span>
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
                                                                    <span data-sidebar-label>{item.title}</span>
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
