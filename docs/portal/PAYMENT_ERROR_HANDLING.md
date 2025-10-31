# Manejo de Errores - Registro de Pagos Portal

## 🎯 Problema resuelto

Cuando un pago fallaba la validación bancaria (ej. código 706: "Cod. Banco de numero cuenta invalido"), el sistema:

- ❌ Siempre redirigía a recibos con mensaje de éxito
- ❌ No mostraba el error al usuario
- ❌ Usuario no sabía qué estaba mal

---

## ✅ Solución implementada

### Backend: Manejo de excepciones

**Archivo**: `PortalPaymentController.php`

```php
try {
    $row = $this->payments->createAndVerify($payload, [...]);

    return redirect()->route('portal.receipts')
        ->with('success', 'Pago registrado correctamente...');

} catch (\App\Exceptions\DomainActionException $e) {
    // Validation failed - show user-friendly error
    return redirect()->back()
        ->withInput()
        ->with('error', $e->getMessage());

} catch (\Throwable $e) {
    // Unexpected error
    \Log::error('Payment registration failed', [...]);
    return redirect()->back()
        ->withInput()
        ->with('error', 'Ocurrió un error al procesar el pago...');
}
```

**Mejoras**:

1. ✅ **Captura DomainActionException**: Errores de validación bancaria
2. ✅ **Preserva datos**: `withInput()` mantiene el formulario llenado
3. ✅ **Mensaje claro**: `with('error', ...)` envía mensaje al frontend
4. ✅ **Fallback genérico**: Captura otros errores inesperados

---

### Frontend: Visualización de errores

**Archivo**: `create-modern.tsx`

#### 1. **Alert prominente** (arriba del wizard)

```tsx
{
    props.error && (
        <Alert variant="destructive" className="mb-6">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>No se pudo registrar el pago</AlertTitle>
            <AlertDescription className="mt-2">{props.error}</AlertDescription>
        </Alert>
    );
}
```

**Características**:

- ✅ **Variant destructive**: Rojo, llamativo
- ✅ **Icono AlertCircle**: Refuerza visualmente el error
- ✅ **Título claro**: "No se pudo registrar el pago"
- ✅ **Mensaje del backend**: Muestra el error exacto

#### 2. **Toast mejorado**

```tsx
onError: (errors) => {
    if (props.error) {
        toast.error('Validación fallida', {
            description: 'Revisa el mensaje de error arriba del formulario.',
        });
    } else {
        toast.error('Por favor corrige los errores señalados.');
    }
};
```

**Diferencia**:

- **Errores de validación** (campos vacíos, formato): Toast genérico
- **Errores del banco** (cuenta inválida, ref duplicada): Toast + Alert

#### 3. **Datos preservados**

Con `withInput()`, el formulario mantiene:

- ✅ Método seleccionado (Transfer/Pago Móvil)
- ✅ Cuenta destino
- ✅ Banco origen
- ✅ Referencia
- ✅ Monto
- ✅ Fecha
- ✅ Todos los campos completados

Usuario solo corrige el error sin volver a llenar todo.

---

## 🔍 Flujo completo

### Escenario: Cuenta bancaria inválida

#### 1. **Usuario completa formulario**

```
Método: Transferencia
Cuenta destino: Banesco 01560030680000776369
Banco: Banco Provincial (171)
Referencia: 345345
Cuenta: 34534534534534534534 ← ❌ Inválida
Monto: Bs 4,654.56
Fecha: 2025-10-22
```

#### 2. **Click "Registrar pago"**

- Toast: "Verificando pago en el banco..."
- Backend llama API 100%Banco
- API responde: `{"sRespCode": "706", "sRespDesc": "Cod. Banco de numero cuenta invalido"}`

#### 3. **Backend maneja error**

```php
catch (DomainActionException $e) {
    // $e->getMessage() = "No validado. Código 706 – Cod. Banco de numero cuenta invalido..."
    return redirect()->back()
        ->withInput()
        ->with('error', $e->getMessage());
}
```

#### 4. **Frontend muestra error**

```
┌────────────────────────────────────────────┐
│ 🔴 No se pudo registrar el pago           │
│                                            │
│ No validado. Código 706 – Cod. Banco de   │
│ numero cuenta invalido. El pago no fue    │
│ registrado.                                │
└────────────────────────────────────────────┘

┌────────────────────────────────────────────┐
│ 🏦 Datos de la transferencia               │
│                                            │
│ 💳 ¿A qué cuenta?                          │
│ [Banesco 01560030680000776369] ← preservado│
│                                            │
│ 🏦 Tu banco                                │
│ [Banco Provincial] ← preservado            │
│                                            │
│ 💳 Tu cuenta bancaria (20 dígitos)         │
│ [34534534534534534534] ← PUEDE CORREGIR   │
│                                            │
│ ...resto del form con datos preservados    │
└────────────────────────────────────────────┘
```

Toast esquina: "⚠️ Validación fallida - Revisa el mensaje arriba"

#### 5. **Usuario corrige cuenta**

- Cambia `34534534534534534534` → `01710123456789012345`
- Click "Registrar pago" nuevamente
- ✅ Validación exitosa
- ✅ Redirect a recibos

---

## 📊 Tipos de errores manejados

### 1. **Errores de validación bancaria** (DomainActionException)

| Código  | Mensaje                              | Causa común                                          |
| ------- | ------------------------------------ | ---------------------------------------------------- |
| **706** | Cod. Banco de numero cuenta invalido | Cuenta no tiene 20 dígitos o código banco incorrecto |
| **707** | Transaccion duplicada                | Referencia ya fue registrada                         |
| **708** | Cuenta destino invalida              | Cuenta destino no existe                             |
| **709** | Referencia no encontrada             | Referencia no existe en banco origen                 |
| **710** | Monto no coincide                    | Monto diferente al registrado en banco               |

**Visualización**:

```
┌─────────────────────────────────────┐
│ 🔴 No se pudo registrar el pago    │
│                                     │
│ No validado. Código 707 –           │
│ Transaccion duplicada. El pago no   │
│ fue registrado.                     │
└─────────────────────────────────────┘
```

### 2. **Errores de validación de campos** (Form validation)

| Campo                     | Error                                           |
| ------------------------- | ----------------------------------------------- |
| `company_bank_account_id` | "El campo cuenta destino es requerido"          |
| `reference`               | "La referencia debe tener entre 6 y 12 dígitos" |
| `amount_bs_minor`         | "El monto debe ser mayor a 0"                   |
| `paid_on`                 | "La fecha es requerida"                         |

**Visualización**:

```
💳 Tu cuenta bancaria (20 dígitos)
[___________] ← vacío
❌ La cuenta bancaria es requerida
```

Toast: "Por favor corrige los errores señalados"

### 3. **Errores inesperados** (Throwable)

- Database connection lost
- Network timeout
- 500 Internal Server Error

**Visualización**:

```
┌─────────────────────────────────────┐
│ 🔴 No se pudo registrar el pago    │
│                                     │
│ Ocurrió un error al procesar el     │
│ pago. Por favor intenta nuevamente  │
│ o contacta soporte.                 │
└─────────────────────────────────────┘
```

---

## 🎨 Diseño del Alert de error

### Variantes

```tsx
// Error bancario (destructive)
<Alert variant="destructive">
    {' '}
    {/* Rojo */}
    <AlertCircle className="h-4 w-4" />
    <AlertTitle>No se pudo registrar el pago</AlertTitle>
    <AlertDescription>{props.error}</AlertDescription>
</Alert>
```

### Colores

| Elemento        | Color                        |
| --------------- | ---------------------------- |
| **Border**      | `border-red-500`             |
| **Background**  | `bg-red-50`                  |
| **Icon**        | `text-red-600`               |
| **Title**       | `text-red-900 font-semibold` |
| **Description** | `text-red-800`               |

### Posición

```
┌──────────────────────────────────┐
│ Registrar un pago                │  ← Título
│ Progress bar: ●──●──○            │  ← Steps
│                                  │
│ 🔴 ERROR ALERT ← AQUÍ           │  ← Prominente
│                                  │
│ Paso 1: Método o Paso 2: Form   │  ← Contenido
└──────────────────────────────────┘
```

**Por qué arriba**:

- ✅ Primera cosa que ve el usuario
- ✅ No se pierde en scroll
- ✅ Consistente con patrón UX estándar
- ✅ Mobile-friendly

---

## 🧪 Testing manual

### Test Case 1: Cuenta inválida

```bash
# Datos de prueba
Método: Transfer
Banco: Provincial (171)
Referencia: 345345
Cuenta: 12345 ← menos de 20 dígitos
Monto: Bs 100.00

# Resultado esperado
✅ Alert rojo con código 706
✅ Datos preservados en form
✅ Toast "Validación fallida"
```

### Test Case 2: Referencia duplicada

```bash
# Registrar pago válido primero
# Luego intentar misma referencia

# Resultado esperado
✅ Alert con código 707
✅ Mensaje "Transaccion duplicada"
```

### Test Case 3: Campos vacíos

```bash
# Submit sin llenar form

# Resultado esperado
✅ Errores inline bajo cada campo
✅ Toast "Por favor corrige..."
✅ NO alert rojo (no es error backend)
```

---

## 📝 Logs generados

### Antes (sin contexto claro)

```
ERROR: No validado. Código 706
```

### Después (contexto completo)

```json
{
  "message": "payment.verify.gateway_result",
  "context": {
    "payment_id": 11,
    "ok": false,
    "code": "706",
    "message": "Cod. Banco de numero cuenta invalido",
    "raw_request_snippet": "...",
    "raw_response_snippet": "..."
  },
  "level": "INFO"
}

{
  "message": "Payment registration failed",
  "context": {
    "message": "No validado. Código 706...",
    "user_id": 4
  },
  "level": "ERROR"
}
```

---

## 🚀 Beneficios

| Antes ❌                   | Después ✅                        |
| -------------------------- | --------------------------------- |
| Usuario no ve el error     | **Alert prominente rojo**         |
| Siempre redirige a recibos | **Vuelve al form con error**      |
| Pierde todos los datos     | **Datos preservados**             |
| Sin contexto del problema  | **Código y mensaje específico**   |
| No sabe qué corregir       | **Sabe exactamente qué está mal** |
| Frustración y abandono     | **Corrección y éxito**            |

---

## 🔄 Rollback

Si necesitas volver atrás:

```bash
# Revertir controller
git checkout HEAD -- app/Http/Controllers/Portal/PortalPaymentController.php

# Revertir component
git checkout HEAD -- resources/js/pages/portal/payments/create-modern.tsx

# Recompilar
npm run build
```

---

## 📚 Referencias

- **DomainActionException**: `app/Exceptions/DomainActionException.php`
- **PaymentService**: `app/Services/PaymentService.php` (línea ~1057 lanza excepción)
- **Códigos de error**: Documentación API 100%Banco
- **Alert component**: `@/components/ui/alert`

---

**Versión:** 1.0 Error Handling  
**Fecha:** 29 Oct 2025  
**Autor:** Sistema  
**Estado:** ✅ Production Ready
