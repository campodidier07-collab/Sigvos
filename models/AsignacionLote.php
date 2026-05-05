<?php
class AsignacionLote {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Trabajadores con rol 2 (no admin) */
    public function obtenerTrabajadores() {
        return $this->conn
            ->query("SELECT id_usuario, nombre, correo FROM usuarios WHERE id_rol = 2 AND activo = 1 ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lotes disponibles para asignar */
    public function obtenerLotesDisponibles() {
        return $this->conn
            ->query("SELECT id_lote, identificador, nombre, area_ha FROM lotes WHERE estado = 'disponible' AND activo = 1 ORDER BY identificador")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Valida que el usuario tenga rol permitido (rol 2 = trabajador) */
    public function esRolPermitido($id_usuario) {
        $stmt = $this->conn->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $id_usuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && (int)$row['id_rol'] === 2;
    }

    /** Verifica si el trabajador ya tiene una asignación activa en ese lote */
    public function tieneAsignacionActiva($id_usuario, $id_lote) {
        $stmt = $this->conn->prepare(
            "SELECT id_asignacion FROM usuarios_lotes
             WHERE id_usuario = :u AND id_lote = :l AND activo = 1 LIMIT 1"
        );
        $stmt->execute([':u' => $id_usuario, ':l' => $id_lote]);
        return $stmt->rowCount() > 0;
    }

    /** Asigna lote a trabajador con clave única */
    public function asignar($id_usuario, $id_lote, $asignado_por) {
        $clave = 'UL-' . $id_usuario . '-' . $id_lote . '-' . time();
        $stmt = $this->conn->prepare(
            "INSERT INTO usuarios_lotes (id_usuario, id_lote, asignado_por, activo, clave_activa, creado_en)
             VALUES (:u, :l, :por, 1, :clave, NOW())"
        );
        $stmt->execute([':u' => $id_usuario, ':l' => $id_lote, ':por' => $asignado_por, ':clave' => $clave]);
        return $this->conn->lastInsertId();
    }

    /** Desactiva asignación sin borrarla (conserva historial) */
    public function desactivar($id_asignacion) {
        $stmt = $this->conn->prepare(
            "UPDATE usuarios_lotes SET activo = 0, clave_activa = NULL WHERE id_asignacion = :id"
        );
        $stmt->execute([':id' => $id_asignacion]);
    }

    /** Reasignación controlada: desactiva la anterior y crea una nueva */
    public function reasignar($id_usuario, $id_lote_nuevo, $asignado_por) {
        $this->conn->beginTransaction();
        try {
            // Desactivar todas las asignaciones activas del trabajador en cualquier lote
            $stmt = $this->conn->prepare(
                "UPDATE usuarios_lotes SET activo = 0, clave_activa = NULL
                 WHERE id_usuario = :u AND activo = 1"
            );
            $stmt->execute([':u' => $id_usuario]);

            // Crear nueva asignación
            $id = $this->asignar($id_usuario, $id_lote_nuevo, $asignado_por);
            $this->conn->commit();
            return $id;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Todas las asignaciones activas con info de trabajador y lote */
    public function obtenerAsignacionesActivas() {
        return $this->conn
            ->query("SELECT ul.id_asignacion, ul.creado_en,
                            u.nombre AS trabajador, u.correo,
                            l.identificador AS lote_id, l.nombre AS lote_nombre, l.area_ha
                     FROM usuarios_lotes ul
                     JOIN usuarios u ON ul.id_usuario = u.id_usuario
                     JOIN lotes l ON ul.id_lote = l.id_lote
                     WHERE ul.activo = 1
                     ORDER BY ul.creado_en DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lotes asignados a un trabajador específico (activos e histórico) */
    public function obtenerPorTrabajador($id_usuario) {
        $stmt = $this->conn->prepare(
            "SELECT ul.id_asignacion, ul.activo, ul.creado_en,
                    l.id_lote, l.identificador AS lote_id, l.nombre AS lote_nombre, l.area_ha, l.estado AS lote_estado
             FROM usuarios_lotes ul
             JOIN lotes l ON ul.id_lote = l.id_lote
             WHERE ul.id_usuario = :u
             ORDER BY ul.activo DESC, ul.creado_en DESC"
        );
        $stmt->execute([':u' => $id_usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
