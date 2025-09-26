---
title: 'Concesionarios — Visión general'
summary: 'Gestión de concesionarios: alta, documentos, contacto y relación con contratos.'
icon: material/account-tie
---

# Concesionarios — Visión general

- Propósito: administrar fichas de concesionarios que operan locales.
- Relación: los contratos vinculan concesionarios y locales (multi-firmante si aplica).
- Requisitos previos: permisos (p. ej., `concessionaires.view`, `concessionaires.create`, `concessionaires.update`).

## Estilo de columnas (tipografía y truncado)

Para una lectura clara y consistente en la lista de concesionarios, aplicamos:

- Documento: representación compacta (p. ej., `CI-12345678`) en fuente monoespaciada y tamaño pequeño.
- Nombre completo: énfasis (`font-medium`), truncado con elipsis y `title` con el valor completo.
- Email: fuente monoespaciada (`font-mono`) y `text-xs`, truncado con elipsis y `title`.
- Creado: fecha corta visible y tooltip con fecha completa y tiempo relativo.
- Estado: insignia (badge) “Activo/Inactivo” con un punto de color (verde/rojo).

Estas mismas pautas se siguen en Usuarios y el resto de catálogos para mantener la coherencia visual del sistema.
