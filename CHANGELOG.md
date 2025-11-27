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
