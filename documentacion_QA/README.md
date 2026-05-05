# 📚 DOCUMENTACIÓN QA - SIGVOS

¡Bienvenido a la Documentación Oficial de Quality Assurance (QA) del proyecto SIGVOS!
Este directorio contiene todas las especificaciones lógicas de cada uno de los módulos del sistema. Está organizado meticulosamente para facilitar su comprensión técnica, desarrollo y mantenimiento.

> 💡 **Nota para la Exposición:**
> Comienza revisando la [**GUÍA DE RECUPERACIÓN Y TROUBLESHOOTING**](./GUIA_DE_RECUPERACION_EXPOSICION.md). Es el documento principal para resolver cualquier sabotaje o error durante la evaluación.

---

## 🗂️ Índice de Módulos

El código y su lógica están distribuidos en las siguientes 6 categorías principales. Haz clic en la carpeta correspondiente para ver los detalles de su lógica:

### 🔐 [1. Autenticación y Usuarios](./1_Autenticacion_y_Usuarios/)

Gestión de ingresos, sesiones, permisos de seguridad (CSRF) y administración de personal.

- `logica_login.md` / `logica_logout.md` / `logica_registro.md`
- `logica_crear_usuario.md` / `logica_editar_usuario.md`
- `logica_editar_perfil_y_contrasena.md` / `logica_foto_perfil.md`
- `logica_remember_me.md` / `logica_toggle_estado_usuario.md`

### 🗺️ [2. Lotes y Asignaciones](./2_Lotes_y_Asignaciones/)

Organización de la tierra y asignación de trabajadores a áreas específicas.

- `logica_crear_lote.md` / `logica_editar_lote.md` / `logica_eliminar_lote.md`
- `logica_asignar_lote.md` / `logica_reasignar_lote.md`
- `logica_desactivar_asignacion.md`

### 🌱 [3. Cultivos](./3_Cultivos/)

Control del ciclo de vida de los productos sembrados.

- `logica_crear_cultivo.md` / `logica_editar_cultivo.md`
- `logica_eliminar_cultivo.md` / `logica_asignar_cultivo.md`

### 🚜 [4. Actividades y Cosechas](./4_Actividades_y_Cosechas/)

Registro diario del trabajo en campo y obtención final de productos.

- `logica_crear_actividad.md` / `logica_editar_actividad.md` / `logica_eliminar_actividad.md`
- `logica_actualizar_estado_actividad.md`
- `logica_registrar_cosecha.md`

### 📸 [5. Fotos y Multimedia](./5_Fotos_y_Multimedia/)

Sistema de almacenamiento y manejo de archivos subidos por los trabajadores y administrador.

- `logica_subir_foto_cultivo.md`
- `logica_subir_foto_actividad.md`
- `logica_manejo_extra_fotos.md`

### 📊 [6. Reportes y Notificaciones](./6_Reportes_y_Notificaciones/)

Generación de documentos PDF y sistema de avisos en tiempo real.

- `logica_generar_reportes.md`
- `logica_notificaciones.md`

---

## 🛠️ Buenas Prácticas de QA (Desarrollador)

- **Rutas seguras:** Todas las subidas de archivos verifican su extensión y tamaño límite (5MB).
- **Protección CSRF:** Todos los formularios críticos (`crear`, `editar`, `eliminar`) incluyen validación del lado del servidor contra ataques Cross-Site Request Forgery.
- **Consultas Preparadas (PDO):** Ningún dato introducido por el usuario se concatena directamente en SQL, previniendo así la Inyección SQL.
- **Aislamiento de Roles:** El trabajador (`rol = 2`) no tiene acceso a las rutas ni acciones exclusivas del Administrador (`rol = 1`).
