<?php
class Actividad {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function obtenerTipos() {
        return $this->conn
            ->query("SELECT id_tipo_actividad, nombre FROM tipos_actividad WHERE activo = 1 ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cultivos activos para asignar actividades */
    public function obtenerCultivosActivos() {
        return $this->conn
            ->query("SELECT c.id_cultivo, c.codigo, l.nombre AS lote_nombre, l.identificador AS lote_id
                     FROM cultivos c
                     JOIN lotes l ON c.id_lote = l.id_lote
                     WHERE c.activo_en_lote IS NOT NULL
                     ORDER BY c.codigo")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Trabajadores activos para asignar */
    public function obtenerTrabajadores() {
        return $this->conn
            ->query("SELECT id_usuario, nombre FROM usuarios WHERE id_rol = 2 AND activo = 1 ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todas las actividades con detalle */
    public function obtenerTodas() {
        return $this->conn
            ->query("SELECT a.id_actividad, a.estado, a.fecha_programada, a.descripcion,
                            a.fotografia,
                            ta.nombre AS tipo_actividad,
                            c.codigo AS cultivo_codigo,
                            l.identificador AS lote_id, l.nombre AS lote_nombre,
                            u.nombre AS trabajador
                     FROM actividades a
                     JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
                     JOIN cultivos c ON a.id_cultivo = c.id_cultivo
                     JOIN lotes l ON c.id_lote = l.id_lote
                     LEFT JOIN usuarios u ON a.id_asignado_a = u.id_usuario
                     ORDER BY a.fecha_programada DESC, a.creado_en DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($datos) {
        $stmt = $this->conn->prepare(
            "INSERT INTO actividades
                (id_cultivo, id_tipo_actividad, id_creado_por, id_asignado_a,
                 estado, fecha_programada, descripcion, observaciones,
                 fotografia, creado_en, actualizado_en)
             VALUES
                (:id_cultivo, :id_tipo, :creado_por, :asignado_a,
                 'pendiente', :fecha, :descripcion, :observaciones,
                 NULL, NOW(), NOW())"
        );
        $stmt->execute([
            ':id_cultivo'  => $datos['id_cultivo'],
            ':id_tipo'     => $datos['id_tipo_actividad'],
            ':creado_por'  => $datos['id_creado_por'],
            ':asignado_a'  => $datos['id_asignado_a'] ?: null,
            ':fecha'       => $datos['fecha_programada'],
            ':descripcion' => $datos['descripcion'],
            ':observaciones'=> $datos['observaciones'] ?? '',
        ]);
        return $this->conn->lastInsertId();
    }

    public function eliminar($id) {
        $stmt = $this->conn->prepare(
            "DELETE FROM actividades WHERE id_actividad = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function obtenerPorId($id) {
        $stmt = $this->conn->prepare(
            "SELECT a.*, ta.nombre AS tipo_actividad, c.codigo AS cultivo_codigo,
                    l.identificador AS lote_id, l.nombre AS lote_nombre,
                    u.nombre AS trabajador
             FROM actividades a
             JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
             JOIN cultivos c ON a.id_cultivo = c.id_cultivo
             JOIN lotes l ON c.id_lote = l.id_lote
             LEFT JOIN usuarios u ON a.id_asignado_a = u.id_usuario
             WHERE a.id_actividad = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function editar($id, $datos) {
        $stmt = $this->conn->prepare(
            "UPDATE actividades SET
                id_tipo_actividad = :id_tipo,
                id_asignado_a     = :asignado_a,
                estado            = :estado,
                fecha_programada  = :fecha,
                descripcion       = :descripcion,
                observaciones     = :observaciones,
                actualizado_en    = NOW()
             WHERE id_actividad = :id"
        );
        $stmt->execute([
            ':id_tipo'      => $datos['id_tipo_actividad'],
            ':asignado_a'   => $datos['id_asignado_a'] ?: null,
            ':estado'       => $datos['estado'],
            ':fecha'        => $datos['fecha_programada'],
            ':descripcion'  => $datos['descripcion'],
            ':observaciones'=> $datos['observaciones'] ?? '',
            ':id'           => $id,
        ]);
    }

    /** Actividades asignadas a un trabajador específico */
    public function obtenerPorTrabajador($id_usuario) {
        $stmt = $this->conn->prepare(
            "SELECT a.id_actividad, a.estado, a.fecha_programada, a.descripcion, a.observaciones,
                    a.fotografia,
                    ta.nombre AS tipo_actividad,
                    c.codigo AS cultivo_codigo,
                    l.identificador AS lote_id, l.nombre AS lote_nombre
             FROM actividades a
             JOIN tipos_actividad ta ON a.id_tipo_actividad = ta.id_tipo_actividad
             JOIN cultivos c ON a.id_cultivo = c.id_cultivo
             JOIN lotes l ON c.id_lote = l.id_lote
             WHERE a.id_asignado_a = :u
             ORDER BY FIELD(a.estado,'pendiente','en_proceso','completada','cancelada'),
                      a.fecha_programada ASC"
        );
        $stmt->execute([':u' => $id_usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
