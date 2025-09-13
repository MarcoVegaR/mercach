import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function TestIndex() {
    return (
        <>
            <Head title="Test Page" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <h1 className="mb-4 text-2xl font-bold">Test Page</h1>
                            <p>This is a test page for CI/CD pipeline testing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

TestIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
