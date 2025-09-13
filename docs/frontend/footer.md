# Footer (frontend)

Este patrón define un Footer reusable, accesible y consistente con el tema Supabase (dark-first) usando Tailwind + shadcn/ui.

- Variante full (multi-columna) para layout público.
- Variante minimal (barra compacta) para vistas internas (dashboard).
- Enlaces condicionados por permisos (`auth.can` compartido por Inertia).
- Sin duplicar utilidades: reutiliza tokens/tema existentes y componentes shadcn.

## Tokens y tema

Se añaden alias semánticos en `resources/css/app.css`:

- `--color-brand` / `--color-brand-foreground` (alias de `--primary`) → clases `text-brand`, `ring-brand`.
- `--color-surface` / `--color-surface-foreground` (alias de `--card`).

El acento de marca (verde Supabase) se usa para estados hover/focus, no para texto extenso.

## Componente

Ubicación: `resources/js/components/app-footer.tsx`

Props:

- `variant?: 'full' | 'minimal'` (default: `full`)
- `container?: 'boxed' | 'fluid'` (default: `boxed` → `max-w-7xl`)
- `className?: string`
- `showLanguage?: boolean` (si existe i18n)

Accesibilidad:

- `<footer role="contentinfo" aria-label="site footer">`
- Navegación con `<nav aria-label="footer ...">` y enlaces con foco visible.
- `rel="noopener"` para externos.

Contenido:

- Branding: `AppLogo` + tagline breve.
- Producto: enlaces a módulos (Usuarios, Roles, Auditoría) condicionados por permisos.
- Recursos: Docs (MkDocs), Repo, Blog.
- Legal: Términos, Privacidad, AUP.
- Social: GitHub, X/Twitter, Discord (icon buttons).
- Barra inferior: `© año appName. Hecho con Laravel + Inertia.` + `requestId` opcional si está en props compartidas.

Estilos clave:

- Contenedor: `mx-auto max-w-7xl px-4 sm:px-6 lg:px-8`.
- Grid: `grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6`.
- Texto: `text-muted-foreground` con headers `text-foreground font-medium`.
- Enlaces: `hover:underline underline-offset-4 hover:text-brand` y `focus-visible:ring-brand`.

## Integración con layouts

Se inyecta directamente:

- Público (header): `AppHeaderLayout` → `<AppFooter variant="full" position="fixed" />`
- Interno (sidebar): `AppSidebarLayout` → `<AppFooter variant="minimal" position="fixed" respectSidebarGap />`

Ambos respetan dark mode (clases `dark:` y tokens).

### Responsive y móviles

- En móviles (sm y abajo) el footer ocupa siempre todo el ancho: `left-0 right-0 w-full`.
- Cuando `respectSidebarGap` está activo, el offset por el sidebar solo se aplica en `lg:`: `lg:[left:var(--sidebar-width)]`.
- Se añade `pb-[env(safe-area-inset-bottom)]` para respetar el safe-area en iOS (notch/home indicator).
- Las barras inferiores usan `flex-wrap` y el `Request ID` aplica `break-all` para evitar overflow en pantallas estrechas.

## Permisos y datos compartidos

`auth.can` y `name` se exponen en `App\Http\Middleware\HandleInertiaRequests::share()`.

- Mostrar/ocultar enlaces según `page.props.auth.can['module.permission']`.
- `name` se usa para el © y alt text del logo.
- `requestId` aparece si está disponible.

## Pruebas (Vitest + RTL)

Archivo: `resources/js/__tests__/app-footer.test.tsx`.

Cubre:

- Landmark `contentinfo` y encabezados de columnas en variante full.
- Enlaces presentes y `aria-label` para iconos sociales.
- Minimal renderiza barra condensada con © y legales.
- Dark mode: se verifica clase `dark:bg-background`.

Ejecutar:

```bash
npm run test:run
```

## Co-branding

- Usar el logo de la app. No recolorear wordmark Supabase.
- Acento verde solo como `:hover`/`ring`. Mantener contraste AA.
