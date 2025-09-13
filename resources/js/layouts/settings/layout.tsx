import Heading from '@/components/heading';
import { Icon } from '@/components/icon';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Lock, Palette, Shield, User } from 'lucide-react';

const baseSidebarNavItems: NavItem[] = [
    { title: 'Perfil', url: '/settings/profile', icon: User },
    { title: 'Contraseña', url: '/settings/password', icon: Lock },
    { title: 'Apariencia', url: '/settings/appearance', icon: Palette },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<{ auth?: { can?: Record<string, boolean> } }>();
    const currentUrl = page.url || '';
    const can = page.props.auth?.can ?? {};

    const sidebarNavItems: NavItem[] = [
        ...baseSidebarNavItems,
        ...(can['settings.security.view'] ? [{ title: 'Seguridad', url: '/settings/security', icon: Shield } as NavItem] : []),
    ];

    return (
        <div className="px-4 py-6 lg:py-8">
            <Heading title="Ajustes" description="Administra tu perfil, seguridad y apariencia" />

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[240px_1fr]">
                <aside className="lg:sticky lg:top-24">
                    <Card className="overflow-hidden">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">Menú de ajustes</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <nav className="flex flex-col gap-1">
                                {sidebarNavItems.map((item) => {
                                    const isActive = currentUrl.startsWith(item.url);
                                    return (
                                        <Link
                                            key={item.url}
                                            href={item.url}
                                            prefetch
                                            aria-current={isActive ? 'page' : undefined}
                                            className={cn(
                                                'focus-visible:ring-primary/50 flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none',
                                                'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                                                isActive && 'bg-accent text-accent-foreground ring-primary/20 ring-1',
                                            )}
                                        >
                                            {item.icon && <Icon iconNode={item.icon} className={cn('h-4 w-4', isActive ? 'text-primary' : '')} />}
                                            <span className="truncate">{item.title}</span>
                                        </Link>
                                    );
                                })}
                            </nav>
                        </CardContent>
                    </Card>
                </aside>

                <div className="min-w-0">
                    <Separator className="my-2 lg:hidden" />
                    <section className="space-y-10">{children}</section>
                </div>
            </div>
        </div>
    );
}
