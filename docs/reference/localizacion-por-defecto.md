---
title: 'Localización por defecto (ES)'
summary: 'Valores por defecto de idioma en backend y frontend: APP_LOCALE, fallback y faker; archivos de traducción y ejemplos de uso.'
icon: material/translate
tags:
    - referencia
    - localización
---

# Referencia: Localización por defecto (ES)

Esta base asume español como idioma por defecto tanto en backend como en frontend.

## Configuración

- `.env` y `.env.example`:

    ```ini
    APP_LOCALE=es
    APP_FALLBACK_LOCALE=es
    APP_FAKER_LOCALE=es_ES
    ```

- `config/app.php` (lee de `.env`):

    ```php
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    ```

## Recursos de traducción

- Archivos de Laravel en `lang/es/`:
    - `auth.php`, `passwords.php`, `validation.php`.
- Cadenas libres del frontend/backend: `lang/es.json`.

## Uso en backend

- Helpers de localización:

    ```php
    __('validation.required'); // desde lang/es/validation.php
    __('reset.link_sent');     // si existe en lang/es.json
    ```

- Forzar/leer idioma actual:

    ```php
    app()->setLocale('es');
    $locale = app()->getLocale();
    ```

## Notas

- La documentación de este proyecto se sirve en ES por defecto (MkDocs Material `theme.language: es`).
- Si agregas EN u otros idiomas en el futuro, ajusta `mkdocs.yml` (plugin `i18n`) y añade archivos en `lang/<locale>/` y `<locale>.json`.
