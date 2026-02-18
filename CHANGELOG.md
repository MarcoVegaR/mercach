## [1.29.11](https://github.com/MarcoVegaR/mercach/compare/v1.29.10...v1.29.11) (2026-02-18)

### Bug Fixes

- **receipts:** qualify deleted_at in credit_applications join to fix ambiguous column ([c11e7dd](https://github.com/MarcoVegaR/mercach/commit/c11e7ddb23716345ff7ef77457921deee2b9a6b6))

## [1.29.10](https://github.com/MarcoVegaR/mercach/compare/v1.29.9...v1.29.10) (2026-02-18)

### Bug Fixes

- **receipts:** show credit applications on payment receipt and UI ([823dad5](https://github.com/MarcoVegaR/mercach/commit/823dad5a2303b1cdf174a4efc49307567113b0b0))

## [1.29.9](https://github.com/MarcoVegaR/mercach/compare/v1.29.8...v1.29.9) (2026-02-11)

### Performance Improvements

- remove debug Log::info from BaseRepository::list() ([f1cc0a6](https://github.com/MarcoVegaR/mercach/commit/f1cc0a6dcc718425657618c3df73895f0d1ba7b9))

## [1.29.8](https://github.com/MarcoVegaR/mercach/compare/v1.29.7...v1.29.8) (2026-02-11)

### Bug Fixes

- rename ADJ charge label to 'Gasto Fijo de Mantenimiento' across all UI layers ([38e0cf8](https://github.com/MarcoVegaR/mercach/commit/38e0cf8dac73bda2d694f33b504964f26f240fa3))

## [1.29.7](https://github.com/MarcoVegaR/mercach/compare/v1.29.6...v1.29.7) (2026-02-06)

### Bug Fixes

- **dashboard:** resolve PHPStan nullCoalesce error in DashboardService ([07419e6](https://github.com/MarcoVegaR/mercach/commit/07419e623c7e2595b413de96a30ccade377825f2))

## [1.29.6](https://github.com/MarcoVegaR/mercach/compare/v1.29.5...v1.29.6) (2026-02-06)

### Bug Fixes

- **debt-analysis:** rewrite backend & frontend for correct multi-currency amounts ([033daee](https://github.com/MarcoVegaR/mercach/commit/033daee139a946a69fb6bac40180b5a36070631e))

## [1.29.5](https://github.com/MarcoVegaR/mercach/compare/v1.29.4...v1.29.5) (2026-02-06)

### Bug Fixes

- **FL:** migrate FL locals to M2/CONV, generate monthly ADJ charges, fix UI labels ([cc83055](https://github.com/MarcoVegaR/mercach/commit/cc83055a8255890876d0c0aee5b51b04cdb7f03b))

## [1.29.4](https://github.com/MarcoVegaR/mercach/compare/v1.29.3...v1.29.4) (2026-01-31)

### Bug Fixes

- **payments:** handle concessionaire-level fines allocations and receipt labels ([b11771b](https://github.com/MarcoVegaR/mercach/commit/b11771b297829a80996d4ca2329a959e75368dc2))

## [1.29.3](https://github.com/MarcoVegaR/mercach/compare/v1.29.2...v1.29.3) (2026-01-29)

### Bug Fixes

- **fx:** corregir discrepancia de redondeo FX en aplicación de pagos ([766e8c3](https://github.com/MarcoVegaR/mercach/commit/766e8c350dfff358b9dcab619bb6975e17723fb9))

## [1.29.2](https://github.com/MarcoVegaR/mercach/compare/v1.29.1...v1.29.2) (2026-01-28)

### Bug Fixes

- fx rounding consistency and receipt totals ([54b5031](https://github.com/MarcoVegaR/mercach/commit/54b50316e378579b79adb8296b0ee63f2c6992bb))

## [1.29.1](https://github.com/MarcoVegaR/mercach/compare/v1.29.0...v1.29.1) (2026-01-27)

### Bug Fixes

- **fx:** make totals consistent across admin and portal ([e924353](https://github.com/MarcoVegaR/mercach/commit/e924353b468f42e36ad82ec8518d14900f5751a2))

# [1.29.0](https://github.com/MarcoVegaR/mercach/compare/v1.28.0...v1.29.0) (2026-01-27)

### Features

- **pdf:** redesign economic profile statement pdf ([f1ed48f](https://github.com/MarcoVegaR/mercach/commit/f1ed48f904aac780650ff851c569e2e1d69b965d))

# [1.28.0](https://github.com/MarcoVegaR/mercach/compare/v1.27.0...v1.28.0) (2026-01-25)

### Features

- **reports:** registered dates in daily bank reconciliation; index payments dates ([d57f9c7](https://github.com/MarcoVegaR/mercach/commit/d57f9c74814317a90c7ae3a110de0352dd4dfc08))

# [1.27.0](https://github.com/MarcoVegaR/mercach/compare/v1.26.6...v1.27.0) (2026-01-25)

### Features

- reports + dashboard + receipts; fix charges Bs display for settled; QA green ([11871a1](https://github.com/MarcoVegaR/mercach/commit/11871a1fead33d10587701eb6a6a5f5f29697192))

## [1.26.6](https://github.com/MarcoVegaR/mercach/compare/v1.26.5...v1.26.6) (2026-01-22)

### Bug Fixes

- handle Inertia validation errors and align payments create button style ([5166469](https://github.com/MarcoVegaR/mercach/commit/5166469a01c345eeb6b33f83ae6ce0e1026bc1d3))

## [1.26.5](https://github.com/MarcoVegaR/mercach/compare/v1.26.4...v1.26.5) (2026-01-21)

### Bug Fixes

- recibo 130 131 ([c4ece1f](https://github.com/MarcoVegaR/mercach/commit/c4ece1f37b94cf73dcbaf827ab5ec3f6c19b6f4f))

## [1.26.4](https://github.com/MarcoVegaR/mercach/compare/v1.26.3...v1.26.4) (2026-01-21)

### Bug Fixes

- split fixed rent USD across dashboard and portal ([069183e](https://github.com/MarcoVegaR/mercach/commit/069183ec61d2e12fb233d2563b0b90e7edd7d744))

## [1.26.3](https://github.com/MarcoVegaR/mercach/compare/v1.26.2...v1.26.3) (2026-01-21)

### Bug Fixes

- deudas locales terraza ([33984f3](https://github.com/MarcoVegaR/mercach/commit/33984f384565c9abf8fd9711f4ef91de5841318b))

## [1.26.2](https://github.com/MarcoVegaR/mercach/compare/v1.26.1...v1.26.2) (2026-01-20)

### Bug Fixes

- log channel ([958bf4b](https://github.com/MarcoVegaR/mercach/commit/958bf4bcf197eece5fd047096ddc84211f9317eb))

## [1.26.1](https://github.com/MarcoVegaR/mercach/compare/v1.26.0...v1.26.1) (2026-01-20)

### Bug Fixes

- logs ([e71a28f](https://github.com/MarcoVegaR/mercach/commit/e71a28f46c4f707af8c6a7e702fdce3d4c7e05b5))

# [1.26.0](https://github.com/MarcoVegaR/mercach/compare/v1.25.4...v1.26.0) (2026-01-20)

### Features

- **contracts:** implement contract assignment flow ([f3276fa](https://github.com/MarcoVegaR/mercach/commit/f3276fada91ea9fc37a03810abfeae12ab89872a))

## [1.25.4](https://github.com/MarcoVegaR/mercach/compare/v1.25.3...v1.25.4) (2026-01-19)

### Bug Fixes

- cesion de derechos ([23d179f](https://github.com/MarcoVegaR/mercach/commit/23d179fd6e6464375fd18d7578410ecdaea72306))

## [1.25.3](https://github.com/MarcoVegaR/mercach/compare/v1.25.2...v1.25.3) (2026-01-19)

### Bug Fixes

- corregir historical_debts.php y compatibilidad PHP 8.2 ([0e6af3f](https://github.com/MarcoVegaR/mercach/commit/0e6af3faa5f6e7d29351541c2a821c48b07788c5))

## [1.25.2](https://github.com/MarcoVegaR/mercach/compare/v1.25.1...v1.25.2) (2026-01-19)

### Bug Fixes

- ajuste de deudas ([9a7e8b1](https://github.com/MarcoVegaR/mercach/commit/9a7e8b133766fbad5946ed7a2f8a97fa9f607ce2))

## [1.25.1](https://github.com/MarcoVegaR/mercach/compare/v1.25.0...v1.25.1) (2026-01-17)

### Bug Fixes

- bucket requirements ([23b648a](https://github.com/MarcoVegaR/mercach/commit/23b648a9e9db048f0c61cb3c2852dd53a5e072e5))

# [1.25.0](https://github.com/MarcoVegaR/mercach/compare/v1.24.3...v1.25.0) (2025-12-18)

### Features

- **payments:** implement complete handoff from Economic Profile to Payment Apply ([d0a4738](https://github.com/MarcoVegaR/mercach/commit/d0a47382934f428095f5ecd76ca33d32929d6815))

## [1.24.3](https://github.com/MarcoVegaR/mercach/compare/v1.24.2...v1.24.3) (2025-12-17)

### Bug Fixes

- phpstan errors in condo seeders and june 2025 exclusions ([8cf60dd](https://github.com/MarcoVegaR/mercach/commit/8cf60dd63f0df845b160e35137d6073f36fd0ddd))

## [1.24.2](https://github.com/MarcoVegaR/mercach/compare/v1.24.1...v1.24.2) (2025-12-16)

### Bug Fixes

- **payments:** void flow + confirmation UI ([441a697](https://github.com/MarcoVegaR/mercach/commit/441a69736aab9accde076d5b4daf5848dc5e94eb))

## [1.24.1](https://github.com/MarcoVegaR/mercach/compare/v1.24.0...v1.24.1) (2025-12-11)

### Bug Fixes

- truncar tasa BCV a 2 decimales sin redondeo ([b9f2ef5](https://github.com/MarcoVegaR/mercach/commit/b9f2ef534af20045d2b064f9645a26d61bb9221a))

# [1.24.0](https://github.com/MarcoVegaR/mercach/compare/v1.23.1...v1.24.0) (2025-12-08)

### Features

- modern admin payment apply UI ([deecf5d](https://github.com/MarcoVegaR/mercach/commit/deecf5d42e895c287284b0da90c5e2422cda009c))

## [1.23.1](https://github.com/MarcoVegaR/mercach/compare/v1.23.0...v1.23.1) (2025-12-05)

### Bug Fixes

- **payments:** block submit without account or reference length ([361c055](https://github.com/MarcoVegaR/mercach/commit/361c055aba0a928634dc9306a07555626eff078f))

# [1.23.0](https://github.com/MarcoVegaR/mercach/compare/v1.22.0...v1.23.0) (2025-12-05)

### Features

- **payments:** add validations and flags for company bank accounts ([1daabb3](https://github.com/MarcoVegaR/mercach/commit/1daabb3401b566af26e82fc08771888a437ca56e))

# [1.22.0](https://github.com/MarcoVegaR/mercach/compare/v1.21.0...v1.22.0) (2025-12-03)

### Features

- improve portal payment UX and stabilize concessionaire email validation ([7139a12](https://github.com/MarcoVegaR/mercach/commit/7139a128cf7d2a0f093a68a19a245c11158c1768))

# [1.21.0](https://github.com/MarcoVegaR/mercach/compare/v1.20.1...v1.21.0) (2025-12-02)

### Features

- redesign welcome page and adjust appearance theme ([2f8edbf](https://github.com/MarcoVegaR/mercach/commit/2f8edbf8a150634f319cd8b2f61a0a660dd546f6))

## [1.20.1](https://github.com/MarcoVegaR/mercach/compare/v1.20.0...v1.20.1) (2025-11-29)

### Bug Fixes

- unify displayed debt amounts to avoid FX discrepancies ([d9566df](https://github.com/MarcoVegaR/mercach/commit/d9566dffce7d3e5205179918e386a9fa65baed17))

# [1.20.0](https://github.com/MarcoVegaR/mercach/compare/v1.19.2...v1.20.0) (2025-11-29)

### Features

- **portal:** rediseño pantalla aplicar pago y UX ([d976973](https://github.com/MarcoVegaR/mercach/commit/d976973c24bd2a40b94dd0c9cc82ec587f85b1b2))

## [1.19.2](https://github.com/MarcoVegaR/mercach/compare/v1.19.1...v1.19.2) (2025-11-27)

### Bug Fixes

- optimize receipt PDF QR flow and clean tracing logs ([c55f4cf](https://github.com/MarcoVegaR/mercach/commit/c55f4cf707b31a326651c34ea0d59aeaadc6a25a))

## [1.19.1](https://github.com/MarcoVegaR/mercach/compare/v1.19.0...v1.19.1) (2025-11-27)

### Bug Fixes

- **ci:** use .env.testing instead of deleted .env.e2e ([f800467](https://github.com/MarcoVegaR/mercach/commit/f80046763eb95e970e1a5505a948d6bcceeda164))

# [1.19.0](https://github.com/MarcoVegaR/mercach/compare/v1.18.5...v1.19.0) (2025-11-27)

### Features

- **portal:** block admin users from accessing portal routes ([763a0d4](https://github.com/MarcoVegaR/mercach/commit/763a0d4986468e660e399be2d5604059fa35b9fe))

## [1.18.5](https://github.com/MarcoVegaR/mercach/compare/v1.18.4...v1.18.5) (2025-11-27)

### Bug Fixes

- **e2e:** resolve session persistence issues in Playwright tests ([a0acc7c](https://github.com/MarcoVegaR/mercach/commit/a0acc7ca81793960a79167ed38b8f9c4755d29ed))

## [1.18.4](https://github.com/MarcoVegaR/mercach/compare/v1.18.3...v1.18.4) (2025-11-26)

### Bug Fixes

- **ci:** use PostgreSQL instead of SQLite in GitHub Actions ([f588e13](https://github.com/MarcoVegaR/mercach/commit/f588e1375b10f85262dd866326fd98ce183ee2dc))

## [1.18.3](https://github.com/MarcoVegaR/mercach/compare/v1.18.2...v1.18.3) (2025-11-26)

### Bug Fixes

- **ci:** professional e2e test configuration ([f4c7b14](https://github.com/MarcoVegaR/mercach/commit/f4c7b140ecdf15dce2980cd06fa97dc810472f95))

## [1.18.2](https://github.com/MarcoVegaR/mercach/compare/v1.18.1...v1.18.2) (2025-11-26)

### Bug Fixes

- **ci:** resolve GitHub Actions workflow failures ([e007f92](https://github.com/MarcoVegaR/mercach/commit/e007f925fa757fee610af7a3154707665508f3da))

## [1.18.1](https://github.com/MarcoVegaR/mercach/compare/v1.18.0...v1.18.1) (2025-11-26)

### Bug Fixes

- **e2e:** prevent migrate:fresh from touching production database ([59e28a1](https://github.com/MarcoVegaR/mercach/commit/59e28a19d75c31b7e80d0cc4150776edc933e5df))

# [1.18.0](https://github.com/MarcoVegaR/mercach/compare/v1.17.1...v1.18.0) (2025-11-21)

### Features

- nuevos reportes y ajustes en contratos y cobros ([ccf5917](https://github.com/MarcoVegaR/mercach/commit/ccf591759ab0fad7b41997e9860074c586cb3406))

## [1.17.1](https://github.com/MarcoVegaR/mercach/compare/v1.17.0...v1.17.1) (2025-11-15)

### Bug Fixes

- ajustar flujo de MFA y compatibilidad de dashboard con modo oscuro ([5e53732](https://github.com/MarcoVegaR/mercach/commit/5e537324fa94382aafad7aff6c7560c533e029fd))

# [1.17.0](https://github.com/MarcoVegaR/mercach/compare/v1.16.0...v1.17.0) (2025-11-14)

### Features

- **dashboard:** add dashboard.view.finance; hide Finance tab, KPIs and charts ([3cdfd7e](https://github.com/MarcoVegaR/mercach/commit/3cdfd7eb1fafd1d7a3d4187356383fd6c183f53d))

# [1.16.0](https://github.com/MarcoVegaR/mercach/compare/v1.15.2...v1.16.0) (2025-11-10)

### Features

- **analytics,seeders:** add debt analysis pages and fix seeders for historical debts ([b55c5b6](https://github.com/MarcoVegaR/mercach/commit/b55c5b6ed4095cd7081bd1f2dc5904865fef2c2a))

## [1.15.2](https://github.com/MarcoVegaR/mercach/compare/v1.15.1...v1.15.2) (2025-11-03)

### Bug Fixes

- **payments:** accept PMOV E.164 or area+number; transactional createAndVerify ([f6b044b](https://github.com/MarcoVegaR/mercach/commit/f6b044b91297907b5a119de1ae519d67a256d2eb))

## [1.15.1](https://github.com/MarcoVegaR/mercach/compare/v1.15.0...v1.15.1) (2025-10-31)

### Bug Fixes

- **payments:** persist failed gateway result and keep transactional gating ([4f0f830](https://github.com/MarcoVegaR/mercach/commit/4f0f8309056e945077577ac47bcf835a6591d63e))

# [1.15.0](https://github.com/MarcoVegaR/mercach/compare/v1.14.0...v1.15.0) (2025-10-26)

### Features

- **payments:** pmov probe uses dynamic ref; add audit fallback; update tests ([9b6192f](https://github.com/MarcoVegaR/mercach/commit/9b6192fde5dd02d41a95a2b0fe7fe96c080ebbf9))

# [1.14.0](https://github.com/MarcoVegaR/mercach/compare/v1.13.0...v1.14.0) (2025-10-16)

### Features

- **receipts:** add receipt system with PDF generation and public verification ([676278e](https://github.com/MarcoVegaR/mercach/commit/676278eeea1a110735aa360c9aca46131fa2f8b1))

# [1.13.0](https://github.com/MarcoVegaR/mercach/compare/v1.12.0...v1.13.0) (2025-10-13)

### Features

- **ui:** reorganiza sidebar: Operación (Cargos + Pagos) y reagrupa catálogos ([f33b7bd](https://github.com/MarcoVegaR/mercach/commit/f33b7bd1ddfe69094637160daf3625230cd5c3f5))

# [1.12.0](https://github.com/MarcoVegaR/mercach/compare/v1.11.0...v1.12.0) (2025-10-08)

### Features

- **menu:** add bancos and códigos de área to catalogs menu ([fd3dcd2](https://github.com/MarcoVegaR/mercach/commit/fd3dcd2a618bae209db6aa644baf4c84989c64ba))

# [1.11.0](https://github.com/MarcoVegaR/mercach/compare/v1.10.1...v1.11.0) (2025-10-06)

### Features

- **dashboard:** add donuts by status and type; filter and seeder fixes ([61e8c0a](https://github.com/MarcoVegaR/mercach/commit/61e8c0ae68b1683ed57736ea603ab81fda38132e))

## [1.10.1](https://github.com/MarcoVegaR/mercach/compare/v1.10.0...v1.10.1) (2025-10-06)

### Bug Fixes

- revert chart tooltip changes and restore ChartContainer ([8787331](https://github.com/MarcoVegaR/mercach/commit/878733162bb9724e0ca023831cadef15b4688014))

# [1.10.0](https://github.com/MarcoVegaR/mercach/compare/v1.9.0...v1.10.0) (2025-10-03)

### Features

- **condo:** bloquear eliminación de local y tipo de gasto según uso ([7c1f920](https://github.com/MarcoVegaR/mercach/commit/7c1f92031dad49c216036c36a3cabb4a9de2942f))

# [1.9.0](https://github.com/MarcoVegaR/mercach/compare/v1.8.1...v1.9.0) (2025-09-30)

### Features

- **dashboard:** timeline tabla con filtros 30/90d y progreso; ranking dedupe; responsive ([4b1e96d](https://github.com/MarcoVegaR/mercach/commit/4b1e96d3af3178f4411168d816bf02b6aad23708))

## [1.8.1](https://github.com/MarcoVegaR/mercach/compare/v1.8.0...v1.8.1) (2025-09-30)

### Bug Fixes

- corregir filtros de concesionarios y columna rubro en contratos ([10abfe9](https://github.com/MarcoVegaR/mercach/commit/10abfe972faab27d3b6d5fd9d6fe9fb830dd2b3e))

# [1.8.0](https://github.com/MarcoVegaR/mercach/compare/v1.7.0...v1.8.0) (2025-09-29)

### Features

- add comprehensive contracts seeder with 800+ contract records ([259c055](https://github.com/MarcoVegaR/mercach/commit/259c055e569f215a90220933c7a1d11a2da6718c))

# [1.7.0](https://github.com/MarcoVegaR/mercach/compare/v1.6.1...v1.7.0) (2025-09-26)

### Features

- **nav:** add concesionarios icon and grouping ([47a126b](https://github.com/MarcoVegaR/mercach/commit/47a126b160a73bc000576f0f639a10ef991e2eab))

## [1.6.1](https://github.com/MarcoVegaR/mercach/compare/v1.6.0...v1.6.1) (2025-09-23)

### Bug Fixes

- resolve Playwright CI session cookies and improve test robustness ([ee60a5e](https://github.com/MarcoVegaR/mercach/commit/ee60a5eb1820b853c579b531628a1e72643fac12))

# [1.6.0](https://github.com/MarcoVegaR/mercach/compare/v1.5.1...v1.6.0) (2025-09-23)

### Features

- implement loadShowData() services and fix Playwright CI ([1c1db9f](https://github.com/MarcoVegaR/mercach/commit/1c1db9fbde988ab30a5589ccb9235bab37eede07))

## [1.5.1](https://github.com/MarcoVegaR/mercach/compare/v1.5.0...v1.5.1) (2025-09-23)

### Bug Fixes

- revert to Inertia-only pattern for show pages ([7807418](https://github.com/MarcoVegaR/mercach/commit/78074185cf9f7b87a3eb15d111ee89fdafa0c827))

# [1.5.0](https://github.com/MarcoVegaR/mercach/compare/v1.4.0...v1.5.0) (2025-09-21)

### Features

- add locales asociados section to catalog show views ([b745ce3](https://github.com/MarcoVegaR/mercach/commit/b745ce3271a1e3ea3f1036dc28bccc6607ad81f9))

# [1.4.0](https://github.com/MarcoVegaR/mercach/compare/v1.3.1...v1.4.0) (2025-09-21)

### Features

- **sidebar:** modernize menu structure with hierarchical organization ([b087f2a](https://github.com/MarcoVegaR/mercach/commit/b087f2a2c5e9bd4819dce49481caad54547d29b5))

## [1.3.1](https://github.com/MarcoVegaR/mercach/compare/v1.3.0...v1.3.1) (2025-09-19)

### Bug Fixes

- **breadcrumbs:** render Catálogos as non-link and prepend Inicio in breadcrumbs ([3631ba7](https://github.com/MarcoVegaR/mercach/commit/3631ba759956be1280d5a8b0b0bebcb5a41c9928))

# [1.3.0](https://github.com/MarcoVegaR/mercach/compare/v1.2.0...v1.3.0) (2025-09-18)

### Features

- **catalogs/local-location:** add module with validations, seeder, UI, and tests ([267ee66](https://github.com/MarcoVegaR/mercach/commit/267ee66e49c1d7dc872c896345d714a5b00c68b9))

# [1.2.0](https://github.com/MarcoVegaR/mercach/compare/v1.1.0...v1.2.0) (2025-09-18)

### Features

- **catalogs/market:** add MarketsSeeder and menu icon/order ([b963644](https://github.com/MarcoVegaR/mercach/commit/b9636445fd037eee770125dab268b60f211fbf37))

# [1.1.0](https://github.com/MarcoVegaR/mercach/compare/v1.0.0...v1.1.0) (2025-09-17)

### Features

- **catalogs:** stricter validation, safe setActive, tests and docs ([f890499](https://github.com/MarcoVegaR/mercach/commit/f89049951d92612d869c50b69d56c8e1143984b8))

# 1.0.0 (2025-09-13)

### Features

- **catalog:** sync updates from boilerplate ([24df98e](https://github.com/MarcoVegaR/mercach/commit/24df98e62548cf5a30b77a8f561078c6347bd098))
