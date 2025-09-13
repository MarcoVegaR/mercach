---
title: 'Auditoría — FAQ'
summary: 'Problemas comunes y respuestas rápidas sobre el módulo de Auditoría.'
icon: material/help-circle
tags:
    - manual
    - auditoria
    - faq
---

# Auditoría — Preguntas frecuentes (FAQ)

## No veo eventos en la tabla

Posibles causas:

- No tienes permiso `auditoria.view`.
- No hay registros que cumplan con los filtros actuales.
- Estás filtrando por un rango de fechas incorrecto.

Soluciones:

- Solicita acceso con `auditoria.view`.
- Limpia filtros desde el panel de filtros.
- Cambia el rango de fechas o revisa la zona horaria.

## ¿Qué roles pueden acceder a Auditoría?

- Ver: `auditoria.view`
- Exportar: `auditoria.export`

Consulta con tu administrador para la asignación de permisos.

## El archivo de exportación tarda o no se descarga

- Los rangos muy amplios generan archivos grandes. Intenta acotar por fechas o entidad.
- Si tu navegador bloquea descargas múltiples, habilítalas para el sitio.

## ¿Se pueden editar o borrar eventos?

No. El módulo es de **solo lectura**. Los eventos se generan automáticamente.

## La IP o el User Agent no aparecen

- Depende del origen del evento y de la configuración.
- Algunos eventos del sistema pueden no incluir esa información.
