---
title: 'Arquitectura (resumen)'
summary: 'Stack técnico, colas, scheduler y motor de cargos.'
icon: material/cube-outline
---

# Arquitectura (resumen)

- Stack: Laravel 12 + Breeze React (SPA), MySQL/PostgreSQL.
- Colas: Redis + Horizon para trabajos pesados.
- Programación: Scheduler (una entrada cron) orquesta jobs desde código.
- Motor de cargos: estrategias por tipo (M2, FIJO, Condo) y target polimórfico (Local v1, Space v2); idempotencia por hash.
- Dinero: montos en unidades menores y/o Brick\Money; documentar redondeo (p. ej., banker’s rounding).
- State machine donde aporta control y legibilidad (Contratos; opcionalmente Pagos/Recibos).
- Auditoría y correcciones con ajustes (+/−) y bitácoras.
