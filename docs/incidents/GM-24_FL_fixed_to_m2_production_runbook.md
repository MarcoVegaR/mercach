---
description: Runbook de producción: Migración FL (TFIJA→M2) + ADJ mensual
---

# Objetivo

Aplicar en **producción** la solución para los locales **FL-01..FL-12**:

- Convertir sus contratos de **TFIJA/CONTR** a **M2/CONV**.
- Migrar historia (solo períodos target seguros):
    - Cancelar `RENT_EUR_FIXED` **ISSUED** sin pagos/créditos.
    - Crear `RENT_EUR_M2` (según tarifa vigente) + `ADJ` (33.10 EUR) para los mismos períodos.
- Activar generación mensual **separada** del extraordinario FL (`ADJ`) a partir de **2026-03**.
- Asegurar UI/PDF:
    - En **Estado de cuenta** y **Recibos** usar el término **Titular** (no “Deudor”).
    - En Estado de cuenta, mostrar los extraordinarios (`ADJ`) en el desglose.

# Alcance

- Mercado: `MERCACH`
- Locales: `FL-01` a `FL-12`
- Historia (target): según `docs/incidents/FL_fixed_to_m2_migration.sql`

# Pre-requisitos

1. Tener acceso a:
    - Servidor app (para deploy y `php artisan ...`)
    - Base de datos (para ejecutar SQL y validar)
2. Confirmar que corre el cron de Laravel:
    - `* * * * * php /path/to/artisan schedule:run ...`
3. Recomendado:
    - Snapshot/backup de BD previo
    - Ventana de ejecución (baja concurrencia)

# Paso 1 — Deploy de código (obligatorio antes del SQL)

Deployar el commit que incluye:

- `charges:fl-adj` (comando separado) + scheduling
- Ajustes en PDFs:
    - Estado de cuenta: incluir `ADJ` en desglose
    - Reemplazo “Deudor” → “Titular” (recibo + estado de cuenta)
- Ajustes frontend (Economic Profile / Portal) para visualizar `ADJ` con label correcto y sumar bucket `other`

Luego del deploy:

- Ejecutar build/migraciones si aplican en tu pipeline.

# Paso 2 — Preflight (solo lectura) en producción

Ejecutar en BD:

- Verificar contratos actuales FL:

```sql
select l.code as local_code, c.id as contract_id, cs.code as status, cm.code as modality, ct.code as type, c.monthly_price_eur
from locals l
join contract_local cl on cl.local_id = l.id
join contracts c on c.id = cl.contract_id and c.deleted_at is null
join contract_statuses cs on cs.id = c.contract_status_id
join contract_modalities cm on cm.id = c.contract_modality_id
join contract_types ct on ct.id = c.contract_type_id
where l.deleted_at is null
  and l.code in ('FL-01','FL-02','FL-03','FL-04','FL-05','FL-06','FL-07','FL-08','FL-09','FL-10','FL-11','FL-12')
order by l.code, c.start_date desc;
```

- Verificar FIXED target elegibles (sin pagos/créditos):

```sql
with target(code, period) as (
  values
    ('FL-01', date '2025-10-01'),('FL-01', date '2025-11-01'),('FL-01', date '2025-12-01'),('FL-01', date '2026-01-01'),('FL-01', date '2026-02-01'),
    ('FL-04', date '2025-12-01'),('FL-04', date '2026-01-01'),('FL-04', date '2026-02-01'),
    ('FL-07', date '2026-01-01'),('FL-07', date '2026-02-01'),
    ('FL-09', date '2026-01-01'),('FL-09', date '2026-02-01'),
    ('FL-10', date '2025-12-01'),('FL-10', date '2026-01-01'),('FL-10', date '2026-02-01'),
    ('FL-11', date '2026-01-01'),('FL-11', date '2026-02-01')
)
select l.code, ch.id, ch.period, cs.code as status
from charges ch
join locals l on l.id = ch.local_id
join charge_statuses cs on cs.id = ch.charge_status_id
join target t on t.code = l.code and t.period = ch.period
where ch.deleted_at is null
  and ch.kind = 'RENT_EUR_FIXED'
  and cs.code = 'ISSUED'
  and not exists (select 1 from payment_allocations pa where pa.charge_id = ch.id and pa.deleted_at is null)
  and not exists (select 1 from credit_applications ca where ca.charge_id = ch.id and ca.deleted_at is null)
order by l.code, ch.period;
```

# Paso 3 — Ejecutar SQL de migración (producción)

Archivo:

- `docs/incidents/FL_fixed_to_m2_migration.sql`

## Opción A (recomendada): `psql` en el servidor

Ejecutar:

```bash
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -f docs/incidents/FL_fixed_to_m2_migration.sql
```

## Opción B: desde Laravel (si no hay acceso directo a psql)

Ejecutar (requiere que el archivo exista en el server):

```bash
php artisan tinker --execute='$sql=file_get_contents(base_path("docs/incidents/FL_fixed_to_m2_migration.sql")); \Illuminate\Support\Facades\DB::unprepared($sql); echo "ok\n";'
```

# Paso 4 — Verificación post-SQL

- Confirmar que todos los contratos FL activos quedaron `M2/CONV`:

```sql
select l.code, cm.code as modality, ct.code as type, cs.code as status
from locals l
join contract_local cl on cl.local_id = l.id
join contracts c on c.id = cl.contract_id and c.deleted_at is null
join contract_statuses cs on cs.id = c.contract_status_id
join contract_modalities cm on cm.id = c.contract_modality_id
join contract_types ct on ct.id = c.contract_type_id
where l.deleted_at is null
  and l.code in ('FL-01','FL-02','FL-03','FL-04','FL-05','FL-06','FL-07','FL-08','FL-09','FL-10','FL-11','FL-12')
  and cs.code in ('VIG','EXT','VENC')
order by l.code;
```

- Confirmar que en períodos target:
    - `RENT_EUR_FIXED` quedó cancelado (soft-delete)
    - existen `RENT_EUR_M2` y `ADJ`

```sql
select l.code, ch.kind, ch.period, cs.code as status, ch.deleted_at
from charges ch
join locals l on l.id = ch.local_id
join charge_statuses cs on cs.id = ch.charge_status_id
where l.code in ('FL-01','FL-04','FL-07','FL-09','FL-10','FL-11')
  and ch.period in (date '2025-10-01',date '2025-11-01',date '2025-12-01',date '2026-01-01',date '2026-02-01')
  and ch.kind in ('RENT_EUR_FIXED','RENT_EUR_M2','ADJ')
order by l.code, ch.period, ch.kind;
```

# Paso 5 — Generación de cargos de marzo (incluye extraordinarios FL)

## 5.1 Generar `RENT_EUR_M2` (marzo)

```bash
php artisan charges:rent-m2 --market_code=MERCACH --period=2026-03-01
```

## 5.2 Generar `ADJ` FL (marzo) — separado

```bash
php artisan charges:fl-adj --period=2026-03-01
```

Notas:

- Es **idempotente** por `idempotency_key` (`FL_ADJ_{localId}_{YYYYMM}`): se puede re-ejecutar sin duplicar.
- El scheduler lo ejecutará automáticamente el **día 1** de cada mes.

# Paso 6 — Validación funcional (UI + PDFs)

## 6.1 Admin

- Perfil Económico (Local / Cesionario):
    - Debe listar `ADJ` como “Cargo por ajuste”.
    - El total debe incluir bucket `other`.
- Estado de cuenta (PDF):
    - Debe mostrar “Titular” (no “Deudor”).
    - Debe incluir `ADJ` en el desglose (chip “Ajustes” y en detalle).

## 6.2 Portal

- `/portal/deuda`:
    - Debe mostrar `ADJ` como “Cargo por ajuste”.

## 6.3 Recibo (PDF)

- Debe mostrar “Titular” (no “Deudor”).

# Paso 7 — Confirmar scheduling en producción

En `routes/console.php` quedó programado:

- `charges:rent-m2 --market_code=MERCACH` mensual 01:00
- `charges:fl-adj` mensual 01:10
- `charges:condo` mensual 03:00

Verifica que `schedule:run` esté activo.

# Rollback

- Si el SQL aún no se ejecuta: no aplicar.
- Si ya se ejecutó y se requiere revertir: restaurar snapshot/backup de BD (recomendado).
