---
title: 'Estados y efectos operativos'
summary: 'Efectos de estados de contrato en el estado de locales y generación de cargos.'
icon: material/state-machine
---

# Estados y efectos operativos

## Contratos
- DRAFT → ACTIVE → ENDED/EXPIRED.
- ACTIVE: el/los locales pasan a OCUPADO (no admite solapes de contrato).
- ENDED/EXPIRED: los locales vuelven a DISPONIBLE desde la fecha correspondiente.

## Expiración automática
- Un job diario marca EXPIRED cuando aplica (sin cierre manual oportuno).

## Deuda sin contrato
- Locales disponibles pueden seguir generando deuda; el próximo contrato puede asumirla según política.
