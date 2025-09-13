---
title: 'Autoeliminación de cuenta deshabilitada'
summary: 'Política para impedir la autoeliminación de cuentas y pasos para re-habilitarla en caso de requerirse (rutas, permisos, UI y seguridad).'
icon: material/account-cancel
tags:
    - how-to
    - seguridad
    - auth
---

# Cómo: Autoeliminación de cuenta deshabilitada

Objetivo: documentar la política de impedir que los usuarios eliminen su propia cuenta y cómo revertirla si fuese necesario.

## Estado actual (en este proyecto)

- La ruta `DELETE settings/profile` está deshabilitada en `routes/settings.php`:

    ```php
    // Route disabled: users cannot delete their own account
    // Route::delete('settings/profile', [ProfileController::class, 'destroy'])
    //     ->middleware('can:settings.profile.delete')
    //     ->name('profile.destroy');
    ```

- La acción `destroy()` del controlador está deshabilitada en `app/Http/Controllers/Settings/ProfileController.php`:

    ```php
    // Account deletion disabled
    // public function destroy(ProfileDeleteRequest $request): RedirectResponse
    // {
    //     abort(404);
    // }
    ```

- El permiso `settings.profile.delete` está comentado en `config/permissions/settings.php`:

    ```php
    // 'settings.profile.delete', // disabled: users cannot delete their own account
    // 'settings.profile.delete' => 'Eliminar cuenta', // disabled
    ```

- En la UI (Inertia/React), el componente de eliminación está comentado en `resources/js/pages/settings/profile.tsx`:

    ```tsx
    // import DeleteUser from '@/components/delete-user'; // disabled: account deletion is not allowed
    // ...
    // <DeleteUser />
    ```

## Re-habilitar la autoeliminación (no recomendada)

1. Permisos

    - Destapar `settings.profile.delete` en `config/permissions/settings.php` (en `permissions` y en `descriptions`).
    - Sincronizar:
        ```bash
        php artisan config:clear
        php artisan db:seed --class=Database\\Seeders\\PermissionsSeeder
        ```

2. Backend

    - Destapar la ruta `Route::delete('settings/profile', ...)` en `routes/settings.php`.
    - Destapar e implementar `destroy(ProfileDeleteRequest $request)` en `ProfileController`.
        - El request existe: `app/Http/Requests/Settings/ProfileDeleteRequest.php`.
        - Implementación sugerida: validar contraseña, cerrar sesión y eliminar usuario.

3. Frontend

    - Volver a importar y renderizar `<DeleteUser />` en `resources/js/pages/settings/profile.tsx`.

4. Seguridad

    - Considera periodo de gracia (soft-delete + reactivación), exportación de datos, verificación por correo y auditoría.

## Estado recomendado

- Mantener deshabilitada la autoeliminación en este proyecto.
- Proveer un proceso administrado (mesa de ayuda o admins) para bajas de cuentas.
