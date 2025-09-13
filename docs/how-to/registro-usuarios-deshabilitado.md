---
title: 'Registro de usuarios deshabilitado (admin-only)'
summary: 'Política para deshabilitar el registro público y cómo re-habilitarlo con seguridad: rutas, UI y controles recomendados.'
icon: material/account-off
tags:
    - how-to
    - seguridad
    - auth
---

# Cómo: Registro de usuarios deshabilitado (admin-only)

Objetivo: documentar la política de deshabilitar el registro público de usuarios y cómo re-habilitarlo si fuese necesario.

## Estado actual (en este proyecto)

- Rutas de registro deshabilitadas en `routes/auth.php`:

    ```php
    // Registration routes disabled (admin-only user provisioning)
    // Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    // Route::post('register', [RegisteredUserController::class, 'store']);
    ```

- El controlador existe para referencia: `app/Http/Controllers/Auth/RegisteredUserController.php` (nota en docblock indicando que no se debe exponer `/register`).
- El README refleja esta política: registro deshabilitado; provisión de usuarios solo por administradores.

## Re-habilitar el registro (no recomendado en este boilerplate)

1. Descomentar las rutas en `routes/auth.php`:

    ```php
    use App\Http\Controllers\Auth\RegisteredUserController;

    Route::middleware('guest')->group(function () {
        Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('register', [RegisteredUserController::class, 'store']);
    });
    ```

2. Verificar que la página de registro exista y funcione (`resources/js/pages/auth/register.tsx` si aplica con Inertia/React).

3. Probar el flujo:

    - GET `/register` debe renderizar el formulario.
    - POST `/register` debe crear el usuario y redirigir a `dashboard`.

4. Seguridad y políticas:

    - Si re-habilitas registro, considera rate limiting, verificación de email, captcha y procesos de onboarding.

## Provisión de usuarios (recomendada)

- Usuario admin por defecto: `database/seeders/UsersSeeder.php` crea/garantiza un usuario con rol `admin`.
- Se espera que administradores creen usuarios. Este boilerplate no incluye (aún) un UI de administración; puedes hacerlo vía seeder/console o agregando módulos de gestión de usuarios protegidos por permisos (`users.create`, `users.update`, …) definidos en `config/permissions/users.php`.

## Checklist rápido

- [x] Rutas de registro están comentadas en `routes/auth.php`.
- [x] Controlador `RegisteredUserController` presente pero no expuesto.
- [x] README comunica política admin-only.
- [ ] Si decides re-habilitar: añade tests de integración y endurece controles anti-abuso.
