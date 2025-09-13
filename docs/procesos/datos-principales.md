---
title: 'Datos principales'
summary: 'Catálogos y entidades core mínimas.'
icon: material/database
---

# Datos principales (mínimo imprescindible)

## Catálogos (en inglés)
- local_types, local_statuses, trade_categories, concessionaire_types
- document_types, contract_types, contract_statuses, contract_modalities
- expense_types, payment_statuses, banks, phone_area_codes (4 dígitos exactos), payment_types

## Core
- markets, locals (único por mercado), concessionaires (email único)
- contracts + pivots (contract_local, contract_concessionaire)
- tariffs (vigencias por mercado), exchange_rates (EUR↔Bs, USD↔Bs, vigencias)
- condo_expenses, condo_periods (+exclusions), charges, payments (+applications), receipts

## Soporte
- files, email_log, *_adjustments (reverso/corrección), contract_staff (encargados asociados al contrato)
