---
title: 'Guía de Contribución a la Documentación'
summary: 'Estándares de estilo y organización (Diátaxis), front-matter, snippets de código, admonitions y tabs para mantener la documentación profesional y consistente.'
icon: material/account-edit
tags:
    - docs
    - contribución
    - diataxis
---

# Guía de Contribución a la Documentación

## Principios

- La documentación se organiza por Diátaxis: Tutoriales, Cómo hacerlo, Referencia, Explicaciones.
- Cada página debe tener front-matter con `title`, `summary`, `icon` y `tags`.
- El idioma base es español; usa títulos en Title Case breve y slugs en kebab-case.

## Front-matter (obligatorio)

```yaml
---
title: 'Título claro (<=60)'
summary: 'Resumen de 1–2 frases para SEO y navegación.'
icon: material/book-open-page-variant
tags:
    - categoria
    - subcategoria
---
```

- El primer `# H1` debe coincidir con `title`.
- Usa `icon` de [Material Icons](https://squidfunk.github.io/mkdocs-material/reference/icons-emojis/#icons) (prefijo `material/`).

## Estilo de escritura

- Escribe en español neutro, directo y orientado a tareas.
- Evita mezclar tipos de contenido: no pongas pasos en Referencia, ni API en Tutoriales.
- Prefiere ejemplos completos y mínimos; añade enlaces a contexto cuando haga falta.

## Snippets de código

- Usa bloques con lenguaje: `php`, `bash`, `tsx`, `ts`, `json`, `http`, `ini`, `sql`.
- Evita líneas muy largas; divide comandos en múltiples líneas cuando sea necesario.
- Marca input/outputs y peligros con admonitions.

## Admonitions y Tabs

- Admonitions: `!!! note|tip|warning|danger` con títulos cortos.
- Tabs de alternativas: `pymdownx.tabbed` con títulos claros (p. ej. Linux/Windows, npm/yarn).

## Clasificación por Diátaxis

- Tutoriales: primeros pasos guiados.
- Cómo hacerlo: tareas concretas paso a paso.
- Referencia: contratos, API, parámetros, límites.
- Explicaciones: conceptos, porqués, arquitectura.

Si una guía mezcla enfoques, divídela o muévela a la categoría correcta.

## Navegación y redirects

- Añade entradas explícitas en `mkdocs.yml` (secciones y orden lógico).
- Si mueves/renombras páginas, añade `redirects` para evitar 404.

## Última actualización

- La fecha se muestra automáticamente con `git-revision-date-localized`. No la edites manualmente.

## Cómo servir y compilar docs

```bash
python -m venv .venv && source .venv/bin/activate  # opcional
pip install -r docs/requirements.txt
mkdocs serve  # http://127.0.0.1:8000
# Build estricto
mkdocs build --strict
```

## Checklist de PRs de documentación

- [ ] Front-matter completo y `H1` consistente
- [ ] Ubicación correcta por Diátaxis y `nav` actualizado
- [ ] Ejemplos con bloques de código y lenguajes correctos
- [ ] Admonitions y/o tabs si hay alternativas
- [ ] Links verificados; añade `redirects` si cambias rutas
- [ ] Lint visual (render local con `mkdocs serve`)
