import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

export default function TestPage() {
    return (
        <>
            <Head title="Test Page" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <h1 className="mb-4 text-2xl font-bold">Test Page</h1>
                            <p>This is a standalone test page for testing purposes.</p>
                            <div className="mt-4">
                                <p className="text-sm text-gray-600 dark:text-gray-400">Used for CI/CD pipeline verification and frontend testing.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

TestPage.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
