import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { ShieldX } from 'lucide-react';

export default function Error403() {
    return (
        <div className="bg-background flex min-h-screen flex-col items-center justify-center">
            <Head title="403 - Acceso Denegado" />

            <div className="text-center">
                <div className="mb-4">
                    <ShieldX className="text-destructive mx-auto h-24 w-24" />
                </div>

                <h1 className="text-foreground mb-2 text-4xl font-bold tracking-tight">403</h1>

                <p className="text-muted-foreground mb-2 text-xl">Acceso Denegado</p>

                <p className="text-muted-foreground mb-8 max-w-md text-sm">
                    No tienes permisos suficientes para acceder a esta página. Si crees que esto es un error, contacta con el administrador.
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
