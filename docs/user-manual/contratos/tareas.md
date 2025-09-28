---
title: 'Contratos — Tareas'
summary: 'Procedimientos para crear, activar y finalizar contratos FIJO/M2.'
icon: material/file-document-edit
---

# Tareas frecuentes

## Crear contrato (DRAFT)

1. Menú → Contratos → Nuevo.
2. Selecciona concesionario(s) y local(es) (multi‑local/multi‑firmante si aplica).
3. Elige modalidad (FIJO o M2) y condiciones (vigencia, billing day si FIJO).
4. Guarda. El contrato queda en estado DRAFT.

## Activar contrato

1. Abre el contrato DRAFT → Activar.
2. Al activar, los locales pasan a estado OCUPADO y no admiten solapes.

## Finalizar contrato

- Opción ENDED: al llegar a la fecha de término.
- Opción EXPIRED: job diario marca EXPIRED cuando corresponde (si venció sin cierre manual).
- Al finalizar, los locales vuelven a DISPONIBLE desde la fecha efectiva.

## Ver contrato (Show)

1. Ir a Catálogos → Contratos → abrir menú de fila → Ver detalles.
2. En la vista se muestran pestañas:
    - Detalles: información básica y metadatos.
    - Documentos: visor del PDF del contrato (si fue cargado). Si no hay archivo, se indica claramente.
3. En Detalles aparecen relaciones:
    - Locales asociados: lista con enlaces hacia cada Local.
    - Concesionarios asociados: lista con enlaces; el firmante principal aparece con la etiqueta "Titular".

### Botón Eliminar

- El botón puede mostrarse según permisos del usuario.
- Sin embargo, por regla de negocio los contratos NO pueden eliminarse: el sistema mostrará un mensaje de error si se intenta.
