import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Ajustes', href: '/settings' },
    { title: 'Ajustes de apariencia', href: '/settings/appearance' },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajustes de apariencia" />

            <SettingsLayout>
                <Card className="rounded-xl">
                    <CardHeader>
                        <CardTitle>Ajustes de apariencia</CardTitle>
                        <CardDescription>Actualiza los ajustes de apariencia de tu cuenta</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <AppearanceTabs />
                    </CardContent>
                </Card>
            </SettingsLayout>
        </AppLayout>
    );
}
