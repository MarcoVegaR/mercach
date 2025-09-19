import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export function Breadcrumbs({ breadcrumbs }: { breadcrumbs: BreadcrumbItemType[] }) {
    // Normalize breadcrumbs for Catalogs: prepend Home when missing and map invalid links
    const items: BreadcrumbItemType[] = (() => {
        if (!breadcrumbs || breadcrumbs.length === 0) return [];
        const first = breadcrumbs[0];
        const startsWithCatalogs = (first.title || '').toLowerCase() === 'catálogos' || first.href === '/catalogs';
        const withHome = startsWithCatalogs
            ? ([{ title: 'Inicio', href: '/dashboard' } as BreadcrumbItemType, ...breadcrumbs] as BreadcrumbItemType[])
            : breadcrumbs;
        // For the 'Catálogos' crumb, render as non-link to avoid redirecting to a specific module or carrying query params
        return withHome.map((it) => ({ ...it, href: it.href === '/catalogs' ? '' : it.href }));
    })();
    return (
        <>
            {items.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList>
                        {items.map((item, index) => {
                            const isLast = index === items.length - 1;
                            // Do not link the generic 'Catálogos' crumb
                            const safeHref = item.href === '/catalogs' ? '' : item.href;
                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {isLast || !safeHref ? (
                                            <BreadcrumbPage>{item.title}</BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={safeHref}>{item.title}</Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
