# 📋 INFORME COMPLETO DE EVALUACIÓN DEL PROTOTIPO SIGVOS
## Historias de Usuario DP-1 a DP-20

**Proyecto:** SIGVOS — Sistema Integral de Gestión y Vigilancia de Operaciones en Siembra  
**Fecha de evaluación:** 14 de junio de 2026  
**Evaluador:** Análisis automatizado de código fuente  
**Archivos revisados:** Controllers, Models, Views (dashboards admin y trabajador), config, partials  

---

## 📊 Resumen Ejecutivo

| Indicador | Valor |
|---|---|
| Total historias de usuario | 20 |
| Total criterios de aceptación | 119 |
| ✅ Criterios cumplidos | 71 |
| ❌ Criterios no cumplidos | 48 |
| **Porcentaje global** | **~60%** |

### Historias con cumplimiento perfecto (100%):
DP-2, DP-4, DP-6, DP-7, DP-8, DP-11, DP-17

### Historias críticas (< 30%):
DP-9 (0%), DP-14 (0%), DP-19 (12.5%), DP-16 (29%)

---

# DP-1: Inicio de Sesión del Administrador

> **Rol:** Administrador  
> **Objetivo:** Acceder al sistema de forma segura con credenciales válidas  
> **Propósito:** Proteger los datos del sistema y garantizar que solo usuarios autorizados accedan

### ✅ C1. Inicio de sesión con credenciales válidas — CUMPLIDO

**Evidencia en código:**  
En [AuthController.php](file:///c:/laragon/www/Gsigvos/controllers/AuthController.php), la función de login verifica las credenciales contra la base de datos usando `password_verify()` con hash bcrypt. Al autenticarse correctamente, se crean las variables de sesión (`id_usuario`, `id_rol`, `nombre`) y se redirige al dashboard correspondiente según el rol.

### ✅ C2. Bloqueo tras intentos fallidos — CUMPLIDO

**Evidencia en código:**  
El sistema cuenta con un mecanismo de bloqueo después de **3 intentos fallidos consecutivos**. En el controlador de autenticación se incrementa un contador `intentos_fallidos` en la tabla de usuarios y, cuando supera el límite, el sistema bloquea la cuenta mostrando un mensaje de error claro al usuario.

### ✅ C3. Restablecimiento de contraseña — CUMPLIDO

**Evidencia en código:**  
Existe una vista y controlador dedicados para el restablecimiento de contraseña. El flujo permite al usuario solicitar un cambio vía correo electrónico o mediante un mecanismo de verificación implementado en el sistema.

### ❌ C4. Cierre de sesión por inactividad (10 minutos) — NO CUMPLIDO

**Evidencia del fallo:**  
En [session.php](file:///c:/laragon/www/Gsigvos/config/session.php), la configuración de sesión establece `session.gc_maxlifetime` pero **no existe un timer en JavaScript** que monitoree la actividad del usuario (movimiento de mouse, clics, teclas) y fuerce el logout tras 10 minutos sin interacción. La sesión solo expira cuando el garbage collector de PHP la elimina, lo cual no es determinístico ni preciso.

**Corrección propuesta:**

Agregar al final de ambos dashboards (admin.php y trabajador.php) el siguiente script:

```javascript
// === CIERRE DE SESIÓN POR INACTIVIDAD (10 minutos) ===
(function() {
    const TIMEOUT_MS = 10 * 60 * 1000; // 10 minutos
    let timer;

    function resetTimer() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            // Mostrar alerta antes de cerrar
            Swal.fire({
                title: 'Sesión expirada',
                text: 'Tu sesión se cerró por inactividad.',
                icon: 'warning',
                confirmButtonColor: '#065f46',
                confirmButtonText: 'Ir al login',
                allowOutsideClick: false,
                customClass: { popup: 'rounded-2xl' }
            }).then(() => {
                window.location.href = '../../controllers/AuthController.php?accion=logout';
            });
        }, TIMEOUT_MS);
    }

    // Eventos que reinician el timer
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev => {
        document.addEventListener(ev, resetTimer, { passive: true });
    });

    resetTimer(); // Iniciar al cargar la página
})();
```

### ✅ C5. Mensajes de error claros — CUMPLIDO

**Evidencia en código:**  
El sistema utiliza el mecanismo de `$_SESSION['toast']` con tipos `error`, `success` e `info` que se renderizan como notificaciones visuales elegantes usando SweetAlert2. Los mensajes son específicos: "Credenciales incorrectas", "Cuenta bloqueada", etc.

**📊 Cumplimiento DP-1: 4/5 = 80%**

---

# DP-2: Acceso del Trabajador

> **Rol:** Trabajador  
> **Objetivo:** Iniciar sesión con credenciales asignadas por el administrador  
> **Propósito:** Acceder únicamente a las funcionalidades de su rol

### ✅ C1. Inicio de sesión exitoso — CUMPLIDO

**Evidencia:** El mismo `AuthController.php` maneja ambos roles. Tras verificar credenciales, detecta `id_rol` y redirige al dashboard de trabajador.

### ✅ C2. Diferenciación de rol — CUMPLIDO

**Evidencia:** En [trabajador.php](file:///c:/laragon/www/Gsigvos/views/dashboards/trabajador.php) línea 7, se verifica `if ((int)$_SESSION['id_rol'] === 1) { header('Location: admin.php'); exit; }`, asegurando que solo trabajadores (rol 2) accedan a esta vista. El admin tiene la verificación inversa.

### ✅ C3. Prevención de acceso sin sesión — CUMPLIDO

**Evidencia:** Todas las vistas y controladores verifican `if (!isset($_SESSION['id_usuario']))` al inicio y redirigen al login si no hay sesión activa.

### ✅ C4. Bloqueo de cuenta — CUMPLIDO

**Evidencia:** El mismo mecanismo de bloqueo de intentos fallidos aplica para ambos roles.

### ✅ C5. Cierre de sesión voluntario — CUMPLIDO

**Evidencia:** El header del trabajador incluye un botón de "Cerrar sesión" que llama al controlador de autenticación con la acción `logout`, destruyendo la sesión PHP.

**📊 Cumplimiento DP-2: 5/5 = 100% ✨**

---

# DP-3: Dashboard Ejecutivo del Administrador

> **Rol:** Administrador  
> **Objetivo:** Visualizar un panel con métricas clave en tiempo real  
> **Propósito:** Tomar decisiones informadas rápidamente

### ✅ C1. Visualización de KPIs — CUMPLIDO

**Evidencia:** En [admin.php](file:///c:/laragon/www/Gsigvos/views/dashboards/admin.php) líneas 78-90, se calculan las métricas clave: `$kpi_lotes_activos`, `$kpi_cultivos_activos`, `$kpi_actividades_pendientes` y `$kpi_cosechas_mes`. Se renderizan como tarjetas con iconos, gradientes y valores numéricos destacados.

### ✅ C2. Gráficas de rendimiento — CUMPLIDO

**Evidencia:** La vista incluye el módulo de reportes con gráficas dinámicas que permiten visualizar producción comparativa entre lotes, actividades por período y próximas cosechas.

### ❌ C3. Actualización automática de datos (sin recargar) — NO CUMPLIDO

**Evidencia del fallo:**  
Los KPIs se calculan una sola vez al cargar la página (PHP del lado del servidor). No existe ningún `setInterval()` ni `fetch()` periódico en el JavaScript del admin que actualice las métricas automáticamente. Si un trabajador completa una actividad, el admin no ve el cambio hasta que recargue manualmente la página (F5).

**Corrección propuesta:**

Agregar un endpoint AJAX en el backend y un timer en el frontend:

```php
// En un nuevo archivo: controllers/KpiController.php
<?php
require_once dirname(__DIR__) . '/config/session.php';
if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    http_response_code(403); exit;
}
require_once dirname(__DIR__) . '/config/database.php';
$db = (new Database())->conectar();

header('Content-Type: application/json');
echo json_encode([
    'lotes_activos'        => $db->query("SELECT COUNT(*) FROM lotes WHERE activo=1")->fetchColumn(),
    'cultivos_activos'     => $db->query("SELECT COUNT(*) FROM cultivos WHERE activo_en_lote IS NOT NULL")->fetchColumn(),
    'actividades_pendientes' => $db->query("SELECT COUNT(*) FROM actividades WHERE estado='pendiente'")->fetchColumn(),
    'cosechas_mes'         => $db->query("SELECT COUNT(*) FROM cultivos WHERE estado='cosechado' AND MONTH(fecha_cosecha_real)=MONTH(CURDATE())")->fetchColumn(),
]);
```

```javascript
// En admin.php, dentro del <script>:
setInterval(() => {
    fetch(`${BASE_URL}/controllers/KpiController.php?PHPSESSID=${SID}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('kpi-lotes').textContent = data.lotes_activos;
            document.getElementById('kpi-cultivos').textContent = data.cultivos_activos;
            document.getElementById('kpi-pendientes').textContent = data.actividades_pendientes;
            document.getElementById('kpi-cosechas').textContent = data.cosechas_mes;
        }).catch(() => {}); // Silenciar errores si está offline
}, 30000); // Cada 30 segundos
```

### ✅ C4. Alertas de proximidad de cosecha — CUMPLIDO

**Evidencia:** En la vista principal del dashboard se muestran cultivos cuya fecha de cosecha estimada está próxima. La tabla de cultivos incluye una columna de "Cosecha estimada" y el calendario marca visualmente las cosechas con color púrpura.

### ✅ C5. Atajos rápidos de acción — CUMPLIDO

**Evidencia:** El sidebar incluye enlaces directos a todos los módulos (Lotes, Cultivos, Actividades, Fotos, Calendario, Reportes, Trabajadores, Perfil) con iconos e indicadores de conteo.

### ❌ C6. Funcionalidad en modo offline — NO CUMPLIDO

**Evidencia del fallo:**  
El panel del **trabajador** sí tiene un banner de modo offline y listeners de eventos `online`/`offline`. Sin embargo, el panel del **administrador** no implementa ninguna de estas funcionalidades. No hay Service Worker, no hay IndexedDB, no hay banner de desconexión en la vista admin.

**Corrección propuesta:**

Replicar la lógica del trabajador en el admin. Agregar al header del admin:

```html
<!-- En partials/admin/header.php -->
<div id="offline-indicator" class="hidden offline-banner mx-6 mt-3">
    <i class="fas fa-wifi-slash text-amber-600"></i>
    <span>Sin conexión a internet — Los datos mostrados pueden no estar actualizados.</span>
</div>
```

Y en el JavaScript del admin:
```javascript
window.addEventListener('offline', () => {
    const ind = document.getElementById('offline-indicator');
    if (ind) { ind.classList.remove('hidden'); ind.classList.add('flex'); }
});
window.addEventListener('online', () => {
    const ind = document.getElementById('offline-indicator');
    if (ind) { ind.classList.add('hidden'); ind.classList.remove('flex'); }
});
```

**📊 Cumplimiento DP-3: 4/6 = 67%**

---

# DP-4: Creación de Lotes Agrícolas

> **Rol:** Administrador  
> **Objetivo:** Registrar nuevos lotes agrícolas  
> **Propósito:** Tener un inventario organizado de las áreas de cultivo

### ✅ C1. Registro de lote exitoso — CUMPLIDO

**Evidencia:** El [LoteController.php](file:///c:/laragon/www/Gsigvos/controllers/LoteController.php) recibe los datos del formulario (identificador, nombre, área, ubicación, tipo de suelo) y los inserta correctamente en la tabla `lotes` con validación de campos obligatorios.

### ✅ C2. Prevención de lotes duplicados — CUMPLIDO

**Evidencia:** Antes de insertar, el controlador ejecuta una consulta `SELECT` para verificar que no exista otro lote con el mismo `identificador`. Si encuentra duplicado, retorna un toast de error.

### ✅ C3. Lotes alternativos/descanso — CUMPLIDO

**Evidencia:** El sistema permite crear lotes con estado `en_descanso`, lo que permite documentar rotaciones y períodos de recuperación del suelo.

### ✅ C4. Estado inicial como disponible — CUMPLIDO

**Evidencia:** Al crear un lote nuevo, el estado se asigna automáticamente como `disponible`, permitiendo que esté listo para recibir un cultivo inmediatamente.

**📊 Cumplimiento DP-4: 4/4 = 100% ✨**

---

# DP-5: Modificación de Información del Lote

> **Rol:** Administrador  
> **Objetivo:** Editar datos de lotes existentes  
> **Propósito:** Mantener la información actualizada según cambios en el terreno

### ✅ C1. Modificar información básica — CUMPLIDO

**Evidencia:** El formulario de edición permite actualizar nombre, área, ubicación, tipo de suelo y observaciones. El controlador procesa el `UPDATE` correctamente.

### ❌ C2. Restricción de modificación del identificador — NO CUMPLIDO

**Evidencia del fallo:**  
En el modal de edición de lotes en `admin.php`, el campo `identificador` es un `<input type="text">` completamente editable. No tiene el atributo `readonly` ni `disabled`. Esto permite que el administrador cambie el identificador único del lote, lo cual puede romper relaciones con cultivos y asignaciones existentes.

**Corrección propuesta:**

Localizar el input del identificador en el modal de edición y agregar el atributo `readonly`:

```html
<!-- En el modal de edición de lotes en admin.php -->
<input type="text" name="identificador" 
       value="<?= htmlspecialchars($lote['identificador']) ?>"
       readonly
       class="w-full px-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm 
              text-gray-500 cursor-not-allowed"
       title="El identificador no puede modificarse una vez creado">
```

Adicionalmente, agregar validación del lado del servidor como medida de seguridad:

```php
// En LoteController.php, función editarLote():
$loteActual = $model->obtenerPorId($id);
if ($loteActual && $datos['identificador'] !== $loteActual['identificador']) {
    $_SESSION['toast'] = ['text' => 'El identificador del lote no puede modificarse.', 'type' => 'error'];
    header('Location: ../views/dashboards/admin.php#lotes'); exit;
}
```

### ❌ C3. Restricción en cultivos activos — NO CUMPLIDO

**Evidencia del fallo:**  
El controlador de edición de lotes no verifica si el lote tiene un cultivo activo (`activo_en_lote IS NOT NULL`) antes de permitir cambios en información crítica como el estado o el área. Esto podría causar inconsistencias: por ejemplo, cambiar un lote a "inactivo" mientras tiene un cultivo en desarrollo.

**Corrección propuesta:**

```php
// En LoteController.php, función editarLote():
$cultivoActivo = $db->prepare(
    "SELECT COUNT(*) FROM cultivos WHERE id_lote = :id AND activo_en_lote IS NOT NULL"
);
$cultivoActivo->execute([':id' => $id]);

if ($cultivoActivo->fetchColumn() > 0) {
    // Solo permitir editar campos no críticos (nombre, observaciones)
    // Bloquear cambios en: estado, área, ubicación
    $camposCriticos = ['estado', 'area_ha', 'ubicacion'];
    foreach ($camposCriticos as $campo) {
        if (isset($datos[$campo]) && $datos[$campo] !== $loteActual[$campo]) {
            $_SESSION['toast'] = [
                'text' => "No puedes modificar '{$campo}' porque este lote tiene un cultivo activo.",
                'type' => 'error'
            ];
            header('Location: ../views/dashboards/admin.php#lotes'); exit;
        }
    }
}
```

### ❌ C4. Historial de modificaciones — NO CUMPLIDO

**Evidencia del fallo:**  
No existe una tabla `historial_cambios_lotes` ni ningún mecanismo de auditoría que registre quién cambió qué campo, cuándo y cuál era el valor anterior. Los cambios se aplican directamente sin dejar rastro.

**Corrección propuesta:**

1. Crear la tabla en la base de datos:

```sql
CREATE TABLE historial_cambios_lotes (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_lote INT NOT NULL,
    id_usuario INT NOT NULL,
    campo_modificado VARCHAR(100) NOT NULL,
    valor_anterior TEXT,
    valor_nuevo TEXT,
    fecha_cambio DATETIME DEFAULT NOW(),
    FOREIGN KEY (id_lote) REFERENCES lotes(id_lote),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
```

2. En el controlador, antes del `UPDATE`, comparar valores:

```php
// En LoteController.php, función editarLote():
$loteAnterior = $model->obtenerPorId($id);
$camposAuditar = ['nombre', 'area_ha', 'ubicacion', 'tipo_suelo', 'estado', 'observaciones'];

foreach ($camposAuditar as $campo) {
    if (isset($datos[$campo]) && $datos[$campo] != $loteAnterior[$campo]) {
        $db->prepare(
            "INSERT INTO historial_cambios_lotes (id_lote, id_usuario, campo_modificado, valor_anterior, valor_nuevo)
             VALUES (:lote, :usuario, :campo, :anterior, :nuevo)"
        )->execute([
            ':lote'     => $id,
            ':usuario'  => $_SESSION['id_usuario'],
            ':campo'    => $campo,
            ':anterior' => $loteAnterior[$campo],
            ':nuevo'    => $datos[$campo],
        ]);
    }
}
```

### ✅ C5. Cambio a estado inactivo — CUMPLIDO

**Evidencia:** El formulario permite cambiar el estado del lote a "inactivo", lo que lo oculta de las listas de lotes disponibles para nuevas siembras.

**📊 Cumplimiento DP-5: 2/5 = 40%**

---

# DP-6: Registro de Cultivos

> **Rol:** Administrador  
> **Objetivo:** Registrar nuevos cultivos en lotes disponibles  
> **Propósito:** Documentar cada ciclo de siembra con su tipo, variedad y fechas

### ✅ C1. Registro exitoso de siembra — CUMPLIDO

**Evidencia:** El [CultivoController.php](file:///c:/laragon/www/Gsigvos/controllers/CultivoController.php) recibe los datos del formulario y crea el registro en la tabla `cultivos` con código, variedad, fecha de siembra y observaciones. El lote se marca automáticamente como "ocupado".

### ✅ C2. Cálculo automático de cosecha — CUMPLIDO

**Evidencia:** El modelo `Cultivo` obtiene los `dias_cosecha` de la tabla `variedades` y calcula automáticamente `fecha_cosecha_estimada = fecha_siembra + dias_cosecha`. Este cálculo se hace en el servidor sin intervención del usuario.

### ✅ C3. Registro por variedad específica — CUMPLIDO

**Evidencia:** El formulario presenta un select con las variedades agrupadas por tipo de cultivo. Cada variedad tiene sus propias características (días de cosecha, días de recordatorio) que se aplican automáticamente al seleccionarla.

### ✅ C4. Validación de lote disponible — CUMPLIDO

**Evidencia:** El `SELECT` del formulario solo muestra lotes con estado `disponible`. Además, el backend verifica que el lote seleccionado no tenga ya un cultivo activo antes de proceder con la inserción.

**📊 Cumplimiento DP-6: 4/4 = 100% ✨**

---

# DP-7: Asignación de Lotes a Trabajadores

> **Rol:** Administrador  
> **Objetivo:** Asignar lotes específicos a trabajadores  
> **Propósito:** Distribuir responsabilidades de forma clara

### ✅ C1. Asignación exitosa — CUMPLIDO

**Evidencia:** El [AsignacionController.php](file:///c:/laragon/www/Gsigvos/controllers/AsignacionController.php) crea un registro en la tabla `usuarios_lotes` vinculando al trabajador con el lote, y genera una notificación automática avisándole de su nueva responsabilidad.

### ✅ C2. Un trabajador por lote — CUMPLIDO

**Evidencia:** El controlador verifica que no exista ya una asignación activa (`activo = 1`) para ese lote antes de crear una nueva. Si ya hay un trabajador asignado, bloquea la operación.

### ✅ C3. Validación de trabajador activo — CUMPLIDO

**Evidencia:** El `SELECT` del formulario de asignación solo muestra trabajadores con `activo = 1`, impidiendo asignar lotes a usuarios deshabilitados.

### ✅ C4. Historial de asignaciones previas — CUMPLIDO

**Evidencia:** En el panel del trabajador, existe una sección "Historial de asignaciones" que muestra los lotes que estuvieron previamente bajo su cargo (registros con `activo = 0`), incluyendo fecha de asignación y estado.

**📊 Cumplimiento DP-7: 4/4 = 100% ✨**

---

# DP-8: Panel Operativo del Trabajador

> **Rol:** Trabajador  
> **Objetivo:** Consultar lotes asignados, cultivos activos y actividades del día  
> **Propósito:** Tener toda la información operativa necesaria para el trabajo diario

### ✅ C1. Visualización de lotes asignados — CUMPLIDO

**Evidencia:** La consulta SQL en `trabajador.php` filtra los lotes por `WHERE ul.id_usuario = :u AND ul.activo = 1`, mostrando solo los lotes asignados al trabajador logueado.

### ✅ C2. Detalle del cultivo activo — CUMPLIDO

**Evidencia:** Para cada lote se muestra la tarjeta del cultivo activo con código, variedad, tipo, fecha de siembra y días restantes para la cosecha estimada.

### ✅ C3. Consulta de actividades de hoy — CUMPLIDO

**Evidencia:** En la sección de actividades, la pestaña "Hoy" filtra y muestra únicamente las actividades cuya `fecha_programada` coincide con `date('Y-m-d')`, con un badge de conteo.

### ✅ C4. Bloqueo a lotes no asignados — CUMPLIDO

**Evidencia:** La consulta SQL usa un `JOIN` con `usuarios_lotes`, por lo que es técnicamente imposible que el trabajador vea información de lotes que no le corresponden.

### ✅ C5. Sin asignaciones activas — CUMPLIDO

**Evidencia:** Cuando `$tieneAsignaciones` es `false`, se muestra un estado vacío elegante con un icono de candado y el mensaje "Sin asignaciones activas".

### ✅ C6. Consulta en modo offline — CUMPLIDO

**Evidencia:** El panel del trabajador tiene implementado el banner de offline (`offline-indicator`) y el listener de eventos de conectividad que permite al menos visualizar los datos que ya fueron cargados.

**📊 Cumplimiento DP-8: 6/6 = 100% ✨**

---

# DP-9: Buscador Rápido y Filtros Avanzados

> **Rol:** Administrador  
> **Objetivo:** Buscar rápidamente cultivos, actividades o lotes  
> **Propósito:** Agilizar la consulta de información sin navegar por múltiples módulos

### ❌ C1. Búsqueda por nombre de cultivo — NO CUMPLIDO
### ❌ C2. Búsqueda por rango de fechas — NO CUMPLIDO
### ❌ C3. Filtro por lote específico — NO CUMPLIDO
### ❌ C4. Filtro combinado múltiple — NO CUMPLIDO
### ❌ C5. Filtro por estado — NO CUMPLIDO
### ❌ C6. Búsqueda sin resultados — NO CUMPLIDO
### ❌ C7. Historial de búsquedas recientes — NO CUMPLIDO

**Evidencia del fallo:**  
Revisando exhaustivamente el sidebar, header y todo el JavaScript del `admin.php`, **no existe ningún componente de buscador global**. No hay barra de búsqueda en el header, no hay modal de búsqueda avanzada, no hay endpoint de búsqueda en los controladores. Este módulo completo **nunca fue programado**.

> [!WARNING]
> Esta es una de las historias más críticas. El buscador global es una funcionalidad fundamental que tu evaluador buscará de inmediato en el prototipo.

**Corrección propuesta — Arquitectura completa:**

1. **Backend** — Crear `controllers/BusquedaController.php`:

```php
<?php
require_once dirname(__DIR__) . '/config/session.php';
if (!isset($_SESSION['id_usuario'])) { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/config/database.php';

$db = (new Database())->conectar();
$q  = trim($_GET['q'] ?? '');
$tipo   = $_GET['tipo']   ?? 'todos';  // todos, cultivos, lotes, actividades
$estado = $_GET['estado']  ?? '';
$desde  = $_GET['desde']   ?? '';
$hasta  = $_GET['hasta']   ?? '';
$lote   = (int)($_GET['lote'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

if (strlen($q) < 2 && empty($estado) && empty($desde)) {
    echo json_encode(['ok' => true, 'resultados' => [], 'total' => 0]);
    exit;
}

$resultados = [];
$params = [];
$where = [];

// Búsqueda en cultivos
if ($tipo === 'todos' || $tipo === 'cultivos') {
    $sql = "SELECT 'cultivo' AS tipo_resultado, c.codigo AS titulo, 
                   v.nombre AS subtitulo, c.estado, l.identificador AS lote_id
            FROM cultivos c
            JOIN variedades v ON c.id_variedad = v.id_variedad
            JOIN lotes l ON c.id_lote = l.id_lote
            WHERE (c.codigo LIKE :q OR v.nombre LIKE :q2)";
    $p = [':q' => "%{$q}%", ':q2' => "%{$q}%"];
    if ($estado) { $sql .= " AND c.estado = :e"; $p[':e'] = $estado; }
    if ($lote)   { $sql .= " AND c.id_lote = :l"; $p[':l'] = $lote; }
    $stmt = $db->prepare($sql . " LIMIT 20");
    $stmt->execute($p);
    $resultados = array_merge($resultados, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Guardar búsqueda en historial
if ($q) {
    $db->prepare("INSERT INTO historial_busquedas (id_usuario, termino, fecha) VALUES (:u, :t, NOW())
                  ON DUPLICATE KEY UPDATE fecha = NOW()")
       ->execute([':u' => $_SESSION['id_usuario'], ':t' => $q]);
}

echo json_encode(['ok' => true, 'resultados' => $resultados, 'total' => count($resultados)]);
```

2. **Frontend** — Agregar la barra de búsqueda en el header del admin:

```html
<!-- En partials/admin/header.php -->
<div class="relative flex-1 max-w-xl mx-4">
    <i class="fas fa-magnifying-glass absolute left-4 top-3.5 text-slate-400"></i>
    <input type="text" id="buscador-global" placeholder="Buscar cultivos, lotes, actividades..."
           class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm 
                  focus:outline-none focus:ring-2 focus:ring-emerald-400"
           oninput="buscarGlobal(this.value)">
    <div id="resultados-busqueda" class="absolute top-full left-0 right-0 mt-1 bg-white 
         rounded-xl shadow-2xl border border-slate-100 hidden max-h-80 overflow-y-auto z-50"></div>
</div>
```

3. **Tabla para historial:**

```sql
CREATE TABLE historial_busquedas (
    id_usuario INT NOT NULL,
    termino VARCHAR(200) NOT NULL,
    fecha DATETIME DEFAULT NOW(),
    PRIMARY KEY (id_usuario, termino),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
```

**📊 Cumplimiento DP-9: 0/7 = 0% 🔴**

---

# DP-10: Programar Actividades Futuras con Recordatorios

> **Rol:** Administrador  
> **Objetivo:** Programar actividades y recibir recordatorios automáticos  
> **Propósito:** Asegurar que ninguna labor agrícola se olvide

### ✅ C1. Programación exitosa — CUMPLIDO

**Evidencia:** El [ActividadController.php](file:///c:/laragon/www/Gsigvos/controllers/ActividadController.php) crea actividades con tipo, cultivo, trabajador asignado, fecha programada y descripción. Funciona correctamente.

### ❌ C2. Recordatorio automático (día anterior) — NO CUMPLIDO

**Evidencia del fallo:**  
La tabla `variedades` tiene un campo `dias_recordatorio_previo`, pero **no existe ninguna tarea programada (cron job)** ni lógica de ejecución al login que compare las fechas de las actividades pendientes con la fecha actual y genere notificaciones automáticas el día anterior.

**Corrección propuesta:**

Crear un script que se ejecute al inicio de sesión del admin:

```php
// En admin.php, después de las consultas de datos:
// === GENERAR RECORDATORIOS AUTOMÁTICOS ===
$mañana = date('Y-m-d', strtotime('+1 day'));
$actividadesManana = $db->prepare(
    "SELECT a.id_actividad, a.descripcion, ta.nombre AS tipo, a.id_asignado_a
     FROM actividades a
     JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
     WHERE a.fecha_programada = :manana AND a.estado = 'pendiente'"
);
$actividadesManana->execute([':manana' => $mañana]);

foreach ($actividadesManana->fetchAll(PDO::FETCH_ASSOC) as $act) {
    // Verificar que no se haya enviado ya el recordatorio hoy
    $yaEnviado = $db->prepare(
        "SELECT COUNT(*) FROM notificaciones 
         WHERE id_referencia = :ref AND tipo = 'recordatorio' 
         AND DATE(creada_en) = CURDATE()"
    );
    $yaEnviado->execute([':ref' => $act['id_actividad']]);
    
    if ($yaEnviado->fetchColumn() == 0 && $act['id_asignado_a']) {
        $notif->crear($act['id_asignado_a'], 'recordatorio', 
            '⏰ Recordatorio: Actividad mañana',
            "Tienes programada '{$act['tipo']} — {$act['descripcion']}' para mañana.",
            'alta', null, $act['id_actividad']);
    }
}
```

### ❌ C3. Alerta cultivo próximo a cosechar — NO CUMPLIDO

**Evidencia:** No existe lógica que genere notificaciones automáticas cuando un cultivo está a X días de su fecha de cosecha estimada.

**Corrección propuesta similar al C2**, consultando cultivos cuya `fecha_cosecha_estimada` esté dentro de los próximos 7 días.

### ❌ C4. Alerta fertilización vencida — NO CUMPLIDO

**Evidencia:** No hay lógica que detecte actividades de tipo "Fertilización" con `fecha_programada < CURDATE()` y `estado = 'pendiente'`, y genere una alerta de urgencia.

### ✅ C5. Marcar como completada — CUMPLIDO

**Evidencia:** El formulario de edición permite cambiar el estado a "completada" tanto desde el panel admin como desde el panel del trabajador.

### ✅ C6. Modificar fecha — CUMPLIDO

**Evidencia:** La función `editarActividad()` acepta y actualiza el campo `fecha_programada` sin restricciones.

### ✅ C7. Cancelar actividad — CUMPLIDO

**Evidencia:** El controlador acepta el estado "cancelada" y también existe la función `eliminarActividad()` que borra el registro completamente.

### ❌ C8. Validación de fecha futura — NO CUMPLIDO

**Evidencia del fallo:**  
En `ActividadController.php`, la función `crearActividad()` valida que los campos no estén vacíos pero **nunca compara la fecha recibida con la fecha actual**. Esto permite crear actividades con fechas en el pasado (ej. "2020-01-01").

**Corrección propuesta:**

```php
// En ActividadController.php, función crearActividad(), después de las validaciones:
if ($fecha < date('Y-m-d')) {
    $_SESSION['toast'] = [
        'text' => 'La fecha programada no puede ser anterior a hoy.', 
        'type' => 'error'
    ];
    header('Location: ../views/dashboards/admin.php#actividades'); exit;
}
```

Y en el frontend como protección adicional:

```html
<input type="date" name="fecha_programada" min="<?= date('Y-m-d') ?>" required>
```

**📊 Cumplimiento DP-10: 4/8 = 50%**

---

# DP-11: Calendario de Actividades

> **Rol:** Administrador  
> **Objetivo:** Visualizar un calendario con actividades y fechas de cosecha  
> **Propósito:** Tener una vista temporal clara de todas las operaciones agrícolas

### ✅ C1. Vista mensual — CUMPLIDO
### ✅ C2. Vista semanal — CUMPLIDO
### ✅ C3. Navegación al detalle — CUMPLIDO
### ✅ C4. Indicadores visuales (hoy) — CUMPLIDO
### ✅ C5. Próximas cosechas en calendario — CUMPLIDO
### ✅ C6. Filtrado por tipo — CUMPLIDO
### ✅ C7. Agregar desde calendario — CUMPLIDO

**Evidencia:**  
El módulo de calendario en `admin.php` (líneas 1595-1655) es uno de los más completos del sistema. Construido 100% en JavaScript puro, incluye:
- Dos vistas intercambiables (mes/semana) con botones en la línea 1614-1617
- Navegación con flechas y botón "Hoy" (líneas 1620-1629)
- Un select `#cal-filtro` para filtrar por tipo de actividad (línea 1604)
- Pastillas de colores: amarillo (pendiente), azul (en proceso), verde (completada), rojo (cancelada), **púrpura (cosecha estimada)**
- Panel de detalle al hacer clic en cualquier evento
- Botón "+" al pasar el cursor sobre un día para crear actividad con fecha precargada

**📊 Cumplimiento DP-11: 7/7 = 100% ✨**

---

# DP-12: Reagendar Actividades

> **Rol:** Administrador  
> **Objetivo:** Modificar o reagendar actividades que aún no se ejecutaron  
> **Propósito:** Ajustar la planificación según cambios climáticos o necesidades

### ✅ C1. Reagendar actividad — CUMPLIDO

**Evidencia:** La función `editarActividad()` acepta y actualiza correctamente el campo `fecha_programada`.

### ✅ C2. Modificar descripción — CUMPLIDO

**Evidencia:** El campo `descripcion` se recibe y actualiza sin problemas en la misma función.

### ❌ C3. Cambiar cultivo objetivo — NO CUMPLIDO

**Evidencia del fallo:**  
En [Actividad.php](file:///c:/laragon/www/Gsigvos/models/Actividad.php) líneas 97-118, el método `editar()` actualiza `id_tipo_actividad`, `id_asignado_a`, `estado`, `fecha_programada`, `descripcion` y `observaciones`, pero **`id_cultivo` no está incluido** en la sentencia `UPDATE`. Una vez creada la actividad, el cultivo asociado no puede cambiarse.

**Corrección propuesta:**

```php
// En models/Actividad.php, método editar():
public function editar($id, $datos) {
    $stmt = $this->conn->prepare(
        "UPDATE actividades SET
            id_cultivo          = :id_cultivo,    /* <-- AGREGAR */
            id_tipo_actividad   = :id_tipo,
            id_asignado_a       = :asignado_a,
            estado              = :estado,
            fecha_programada    = :fecha,
            descripcion         = :descripcion,
            observaciones       = :observaciones,
            actualizado_en      = NOW()
         WHERE id_actividad = :id"
    );
    $stmt->execute([
        ':id_cultivo'   => $datos['id_cultivo'],   /* <-- AGREGAR */
        ':id_tipo'      => $datos['id_tipo_actividad'],
        ':asignado_a'   => $datos['id_asignado_a'] ?: null,
        ':estado'       => $datos['estado'],
        ':fecha'        => $datos['fecha_programada'],
        ':descripcion'  => $datos['descripcion'],
        ':observaciones'=> $datos['observaciones'] ?? '',
        ':id'           => $id,
    ]);
}
```

Y en `ActividadController.php`, recibir el nuevo campo:
```php
$id_cultivo = (int)($_POST['id_cultivo'] ?? 0);
```

### ❌ C4. Restricción actividades completadas — NO CUMPLIDO

**Evidencia del fallo:**  
La función `editarActividad()` en el controlador no consulta el estado actual de la actividad antes de aplicar cambios. Cualquier persona que manipule el HTML podría enviar un POST para modificar una actividad que ya estaba cerrada/completada.

**Corrección propuesta:**

```php
// En ActividadController.php, función editarActividad(), al inicio:
$actividadActual = $model->obtenerPorId($id);
if (!$actividadActual) {
    $_SESSION['toast'] = ['text' => 'Actividad no encontrada.', 'type' => 'error'];
    header('Location: ../views/dashboards/admin.php#actividades'); exit;
}
if ($actividadActual['estado'] === 'completada') {
    $_SESSION['toast'] = [
        'text' => 'No se puede editar una actividad ya completada.', 
        'type' => 'error'
    ];
    header('Location: ../views/dashboards/admin.php#actividades'); exit;
}
```

### ✅ C5. Cancelar actividad — CUMPLIDO
### ❌ C6. Validación fecha futura al reagendar — NO CUMPLIDO

**Evidencia:** Mismo problema que DP-10 C8. La función de edición no valida que la nueva fecha sea futura.

**Corrección:** Aplicar la misma validación `$fecha < date('Y-m-d')` dentro de `editarActividad()`.

### ❌ C7. Modificación masiva recurrentes — NO CUMPLIDO

**Evidencia del fallo:**  
El sistema no tiene el concepto de "actividades recurrentes" ni "series". Cada actividad es un registro individual e independiente. No hay tablas como `series_actividad` ni campos como `id_serie` o `frecuencia`.

**Corrección propuesta (simplificada):**

Para una versión mínima viable, se puede agregar un campo `id_grupo` en la tabla actividades:

```sql
ALTER TABLE actividades ADD COLUMN id_grupo INT DEFAULT NULL;
```

Y en el controlador de creación, permitir crear múltiples actividades con el mismo `id_grupo`:

```php
// Al crear una actividad recurrente:
$grupoId = $db->query("SELECT COALESCE(MAX(id_grupo),0)+1 FROM actividades")->fetchColumn();
$frecuencia = (int)$_POST['frecuencia_dias']; // ej: cada 7 días
$repeticiones = (int)$_POST['repeticiones'];   // ej: 4 semanas

for ($i = 0; $i < $repeticiones; $i++) {
    $fechaIteracion = date('Y-m-d', strtotime("+".($i * $frecuencia)." days", strtotime($fecha)));
    $model->crear(array_merge($datos, [
        'fecha_programada' => $fechaIteracion,
        'id_grupo' => $grupoId,
    ]));
}
```

### ✅ C8. Notificación de cambios — CUMPLIDO

**Evidencia:** En `ActividadController.php` línea 107, al editar una actividad asignada, se envía una notificación al trabajador: `$notif->crear($id_asignado_a, 'actividad', '✏️ Actividad actualizada', ...)`.

**📊 Cumplimiento DP-12: 4/8 = 50%**

---

# DP-13: Registrar Actividades Realizadas (Admin)

> **Rol:** Administrador  
> **Objetivo:** Documentar todas las labores realizadas en los cultivos  
> **Propósito:** Mantener un historial completo de operaciones

### ✅ C1. Registro exitoso — CUMPLIDO

**Evidencia:** El formulario de creación de actividades permite al administrador registrar cualquier tipo de actividad con su información completa.

### ✅ C2. Registro por tipo — CUMPLIDO

**Evidencia:** El sistema presenta los tipos de actividad (Fertilización, Fumigación, Poda, Riego, etc.) cargados dinámicamente desde la tabla `tipos_actividad`.

### ❌ C3. Validación de fecha no futura — NO CUMPLIDO

**Evidencia del fallo:**  
Al reportar una labor **ya realizada**, la fecha debería ser hoy o anterior. Sin embargo, el sistema no distingue entre "programar" (fecha futura) y "registrar realizada" (fecha pasada/presente). No hay validación que impida poner una fecha del año 2030 como "labor realizada".

**Corrección propuesta:**

Implementar un modo dual en el formulario:

```javascript
// En el formulario de actividades del admin:
function cambiarModoActividad(modo) {
    const inputFecha = document.getElementById('fecha-actividad');
    if (modo === 'registrar') {
        // Labor ya realizada: fecha debe ser <= hoy
        inputFecha.max = new Date().toISOString().split('T')[0];
        inputFecha.removeAttribute('min');
    } else {
        // Programar: fecha debe ser >= hoy
        inputFecha.min = new Date().toISOString().split('T')[0];
        inputFecha.removeAttribute('max');
    }
}
```

### ❌ C4. Historial por cultivo — NO CUMPLIDO

**Evidencia:** No existe un filtro dinámico que permita seleccionar un cultivo y ver **exclusivamente** las actividades históricas asociadas a ese cultivo.

**Corrección propuesta:**

```html
<!-- Agregar selector de filtro en el módulo de actividades -->
<select id="filtro-cultivo-act" onchange="filtrarActividadesPorCultivo(this.value)">
    <option value="">Todos los cultivos</option>
    <?php foreach ($cultivosFoto as $c): ?>
    <option value="<?= htmlspecialchars($c['codigo']) ?>">
        <?= htmlspecialchars($c['codigo'] . ' — ' . $c['lote_nombre']) ?>
    </option>
    <?php endforeach; ?>
</select>
```

### ❌ C5. Historial por tipo — NO CUMPLIDO

**Evidencia:** Mismo caso que C4, no hay filtro por tipo de actividad en la tabla principal de actividades.

### ❌ C6. Validación descripción mínima — NO CUMPLIDO

**Evidencia del fallo:**  
En `ActividadController.php`, la validación solo verifica `empty($descripcion)`. Esto acepta descripciones de un solo carácter como "a" o "x", lo cual no cumple el propósito de documentar el trabajo.

**Corrección propuesta:**

```php
// En ActividadController.php:
if (mb_strlen($descripcion) < 10) {
    $_SESSION['toast'] = [
        'text' => 'La descripción debe tener al menos 10 caracteres para documentar adecuadamente la labor.',
        'type' => 'error'
    ];
    header('Location: ../views/dashboards/admin.php#actividades'); exit;
}
```

Y en el frontend:
```html
<textarea name="descripcion" minlength="10" required 
          placeholder="Describe detalladamente la labor realizada (mínimo 10 caracteres)..."></textarea>
```

**📊 Cumplimiento DP-13: 2/6 = 33%**

---

# DP-14: Registrar Actividades (Trabajador)

> **Rol:** Trabajador  
> **Objetivo:** Registrar actividades realizadas en cultivos asignados  
> **Propósito:** Dejar evidencia operativa del trabajo ejecutado en campo

### ❌ C1. Registro exitoso — NO CUMPLIDO
### ❌ C2. Descripción mínima — NO CUMPLIDO
### ❌ C3. Restricción sobre cultivos finalizados — NO CUMPLIDO
### ❌ C4. Asignación automática de fecha — NO CUMPLIDO
### ❌ C5. Bloqueo en cultivos no asignados — NO CUMPLIDO
### ❌ C6. Registro offline — NO CUMPLIDO

**Evidencia del fallo:**  
Revisando exhaustivamente [trabajador.php](file:///c:/laragon/www/Gsigvos/views/dashboards/trabajador.php), el trabajador **solo tiene la capacidad de cambiar el estado** de actividades que el administrador le programó previamente (mediante un `<select>` con `onchange="this.form.submit()"`). **No existe ningún botón "Crear nueva actividad"**, ningún formulario de registro, ni ningún modal para que el trabajador documente labores espontáneas.

> [!CAUTION]
> Esta es una de las historias más críticas. Tu evaluador espera que el trabajador pueda crear sus propias actividades desde el campo.

**Corrección propuesta — Implementación completa:**

1. **Agregar botón y modal en `trabajador.php`:**

```html
<!-- Botón "Registrar labor" en el módulo de actividades del trabajador -->
<button onclick="abrirModalRegistrarLabor()" 
    class="bg-[#10b981] hover:bg-[#059669] text-white text-sm font-semibold px-5 py-2.5 
           rounded-xl transition flex items-center gap-2">
    <i class="fas fa-plus"></i> Registrar labor realizada
</button>
```

2. **Crear `controllers/TrabajadorRegistroController.php`:**

```php
<?php
session_start();
if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 2) {
    header('Location: ../views/auth/login.php'); exit;
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/config/database.php';

$db = (new Database())->conectar();
$id_usuario = (int)$_SESSION['id_usuario'];

$id_cultivo    = (int)($_POST['id_cultivo'] ?? 0);
$id_tipo       = (int)($_POST['id_tipo_actividad'] ?? 0);
$descripcion   = trim($_POST['descripcion'] ?? '');
$fecha         = trim($_POST['fecha'] ?? date('Y-m-d'));

// Validar descripción mínima
if (mb_strlen($descripcion) < 10) {
    $_SESSION['toast'] = ['text' => 'La descripción debe tener al menos 10 caracteres.', 'type' => 'error'];
    header('Location: ../views/dashboards/trabajador.php#actividades'); exit;
}

// Validar que el cultivo pertenece a sus lotes
$chk = $db->prepare(
    "SELECT c.id_cultivo FROM cultivos c 
     JOIN usuarios_lotes ul ON c.id_lote = ul.id_lote
     WHERE c.id_cultivo = :c AND ul.id_usuario = :u AND ul.activo = 1 
     AND c.estado != 'cosechado' LIMIT 1"
);
$chk->execute([':c' => $id_cultivo, ':u' => $id_usuario]);
if (!$chk->fetch()) {
    $_SESSION['toast'] = ['text' => 'No tienes permiso o el cultivo ya fue cosechado.', 'type' => 'error'];
    header('Location: ../views/dashboards/trabajador.php#actividades'); exit;
}

// Validar fecha no futura
if ($fecha > date('Y-m-d')) {
    $_SESSION['toast'] = ['text' => 'La fecha no puede ser futura para una labor ya realizada.', 'type' => 'error'];
    header('Location: ../views/dashboards/trabajador.php#actividades'); exit;
}

// Si no se proporcionó fecha, asignar hoy automáticamente
if (empty($fecha)) $fecha = date('Y-m-d');

// Insertar actividad como "completada" directamente
$stmt = $db->prepare(
    "INSERT INTO actividades (id_cultivo, id_tipo_actividad, id_creado_por, id_asignado_a, 
     estado, fecha_programada, descripcion, creado_en, actualizado_en)
     VALUES (:cultivo, :tipo, :creador, :creador2, 'completada', :fecha, :desc, NOW(), NOW())"
);
$stmt->execute([
    ':cultivo'  => $id_cultivo,
    ':tipo'     => $id_tipo,
    ':creador'  => $id_usuario,
    ':creador2' => $id_usuario,
    ':fecha'    => $fecha,
    ':desc'     => $descripcion,
]);

$_SESSION['toast'] = ['text' => 'Labor registrada exitosamente.', 'type' => 'success'];
header('Location: ../views/dashboards/trabajador.php#actividades');
```

**📊 Cumplimiento DP-14: 0/6 = 0% 🔴**

---

# DP-15: Completar Tareas y Adjuntar Foto (Trabajador)

> **Rol:** Trabajador  
> **Objetivo:** Completar actividades pendientes y documentar con evidencia fotográfica  
> **Propósito:** Cerrar tareas con evidencia visual del trabajo realizado

### ✅ C1. Ver pendientes asignadas — CUMPLIDO

**Evidencia:** La pestaña "Hoy" filtra las actividades por `fecha_programada = date('Y-m-d')` y la pestaña "Todas" muestra el listado completo con agrupación por estado.

### ✅ C2. Marcar como realizada — CUMPLIDO

**Evidencia:** El `<select>` con `onchange="this.form.submit()"` envía el nuevo estado al `TrabajadorActividadController.php`, que verifica permisos y actualiza el registro.

### ❌ C3. Validación fecha no futura — NO CUMPLIDO

**Evidencia:** Al marcar una actividad como "completada", no se valida que la fecha de ejecución sea coherente (hoy o en el pasado). No hay un campo de "fecha de ejecución real" en el formulario del trabajador.

**Corrección:** Agregar validación en `TrabajadorActividadController.php`:

```php
if ($estado === 'completada') {
    $actData = $db->prepare("SELECT fecha_programada FROM actividades WHERE id_actividad = :id LIMIT 1");
    $actData->execute([':id' => $id]);
    $fechaProg = $actData->fetchColumn();
    // Si la actividad está programada para el futuro, advertir
    if ($fechaProg > date('Y-m-d')) {
        $_SESSION['toast'] = [
            'text' => 'Advertencia: esta actividad estaba programada para una fecha futura.',
            'type' => 'warning'
        ];
    }
}
```

### ✅ C4. Capturar foto como evidencia — CUMPLIDO

**Evidencia:** El modal "Subir foto" permite seleccionar entre asociar la foto a un cultivo o a una actividad, y el `FotoController.php` la guarda correctamente en el servidor.

### ❌ C5. Fotografía offline — NO CUMPLIDO

**Evidencia:** La subida de fotos usa un formulario HTML estándar (`multipart/form-data`). Sin internet, el envío falla. No hay implementación de IndexedDB ni Service Worker para almacenar la foto localmente.

**Corrección propuesta (conceptual):**

```javascript
// Interceptar el submit del formulario de foto
document.getElementById('form-foto').addEventListener('submit', async function(e) {
    if (!navigator.onLine) {
        e.preventDefault();
        const formData = new FormData(this);
        const file = formData.get('foto');
        const reader = new FileReader();
        reader.onload = async () => {
            // Guardar en IndexedDB
            const db = await openDB('sigvos-offline', 1);
            await db.put('fotos-pendientes', {
                id: Date.now(),
                data: reader.result,
                cultivo: formData.get('id_cultivo'),
                timestamp: new Date().toISOString()
            });
            Swal.fire('Guardado offline', 'La foto se subirá cuando recuperes conexión.', 'info');
        };
        reader.readAsDataURL(file);
    }
});
```

### ❌ C6. Consulta historial fotográfico — NO CUMPLIDO

**Evidencia:** Como las fotos sobrescriben el campo `fotografia` en la tabla `cultivos` (un solo campo por registro), las fotos anteriores se pierden permanentemente. No existe un historial visual.

**Corrección:** Se requiere la tabla `fotos_cultivo` (ver DP-16).

**📊 Cumplimiento DP-15: 3/6 = 50%**

---

# DP-16: Fotografías de Progreso de Cultivo (Admin)

> **Rol:** Administrador  
> **Objetivo:** Capturar y almacenar fotografías del progreso de los cultivos  
> **Propósito:** Documentar visualmente el desarrollo y tener un registro fotográfico

### ✅ C1. Captura en recorrido — CUMPLIDO

**Evidencia:** El modal de subir foto acepta imágenes JPG/PNG/WEBP hasta 5MB y las almacena en `public/storage/fotos/`.

### ❌ C2. Visualización del progreso cronológico — NO CUMPLIDO

**Evidencia del fallo:**  
En [FotoController.php](file:///c:/laragon/www/Gsigvos/controllers/FotoController.php) línea 47: `$db->prepare("UPDATE cultivos SET fotografia = :r ...")`. Cada foto nueva **reemplaza** la anterior. Es imposible tener un historial de progreso con esta arquitectura.

**Corrección propuesta:**

1. Crear tabla de galería:

```sql
CREATE TABLE fotos_cultivo (
    id_foto INT AUTO_INCREMENT PRIMARY KEY,
    id_cultivo INT NOT NULL,
    id_usuario INT NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    descripcion VARCHAR(500) DEFAULT NULL,
    fecha_captura DATETIME DEFAULT NOW(),
    FOREIGN KEY (id_cultivo) REFERENCES cultivos(id_cultivo),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);
```

2. Modificar `FotoController.php` para insertar en vez de actualizar:

```php
// Reemplazar el UPDATE por un INSERT:
$db->prepare(
    "INSERT INTO fotos_cultivo (id_cultivo, id_usuario, ruta, descripcion, fecha_captura)
     VALUES (:cultivo, :usuario, :ruta, :desc, NOW())"
)->execute([
    ':cultivo' => $id,
    ':usuario' => $id_usuario,
    ':ruta'    => $ruta,
    ':desc'    => trim($_POST['descripcion'] ?? ''),
]);
```

### ❌ C3. Limitación almacenamiento local — NO CUMPLIDO
### ❌ C4. Sincronización automática — NO CUMPLIDO
### ❌ C5. Captura en modo offline — NO CUMPLIDO

**Evidencia:** No hay Service Worker, IndexedDB ni API de almacenamiento local implementado para el módulo de fotografías.

### ✅ C6. Eliminación de fotografías — CUMPLIDO

**Evidencia:** La acción `eliminar_cultivo` en `FotoController.php` (línea 96) elimina el archivo físico con `unlink()` y limpia el campo en la BD.

### ❌ C7. Agregar descripción a fotografía — NO CUMPLIDO

**Evidencia:** El modal de subir foto (`modal-subir-foto`) no incluye un campo de texto para la descripción. El `FotoController.php` tampoco recibe ni almacena ese dato.

**Corrección:**

```html
<!-- Agregar en el modal de subir foto: -->
<div>
    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">
        Descripción (opcional)
    </label>
    <input type="text" name="descripcion" maxlength="300" 
           placeholder="Ej: Estado del cultivo semana 3, buen desarrollo foliar..."
           class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm 
                  focus:border-emerald-400 focus:outline-none">
</div>
```

**📊 Cumplimiento DP-16: 2/7 = 29%**

---

# DP-17: Registrar Cosecha y Liberar Lote

> **Rol:** Administrador  
> **Objetivo:** Registrar cuando un cultivo es cosechado y liberar el lote  
> **Propósito:** Cerrar el ciclo productivo y preparar el lote para nuevos cultivos

### ✅ C1. Registro exitoso de cosecha — CUMPLIDO

**Evidencia:** El [CosechaController.php](file:///c:/laragon/www/Gsigvos/controllers/CosechaController.php) recibe cultivo, fecha real, cantidad en kg y observaciones. Internamente llama a `$model->registrarCosecha()` que marca el cultivo como "cosechado", limpia `activo_en_lote` y cambia el estado del lote a "disponible" en una sola transacción.

### ✅ C2. Lote disponible después de cosecha — CUMPLIDO

**Evidencia:** El modelo `Cultivo` ejecuta automáticamente `UPDATE lotes SET estado = 'disponible'` como parte del proceso de registro de cosecha.

### ✅ C3. Historial de producción por lote — CUMPLIDO

**Evidencia:** En `admin.php` línea 1304, el módulo de cosecha muestra una tabla completa con todas las cosechas históricas, incluyendo KPIs de producción total, número de cosechas y lotes con historial.

### ✅ C4. Validación de cantidad mínima — CUMPLIDO

**Evidencia:** `CosechaController.php` línea 29: `if (!is_numeric($cantidad_kg) || (float)$cantidad_kg < 0.1)` — rechaza cantidades menores a 0.1 kg.

### ✅ C5. Registro de cosecha con observaciones — CUMPLIDO

**Evidencia:** El formulario tiene un `<textarea>` para observaciones (línea 1413 del admin) y el controlador las guarda en el campo `observaciones` de la tabla `cultivos`.

### ✅ C6. Comparación con estimación inicial — CUMPLIDO

**Evidencia:** `CosechaController.php` líneas 67-70: calcula `$diasDiff` entre fecha real y estimada, y genera un mensaje: "Realizada X día(s) antes/después de lo estimado". La tabla del historial también muestra esta diferencia con colores (azul si fue antes, ámbar si fue después, verde si fue en fecha).

**📊 Cumplimiento DP-17: 6/6 = 100% ✨**

---

# DP-18: Notificaciones y Sincronización Manual (Trabajador)

> **Rol:** Trabajador  
> **Objetivo:** Consultar notificaciones y ejecutar sincronización manual  
> **Propósito:** Mantenerse informado y asegurar que los datos de campo queden respaldados

### ✅ C1. Visualización de notificaciones prioritarias — CUMPLIDO

**Evidencia:** En `trabajador.php` líneas 56-62, las notificaciones se cargan con `ORDER BY leida ASC, creada_en DESC`, priorizando las no leídas. Se muestran con iconos de prioridad y timestamp.

### ✅ C2. Marcar notificación como leída — CUMPLIDO

**Evidencia:** [NotificacionController.php](file:///c:/laragon/www/Gsigvos/controllers/NotificacionController.php) caso `marcar_leida` (línea 18): actualiza `leida = 1, leida_en = NOW()`.

### ✅ C3. Ejecución de sincronización manual — CUMPLIDO

**Evidencia:** Caso `sincronizar` (línea 32): inserta un registro en la tabla `sincronizaciones` con `tipo = 'manual'` y `estado = 'completada'`, y almacena el timestamp en la sesión.

### ✅ C4. Indicador de estado de sincronización — CUMPLIDO

**Evidencia:** En `trabajador.php` línea 1124, al recuperar conexión, el JavaScript actualiza el elemento `#sync-status-t` con la fecha y hora formateada.

### ❌ C5. Recuperación tras fallo de sincronización — NO CUMPLIDO

**Evidencia del fallo:**  
En `NotificacionController.php` línea 36, la sincronización **siempre** inserta `estado = 'completada'` sin verificar si hubo errores reales de transmisión. No hay bloque `try/catch`, no hay manejo de excepciones y no hay opción de reintentar.

**Corrección propuesta:**

```php
case 'sincronizar':
    try {
        $db->beginTransaction();
        // ... lógica de sincronización real ...
        $db->commit();
        $estado = 'completada';
        $msg = 'Sincronización completada correctamente.';
    } catch (Exception $e) {
        $db->rollBack();
        $estado = 'fallida';
        $msg = 'Error en la sincronización: ' . $e->getMessage() . '. Puedes reintentar.';
    }
    
    $stmt = $db->prepare(
        "INSERT INTO sincronizaciones (id_usuario, dispositivo, tipo, estado, iniciada_en, finalizada_en)
         VALUES (:u, 'Web', 'manual', :estado, NOW(), NOW())"
    );
    $stmt->execute([':u' => $id_usuario, ':estado' => $estado]);
    
    $_SESSION['toast'] = [
        'text' => $msg, 
        'type' => $estado === 'completada' ? 'success' : 'error'
    ];
    break;
```

### ✅ C6. Sincronización al recuperar conexión — CUMPLIDO

**Evidencia:** El listener `window.addEventListener('online', ...)` en `trabajador.php` línea 1119 dispara automáticamente un `fetch()` al endpoint de sincronización del servidor.

**📊 Cumplimiento DP-18: 5/6 = 83%**

---

# DP-19: Sincronización Automática del Administrador

> **Rol:** Administrador  
> **Objetivo:** Que el sistema sincronice automáticamente los datos con la nube  
> **Propósito:** Tener respaldo seguro y acceso desde cualquier dispositivo

### ❌ C1. Sincronización automática con WiFi — NO CUMPLIDO

**Evidencia:** La vista del administrador (`admin.php`) **no tiene ningún listener de eventos de red** (`online`/`offline`). A diferencia del trabajador que sí los implementa, el admin no detecta cambios de conectividad.

**Corrección:** Replicar el código del trabajador en el admin y agregar detección de WiFi:

```javascript
// En el JavaScript del admin:
window.addEventListener('online', () => {
    document.getElementById('offline-indicator')?.classList.add('hidden');
    // Sincronizar automáticamente al recuperar WiFi
    fetch(`${BASE_URL}/controllers/NotificacionController.php?accion=sincronizar&PHPSESSID=${SID}`)
        .then(r => r.json())
        .then(() => {
            const st = document.getElementById('sync-status');
            if (st) st.textContent = 'Sincronizado: ' + new Date().toLocaleString('es-CO');
        });
});

window.addEventListener('offline', () => {
    document.getElementById('offline-indicator')?.classList.remove('hidden');
});
```

### ❌ C2. Indicador visual de estado de sync — NO CUMPLIDO
### ✅ C3. Sincronización manual forzada — CUMPLIDO
### ❌ C4. Funcionamiento offline básico — NO CUMPLIDO
### ❌ C5. Sincronización automática al recuperar conexión — NO CUMPLIDO
### ❌ C6. Resolución de conflictos de datos — NO CUMPLIDO

**Evidencia:** No hay lógica de versionado ni comparación de timestamps. La corrección requiere agregar un campo `version` o `updated_at` a cada tabla y comparar antes de sobrescribir:

```php
// Concepto de resolución de conflictos:
$local = $datos['actualizado_en'];
$servidor = $db->prepare("SELECT actualizado_en FROM cultivos WHERE id_cultivo = :id")->...;
if (strtotime($local) < strtotime($servidor)) {
    // El servidor tiene datos más recientes, conservar servidor
    $_SESSION['toast'] = ['text' => 'Conflicto resuelto: se conservó la versión más reciente.', 'type' => 'warning'];
}
```

### ❌ C7. Alerta de sincronización pendiente prolongada — NO CUMPLIDO

**Corrección propuesta:**

```php
// Al inicio de admin.php, después de cargar datos:
$diasSinSync = $db->prepare(
    "SELECT DATEDIFF(NOW(), MAX(finalizada_en)) FROM sincronizaciones WHERE id_usuario = :u"
);
$diasSinSync->execute([':u' => $_SESSION['id_usuario']]);
$dias = (int)$diasSinSync->fetchColumn();

if ($dias > 3) {
    $_SESSION['alerta_sync'] = "Llevas {$dias} días sin sincronizar. Tus datos podrían no estar respaldados.";
}
```

### ❌ C8. Sincronización selectiva (WiFi vs. datos móviles) — NO CUMPLIDO

**Corrección propuesta:**

```javascript
// Detectar tipo de conexión
if ('connection' in navigator) {
    const conn = navigator.connection;
    if (conn.type === 'wifi') {
        // Sincronizar todo, incluyendo fotos pesadas
        sincronizarTodo();
    } else if (conn.type === 'cellular') {
        // Solo sincronizar datos de texto, posponer fotos
        sincronizarSoloTexto();
        Swal.fire('Conexión limitada', 
            'Las fotografías se sincronizarán cuando te conectes a WiFi.', 'info');
    }
}
```

**📊 Cumplimiento DP-19: 1/8 = 12.5% 🔴**

---

# DP-20: Generar Reportes Agrícolas Comparativos

> **Rol:** Administrador  
> **Objetivo:** Generar reportes que comparen la productividad entre lotes  
> **Propósito:** Tomar decisiones basadas en datos sobre cuáles lotes son más eficientes

### ✅ C1. Generación de reportes por tipo — CUMPLIDO

**Evidencia:** El [ReporteController.php](file:///c:/laragon/www/Gsigvos/controllers/ReporteController.php) implementa 3 tipos de reporte completos:
- **Producción comparativa:** Compara kg por lote, promedio, máximo y kg/ha.
- **Actividades por período:** Muestra todas las actividades realizadas en un rango de fechas.
- **Próximas cosechas:** Lista cultivos con cosecha estimada próxima.

Los datos se entregan via JSON (acción `ajax`) y se renderizan dinámicamente en la vista.

### ❌ C2. Filtro de fecha "Desde" en reporte de cosechas — NO CUMPLIDO

**Evidencia del fallo:**  
En `ReporteController.php` línea 98, el reporte de tipo `cosechas` usa: `WHERE c.fecha_cosecha_estimada BETWEEN CURDATE() AND :hasta`. El parámetro `$fecha_desde` se ignora y se reemplaza por `CURDATE()`, lo cual impide al usuario consultar cosechas pasadas o definir un rango personalizado.

**Corrección propuesta:**

```php
// Línea 98 de ReporteController.php, cambiar:
// ANTES:
"WHERE c.estado != 'cosechado' AND c.fecha_cosecha_estimada BETWEEN CURDATE() AND :hasta"
// DESPUÉS:
"WHERE c.estado != 'cosechado' AND c.fecha_cosecha_estimada BETWEEN :desde AND :hasta"
// Y agregar :desde a los parámetros:
$params = [':desde' => $desde, ':hasta' => $hasta];
```

### ✅ C4. Exportación a PDF — CUMPLIDO

**Evidencia:** La acción `pdf` (línea 151) usa **Dompdf** con plantilla dedicada (`reporte_pdf.php`), formato A4, logo del sistema, métricas resumen y tabla de datos. El archivo se descarga como `SIGVOS_{tipo}_{fecha}.pdf`.

### ✅ C5. Filtrado por tipo de cultivo — CUMPLIDO

**Evidencia:** Los 3 tipos de reporte aceptan `$id_tipo` como parámetro. Si es mayor a 0, se agrega `AND tc.id_tipo = :id_tipo` a la consulta SQL, filtrando los resultados.

### ✅ C6. Métricas clave en reportes — CUMPLIDO

**Evidencia:** La función `obtenerMetricas()` (línea 114) calcula 7 indicadores: cultivos activos, cosechas en período, kg totales, actividades completadas, actividades pendientes, cosechas próximas (30 días) y total de lotes activos.

**📊 Cumplimiento DP-20: 4/5 = 80%**

---

# 📊 TABLA RESUMEN FINAL

| Historia | Descripción | Total | ✅ | ❌ | % |
|----------|-------------|-------|---|---|---|
| DP-1 | Login Admin | 5 | 4 | 1 | 80% |
| DP-2 | Login Trabajador | 5 | 5 | 0 | **100%** |
| DP-3 | Dashboard Admin | 6 | 4 | 2 | 67% |
| DP-4 | Crear Lotes | 4 | 4 | 0 | **100%** |
| DP-5 | Editar Lotes | 5 | 2 | 3 | 40% |
| DP-6 | Registrar Cultivos | 4 | 4 | 0 | **100%** |
| DP-7 | Asignar Lotes | 4 | 4 | 0 | **100%** |
| DP-8 | Panel Trabajador | 6 | 6 | 0 | **100%** |
| DP-9 | Buscador Global | 7 | 0 | 7 | **0%** 🔴 |
| DP-10 | Programar Actividades | 8 | 4 | 4 | 50% |
| DP-11 | Calendario | 7 | 7 | 0 | **100%** |
| DP-12 | Reagendar Actividades | 8 | 4 | 4 | 50% |
| DP-13 | Registrar Labor (Admin) | 6 | 2 | 4 | 33% |
| DP-14 | Registrar Labor (Trabajador) | 6 | 0 | 6 | **0%** 🔴 |
| DP-15 | Completar + Foto (Trabajador) | 6 | 3 | 3 | 50% |
| DP-16 | Fotos de Progreso (Admin) | 7 | 2 | 5 | 29% |
| DP-17 | Registrar Cosecha | 6 | 6 | 0 | **100%** |
| DP-18 | Notificaciones (Trabajador) | 6 | 5 | 1 | 83% |
| DP-19 | Sincronización (Admin) | 8 | 1 | 7 | **12.5%** 🔴 |
| DP-20 | Reportes | 5 | 4 | 1 | 80% |
| **TOTAL** | | **119** | **71** | **48** | **~60%** |

---

> [!IMPORTANT]
> **Prioridad de corrección sugerida:**
> 1. 🔴 **DP-14** (0%) — Agregar formulario de registro de actividades para el trabajador
> 2. 🔴 **DP-9** (0%) — Construir el módulo de buscador global completo
> 3. 🔴 **DP-19** (12.5%) — Implementar offline y sincronización para el admin
> 4. ⚠️ **DP-16** (29%) — Crear tabla de galería fotográfica con historial
> 5. ⚠️ **DP-13** (33%) — Agregar validaciones de fecha y descripción
> 6. ⚠️ **DP-5** (40%) — Candados de edición de lotes
> 7. Las demás historias con fallos menores (DP-1, DP-3, DP-10, DP-12, DP-15, DP-18, DP-20)
