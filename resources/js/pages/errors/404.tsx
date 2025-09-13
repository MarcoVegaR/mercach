import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { FileQuestion } from 'lucide-react';

export default function Error404() {
    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center">
            <Head title="404 - Página No Encontrada" />

            <div className="text-center">
                <div className="mb-4">
                    <FileQuestion className="text-muted-foreground mx-auto h-24 w-24" />
                </div>

                <h1 className="text-foreground mb-2 text-4xl font-bold tracking-tight">404</h1>

                <p className="text-muted-foreground mb-2 text-xl">Página No Encontrada</p>

                <p className="text-muted-foreground mb-8 max-w-md text-sm">
                    La página que estás buscando no existe o ha sido movida. Verifica la URL o navega a otra sección.
                </p>

                <div className="flex justify-center gap-4">
                    <Button asChild variant="outline">
                        <Link href={route('dashboard')}>Ir al Dashboard</Link>
                    </Button>

                    <Button asChild>
                        <button onClick={() => window.history.back()}>Volver Atrás</button>
                    </Button>
                </div>
            </div>
        </div>
    );
}
