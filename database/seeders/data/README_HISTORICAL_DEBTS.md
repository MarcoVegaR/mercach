# 📋 Migración de Deuda Histórica

## 🎯 Objetivo

Migrar la deuda histórica de **280 registros de deuda** al sistema, creando cargos individuales por cada mes pendiente desde el último pago registrado.

---

## 📁 Estructura de Archivos

```
database/seeders/data/
├── historical_debts.php         (280 registros consolidados)
└── README_HISTORICAL_DEBTS.md   (Este archivo)

database/seeders/
└── HistoricalDebtsSeeder.php    (Seeder principal)
```

---

## 🔧 Estrategia Implementada

### **1. Cargos Individuales por Mes** ✅

Se crean **cargos separados** por cada mes pendiente:

```php
// Ejemplo: NOHEMI GAFARO (11 meses pendientes desde nov-24)
// Se crean 11 cargos:
- Cargo nov-24 (RENT_EUR_M2, period: 2024-11-01)
- Cargo dic-24 (RENT_EUR_M2, period: 2024-12-01)
- Cargo ene-25 (RENT_EUR_M2, period: 2025-01-01)
- ... hasta sep-25
```

**Ventajas:**

- ✅ Trazabilidad completa por período
- ✅ Reportes precisos de antigüedad
- ✅ Análisis de morosidad detallado
- ✅ Pagos parciales por período específico

### **2. Procesamiento en Tramos**

El seeder procesa los datos en **chunks de 50 registros** para:

- Control granular del proceso
- Manejo eficiente de memoria
- Fácil debugging si hay errores
- Rollback parcial en caso de fallo

### **3. Identificación de Origen**

Los cargos históricos usan el mismo `source` que los cargos regulares:

```php
'source' => 'RENT_RUN'     // Para RENT_EUR_M2
'source' => 'FIXED_RUN'    // Para RENT_EUR_FIXED (si aplica)
'idempotency_key' => "historical_{contract_id}_{local_id}_{period}"
```

**Importante:** No se usa un source especial como "MIGRATION" porque estos son **meses de arrendamiento atrasados**, deben tratarse igual que los cargos normales.

---

## 📊 Formato de Datos

Cada registro en los archivos PHP tiene:

```php
[
    'cedula' => '12376692',
    'nombre' => 'NOHEMI MAXIMINA GAFARO GARCIA',
    'locales' => 'B-25,B-29,B-30,B-31',  // Separados por coma
    'ultimo_pago' => '2024-10',           // Formato: YYYY-MM
    'meses_pendientes' => 11,
]
```

---

## 🚀 Uso

### **Ejecutar Migración Completa**

```bash
php artisan db:seed --class=HistoricalDebtsSeeder
```

### **Ejecutar con Fresh Database**

```bash
php artisan migrate:fresh --seed
```

### **Solo Migración de Deuda (después de seeders principales)**

```bash
php artisan db:seed --class=HistoricalDebtsSeeder
```

---

## 📈 Reporte de Ejecución

El seeder genera un reporte detallado:

```
═══════════════════════════════════════════════════════
📊 REPORTE FINAL - MIGRACIÓN DE DEUDA HISTÓRICA
═══════════════════════════════════════════════════════
✅ Total registros procesados: 220/220
💰 Cargos creados: 2,450
❌ Errores: 0

⚠️  CONCESIONARIOS NO ENCONTRADOS:
   - JUAN PEREZ (12345678)
   ... y 5 más

⚠️  LOCALES NO ENCONTRADOS:
   - MARIA LOPEZ - Locales: A-99,B-99
   ... y 3 más
═══════════════════════════════════════════════════════
```

---

## ⚙️ Configuración

### **Tarifa de Renta**

Actualmente usa una tarifa fija de ejemplo. **AJUSTAR** en el seeder:

```php
// HistoricalDebtsSeeder.php - línea ~140
$ratePerM2 = 2.50; // ⚠️ AJUSTAR según tu lógica de negocio
```

### **Cálculo Real de Tarifa**

Para usar la tarifa real del mercado:

```php
// Opción 1: Desde MarketTariff
$tariff = MarketTariff::where('market_id', $local->market_id)
    ->where('is_active', true)
    ->first();
$ratePerM2 = $tariff ? $tariff->rate_eur_m2 / 100 : 2.50;

// Opción 2: Desde contrato
$ratePerM2 = $contract->rate_eur_m2 / 100;
```

---

## 🔍 Validaciones

El seeder valida automáticamente:

1. ✅ **Concesionario existe** (por cédula)
2. ✅ **Locales existen** (por código)
3. ✅ **Contrato activo** del concesionario
4. ✅ **No duplicar cargos** (verifica por period + local + contract)

---

## ⚠️ Consideraciones Importantes

### **1. Ejecutar DESPUÉS de seeders principales**

```bash
# Orden correcto:
php artisan db:seed --class=PermissionsSeeder
php artisan db:seed --class=UsersSeeder
php artisan db:seed --class=LocalsSeeder
php artisan db:seed --class=ConcessionairesSeeder
php artisan db:seed --class=ContractsSeeder
php artisan db:seed --class=HistoricalDebtsSeeder  # ← AL FINAL
```

### **2. Source de Cargos**

Los cargos históricos usan los mismos valores de `source` que los cargos regulares:

- `RENT_RUN` - Cargos de renta por m² (incluye históricos)
- `FIXED_RUN` - Cargos de renta fija (incluye históricos)
- `CONDO_RUN` - Cargos de condominio

**No existe un source "MIGRATION"** porque los cargos históricos son simplemente meses de arrendamiento atrasados.

### **3. Idempotencia**

El seeder es **idempotente**: puedes ejecutarlo múltiples veces sin duplicar cargos.

---

## 📝 Datos Estadísticos

### **Distribución de Deuda**

| Meses Pendientes | Cantidad de Casos |
| ---------------- | ----------------- |
| 6 meses          | 17 casos          |
| 7 meses          | 73 casos          |
| 8 meses          | 33 casos          |
| 9 meses          | 43 casos          |
| 10 meses         | 24 casos          |
| 11 meses         | 21 casos          |
| 12+ meses        | ~9 casos          |

### **Casos Extremos**

- **Máximo:** 46 meses pendientes (dic-21)
- **Mínimo:** 6 meses pendientes (mar-25)
- **Promedio:** ~11 meses pendientes

---

## 🐛 Troubleshooting

### **Error: Concesionario no encontrado**

```
⚠️ CONCESIONARIOS NO ENCONTRADOS:
   - JUAN PEREZ (12345678)
```

**Solución:** Verificar que el concesionario existe en `ConcessionairesSeeder` con esa cédula.

### **Error: Local no encontrado**

```
⚠️ LOCALES NO ENCONTRADOS:
   - MARIA LOPEZ - Locales: A-99
```

**Solución:** Verificar que el local existe en `LocalsSeeder` con ese código.

### **Error: No se encontró contrato activo**

```
⚠️ No se encontró contrato activo para JUAN PEREZ
```

**Solución:** Verificar que existe un contrato con `contract_status_id = 1` (ACTIVE) para ese concesionario.

---

## 📞 Soporte

Para dudas o problemas:

1. Revisar logs del seeder
2. Verificar datos en archivos `historical_debts_part*.php`
3. Consultar este README

---

**Última actualización:** 21 de octubre de 2025
