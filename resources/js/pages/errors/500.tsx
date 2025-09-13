import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { ServerCrash } from 'lucide-react';

export default function Error500() {
    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center">
            <Head title="500 - Error del Servidor" />

            <div className="text-center">
                <div className="mb-4">
                    <ServerCrash className="text-destructive mx-auto h-24 w-24" />
                </div>

                <h1 className="text-foreground mb-2 text-4xl font-bold tracking-tight">500</h1>

                <p className="text-muted-foreground mb-2 text-xl">Error del Servidor</p>

                <p className="text-muted-foreground mb-8 max-w-md text-sm">
                    Ha ocurrido un error interno del servidor. El equipo técnico ha sido notificado automáticamente. Por favor, inténtalo de nuevo más
                    tarde.
                </p>

                <div className="flex justify-center gap-4">
                    <Button asChild variant="outline">
                        <Link href={route('dashboard')}>Ir al Dashboard</Link>
                    </Button>

                    <Button asChild>
                        <button onClick={() => window.location.reload()}>Recargar Página</button>
                    </Button>
                </div>
            </div>
        </div>
    );
}
