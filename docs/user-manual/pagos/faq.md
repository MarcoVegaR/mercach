---
title: 'Pagos — FAQ'
summary: 'Dudas frecuentes de caja/tesorería.'
icon: material/help-circle
---

# Preguntas frecuentes

## No puedo registrar el pago (error del banco)

- El sistema intenta verificación automática al crear. Si el banco rechaza, verás un mensaje claro (se preservan los datos). Errores comunes:
    - 706: Cuenta bancaria inválida para el banco. Verifica los 20 dígitos y el banco origen.
    - 707: Referencia duplicada. Ingresa una referencia distinta (6–12 dígitos).
    - 708: Cuenta destino inválida. Revisa la cuenta receptora de la empresa.
    - 709: Referencia no encontrada. Verifica número y fecha de pago.
    - 710: Monto no coincide. Revisa el monto y vuelve a intentar.

## El pago no pasa a CONFIRMED

- Usa el botón Verificar en el detalle del pago (estatus REGISTERED).
- Si el banco responde 00 o el estado cambia a CONFIRMED, podrás aplicar.

## No aparecen cargos al aplicar

- En la pestaña "Cruce/Aplicar", pulsa "Buscar cargos". Asegúrate de:
    - Tipo de deudor correcto (Concesionario o Local) y fecha de pago.
    - Filtros: elimina moneda/tipo/periodo/"solo vencidos" para buscar más amplio.
    - Los cargos listados son `ISSUED`/`PARTIAL`.

## No puedo aplicar todo el monto

- El total solicitado no puede exceder el disponible (pago + crédito si activas "usar crédito").
- Ningún cargo puede exceder su saldo.

## Doble aplicación por error

- La operación de aplicación usa clave idempotente; reintentos con la misma clave no duplican. Si crees que hubo duplicado, revisa la pestaña "Asignaciones".

## El estado de cuenta no coincide

- Revisa filtros de periodo y si quedan conciliaciones/aplicaciones pendientes.
