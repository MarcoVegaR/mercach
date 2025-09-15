import { Icon } from '@/components/icon';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Merca Chacao</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const iconClass =
                        item.title === 'Dashboard'
                            ? 'text-neutral-700 dark:text-neutral-300'
                            : item.title === 'Usuarios'
                              ? 'text-sky-600 dark:text-sky-400'
                              : item.title === 'Roles'
                                ? 'text-indigo-600 dark:text-indigo-400'
                                : item.title === 'Auditoría'
                                  ? 'text-orange-600 dark:text-orange-400'
                                  : item.title === 'Tipos de local'
                                    ? 'text-violet-600 dark:text-violet-400'
                                    : item.title === 'Estados de local'
                                      ? 'text-emerald-600 dark:text-emerald-400'
                                      : item.title === 'Rubros'
                                        ? 'text-fuchsia-600 dark:text-fuchsia-400'
                                        : item.title === 'Tipos de concesionario'
                                          ? 'text-amber-600 dark:text-amber-400'
                                          : item.title === 'Tipos de documento'
                                            ? 'text-cyan-600 dark:text-cyan-400'
                                            : item.title === 'Tipos de contrato'
                                              ? 'text-teal-600 dark:text-teal-400'
                                              : item.title === 'Estados de contrato'
                                                ? 'text-rose-600 dark:text-rose-400'
                                                : item.title === 'Tipos de gasto'
                                                  ? 'text-lime-600 dark:text-lime-400'
                                                  : item.title === 'Códigos de área'
                                                    ? 'text-purple-600 dark:text-purple-400'
                                                    : item.title === 'Tipos de pago'
                                                      ? 'text-sky-600 dark:text-sky-400'
                                                      : undefined;
                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={item.url === page.url}>
                                <Link href={item.url} prefetch>
                                    {item.icon && <Icon iconNode={item.icon} className={`h-5 w-5 ${iconClass || ''}`} />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
