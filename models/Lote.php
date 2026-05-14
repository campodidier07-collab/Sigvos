<?php
class Lote {
    private $conn;
    private $tabla = "lotes";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function existeIdentificador($identificador, $excluir_id = null) {
        $sql = "SELECT id_lote FROM {$this->tabla} WHERE identificador = :identificador";
        if ($excluir_id) $sql .= " AND id_lote != :excluir";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':identificador', $identificador);
        if ($excluir_id) $stmt->bindParam(':excluir', $excluir_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function obtenerTodos() {
        $sql = "SELECT l.*, tc.nombre AS tipo_nombre
                FROM {$this->tabla} l
                LEFT JOIN tipos_cultivo tc ON l.id_tipo_preferido = tc.id_tipo
                WHERE l.activo = 1
                ORDER BY l.identificador ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($datos) {
        $sql = "INSERT INTO {$this->tabla}
                    (identificador, nombre, ubicacion, area_ha, id_tipo_preferido, es_alternativo, estado, activo, fotografia, creado_en, actualizado_en)
                VALUES
                    (:identificador, :nombre, :ubicacion, :area_ha, :id_tipo_preferido, :es_alternativo, 'disponible', 1, :fotografia, NOW(), NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':identificador'    => strtoupper(trim($datos['identificador'])),
            ':nombre'           => trim($datos['nombre']),
            ':ubicacion'        => trim($datos['ubicacion']),
            ':area_ha'          => $datos['area_ha'],
            ':id_tipo_preferido'=> $datos['id_tipo_preferido'] ?: null,
            ':es_alternativo'   => $datos['es_alternativo'] ? 1 : 0,
            ':fotografia'       => $datos['fotografia'] ?? null,
        ]);
        return $this->conn->lastInsertId();
    }

    public function obtenerPorId($id) {
        $sql = "SELECT * FROM {$this->tabla} WHERE id_lote = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function editar($id, $datos) {
        $sql = "UPDATE {$this->tabla} SET
                    identificador = :identificador,
                    nombre = :nombre,
                    ubicacion = :ubicacion,
                    area_ha = :area_ha,
                    id_tipo_preferido = :id_tipo_preferido,
                    es_alternativo = :es_alternativo,
                    estado = :estado,
                    actualizado_en = NOW()
                WHERE id_lote = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':identificador'    => strtoupper(trim($datos['identificador'])),
            ':nombre'           => trim($datos['nombre']),
            ':ubicacion'        => trim($datos['ubicacion']),
            ':area_ha'          => (float)$datos['area_ha'],
            ':id_tipo_preferido'=> $datos['id_tipo_preferido'] ?: null,
            ':es_alternativo'   => $datos['es_alternativo'] ? 1 : 0,
            ':estado'           => $datos['estado'],
            ':id'               => $id,
        ]);
    }

    public function eliminar($id) {
        $sql = "UPDATE {$this->tabla} SET activo = 0, actualizado_en = NOW() WHERE id_lote = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
    }

    public function obtenerEstados() {
        $sql = "SELECT estado, COUNT(*) as total FROM {$this->tabla} WHERE activo = 1 GROUP BY estado";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
