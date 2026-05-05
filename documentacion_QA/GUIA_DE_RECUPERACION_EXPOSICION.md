# 🚨 GUÍA DE RECUPERACIÓN Y TROUBLESHOOTING (EXPOSICIÓN)

Esta guía está diseñada específicamente para tu evaluación. Si el instructor borra o altera alguna línea de código para probar tus conocimientos, aquí tienes el "acordeón" exacto de qué buscar y cómo repararlo, separado por módulos.

---

## 1. MÓDULO DE AUTENTICACIÓN (Login / Registro)

### ❌ Falla: El login da "Acceso Denegado" aunque la clave sea correcta
- **Dónde buscar:** Base de datos (HeidiSQL), tabla `usuarios`.
- **Por qué pasa:** El usuario está inactivo (`activo = 0`) o bloqueado por intentos fallidos (`intentos_fallidos >= 3`).
- **Cómo reparar:** 
  Ejecuta en SQL: `UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, activo = 1 WHERE correo = 'tu@correo.com';`
- **Controlador implicado:** `controllers/UsuarioController.php` (método `login`).

### ❌ Falla: Al enviar el formulario de Login/Registro se queda en blanco o da Error 403
- **Dónde buscar:** `views/auth/login.php` o `registro.php`.
- **Por qué pasa:** Borraron el token de seguridad CSRF o el action del form está vacío.
- **Cómo reparar:**
  1. Verifica que el form tenga: `<form action="../../controllers/UsuarioController.php" method="POST">`
  2. Verifica que el form tenga el input oculto o la instrucción de accion, ejemplo: `<input type="hidden" name="accion" value="login">`

---

## 2. MÓDULO DE USUARIOS (Dashboards y Modales)

### ❌ Falla: Al dar "Guardar" en Nuevo Trabajador o Editar da ERROR 403 "Petición no autorizada"
- **Dónde buscar:** `views/dashboards/admin.php`.
- **Por qué pasa:** El instructor borró el token de seguridad contra ataques (CSRF).
- **Cómo reparar:** Busca los formularios `<form>` de los modales y asegúrate de que justo debajo de la etiqueta `<form>` esté esta línea en PHP:
  ```php
  <?= csrf_field() ?>
  ```

### ❌ Falla: Le doy clic al botón "Editar" o "Nuevo" y el modal NO se abre
- **Dónde buscar:** `views/dashboards/admin.php` o `trabajador.php` (al final del archivo, en el HTML del modal).
- **Por qué pasa:** Borraron o alteraron el código de JavaScript en el atributo `onclick` del botón.
- **Cómo reparar:** 
  El botón debe hacer esto: `document.getElementById('id_del_modal').classList.remove('hidden'); document.getElementById('id_del_modal').style.display='flex';`

### ❌ Falla: El modal se cierra mágicamente cuando intento escribir en un input
- **Dónde buscar:** En el HTML del fondo oscuro (overlay) del modal.
- **Por qué pasa:** El evento de cerrar está mal estructurado y se activa con cualquier clic.
- **Cómo reparar:** Asegúrate de que el cierre preventivo tenga LLAVES `{}`:
  ```javascript
  onclick="if(event.target===this) { this.classList.add('hidden'); this.style.display='none'; }"
  ```

---

## 3. MÓDULO DE DISEÑO (CSS / Interfaz / Imágenes)

### ❌ Falla: La página se ve en blanco y negro, letras gigantes o fea (sin estilos)
- **Dónde buscar:** En las primeras líneas de las vistas (`<head>`).
- **Por qué pasa:** Borraron el `<link>` al archivo CSS de Tailwind.
- **Cómo reparar:** Asegúrate de tener este link en el `<head>`:
  ```html
  <link rel="stylesheet" href="../../public/css/output.css">
  ```
- *Nota: En `index.php` el link es `css/output.css`.*

### ❌ Falla: Faltan partes de la página (el menú lateral no aparece o el topbar desapareció)
- **Dónde buscar:** En las vistas principales (`admin.php`, `trabajador.php`).
- **Por qué pasa:** Borraron la línea de PHP que "incluye" los fragmentos de código (partials).
- **Cómo reparar:** Restaura las líneas de `include`:
  ```php
  <?php include '../../views/partials/admin/sidebar.php'; ?>
  <?php include '../../views/partials/admin/header.php'; ?>
  ```

---

## 4. MÓDULO DE FOTOGRAFÍAS (Storage)

### ❌ Falla: Subo una foto y marca error de ruta o la foto "se rompe" en pantalla
- **Dónde buscar:** `controllers/FotoController.php` y las carpetas del proyecto.
- **Por qué pasa:** 
  1. El formulario no tiene el atributo multipart para enviar archivos.
  2. La carpeta `storage/fotos/` o `public/storage/` fue borrada.
- **Cómo reparar:**
  1. En el HTML del formulario verifica que exista esto: `<form enctype="multipart/form-data" ...>`
  2. Si la ruta se rompe en la vista HTML, asegúrate de que al imprimir el `src` de la imagen tenga `../../` antes de la ruta almacenada en la BD. Ejemplo: `<img src="../../<?= $cultivo['fotografia'] ?>">`

---

## 5. MÓDULO DE BASE DE DATOS Y PHP

### ❌ Falla: Todo el sistema se rompe ("Warning: require_once... failed to open stream")
- **Dónde buscar:** Arriba del todo en los archivos `.php` (Controladores o Dashboards).
- **Por qué pasa:** El instructor alteró la ruta de las dependencias o el `$rootPath`.
- **Cómo reparar:** Los archivos necesitan encontrar la raíz. En los *dashboards*, la raíz se define subiendo 2 niveles:
  ```php
  $rootPath = dirname(__DIR__, 2);
  require_once $rootPath . '/config/session.php';
  require_once $rootPath . '/config/database.php';
  ```

### ❌ Falla: No hay conexión a base de datos (Error 500)
- **Dónde buscar:** Archivo `.env` en la raíz del proyecto.
- **Por qué pasa:** Cambiaron el nombre de la BD o el usuario en el `.env`.
- **Cómo reparar:** Asegúrate de que el `.env` tenga:
  ```
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_NAME=agro_db
  DB_USER=root
  DB_PASS=
  ```
  *(Si no hay contraseña en Laragon, DB_PASS debe estar vacío).*

---

## 💡 RESUMEN DE EMERGENCIA PARA EL EXAMEN:
1. **¿Error Visual?** → Revisa las clases de Tailwind, el `<link>` al CSS y el `id` de los modales.
2. **¿Falla al Guardar/Crear/Eliminar?** → Revisa el `action=""` del `<form>` y el `<?= csrf_field() ?>`.
3. **¿Redirección a Login inesperada?** → Revisa que `require_once .../session.php` esté arriba y que la base de datos tenga al usuario `activo=1`.
4. **¿Página rota (PHP Error)?** → Revisa los `require_once` de los modelos y controladores.
