# Checklist de instalación - Portal UX Moderno

## 1. Instalar dependencias NPM

```bash
npm install @radix-ui/react-progress
```

## 2. Compilar assets

```bash
npm run build
# o para desarrollo:
npm run dev
```

## 3. Verificar rutas

Las siguientes rutas deben estar disponibles:

- ✅ `GET /portal/pagos` - Lista de pagos
- ✅ `GET /portal/pagos/{payment}/aplicar` - Wizard de aplicación
- ✅ `GET /portal/pagos/{payment}/open-charges` - API cargos abiertos
- ✅ `POST /portal/pagos/{payment}/allocations/suggest` - API sugerencias
- ✅ `POST /portal/pagos/{payment}/allocations/preview` - API preview
- ✅ `POST /portal/pagos/{payment}/allocations` - API aplicar

## 4. Testing manual recomendado

### Test 1: Listar pagos

1. Navegar a `/portal/pagos`
2. Verificar que muestre:
    - Sección "Pagos listos para aplicar" (si hay CONFIRMED con disponible)
    - Sección "Pagos aplicados" (si hay APPLIED)
    - Botón "Registrar nuevo pago"

### Test 2: Aplicar pago (Paso 1 - Sugerencia)

1. Hacer clic en "Aplicar a deudas" de un pago CONFIRMED
2. Verificar que cargue automáticamente sugerencias
3. Debe mostrar:
    - Alert con mensaje "Hemos seleccionado automáticamente..."
    - Lista de deudas sugeridas (máximo 5 visibles)
    - Botón "Continuar"

### Test 3: Aplicar pago (Paso 2 - Revisión)

1. Hacer clic en "Continuar"
2. Verificar que muestre:
    - Card "Saldo disponible" con barra de progreso
    - Card "Deudas seleccionadas"
    - Sección "Deudas vencidas" (si aplica) con ícono rojo
    - Sección "Deudas al día"
    - Checkboxes funcionales (click para seleccionar/deseleccionar)
    - Toggle "Usar mi saldo a favor" (si tiene crédito)

### Test 4: Aplicar pago (Paso 3 - Confirmación)

1. Hacer clic en "Continuar"
2. Verificar que muestre:
    - Alert azul con resumen
    - Card "Resumen de aplicación" con desglose
    - Card "Deudas que serán cubiertas"
    - Botón verde "Aplicar pago"
3. Hacer clic en "Aplicar pago"
4. Verificar redirección a `/portal/pagos`
5. Verificar mensaje de éxito

### Test 5: Navegación entre pasos

1. En cualquier paso, verificar que:
    - Botón "Atrás" funcione correctamente
    - El indicador de pasos muestre el paso actual destacado
2. Verificar que los datos persistan al ir atrás

### Test 6: Validaciones

1. Intentar aplicar sin seleccionar deudas
2. Verificar mensaje de error claro
3. Intentar aplicar más del disponible
4. Verificar alerta de límite

## 5. Verificar estilos

Asegurarse de que los siguientes componentes se vean correctamente:

- [ ] Badges de estado (colores correctos)
- [ ] Barras de progreso (azul, animadas)
- [ ] Cards con bordes de colores según estado
- [ ] Íconos (CheckCircle2, Clock, AlertCircle, etc.)
- [ ] Botones con tamaños apropiados
- [ ] Responsive en móvil (grid cols colapsan correctamente)

## 6. Testing de edge cases

### Caso 1: Sin deudas pendientes

1. Probar con un concesionario sin deudas
2. Debe mostrar: "No hay deudas pendientes para aplicar este pago"

### Caso 2: Pago ya aplicado totalmente

1. Probar con un pago donde available_bs_minor = 0
2. No debe aparecer en "Pagos listos para aplicar"
3. Debe aparecer en "Pagos aplicados"

### Caso 3: Pago parcialmente aplicado

1. Probar con un pago que tenga applied_bs_minor > 0 pero available_bs_minor > 0
2. Debe mostrar barra de progreso en la lista
3. Al entrar al wizard, debe sugerir solo por el disponible restante

### Caso 4: Usuario sin saldo a favor

1. customer_credit_bs_minor = 0
2. No debe mostrar el toggle "Usar mi saldo a favor"

### Caso 5: Usuario con saldo a favor

1. customer_credit_bs_minor > 0
2. Debe mostrar toggle
3. Al activarlo, debe sumar al disponible total
4. Debe reflejarse en el resumen final

## 7. Verificar comportamiento de la API

### Endpoint: GET /portal/pagos/{payment}/open-charges

```bash
curl -X GET "http://localhost/portal/pagos/123/open-charges" \
  -H "Accept: application/json" \
  -H "Cookie: ..."
```

Debe retornar:

```json
{
    "items": [
        {
            "charge_id": 1,
            "period": "2025-01",
            "due_on": "2025-01-31",
            "currency": "USD",
            "outstanding_bs_minor": 50000,
            "kind": "CONDO_USD"
        }
    ]
}
```

### Endpoint: POST /portal/pagos/{payment}/allocations/suggest

```bash
curl -X POST "http://localhost/portal/pagos/123/allocations/suggest" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Cookie: ..." \
  -d '{"strategy": "fifo"}'
```

Debe retornar:

```json
{
    "items": [
        {
            "charge_id": 1,
            "amount_bs_minor": 50000
        }
    ],
    "summary": {
        "available_bs_minor": 100000,
        "suggested_bs_minor": 50000,
        "after_available_bs_minor": 50000
    }
}
```

## 8. Monitoreo de errores

Verificar logs de Laravel durante el testing:

```bash
tail -f storage/logs/laravel.log
```

Errores comunes a buscar:

- `No se pudieron obtener los cargos abiertos`
- `No se pudo obtener sugerencia`
- `Error al aplicar el pago`
- Errores de scope/permisos (403, 404)

## 9. Performance

Verificar tiempos de carga:

- [ ] Lista de pagos: < 500ms
- [ ] Carga de wizard (Paso 1): < 1s (incluye fetch de charges + suggest)
- [ ] Transición entre pasos: < 100ms (local)
- [ ] Aplicación final: < 2s (incluye validación + DB writes + receipts)

## 10. Rollback (si es necesario)

Si hay problemas, se puede volver a las versiones originales:

### En el controlador:

```php
// Cambiar de:
return Inertia::render('portal/payments/index-modern', [

// A:
return Inertia::render('portal/payments/index', [
```

```php
// Cambiar de:
return Inertia::render('portal/payments/apply-modern', [

// A:
return Inertia::render('portal/payments/apply', [
```

### En routes/portal.php:

```php
// Cambiar de:
Route::get('/portal/pagos/{payment}/aplicar', ...

// A:
Route::get('/portal/pagos/{payment}/cruzar', ...
```

## 11. Documentar cambios en CHANGELOG

Añadir entrada:

```markdown
## [Unreleased]

### Changed

- Modernizó la UX del portal de pagos siguiendo mejores prácticas de portales financieros
- Cambió terminología: "Cruzar pago" → "Aplicar pago a mis deudas"
- Implementó wizard de 3 pasos para aplicación de pagos
- Mejoró visualización de lista de pagos con agrupación inteligente
- Añadió sugerencias automáticas priorizando deudas vencidas (FIFO)
- Añadió barras de progreso visuales y feedback en tiempo real

### Added

- Componente Progress (barra de progreso)
- Páginas modernas: index-modern.tsx, apply-modern.tsx
- Documentación UX: PORTAL_UX_MODERNIZATION.md
```

## 12. Capacitación de usuarios

Preparar material para usuarios:

- [ ] Screenshot del nuevo flujo (3 pasos)
- [ ] Video tutorial corto (< 2 min)
- [ ] FAQ sobre cambios
- [ ] Comunicado de mejoras

## 13. Métricas a monitorear post-lanzamiento

Configurar tracking de:

- Tasa de completación del wizard (step 1 → step 3)
- Tasa de abandono en cada paso
- Tiempo promedio para completar aplicación
- Uso de sugerencias automáticas vs manual
- Uso del toggle "Usar saldo a favor"
- Errores de validación más comunes

---

## Resumen de cambios

✅ **Backend**: Sin cambios (APIs ya existían)  
✅ **Frontend**: Nuevas páginas modernas  
✅ **Rutas**: Cambio de URL (`/cruzar` → `/aplicar`)  
✅ **Base de datos**: Sin cambios (schema compatible)  
✅ **Dependencias**: `@radix-ui/react-progress` (nueva)

**Tiempo estimado de instalación**: 15 minutos  
**Tiempo estimado de testing**: 30 minutos  
**Riesgo**: Bajo (archivos originales preservados, rollback fácil)
