---
title: 'Reglas de negocio'
summary: 'Tarifas con vigencias, efectos por estado de contrato y reglas clave.'
icon: material/scale-balance
---

# Reglas de negocio (v1 Locales)

- Tarifas por mercado en EUR/m² con vigencias sin solape.
- Contratos M2 calculan según la tarifa vigente al momento de generar el cargo.
- Cambios de tarifa afectan solos cargos futuros (histórico intacto).
- Estados de contrato: DRAFT → ACTIVE → ENDED/EXPIRED.
- ACTIVE marca locales como OCUPADOS; ENDED/EXPIRED libera a DISPONIBLE.
- Deuda sin contrato: locales disponibles pueden seguir generando deuda, asumible por un contrato posterior.
