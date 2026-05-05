<?php
class Notificacion {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Crea una notificación para un usuario.
     */
    public function crear($id_usuario, $tipo, $titulo, $mensaje, $prioridad = 'media', $id_cultivo = null, $id_actividad = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO notificaciones
                (id_usuario, id_cultivo, id_actividad, tipo, prioridad, titulo, mensaje, leida, creada_en)
             VALUES
                (:u, :c, :a, :tipo, :prio, :titulo, :mensaje, 0, NOW())"
        );
        $stmt->execute([
            ':u'       => $id_usuario,
            ':c'       => $id_cultivo,
            ':a'       => $id_actividad,
            ':tipo'    => $tipo,
            ':prio'    => $prioridad,
            ':titulo'  => $titulo,
            ':mensaje' => $mensaje,
        ]);
    }

    /** Obtiene el id del admin (rol 1) */
    public function getAdminId() {
        return $this->db->query("SELECT id_usuario FROM usuarios WHERE id_rol = 1 AND activo = 1 LIMIT 1")->fetchColumn();
    }

    /** Obtiene el nombre de un usuario */
    public function getNombre($id_usuario) {
        $stmt = $this->db->prepare("SELECT nombre FROM usuarios WHERE id_usuario = :id LIMIT 1");
        $stmt->execute([':id' => $id_usuario]);
        return $stmt->fetchColumn() ?: 'Usuario';
    }
}
?>
