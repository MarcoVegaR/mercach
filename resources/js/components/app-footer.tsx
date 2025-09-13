import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { Github } from 'lucide-react';
import * as React from 'react';

export type AppFooterProps = {
    className?: string;
    variant?: 'full' | 'minimal';
    container?: 'boxed' | 'fluid'; // default: boxed (max-w-7xl)
    showLanguage?: boolean; // render language control if i18n is present
    position?: 'static' | 'sticky' | 'fixed'; // footer positioning, default static
    respectSidebarGap?: boolean; // when used inside AppSidebarLayout, offset to avoid covering sidebar
};

export function AppFooter({
    className,
    variant = 'full',
    container = 'boxed',
    showLanguage = false,
    position = 'static',
    respectSidebarGap = false,
}: AppFooterProps) {
    const page = usePage<{ name?: string; requestId?: string; auth?: { can?: Record<string, boolean> } }>();
    const companyName = 'Caracoders Pro Services';
    const can = page.props.auth?.can ?? {};
    const requestId = page.props.requestId;
    const year = new Date().getFullYear();

    // Reserve space when footer is fixed so content is not overlapped
    const footerRef = React.useRef<HTMLElement | null>(null);
    const [spacerHeight, setSpacerHeight] = React.useState<number>(position === 'fixed' ? (variant === 'minimal' ? 80 : 320) : 0);

    React.useEffect(() => {
        if (position !== 'fixed') return;
        const measure = () => {
            if (footerRef.current) {
                const h = Math.ceil(footerRef.current.getBoundingClientRect().height);
                if (Number.isFinite(h) && h > 0) setSpacerHeight(h);
            }
        };
        measure();
        window.addEventListener('resize', measure);
        return () => window.removeEventListener('resize', measure);
    }, [position, variant]);

    const Container: React.FC<React.PropsWithChildren<{ boxed?: boolean }>> = ({ children, boxed = true }) => (
        <div className={cn('w-full', boxed && 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8')}>{children}</div>
    );

    const ProductLinks = () => (
        <nav aria-label="footer product">
            <ul className="space-y-2">
                {can['users.view'] && (
                    <li>
                        <Link
                            href="/users"
                            className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                        >
                            Usuarios
                        </Link>
                    </li>
                )}
                {can['roles.view'] && (
                    <li>
                        <Link
                            href="/roles"
                            className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                        >
                            Roles
                        </Link>
                    </li>
                )}
                {can['auditoria.view'] && (
                    <li>
                        <Link
                            href="/auditoria"
                            className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                        >
                            Auditoría
                        </Link>
                    </li>
                )}
            </ul>
        </nav>
    );

    const ResourceLinks = () => (
        <nav aria-label="footer resources">
            <ul className="space-y-2">
                <li>
                    <a
                        href="https://marcovegar.github.io/boilerplate-laravel12"
                        target="_blank"
                        rel="noopener"
                        className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Documentación
                    </a>
                </li>
                <li>
                    <a
                        href="https://github.com/MarcoVegaR/boilerplate-laravel12"
                        target="_blank"
                        rel="noopener"
                        className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Repositorio
                    </a>
                </li>
                <li>
                    <a
                        href="https://blog.supabase.com/"
                        target="_blank"
                        rel="noopener"
                        className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Blog
                    </a>
                </li>
            </ul>
        </nav>
    );

    const LegalLinks = () => (
        <nav aria-label="footer legal">
            <ul className="space-y-2">
                <li>
                    <a
                        href="https://marcovegar.github.io/boilerplate-laravel12/legal/terminos/"
                        target="_blank"
                        rel="noopener"
                        className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Términos
                    </a>
                </li>
                <li>
                    <a
                        href="https://marcovegar.github.io/boilerplate-laravel12/legal/privacidad/"
                        target="_blank"
                        rel="noopener"
                        className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Privacidad
                    </a>
                </li>
                <li>
                    <a
                        href="#"
                        className="text-muted-foreground hover:text-brand focus-visible:ring-brand rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    >
                        AUP
                    </a>
                </li>
            </ul>
        </nav>
    );

    const Social = () => (
        <div className="flex items-center gap-2" aria-label="social">
            <Button asChild variant="ghost" size="icon" aria-label="GitHub">
                <a href="https://github.com/MarcoVegaR/boilerplate-laravel12" target="_blank" rel="noopener">
                    <Github className="h-5 w-5" />
                    <span className="sr-only">GitHub</span>
                </a>
            </Button>
        </div>
    );

    const Language = () =>
        showLanguage ? (
            <div className="flex items-center gap-2">
                <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-brand">
                    ES
                </Button>
                <Separator orientation="vertical" className="h-4" />
                <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-brand">
                    EN
                </Button>
            </div>
        ) : null;

    // Base positioning classes; always span full width on small screens
    let positionClasses = position === 'fixed' ? 'fixed bottom-0 z-40 left-0 right-0' : position === 'sticky' ? 'sticky bottom-0 left-0 right-0' : '';
    // On large screens, if we need to respect the sidebar width, offset the footer from the left
    if (position === 'fixed' && respectSidebarGap) {
        positionClasses += ' lg:[left:var(--sidebar-width)]';
    }

    if (variant === 'minimal') {
        return (
            <>
                {position === 'fixed' && <div aria-hidden className="w-full" style={{ height: spacerHeight }} />}
                <footer
                    ref={footerRef}
                    role="contentinfo"
                    aria-label="site footer"
                    className={cn('bg-background dark:bg-background w-full border-t', positionClasses, className)}
                >
                    <Container boxed={container !== 'fluid'}>
                        <div className="text-muted-foreground flex flex-col items-start justify-between gap-3 py-6 pb-[env(safe-area-inset-bottom)] text-sm md:flex-row md:flex-wrap md:items-center">
                            <p>
                                © {year} {companyName}
                                {requestId ? <span className="ml-2 text-xs break-all"> Request ID: {requestId}</span> : null}
                            </p>
                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-3">
                                    <a
                                        href="https://marcovegar.github.io/boilerplate-laravel12/legal/terminos/"
                                        target="_blank"
                                        rel="noopener"
                                        className="hover:text-brand underline-offset-4 hover:underline"
                                    >
                                        Términos
                                    </a>
                                    <a
                                        href="https://marcovegar.github.io/boilerplate-laravel12/legal/privacidad/"
                                        target="_blank"
                                        rel="noopener"
                                        className="hover:text-brand underline-offset-4 hover:underline"
                                    >
                                        Privacidad
                                    </a>
                                </div>
                                <Separator orientation="vertical" className="hidden h-4 md:block" />
                                <Social />
                                <Language />
                            </div>
                        </div>
                    </Container>
                </footer>
            </>
        );
    }

    return (
        <>
            {position === 'fixed' && <div aria-hidden className="w-full" style={{ height: spacerHeight }} />}
            <footer
                ref={footerRef}
                role="contentinfo"
                aria-label="site footer"
                className={cn('bg-background dark:bg-background w-full border-t', positionClasses, className)}
            >
                <Container boxed={container !== 'fluid'}>
                    <div className="text-muted-foreground grid grid-cols-1 gap-8 py-10 pb-[env(safe-area-inset-bottom)] text-sm sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6">
                        <div className="col-span-1 lg:col-span-2">
                            <div className="flex items-start gap-3">
                                <AppLogo />
                            </div>
                            <p className="mt-2 max-w-md text-xs sm:text-sm">
                                Base moderna Laravel 12 + Inertia (React + TS). Estética Supabase, dark-first.
                            </p>
                        </div>

                        <div>
                            <h4 id="footer-product" className="text-foreground mb-3 text-sm font-medium">
                                Producto
                            </h4>
                            <ProductLinks />
                        </div>

                        <div>
                            <h4 id="footer-resources" className="text-foreground mb-3 text-sm font-medium">
                                Recursos
                            </h4>
                            <ResourceLinks />
                        </div>

                        <div>
                            <h4 id="footer-legal" className="text-foreground mb-3 text-sm font-medium">
                                Legal
                            </h4>
                            <LegalLinks />
                        </div>

                        <div>
                            <h4 id="footer-social" className="text-foreground mb-3 text-sm font-medium">
                                Social
                            </h4>
                            <Social />
                            <div className="mt-3">
                                <Language />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div className="text-muted-foreground flex flex-col items-start justify-between gap-3 py-6 text-sm md:flex-row md:flex-wrap md:items-center">
                        <p>
                            © {year} {companyName}
                            {requestId ? <span className="ml-2 text-xs break-all">Request ID: {requestId}</span> : null}
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href="https://marcovegar.github.io/boilerplate-laravel12/legal/terminos/"
                                target="_blank"
                                rel="noopener"
                                className="hover:text-brand underline-offset-4 hover:underline"
                            >
                                Términos
                            </a>
                            <a
                                href="https://marcovegar.github.io/boilerplate-laravel12/legal/privacidad/"
                                target="_blank"
                                rel="noopener"
                                className="hover:text-brand underline-offset-4 hover:underline"
                            >
                                Privacidad
                            </a>
                            <a href="#" className="hover:text-brand underline-offset-4 hover:underline">
                                AUP
                            </a>
                        </div>
                    </div>
                </Container>
            </footer>
        </>
    );
}
