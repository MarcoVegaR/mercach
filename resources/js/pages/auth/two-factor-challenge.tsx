import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { toast } from '@/lib/toast';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';

export default function TwoFactorChallenge() {
    const [usingRecovery, setUsingRecovery] = useState(false);
    const [cooldown, setCooldown] = useState<number | null>(null);

    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
        code: '',
        recovery_code: '',
    });

    useEffect(() => {
        const remove = router.on('error', (event) => {
            // Inertia 'error' event typing doesn't expose Response; some backends provide it.
            // We narrow safely without using 'any'.
            const ev = event as unknown as { detail?: { response?: Response } };
            const res = ev.detail?.response;
            if (res?.status === 429) {
                const retry = Number(res.headers?.get?.('retry-after') ?? 60);
                setCooldown(retry);
                toast.error(`Demasiados intentos. Intenta de nuevo en ${retry}s`);
            }
        });
        return () => {
            try {
                remove?.();
            } catch {
                /* no-op */
            }
        };
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        clearErrors();
        post('/two-factor-challenge', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            onError: (errs) => {
                // Map Fortify English messages to Spanish
                if (errs?.code) {
                    setError('code', 'El código de verificación TOTP es inválido.');
                }
                if (errs?.recovery_code) {
                    setError('recovery_code', 'El código de recuperación es inválido.');
                }
            },
            onSuccess: () => {
                reset('code', 'recovery_code');
            },
        });
    };

    const toggleMode = () => {
        setUsingRecovery((v) => !v);
        reset('code', 'recovery_code');
    };

    const title = useMemo(() => (usingRecovery ? 'Ingresa un código de recuperación' : 'Ingresa el código de tu app (TOTP)'), [usingRecovery]);

    return (
        <AuthLayout title="Verificación en dos pasos" description="Protege tu cuenta con un segundo factor (TOTP)">
            <Head title="Verificación 2FA" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    {!usingRecovery ? (
                        <div className="grid gap-2">
                            <Label htmlFor="code">{title}</Label>
                            <p className="text-muted-foreground text-xs">
                                Abre Google Authenticator, Authy u otra app TOTP y escribe el código de 6 dígitos. Cambia cada 30s.
                            </p>
                            <InputOTP length={6} value={data.code} onChange={(v) => setData('code', v.replace(/\D/g, '').slice(0, 6))} />
                            <InputError message={errors.code} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="recovery_code">{title}</Label>
                            <p className="text-muted-foreground text-xs">
                                Usa uno de tus códigos de recuperación guardados. Si no los tienes, vuelve a tu perfil y regénéralos.
                            </p>
                            <Input
                                id="recovery_code"
                                value={data.recovery_code}
                                onChange={(e) => setData('recovery_code', e.target.value.trim())}
                                placeholder="ej. apple-bread-..."
                                autoComplete="one-time-code"
                            />
                            <InputError message={errors.recovery_code} />
                        </div>
                    )}

                    <div className="flex items-center justify-between">
                        <TextLink
                            href="#"
                            onClick={(e) => {
                                e.preventDefault();
                                toggleMode();
                            }}
                        >
                            {usingRecovery ? 'Usar código de la app (TOTP)' : 'Usar código de recuperación'}
                        </TextLink>
                    </div>

                    <Button type="submit" className="mt-2 w-full" disabled={processing || (cooldown ?? 0) > 0}>
                        {cooldown ? `Reintentar en ${cooldown}s` : 'Verificar'}
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
