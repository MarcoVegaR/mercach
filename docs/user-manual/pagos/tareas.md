---
title: 'Pagos — Tareas'
summary: 'Procedimientos para registrar y consultar pagos.'
icon: material/clipboard-text
---

# Tareas frecuentes

## Registrar un pago (interno)

1. Menú → Pagos → Nuevo.
2. Selecciona el deudor: Concesionario o Local.
3. Elige el método: Transferencia (TRF), Pago Móvil (PMOV) o Débito (DEB).
4. Completa los datos del método:
    - Transferencia: Banco origen, Cuenta del pagador (20 dígitos), Referencia (6–12 dígitos).
    - Pago Móvil: Teléfono E.164 (58 + 10 dígitos, con selector de código), Referencia (6–12).
    - Débito: Referencia (si aplica). No requiere banco origen.
    - Cuenta receptora (de la empresa) y Monto Bs. Fecha del pago.
    - La tasa FX se resuelve automáticamente al cambiar la fecha (para equivalentes).
5. Guardar: el sistema intentará verificación con el banco. Si es exitosa, el pago queda CONFIRMED y se redirige a “Cruce/Aplicar”. Si falla, se muestra un error claro y se conservan los datos.

## Verificar un pago manualmente

- En el detalle del pago (estatus REGISTERED), pulsa Verificar. Si la pasarela responde 00 o el estado cambia a CONFIRMED, podrás aplicar.

## Aplicar un pago a cargos

1. Abre la pestaña “Cruce/Aplicar”.
2. Pulsa “Buscar cargos” para cargar los cargos abiertos del deudor (ISSUED/PARTIAL) a la fecha del pago. Usa filtros (moneda, tipo, periodo, solo vencidos) según necesites.
3. Opcional: elige una estrategia de sugerencia:
    - FIFO (antigüedad).
    - Por tipo (prioriza tipos con mayor deuda, y por antigüedad dentro del tipo).
4. Ajusta montos por cargo y presiona “Confirmar” (previo de validación). Luego “Aplicar”.
5. Resultado: el pago pasa a APPLIED, se generan recibos por cargo y/o por pago. Revisa la pestaña “Asignaciones” y el bloque “Recibo”.

## Descargar recibos

- En el detalle del pago: bloque “Recibo” (PDF/Verificar) o “Recibos por cargo”.
