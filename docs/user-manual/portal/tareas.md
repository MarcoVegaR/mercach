---
title: 'Portal — Tareas'
summary: 'Guía paso a paso para registrar pagos, aplicarlos a deudas y descargar recibos desde el Portal.'
icon: material/hand-coin
---

# Tareas frecuentes en el Portal

## Registrar un pago (Transferencia o Pago Móvil)

1. En el menú lateral, abre Portal → Registrar pago.
2. Elige el método de pago:
    - Transferencia bancaria (TRF).
    - Pago Móvil (PMOV).
    - Nota: En el Portal solo están permitidos Transferencia y Pago Móvil. Débito se gestiona presencialmente.
3. Completa el formulario según el método seleccionado:
    - Cuenta receptora (de la empresa) y Banco.
    - Monto en Bs y Fecha del pago.
    - Referencia (6–12 dígitos). Para PMOV además tu teléfono (E.164, p. ej. 584241234567). Para TRF tu cuenta (20 dígitos) y banco de origen.
4. Envía. El sistema intentará la verificación automática con el banco:
    - Si todo está correcto, el pago se registra como “verificado” y se redirige a “Aplicar pago”.
    - Si el banco rechaza (p. ej. código 706), verás un aviso rojo con el mensaje exacto. Tus datos se preservan para corregir y reenviar.

## Aplicar un pago a mis deudas (wizard 3 pasos)

1. Abre Portal → Mis pagos y entra en “Aplicar” para el pago recién registrado.
2. Paso 1 — Sugerencia: el sistema propone una distribución (por defecto FIFO, priorizando vencidos). Puedes filtrar por moneda, periodo o tipo.
3. Paso 2 — Revisión: ajusta montos por cargo con casillas y totales claros. Opcional: usar “Saldo a favor” si tienes crédito disponible.
4. Paso 3 — Confirmación: confirma la aplicación. El sistema valida montos, saldo disponible e idempotencia para evitar dobles aplicaciones.
5. Resultado: verás un mensaje de éxito y podrás ir a “Mis recibos”.

## Descargar recibos

- Ve a Portal → Mis recibos. Ubica el recibo emitido para tu pago aplicado y descárgalo en PDF.

## Ver mi deuda (estado de cuenta)

1. Abre Portal → Mi deuda.
2. Usa filtros (moneda, tipo, periodo, solo vencidos) para enfocar.
3. El listado agrupa por periodo y muestra equivalentes en Bs (tasa vigente a la fecha del pago cuando aplica).

## Consejos

- Referencias: usa solo dígitos. Si son < 6, el sistema las rellena con ceros a la izquierda.
- Teléfono PMOV: si comienzas con 0 y tiene 11 dígitos, se convierte automáticamente a 58XXXXXXXXXX.
- Idempotencia: operaciones de aplicación usan claves idempotentes para evitar duplicados si reintentas.
- Seguridad: solo puedes ver y operar sobre pagos y cargos de tu concesionario vinculado.
