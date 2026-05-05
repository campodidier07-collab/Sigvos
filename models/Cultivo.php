<?php
class Cultivo {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** Lotes con estado 'disponible' */
    public function obtenerLotesDisponibles() {
        return $this->conn
            ->query("SELECT id_lote, identificador, nombre, area_ha FROM lotes WHERE estado = 'disponible' AND activo = 1 ORDER BY identificador")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Variedades activas con días de cosecha */
    public function obtenerVariedades() {
        return $this->conn
            ->query("SELECT v.id_variedad, v.nombre, v.dias_cosecha_promedio, tc.nombre AS tipo_nombre, tc.id_tipo
                     FROM variedades v
                     JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
                     WHERE v.activo = 1
                     ORDER BY tc.nombre, v.nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Verifica que el lote esté disponible */
    public function loteDisponible($id_lote) {
        $stmt = $this->conn->prepare("SELECT estado FROM lotes WHERE id_lote = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $id_lote]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && $row['estado'] === 'disponible';
    }

    /** Genera código único: TIPO-LOTE-AÑO-SEQ */
    public function generarCodigo($tipo_codigo, $lote_id) {
        $anio = date('Y');
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM cultivos c
             JOIN lotes l ON c.id_lote = l.id_lote
             JOIN variedades v ON c.id_variedad = v.id_variedad
             JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
             WHERE tc.codigo = :tipo AND YEAR(c.creado_en) = :anio"
        );
        $stmt->execute([':tipo' => $tipo_codigo, ':anio' => $anio]);
        $seq = (int)$stmt->fetchColumn() + 1;

        $stmt2 = $this->conn->prepare("SELECT identificador FROM lotes WHERE id_lote = :id LIMIT 1");
        $stmt2->execute([':id' => $lote_id]);
        $ident = $stmt2->fetchColumn();

        return strtoupper($tipo_codigo) . '-' . $ident . '-' . $anio . '-' . str_pad($seq, 2, '0', STR_PAD_LEFT);
    }

    /** Registra el cultivo y marca el lote como ocupado */
    public function crear($datos) {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO cultivos
                    (id_lote, id_variedad, id_registrado_por, codigo, estado,
                     fecha_siembra, fecha_cosecha_estimada, observaciones,
                     fotografia, activo_en_lote, creado_en, actualizado_en)
                 VALUES
                    (:id_lote, :id_variedad, :registrado_por, :codigo, 'sembrado',
                     :fecha_siembra, :fecha_cosecha, :observaciones,
                     NULL, :id_lote, NOW(), NOW())"
            );
            $stmt->execute([
                ':id_lote'       => $datos['id_lote'],
                ':id_variedad'   => $datos['id_variedad'],
                ':registrado_por'=> $datos['id_registrado_por'],
                ':codigo'        => $datos['codigo'],
                ':fecha_siembra' => $datos['fecha_siembra'],
                ':fecha_cosecha' => $datos['fecha_cosecha_estimada'],
                ':observaciones' => $datos['observaciones'] ?? '',
            ]);
            $id = $this->conn->lastInsertId();

            // Marcar lote como ocupado
            $this->conn->prepare("UPDATE lotes SET estado = 'ocupado', actualizado_en = NOW() WHERE id_lote = :id")
                       ->execute([':id' => $datos['id_lote']]);

            $this->conn->commit();
            return $id;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Edita variedad, estado, fecha siembra, cosecha y observaciones */
    public function editar($id, $datos) {
        $stmt = $this->conn->prepare(
            "UPDATE cultivos SET
                id_variedad             = :id_variedad,
                estado                  = :estado,
                fecha_siembra           = :fecha_siembra,
                fecha_cosecha_estimada  = :fecha_cosecha,
                observaciones           = :observaciones,
                actualizado_en          = NOW()
             WHERE id_cultivo = :id"
        );
        $stmt->execute([
            ':id_variedad'  => $datos['id_variedad'],
            ':estado'       => $datos['estado'],
            ':fecha_siembra'=> $datos['fecha_siembra'],
            ':fecha_cosecha'=> $datos['fecha_cosecha_estimada'],
            ':observaciones'=> $datos['observaciones'],
            ':id'           => $id,
        ]);
    }

    /** Elimina el cultivo (soft) y libera el lote */
    public function eliminar($id) {
        $this->conn->beginTransaction();
        try {
            // Obtener el lote asociado
            $stmt = $this->conn->prepare("SELECT id_lote FROM cultivos WHERE id_cultivo = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Cancelar actividades pendientes/en_proceso del cultivo
            $this->conn->prepare(
                "UPDATE actividades SET estado = 'cancelada', actualizado_en = NOW()
                 WHERE id_cultivo = :id AND estado IN ('pendiente','en_proceso')"
            )->execute([':id' => $id]);

            // Soft delete: quitar activo_en_lote
            $this->conn->prepare("UPDATE cultivos SET activo_en_lote = NULL, actualizado_en = NOW() WHERE id_cultivo = :id")
                       ->execute([':id' => $id]);

            // Liberar el lote
            if ($row) {
                $this->conn->prepare("UPDATE lotes SET estado = 'disponible', actualizado_en = NOW() WHERE id_lote = :id")
                           ->execute([':id' => $row['id_lote']]);
            }
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Lista todos los cultivos activos con info de lote y variedad */
    public function obtenerTodos() {
        return $this->conn
            ->query("SELECT c.id_cultivo, c.codigo, c.estado, c.fecha_siembra,
                            c.fecha_cosecha_estimada, c.observaciones,
                            l.id_lote, l.nombre AS lote_nombre, l.identificador AS lote_id,
                            v.nombre AS variedad_nombre, v.id_variedad,
                            tc.nombre AS tipo_nombre
                     FROM cultivos c
                     JOIN lotes l ON c.id_lote = l.id_lote
                     JOIN variedades v ON c.id_variedad = v.id_variedad
                     JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
                     WHERE c.activo_en_lote IS NOT NULL
                     ORDER BY c.creado_en DESC")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Registra la cosecha: cierra el cultivo y libera el lote */
    public function registrarCosecha($id_cultivo, $datos) {
        $this->conn->beginTransaction();
        try {
            // Obtener lote
            $stmt = $this->conn->prepare("SELECT id_lote, fecha_cosecha_estimada FROM cultivos WHERE id_cultivo = :id LIMIT 1");
            $stmt->execute([':id' => $id_cultivo]);
            $cultivo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cultivo) throw new Exception('Cultivo no encontrado');

            // Actualizar cultivo como cosechado
            $this->conn->prepare(
                "UPDATE cultivos SET
                    estado                = 'cosechado',
                    fecha_cosecha_real    = :fecha_real,
                    cantidad_cosechada_kg = :cantidad,
                    observaciones         = :obs,
                    activo_en_lote        = NULL,
                    actualizado_en        = NOW()
                 WHERE id_cultivo = :id"
            )->execute([
                ':fecha_real' => $datos['fecha_cosecha_real'],
                ':cantidad'   => $datos['cantidad_kg'],
                ':obs'        => $datos['observaciones'] ?? '',
                ':id'         => $id_cultivo,
            ]);

            // Liberar lote → disponible
            $this->conn->prepare("UPDATE lotes SET estado = 'disponible', actualizado_en = NOW() WHERE id_lote = :id")
                       ->execute([':id' => $cultivo['id_lote']]);

            $this->conn->commit();
            return $cultivo['fecha_cosecha_estimada'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /** Historial de cosechas (cultivos cosechados) */
    public function obtenerHistorialCosechas() {
        return $this->conn->query(
            "SELECT c.id_cultivo, c.codigo, c.fecha_siembra,
                    c.fecha_cosecha_estimada, c.fecha_cosecha_real,
                    c.cantidad_cosechada_kg, c.observaciones,
                    l.identificador AS lote_id, l.nombre AS lote_nombre,
                    v.nombre AS variedad_nombre, tc.nombre AS tipo_nombre
             FROM cultivos c
             JOIN lotes l ON c.id_lote = l.id_lote
             JOIN variedades v ON c.id_variedad = v.id_variedad
             JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
             WHERE c.estado = 'cosechado'
             ORDER BY c.fecha_cosecha_real DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Historial de cosechas filtrado por lotes del trabajador */
    public function obtenerHistorialPorLotes(array $ids_lotes) {
        if (empty($ids_lotes)) return [];
        $ph = implode(',', array_fill(0, count($ids_lotes), '?'));
        $stmt = $this->conn->prepare(
            "SELECT c.id_cultivo, c.codigo, c.fecha_siembra,
                    c.fecha_cosecha_estimada, c.fecha_cosecha_real,
                    c.cantidad_cosechada_kg, c.observaciones,
                    l.identificador AS lote_id, l.nombre AS lote_nombre,
                    v.nombre AS variedad_nombre, tc.nombre AS tipo_nombre
             FROM cultivos c
             JOIN lotes l ON c.id_lote = l.id_lote
             JOIN variedades v ON c.id_variedad = v.id_variedad
             JOIN tipos_cultivo tc ON v.id_tipo = tc.id_tipo
             WHERE c.estado = 'cosechado' AND c.id_lote IN ($ph)
             ORDER BY c.fecha_cosecha_real DESC"
        );
        $stmt->execute($ids_lotes);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
