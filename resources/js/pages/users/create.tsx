import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import React from 'react';
import UserForm from './form';

interface UsersCreatePageProps extends PageProps {
    options: { roleOptions: Array<{ id: number; name: string }> };
    can?: Record<string, boolean>;
}

export default function UsersCreatePage({ options, can }: UsersCreatePageProps) {
    return <UserForm mode="create" options={options} can={can ?? {}} />;
}

UsersCreatePage.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Inicio', href: '/dashboard' },
            { title: 'Usuarios', href: '/users' },
            { title: 'Crear', href: '' },
        ]}
    >
        {page}
    </AppLayout>
);
