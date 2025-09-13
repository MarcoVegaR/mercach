import { Icon } from '@/components/icon';
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';

export function NavFooter({
    items,
    className,
    ...props
}: React.ComponentPropsWithoutRef<typeof SidebarGroup> & {
    items: NavItem[];
}) {
    return (
        <SidebarGroup {...props} className={`group-data-[collapsible=icon]:p-0 ${className || ''}`}>
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) => {
                        const colorClass =
                            item.title === 'Repositorio'
                                ? 'text-amber-600 dark:text-amber-400'
                                : item.title === 'Documentación'
                                  ? 'text-primary'
                                  : undefined;
                        return (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                >
                                    <a href={item.url} target="_blank" rel="noopener noreferrer">
                                        {item.icon && <Icon iconNode={item.icon} className={`h-5 w-5 ${colorClass || ''}`} />}
                                        <span>{item.title}</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
