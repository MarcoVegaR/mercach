import AppLayout from '@/layouts/app-layout';
import type { PageProps } from '@inertiajs/core';
import React from 'react';
import UserForm from './form';

interface UsersEditPageProps extends PageProps {
    item: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        roles_ids?: number[];
        roles?: Array<{ id: number; name: string }>;
        updated_at?: string | null;
    };
    options: { roleOptions: Array<{ id: number; name: string }> };
    can: Record<string, boolean>;
}

export default function UsersEditPage({ item, options, can }: UsersEditPageProps) {
    return <UserForm mode="edit" initial={item} options={options} can={can} />;
}

UsersEditPage.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
