# Modernización de la Vista "Aplicar Pago"

## Objetivo

Rediseñar `/payments/{id}?tab=apply` con UX moderna, profesional y guiada basada en buenas prácticas de diseño.

## Problemas Actuales

### UX

- **No guiado**: Usuario no sabe qué hacer primero
- **Tabla densa**: Difícil de escanear, especialmente en mobile
- **Filtros ocultos**: En Sheet, poco accesibles
- **Estrategias confusas**: Dropdown técnico sin explicaciones
- **Sin feedback visual**: No hay indicadores claros de progreso
- **Inputs sin formato**: Montos poco claros visualmente

### Flujo Actual

1. Usuario ve tabla vacía
2. Busca botón "Obtener cargos"
3. Aplica filtros (si los encuentra)
4. Elige estrategia (si entiende qué hacen)
5. Revisa tabla densa
6. Aplica selección

## Diseño Propuesto

### Progressive Disclosure: 3 Pasos Visuales

#### **PASO 1: Cargar Cargos**

```
┌─────────────────────────────────────────────┐
│ 🎯 Paso 1: Buscar cargos pendientes         │
├─────────────────────────────────────────────┤
│                                             │
│   [Filtros inline opcionales]              │
│   ┌──────────────┐ ┌──────────────┐        │
│   │ Moneda: USD ▼│ │ Tipo: Todas ▼│        │
│   └──────────────┘ └──────────────┘        │
│                                             │
│   ☑ Solo cargos vencidos                   │
│                                             │
│   [Buscar cargos →]                        │
│                                             │
└─────────────────────────────────────────────┘
```

**Mejoras:**

- Filtros inline (no en Sheet)
- Lenguaje simple: "Solo cargos vencidos" vs "overdue_only"
- Botón primario grande con CTA claro
- Loading state: "Buscando cargos..." con spinner

#### **PASO 2: Elegir Estrategia**

```
┌─────────────────────────────────────────────┐
│ 💡 Paso 2: ¿Cómo quieres distribuir?       │
├─────────────────────────────────────────────┤
│                                             │
│  ┌──────────────┐ ┌──────────────┐ ┌─────┐ │
│  │ 🕐 FIFO      │ │ ⚖ Proporcional│ │ ✏  │ │
│  │              │ │               │ │     │ │
│  │ Por          │ │ Distribuye    │ │ Yo  │ │
│  │ antigüedad   │ │ equitativamente│ │ lo  │ │
│  │              │ │               │ │ hago│ │
│  │ [Automático] │ │ [Automático]  │ │     │ │
│  └──────────────┘ └──────────────┘ └─────┘ │
│                                             │
│  ? ¿Cuál elegir? Ver guía →                │
│                                             │
└─────────────────────────────────────────────┘
```

**Cards grandes como `create-modern.tsx`:**

- **FIFO Card**:

    - Icono: `Clock`
    - Título: "Por antigüedad"
    - Descripción: "Paga primero los cargos más antiguos"
    - Badge: "Automático"
    - Gradiente azul
    - Hover: scale + shadow

- **Proporcional Card**:

    - Icono: `Scale`
    - Título: "Proporcional"
    - Descripción: "Distribuye el monto equitativamente entre todos los cargos"
    - Badge: "Automático"
    - Gradiente verde
    - Hover: scale + shadow

- **Manual Card**:
    - Icono: `Edit3`
    - Título: "Personalizado"
    - Descripción: "Tú decides cuánto a cada cargo"
    - Badge: "Manual"
    - Gradiente gris
    - Hover: scale + shadow

**Tooltip "¿Cuál elegir?":**

- FIFO: Mejor para pagos antiguos prioritarios
- Proporcional: Mejor para distribuir uniforme
- Manual: Control total

#### **PASO 3: Revisar y Confirmar**

```
┌─────────────────────────────────────────────┐
│ ✅ Paso 3: Revisa y confirma                │
├─────────────────────────────────────────────┤
│                                             │
│  📊 Distribución:                           │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ 85%    │
│                                             │
│  Cargos seleccionados: 8 de 12             │
│  Monto a aplicar: Bs 12,450.00             │
│  Disponible restante: Bs 2,250.00          │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │ Local 101 • Ago 2024 • Alquiler      │  │
│  │ Bs 1,500.00 ✓                        │  │
│  ├──────────────────────────────────────┤  │
│  │ Local 102 • Ago 2024 • Condominio   │  │
│  │ Bs 800.00 ✓                          │  │
│  └──────────────────────────────────────┘  │
│                                             │
│  [← Ajustar]  [Confirmar aplicación →]    │
│                                             │
└─────────────────────────────────────────────┘
```

**Cards de cargo (no tabla):**

- Info compacta y visual
- Checkmark verde cuando completo
- Badge rojo "VENCIDO" si aplica
- Editable inline (manual mode)
- Swipe para eliminar (mobile)

**Progress Bar:**

- Visual del % usado
- Colores: verde < 85%, amarillo 85-95%, rojo > 95%

### Componentes Nuevos

#### 1. `ApplyStepProgress.tsx`

```tsx
<div className="flex items-center gap-4">
  {[1, 2, 3].map((s) => (
    <div key={s} className={step >= s ? 'text-blue-600' : 'text-slate-400'}>
      <div className={`flex h-10 w-10 items-center justify-center rounded-full ${
        step >= s ? 'bg-blue-600 text-white' : 'bg-slate-200'
      }`}>
        {step > s ? <Check /> : s}
      </div>
      <span className="text-xs mt-1">
        {s === 1 ? 'Cargos' : s === 2 ? 'Estrategia' : 'Confirmar'}
      </span>
    </div>
    {s < 3 && <div className={`h-0.5 flex-1 ${step > s ? 'bg-blue-600' : 'bg-slate-200'}`} />}
  </Fragment>
))}
</div>
```

#### 2. `StrategyCard.tsx`

```tsx
<button onClick={onSelect} className="group text-left">
    <Card className="h-full cursor-pointer border-2 transition-all hover:border-blue-500 hover:shadow-xl">
        <CardContent className="pt-8 pb-6">
            <div className="flex flex-col items-center text-center">
                <div
                    className={`mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br ${gradient} transition-transform group-hover:scale-110`}
                >
                    {icon}
                </div>
                <h3 className="mb-2 text-lg font-bold">{title}</h3>
                <p className="text-muted-foreground mb-4 text-sm">{description}</p>
                <Badge variant="secondary">{badge}</Badge>
            </div>
        </CardContent>
    </Card>
</button>
```

#### 3. `ChargeCard.tsx` (mobile-friendly)

```tsx
<Card className="mb-3">
    <CardContent className="p-4">
        <div className="flex items-start justify-between">
            <div className="flex-1">
                <div className="flex items-center gap-2">
                    <Badge variant="outline">{local}</Badge>
                    {isOverdue && <Badge variant="destructive">VENCIDO</Badge>}
                </div>
                <div className="mt-2 text-sm">
                    <div className="font-medium">
                        {period} • {kind}
                    </div>
                    <div className="text-muted-foreground">Saldo: Bs {outstanding}</div>
                </div>
            </div>
            <div className="text-right">
                {isSelected ? (
                    <CheckCircle2 className="h-6 w-6 text-green-600" />
                ) : (
                    <Input value={amount} onChange={onChange} className="w-24 text-right" placeholder="0.00" />
                )}
            </div>
        </div>
    </CardContent>
</Card>
```

#### 4. `AllocationSummary.tsx` (sticky sidebar)

```tsx
<Card className="sticky top-4">
    <CardHeader>
        <CardTitle className="text-base">Resumen</CardTitle>
    </CardHeader>
    <CardContent className="space-y-4">
        <div>
            <div className="text-muted-foreground mb-2 text-sm">Progreso de aplicación</div>
            <Progress value={percent} className="h-2" />
            <div className="mt-1 text-right text-xs">{percent}%</div>
        </div>
        <div className="flex justify-between">
            <span className="text-sm">Disponible</span>
            <span className="font-semibold">Bs {available}</span>
        </div>
        <div className="flex justify-between">
            <span className="text-sm">A aplicar</span>
            <span className="font-semibold text-blue-600">Bs {toApply}</span>
        </div>
        <div className="flex justify-between">
            <span className="text-sm">Restante</span>
            <span className="font-semibold text-green-600">Bs {remaining}</span>
        </div>
    </CardContent>
</Card>
```

### Mejoras de Accesibilidad

1. **Tooltips informativos**: Explicar conceptos técnicos
2. **Keyboard navigation**: Tab entre cards y campos
3. **ARIA labels**: Descriptivos para screen readers
4. **Focus visible**: Estados claros
5. **Error messages inline**: Junto a cada campo

### Mejoras de Feedback

1. **Loading states**:

    - Skeleton screens mientras carga
    - Spinners en botones
    - Toast: "Buscando cargos..."

2. **Success states**:

    - Checkmarks verdes
    - Animación suave
    - Toast: "Estrategia aplicada"

3. **Error states**:
    - Inline debajo de inputs
    - Alert rojo arriba si global
    - Toast: "Error al cargar"

### Mobile-First

1. **Cards verticales** (no tabla horizontal)
2. **Inputs grandes** (h-12, 48px)
3. **Touch targets** (min 44x44px)
4. **Swipe gestures** para eliminar
5. **Bottom sheet** para filtros

### Lenguaje Centrado en Usuario

| Antes                     | Después                    |
| ------------------------- | -------------------------- |
| "Obtener cargos abiertos" | "Buscar cargos pendientes" |
| "Sugerir por antigüedad"  | "Por antigüedad (FIFO)"    |
| "Aplicar selección"       | "Confirmar aplicación"     |
| "overdue_only"            | "Solo cargos vencidos"     |
| "kind"                    | "Tipo de cargo"            |
| "outstanding_bs_minor"    | "Saldo pendiente"          |

## Implementación por Fases

### Fase 1: Progressive Disclosure (Core)

- Dividir en 3 pasos visuales
- Progress bar superior
- Navegación entre pasos

### Fase 2: Strategy Cards

- Reemplazar dropdown por cards grandes
- Tooltips explicativos
- Auto-avance a paso 3

### Fase 3: Charge Cards (Mobile-First)

- Reemplazar tabla por cards
- Inline editing
- Swipe gestures

### Fase 4: Sticky Summary

- Sidebar con resumen financiero
- Progress bar de aplicación
- Real-time updates

### Fase 5: Polish

- Animaciones suaves
- Skeleton screens
- Empty states bonitos

## Métricas de Éxito

- ⏱ Tiempo de completado: -40% (8min → 5min)
- ❌ Tasa de errores: -60%
- 📱 Usabilidad mobile: +150%
- 😊 Satisfacción usuario (NPS): +30 puntos
- 🎯 Tasa de completado: +25%

## Testing Checklist

- [ ] Usuario puede completar flujo sin ayuda
- [ ] Tooltips explican conceptos claramente
- [ ] Mobile funciona sin scroll horizontal
- [ ] Loading states claros
- [ ] Errores visibles y accionables
- [ ] Navegación fluida entre pasos
- [ ] Resumen siempre visible (sticky)
- [ ] Estrategias auto-aplican correctamente
- [ ] Validaciones inline funcionan
- [ ] Confirmación final clara

## Archivos a Modificar

1. `resources/js/pages/catalogs/payment/show.tsx`

    - Agregar state `applyStep` (1, 2, 3)
    - Renderizar componentes según step
    - Mantener lógica de negocio actual

2. **NUEVO**: `resources/js/components/payments/ApplyStepProgress.tsx`
3. **NUEVO**: `resources/js/components/payments/StrategyCard.tsx`
4. **NUEVO**: `resources/js/components/payments/ChargeCard.tsx`
5. **NUEVO**: `resources/js/components/payments/AllocationSummary.tsx`

## Wireframe ASCII

```
┌────────────────────────────────────────────────────────┐
│  ← Pagos   Pago #10                      [Editar] [×]  │
├────────────────────────────────────────────────────────┤
│                                                         │
│  ⚪────⚪────⚪  [Progress: Cargos → Estrategia → ✓]   │
│  1    2    3                                           │
│                                                         │
│  ┌──────────────────────────┐  ┌──────────────────┐   │
│  │  PASO 1: BUSCAR CARGOS   │  │  RESUMEN         │   │
│  │                          │  │  ━━━━━━━━ 85%    │   │
│  │  Filtros:                │  │                  │   │
│  │  [USD ▼] [Todas ▼]       │  │  Disponible      │   │
│  │                          │  │  Bs 14,700       │   │
│  │  ☑ Solo vencidos         │  │                  │   │
│  │                          │  │  A aplicar       │   │
│  │  [Buscar cargos →]       │  │  Bs 12,450       │   │
│  │                          │  │                  │   │
│  └──────────────────────────┘  │  Restante        │   │
│                                │  Bs 2,250        │   │
│                                └──────────────────┘   │
│                                                         │
└────────────────────────────────────────────────────────┘
```

## Referencias de Diseño

- **Inspiración**: create-modern.tsx (wizard de pagos)
- **Componentes**: shadcn/ui (Cards, Progress, Badges)
- **Íconos**: lucide-react
- **Gradientes**: Tailwind CSS
- **Animaciones**: transition-all, hover:scale-110
