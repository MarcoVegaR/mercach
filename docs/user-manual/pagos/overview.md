---
title: 'Pagos — Visión general'
summary: 'Registro, verificación bancaria y aplicación de pagos a cargos. Emisión de recibos vinculados.'
icon: material/cash-register
---

# Pagos — Visión general

- Propósito: registrar pagos en Bs, verificarlos contra el banco y aplicarlos a cargos abiertos (por concesionario o local). Generar recibos.
- Audiencia: caja/tesorería.
- Permisos (back‑office):
    - Listado/ver: `catalogs.payment.view`
    - Crear: `catalogs.payment.create`
    - Verificar/aplicar: `catalogs.payment.update`
    - Eliminar/bulk: `catalogs.payment.delete|catalogs.payment.restore|catalogs.payment.forceDelete|catalogs.payment.setActive`
- Pantallas principales:
    - Listado de pagos con filtros y exportación.
    - Nuevo pago (verificación automática al guardar).
    - Detalle de pago con pestañas: verificación, aplicación, recibos.

## Conceptos clave

- Verificación bancaria: se firma y consulta la transacción (Transferencia 211, Pago Móvil 300). En éxito, estado `CONFIRMED`.
- Aplicación: distribuye el monto confirmado entre cargos (`ISSUED`/`PARTIAL`) y emite recibos.
- Idempotencia: huellas determinísticas y claves idempotentes evitan dobles registros/aplicaciones.
