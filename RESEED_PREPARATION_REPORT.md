# Reporte de Preparación para Reseed - Enero 2026

**Fecha**: 2026-01-19  
**Objetivo**: Preparar datos para reseed incluyendo enero 2026 (tasas FX, deuda histórica, gastos comunes)

---

## ✅ Resumen Ejecutivo

Todos los pasos de preparación completados exitosamente:

- ✅ **Paso 0**: Descartado (sin cambio de limitación de períodos en generación de cargos)
- ✅ **Paso 1**: FxRatesOctober2025Seeder actualizado con tasas dic-2025/ene-2026 + truncamiento correcto
- ✅ **Paso 2**: Incrementados `meses_pendientes` en +1 para incluir enero 2026 (355 registros)
- ✅ **Paso 3A**: Aplicados 43 ajustes manuales de deuda con resolución "más reciente"
- ✅ **Paso 3B**: Limpiados 8 CSV de gastos comunes según reglas de solvencia

---

## 📋 Paso 1: Tasas FX (Diciembre 2025 + Enero 2026)

### Archivo modificado

- `database/seeders/FxRatesOctober2025Seeder.php`

### Cambios realizados

1. **Agregadas 20 nuevas tasas**:

    - **Diciembre 2025**: 6 tasas (19, 22, 23, 26, 29, 30)
    - **Enero 2026**: 14 tasas (02, 05, 06, 07, 08, 09, 13, 14, 15, 16, 19)

2. **Corrección de truncamiento**:

    - ❌ Antes: `round($rate, 2)` (redondeo incorrecto)
    - ✅ Ahora: `BigDecimal::of((string) $rate)->toScale(2, RoundingMode::DOWN)` (truncamiento a 2 decimales)

3. **Manejo de tasas nulas**:
    - Agregado `if ($rateToVes === null) { continue; }` para evitar insertar `0.00`

### Validación

```bash
# Verificar tasas insertadas para dic-2025 y ene-2026
php artisan tinker
>>> use App\Models\FxRate;
>>> FxRate::whereBetween('value_date', ['2025-12-01', '2026-01-31'])->count();
# Debe retornar: 20 tasas

>>> FxRate::where('value_date', '2026-01-19')->first(['currency', 'rate_to_ves']);
# Debe retornar: EUR => 396.47 (feriado, repite tasa del 16)

>>> FxRate::where('value_date', '2026-01-16')->first(['currency', 'rate_to_ves']);
# Debe retornar: EUR => 396.47
```

---

## 📋 Paso 2: Incremento de Meses Pendientes

### Archivo modificado

- `database/seeders/data/historical_debts.php`

### Cambios realizados

- **Total registros**: 349
- **Registros modificados**: 355 ocurrencias de `meses_pendientes`
- **Operación**: Cada valor incrementado en +1 para incluir enero 2026
- **Backup guardado**: `database/seeders/data/historical_debts.php.bak`

### Distribución antes/después

| Valor Original | Valor Final | Registros |
| -------------- | ----------- | --------- |
| 0 → 1          | 1 → 2       | 40        |
| 1 → 2          | 2 → 3       | 60        |
| 2 → 3          | 3 → 4       | 39        |
| ...            | ...         | ...       |
| 17 → 18        | 18 → 19     | 28        |
| 62 → 63        | 63 → 64     | 1         |

### Validación

```bash
# Verificar total de registros
php -r '$a=require "database/seeders/data/historical_debts.php"; echo count($a)."\n";'
# Debe retornar: 349

# Verificar suma total de meses pendientes
php -r '$a=require "database/seeders/data/historical_debts.php"; $s=0; foreach($a as $r) $s+=$r["meses_pendientes"]; echo $s."\n";'
# Debe retornar: 2960 (antes era 2605, incremento de +355)
```

---

## 📋 Paso 3A: Ajustes Manuales de Deuda

### Archivo modificado

- `database/seeders/data/historical_debts.php`

### Cambios realizados

Aplicados **43 ajustes manuales** según lista proporcionada, con resolución por "fecha más reciente":

| Local(es)                         | Cambio Aplicado             | Razón                                |
| --------------------------------- | --------------------------- | ------------------------------------ |
| B-34                              | 2025-10 / 4→2 meses         | Solvente hasta oct-2025              |
| B-35, B-36                        | 2025-10 / 2→2 meses         | Confirmación solvencia oct-2025      |
| I-07                              | 2025-11 / 3→1 meses         | Solvente hasta nov-2025              |
| BM-12                             | 2025-11 / 3→1 meses         | Solvente hasta nov-2025              |
| HM-12A                            | 2025-09 / 5→3 meses         | Solvente hasta sep-2025              |
| HM-12B                            | 2025-09 / 3→3 meses         | Confirmación sep-2025                |
| HM-27                             | 2025-12 / 2→1 meses         | Sin deuda                            |
| K-02                              | 2025-10 / 4→2 meses         | Solvente hasta oct-2025              |
| D-22, D-23, D-24, D-25            | 2025-12 / 1-2→1 meses       | Sin deuda                            |
| DM-15                             | 2025-08→2025-07 / 6→5 meses | Corrección fecha + solvente jul-2025 |
| DM-16                             | 2025-07 / 5→5 meses         | Confirmación jul-2025                |
| AM-09, AM-10, AM-11, AM-12        | 2025-12 / 1-2→1 meses       | Sin deuda                            |
| DM-11, DM-12, DM-13, DM-14        | 2025-08 / 4-6→4 meses       | Solvente hasta ago-2025              |
| A-23, A-24                        | 2025-10 / 2-4→2 meses       | Solvente hasta oct-2025              |
| S-61                              | 2025-07→2025-06 / 7→6 meses | Corrección fecha (saltó julio)       |
| FL-09, FL-11                      | 2025-12 / 1-2→1 meses       | Sin deuda                            |
| HM-21                             | 2025-09→2025-08 / 5→4 meses | Corrección fecha + solvente ago-2025 |
| H-14, HM-08, A-27, H-12           | 2025-12 / 2→1 meses         | Sin deuda                            |
| F-22                              | 2025-10 / 4→2 meses         | Solvente hasta oct-2025              |
| D-02, D-03, D-04                  | 2024-07 / 18-19→18 meses    | Deuda desde ago-2024                 |
| AM-13, AM-14, AM-15, AM-16, AM-17 | 2025-12 / 1-2→1 meses       | Sin deuda                            |
| G-12                              | 2025-11 / 3→1 meses         | Solvente hasta nov-2025              |

### Validación

```bash
# Verificar ajustes específicos
php -r '
$a = require "database/seeders/data/historical_debts.php";
$checks = [
    "B-34" => ["ultimo_pago" => "2025-10", "meses_pendientes" => 2],
    "I-07" => ["ultimo_pago" => "2025-11", "meses_pendientes" => 1],
    "DM-15" => ["ultimo_pago" => "2025-07", "meses_pendientes" => 5],
    "S-61" => ["ultimo_pago" => "2025-06", "meses_pendientes" => 6],
    "G-12" => ["ultimo_pago" => "2025-11", "meses_pendientes" => 1],
];
foreach ($a as $r) {
    foreach ($checks as $local => $expected) {
        if (strpos($r["locales"], $local) !== false) {
            $match = $r["ultimo_pago"] === $expected["ultimo_pago"] &&
                     $r["meses_pendientes"] === $expected["meses_pendientes"];
            echo "$local: " . ($match ? "✓" : "✗") . " ({$r["ultimo_pago"]}, {$r["meses_pendientes"]} meses)\n";
        }
    }
}
'
```

---

## 📋 Paso 3B: Limpieza de CSV Gastos Comunes

### Archivos modificados

- `database/seeders/data/locales_solo_agosto.csv`
- `database/seeders/data/locales_solo_septiembre.csv`
- `database/seeders/data/locales_solo_octubre.csv`
- `database/seeders/data/locales_solo_noviembre.csv`
- `database/seeders/data/locales_solo_diciembre.csv`
- `database/seeders/data/locales_solo_enero_2025.csv`
- `database/seeders/data/locales_solo_febrero_2025.csv`
- `database/seeders/data/locales_solo_marzo_2025.csv`
- `database/seeders/data/locales_solo_abril_2025.csv`
- `database/seeders/data/locales_solo_mayo_2025.csv`

### Reglas aplicadas

| Local     | Regla               | Meses Removidos                             |
| --------- | ------------------- | ------------------------------------------- |
| **J-06**  | Solo desde dic-2024 | ago, sep, oct, nov 2024                     |
| **HM-24** | Solo desde ene-2025 | ago, sep, oct, nov, dic 2024                |
| **G-15B** | Solo desde jun-2025 | ago, sep, oct, nov, dic 2024 + ene-may 2025 |

### Validación

```bash
# Verificar que los locales NO aparecen en los meses prohibidos
grep -n "^J-06$" database/seeders/data/locales_solo_agosto.csv
# Debe retornar: (vacío)

grep -n "^HM-24$" database/seeders/data/locales_solo_diciembre.csv
# Debe retornar: (vacío)

grep -n "^G-15B$" database/seeders/data/locales_solo_mayo_2025.csv
# Debe retornar: (vacío)

# Verificar que SÍ aparecen en los meses permitidos
grep -n "^J-06$" database/seeders/data/locales_solo_diciembre.csv
# Debe retornar: línea con J-06

grep -n "^HM-24$" database/seeders/data/locales_solo_enero_2025.csv
# Debe retornar: línea con HM-24

grep -n "^G-15B$" database/seeders/data/locales_solo_junio_2025.csv
# Debe retornar: línea con G-15B
```

---

## 🧪 Validaciones Finales

### 1. Reseed completo

```bash
# Limpiar BD y reseed
php artisan migrate:fresh --seed

# Verificar tasas FX
php artisan tinker
>>> \App\Models\FxRate::whereBetween('value_date', ['2025-12-01', '2026-01-31'])->count();
# Esperado: 20

>>> \App\Models\FxRate::where('value_date', '2026-01-16')->where('currency', 'EUR')->value('rate_to_ves');
# Esperado: "396.47" (truncado, no redondeado)
```

### 2. Generación de cargos enero 2026

```bash
# Generar cargos para enero 2026 (desde UI o comando)
# Mercado: 1 (Mercado Central)
# Período: 2026-01

# Verificar cargos generados
php artisan tinker
>>> \App\Models\Charge::where('period', '2026-01-01')->count();
# Esperado: ~600 cargos (depende de contratos activos)

>>> \App\Models\Charge::where('period', '2026-01-01')->groupBy('kind')->selectRaw('kind, count(*) as total')->get();
# Esperado: distribución por tipo (RENT_EUR_M2, RENT_EUR_FIXED, CONDO_USD)
```

### 3. Verificar deuda histórica enero 2026

```bash
php artisan tinker
>>> $debt = \App\Models\Charge::where('period', '2026-01-01')
    ->where('charge_status_id', 1) // ISSUED
    ->sum('amount_minor');
>>> echo "Deuda enero 2026: " . ($debt / 100) . " EUR\n";
# Comparar con expectativa basada en contratos activos
```

### 4. Verificar gastos comunes

```bash
# Verificar que J-06, HM-24, G-15B NO tienen cargos CONDO_USD en meses prohibidos
php artisan tinker
>>> $prohibited = [
    ['local' => 'J-06', 'before' => '2024-12-01'],
    ['local' => 'HM-24', 'before' => '2025-01-01'],
    ['local' => 'G-15B', 'before' => '2025-06-01'],
];
>>> foreach ($prohibited as $rule) {
    $local = \App\Models\Local::where('code', $rule['local'])->first();
    if ($local) {
        $count = \App\Models\Charge::where('local_id', $local->id)
            ->where('kind', 'CONDO_USD')
            ->where('period', '<', $rule['before'])
            ->count();
        echo "{$rule['local']}: $count cargos antes de {$rule['before']} (esperado: 0)\n";
    }
}
```

---

## 📊 Resumen de Cambios

### Archivos modificados

1. **Seeder FX Rates**:

    - `database/seeders/FxRatesOctober2025Seeder.php` (+20 tasas, truncamiento corregido)

2. **Deuda histórica**:

    - `database/seeders/data/historical_debts.php` (355 incrementos + 43 ajustes manuales)
    - Backup: `database/seeders/data/historical_debts.php.bak`

3. **Gastos comunes** (10 archivos CSV):
    - Removidos J-06, HM-24, G-15B según reglas de solvencia

### Estadísticas

- **Tasas FX agregadas**: 20 (dic-2025: 6, ene-2026: 14)
- **Registros deuda incrementados**: 355
- **Ajustes manuales aplicados**: 43 locales
- **CSV limpiados**: 10 archivos
- **Locales removidos de CSV**: 3 (J-06, HM-24, G-15B) en múltiples meses

---

## ⚠️ Notas Importantes

1. **Truncamiento vs Redondeo**: Las tasas FX ahora usan truncamiento real (`RoundingMode::DOWN`) en lugar de redondeo. Esto puede generar diferencias de céntimos en cargos históricos.

2. **Backup disponible**: El archivo `historical_debts.php.bak` contiene el estado previo al Paso 2 (antes del incremento masivo).

3. **Paso 0 descartado**: No se implementó limitación de períodos en `RunController::preflight`. La generación de cargos para enero 2026 está permitida si existen contratos, tariffs y condo_periods.

4. **Validación de locales**: Todos los locales en ajustes manuales (B-34, I-07, etc.) existen en `LocalsSeeder.php`.

5. **Gastos comunes**: Los CSV modificados afectan la generación de cargos `CONDO_USD`. Verificar que los 3 locales (J-06, HM-24, G-15B) no tengan cargos en meses prohibidos después del reseed.

---

## 🚀 Próximos Pasos

1. **Reseed en desarrollo**:

    ```bash
    php artisan migrate:fresh --seed
    ```

2. **Generar cargos enero 2026** desde UI o comando

3. **Ejecutar validaciones** (comandos en sección anterior)

4. **Verificar totales** contra expectativas:

    - Cargos enero 2026 por tipo
    - Deuda total en EUR
    - Locales sin cargos prohibidos

5. **Commit cambios**:

    ```bash
    git add database/seeders/FxRatesOctober2025Seeder.php
    git add database/seeders/data/historical_debts.php
    git add database/seeders/data/locales_solo_*.csv
    git commit -m "feat: preparar datos para reseed enero 2026

    - Agregar tasas FX dic-2025 y ene-2026 con truncamiento correcto
    - Incrementar meses_pendientes +1 en deuda histórica (355 registros)
    - Aplicar 43 ajustes manuales de deuda con resolución 'más reciente'
    - Limpiar CSV gastos comunes según reglas solvencia (J-06, HM-24, G-15B)"
    ```

---

**Fin del reporte**
