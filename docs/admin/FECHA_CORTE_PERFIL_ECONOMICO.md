# Fecha de Corte en Perfil Económico

**Módulo:** Perfil Económico (Admin)  
**Feature:** Consulta histórica de deuda "al día X"

---

## 🎯 ¿Qué es la Fecha de Corte?

La **fecha de corte** te permite consultar cómo estaba la situación económica de un concesionario o local **en una fecha específica del pasado**, no solo hoy.

### Ejemplo Visual

```
HOY (13 Nov 2025)          FECHA CORTE (1 Oct 2025)
═══════════════════        ══════════════════════════
Deuda: Bs. 5,450.00        Deuda: Bs. 3,200.00
Vencido: Bs. 2,100.00      Vencido: Bs. 800.00
Al día: Bs. 3,350.00       Al día: Bs. 2,400.00
```

---

## 💡 Casos de Uso Prácticos

### 1. **Auditorías y Revisión Histórica**

**Escenario:** Un concesionario dice que el 15 de octubre pagó Bs. 2,000 para ponerse al día, pero no hay recibo registrado.

**Solución con fecha de corte:**

1. Consultas perfil al **14 de octubre** (antes del supuesto pago)
    - Ves que debía Bs. 4,500 vencido
2. Consultas perfil al **16 de octubre** (después del supuesto pago)
    - Ves que sigue debiendo Bs. 4,500 vencido
3. **Conclusión:** No hay registro del pago, el reclamo no es válido

**Sin fecha de corte:** Solo verías la deuda de hoy, sin forma de verificar históricos.

---

### 2. **Conciliación con Estados de Cuenta**

**Escenario:** Concesionario trae estado de cuenta bancario de septiembre que muestra pago de Bs. 1,500.

**Solución:**

1. Consulta perfil al **30 de septiembre**
2. Compara saldo según sistema vs estado de cuenta
3. Identifica discrepancias para investigar

---

### 3. **Generación de Reportes Mensuales**

**Escenario:** Necesitas generar reporte de deudas vencidas al cierre de cada mes (ej: 30 de cada mes).

**Solución:**

1. **30 enero:** Exportas CSV con fecha corte = 2025-01-30
2. **28 febrero:** Exportas CSV con fecha corte = 2025-02-28
3. **31 marzo:** Exportas CSV con fecha corte = 2025-03-31

Cada reporte refleja la situación **exacta** de ese día, no afectada por pagos posteriores.

---

### 4. **Reuniones con Concesionarios**

**Escenario:** Tienes reunión programada con concesionario el 20 de noviembre. Hoy es 13 de noviembre.

**Preparación:**

1. Consulta perfil con fecha corte = **hoy (13 nov)**
2. Imprime reporte para discutir en reunión
3. En la reunión (20 nov), consulta con fecha corte = **13 nov** para ver exactamente lo que discutieron
4. Compara con fecha corte = **20 nov** para ver si hubo cambios

**Beneficio:** Evita confusión de "pero hace una semana me dijiste que debía X y ahora dices Y".

---

### 5. **Análisis de Tendencias**

**Escenario:** Quieres analizar cómo ha evolucionado la deuda de un concesionario en los últimos 3 meses.

**Solución:**

```
Fecha Corte    | Deuda Total  | Vencido     | Variación
---------------|------------- |-------------|----------
2025-08-31     | Bs. 2,100    | Bs. 0       | Base
2025-09-30     | Bs. 3,450    | Bs. 500     | +64%
2025-10-31     | Bs. 5,200    | Bs. 1,800   | +51%
2025-11-13     | Bs. 6,100    | Bs. 2,900   | +17%
```

**Insight:** La deuda está creciendo aceleradamente, necesita intervención.

---

### 6. **Validación de Pagos Retroactivos**

**Escenario:** Concesionario pagó fuera de plazo un cargo de septiembre. Se registra el pago el 15 de noviembre pero con fecha efectiva 30 de septiembre.

**Validación:**

1. Consulta perfil con fecha corte = **29 septiembre**
    - Cargo SEP-2025 aparece como pendiente (Bs. 800)
2. Consulta perfil con fecha corte = **30 septiembre**
    - Cargo SEP-2025 aparece como pagado (Bs. 0)
3. **Conclusión:** Sistema registró correctamente el pago retroactivo

---

### 7. **Resolución de Disputas**

**Escenario:** Concesionario afirma que "hace 2 meses estaba al día, no sé por qué ahora debo tanto".

**Investigación:**

1. Consulta con fecha corte = **hace 2 meses (13 septiembre)**
    - Confirmas: estaba al día (Bs. 0 vencido)
2. Consulta mes por mes hasta hoy
    - **Octubre:** Bs. 950 vencido (cargo CONDO-OCT no pagado)
    - **Noviembre:** Bs. 2,100 vencido (sumado CONDO-NOV)
3. Muestras timeline al concesionario con evidencia clara

**Sin fecha de corte:** Solo dirías "hoy debes Bs. 2,100" sin poder explicar el origen.

---

## 🔧 Cómo Funciona Técnicamente

### Backend (EconomicProfileService.php)

```php
public function forConcessionaire(int $id, ?CarbonImmutable $at, array $filters): array
{
    // Si no hay fecha de corte, usa hoy
    $at = $at ?: Carbon::now();

    // Consulta cargos que existían al día $at
    // Solo incluye cargos con created_at <= $at
    // Y pagos aplicados antes o en la fecha $at
}
```

### Frontend

```tsx
// Input de fecha en página de búsqueda
<input type="date" value={at} onChange={(e) => setAt(e.target.value)} />;

// Al hacer clic en resultado, pasa fecha como parámetro
router.visit(`/admin/economic-profile/concessionaire/${id}?at=${at}`);
```

---

## 📊 Qué se Calcula con Fecha de Corte

### Incluye (todo al día X)

✅ **Cargos creados** hasta la fecha de corte  
✅ **Pagos aplicados** hasta la fecha de corte  
✅ **Créditos emitidos** hasta la fecha de corte  
✅ **Estado vencido/al día** según fecha de corte

### No Incluye

❌ Cargos creados **después** de la fecha de corte  
❌ Pagos aplicados **después** de la fecha de corte  
❌ Cambios realizados después de esa fecha

---

## 💼 Ejemplo Completo: Flujo Real

### Contexto

- **Concesionario:** Juan Pérez
- **Hoy:** 13 de noviembre de 2025
- **Problema:** Juan dice que pagó todo el 5 de noviembre

### Investigación Paso a Paso

#### 1. Consulta Previa al Supuesto Pago

```
Fecha de corte: 4 de noviembre de 2025

RESULTADO:
- Cargos abiertos:
  * CONDO-SEP-2025: Bs. 850 (vencido desde 30-sep)
  * RENT-OCT-2025: Bs. 1,200 (vencido desde 31-oct)
  * CONDO-NOV-2025: Bs. 850 (al día, vence 30-nov)

TOTAL VENCIDO: Bs. 2,050
TOTAL AL DÍA: Bs. 850
```

#### 2. Consulta Posterior al Supuesto Pago

```
Fecha de corte: 6 de noviembre de 2025

RESULTADO:
- Cargos abiertos:
  * CONDO-SEP-2025: Bs. 850 (vencido)
  * RENT-OCT-2025: Bs. 1,200 (vencido)
  * CONDO-NOV-2025: Bs. 850 (al día)

TOTAL VENCIDO: Bs. 2,050
TOTAL AL DÍA: Bs. 850

PAGOS REGISTRADOS EL 5-NOV: 0
```

#### 3. Conclusión

**No hay pago registrado el 5 de noviembre.** Juan debe:

- Presentar comprobante del pago
- O reconocer que el pago no se realizó

---

## 🎨 UI/UX de Fecha de Corte

### Página de Búsqueda

```
┌─────────────────────────────────────┐
│ 📅 Fecha de corte                   │
│ [13/11/2025        ]                │
│                                     │
│ 🔍 Buscar                           │
│ [Juan Pérez...                ]    │
└─────────────────────────────────────┘
```

### Perfil con Fecha Histórica

```
┌─────────────────────────────────────────────┐
│ ⚠️ VISTA HISTÓRICA                          │
│ Mostrando datos al 1 de octubre de 2025    │
│                                             │
│ Deuda Total: Bs. 3,200.00                   │
│ Vencido: Bs. 800.00                         │
└─────────────────────────────────────────────┘
```

---

## 🚀 Mejoras Futuras Sugeridas

### Corto Plazo

- [ ] **Alerta visual** cuando fecha de corte ≠ hoy
- [ ] **Badge** "Histórico" en header del perfil
- [ ] **Botón "Ver hoy"** para volver rápidamente a fecha actual

### Mediano Plazo

- [ ] **Comparación lado a lado:** Ver fecha X vs hoy
- [ ] **Timeline interactiva:** Slider para navegar por fechas
- [ ] **Snapshots guardados:** Guardar consultas frecuentes

### Largo Plazo

- [ ] **Animación de evolución:** Ver deuda creciendo en el tiempo
- [ ] **Predicciones:** Proyectar deuda futura basada en histórico
- [ ] **Alertas automáticas:** Notificar cuando deuda crece X% en Y días

---

## ⚠️ Limitaciones y Consideraciones

### ¿Qué pasa si consulto una fecha muy antigua?

**Ejemplo:** Consultas fecha de corte = 1 de enero de 2024, pero el sistema se implementó en septiembre de 2025.

**Resultado:** No hay datos, mostrará perfil vacío (Bs. 0 en todo).

### ¿Los datos históricos son 100% exactos?

**Sí, SI:** Los cargos, pagos y créditos se crearon/aplicaron correctamente en su momento.

**No, SI:** Hubo correcciones manuales en la BD que alteraron fechas retroactivamente (no recomendado).

### ¿Puedo consultar fechas futuras?

**Sí técnicamente**, pero no es útil:

- Solo verás cargos ya creados (facturados por adelantado)
- No aparecen cargos futuros que aún no se han generado

---

## 📚 Recursos Relacionados

- **Documentación principal:** `docs/admin/ECONOMIC_PROFILE_MODERNIZATION.md`
- **Service backend:** `app/Services/EconomicProfileService.php`
- **Controller:** `app/Http/Controllers/Admin/EconomicProfileController.php`
- **Frontend:** `resources/js/pages/admin/economic-profile/`

---

**Fin del documento**
