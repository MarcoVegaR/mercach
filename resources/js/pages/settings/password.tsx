import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { zxcvbn, zxcvbnOptions } from '@zxcvbn-ts/core';
import { adjacencyGraphs, dictionary as commonDictionary } from '@zxcvbn-ts/language-common';
import { dictionary as esDictionary, translations } from '@zxcvbn-ts/language-es-es';
import { Copy as CopyIcon, Eye, EyeOff, Wand2 } from 'lucide-react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/dashboard' },
    { title: 'Ajustes', href: '/settings' },
    { title: 'Ajustes de contraseña', href: '/settings/password' },
];

export default function Password() {
    const { auth } = usePage<SharedData>().props;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [showPassword, setShowPassword] = useState(false);
    const [copyAnnouncement, setCopyAnnouncement] = useState('');

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    // Initialize zxcvbn language/config once
    useEffect(() => {
        zxcvbnOptions.setOptions({
            translations,
            dictionary: {
                ...commonDictionary,
                ...esDictionary,
            },
            graphs: adjacencyGraphs,
        });
    }, []);

    // Strength evaluation based on current input and user context
    const zx = useMemo(() => {
        const pwd = (data.password as string) || '';
        if (!pwd) return null;
        try {
            return zxcvbn(pwd, [auth?.user?.name ?? '', auth?.user?.email ?? '']);
        } catch {
            return null;
        }
    }, [data.password, auth?.user?.name, auth?.user?.email]);

    const score = zx?.score ?? 0;
    const scoreLabel = ['Muy débil', 'Débil', 'Aceptable', 'Fuerte', 'Excelente'][score] ?? '';
    const scoreColor = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-lime-500', 'bg-green-600'][score] ?? 'bg-gray-300';

    // Secure password generator
    const generateSecurePassword = useMemo(
        () =>
            function (length = 16) {
                const U = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                const L = 'abcdefghijkmnopqrstuvwxyz';
                const D = '23456789';
                const S = '!@#$%^&*()-_=+[]{};:,.?/';
                const all = U + L + D + S;

                const pick = (set: string) => set[Math.floor(Math.random() * set.length)];
                const chars = [pick(U), pick(L), pick(D), pick(S)];
                for (let i = chars.length; i < length; i++) chars.push(pick(all));
                for (let i = chars.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [chars[i], chars[j]] = [chars[j], chars[i]];
                }
                return chars.join('');
            },
        [],
    );

    const handleGeneratePassword = () => {
        const pwd = generateSecurePassword(16);
        setData('password', pwd);
        setData('password_confirmation', pwd);
        toast.success('Contraseña generada');
        setShowPassword(true);
        passwordInput.current?.focus();
    };

    const handleCopyPassword = async () => {
        const pwd = (data.password as string) || '';
        if (!pwd) return;
        try {
            await navigator.clipboard.writeText(pwd);
            setCopyAnnouncement('Copiada');
            setTimeout(() => setCopyAnnouncement(''), 1200);
            toast.success('Contraseña copiada');
        } catch {
            toast.error('No se pudo copiar la contraseña');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ajustes de contraseña" />

            <SettingsLayout>
                <Card className="rounded-xl">
                    <CardHeader>
                        <CardTitle>Actualizar contraseña</CardTitle>
                        <CardDescription>Asegúrate de usar una contraseña larga y aleatoria para mantener tu cuenta segura</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={updatePassword} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">Contraseña actual</Label>
                                <Input
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    value={data.current_password}
                                    onChange={(e) => setData('current_password', e.target.value)}
                                    type="password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder="Contraseña actual"
                                />
                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Nueva contraseña</Label>
                                <Input
                                    id="password"
                                    ref={passwordInput}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    type={showPassword ? 'text' : 'password'}
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Nueva contraseña"
                                />
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleGeneratePassword}
                                        title="Generar contraseña segura"
                                    >
                                        <Wand2 className="h-4 w-4" />
                                        <span className="sr-only sm:not-sr-only sm:ml-1">Generar</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleCopyPassword}
                                        title="Copiar contraseña"
                                        disabled={!data.password}
                                    >
                                        <CopyIcon className="h-4 w-4" />
                                        <span className="sr-only sm:not-sr-only sm:ml-1">Copiar</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setShowPassword((v) => !v)}
                                        title={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        <span className="sr-only sm:not-sr-only sm:ml-1">{showPassword ? 'Ocultar' : 'Mostrar'}</span>
                                    </Button>
                                </div>
                                <span aria-live="polite" role="status" className="sr-only">
                                    {copyAnnouncement}
                                </span>
                                {data.password && (
                                    <div className="mt-1">
                                        <div className="overflow-hidden rounded bg-gray-200 dark:bg-gray-700">
                                            <div
                                                className={`${scoreColor} h-2 transition-all`}
                                                style={{ width: `${Math.max(1, score + 1) * 20}%` }}
                                            />
                                        </div>
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            {scoreLabel}
                                            {zx?.feedback?.warning ? ` — ${zx.feedback.warning}` : ''}
                                        </div>
                                    </div>
                                )}
                                <p className="text-muted-foreground text-xs">
                                    Mínimo 8 caracteres. Incluye mayúscula, minúscula, dígito y símbolo. Recomendado 12+.
                                </p>
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                                <Input
                                    id="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    type="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Confirmar contraseña"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Guardar contraseña</Button>
                                <Transition
                                    show={recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-neutral-600">Guardado</p>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </SettingsLayout>
        </AppLayout>
    );
}
