<?php
class Usuario {
    private $conn;
    private $tabla = "usuarios";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function existeCorreo($correo) {
        $sql = "SELECT id_usuario FROM " . $this->tabla . " WHERE correo = :correo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":correo", $correo);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function obtenerPorCorreo($correo) {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM " . $this->tabla . " u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.correo = :correo AND u.activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":correo", $correo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerPorCorreoSinFiltro($correo) {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM " . $this->tabla . " u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.correo = :correo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":correo", $correo);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registrar($datos) {
        try {
            $sql = "INSERT INTO usuarios
                        (id_rol, nombre, correo, contrasena_hash, telefono, activo, creado_en, actualizado_en)
                    VALUES
                        (:id_rol, :nombre, :correo, :contrasena_hash, :telefono, 1, NOW(), NOW())";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":id_rol",          $datos['id_rol']);
            $stmt->bindParam(":nombre",          $datos['nombre']);
            $stmt->bindParam(":correo",          $datos['correo']);
            $stmt->bindParam(":contrasena_hash", $datos['contrasena_hash']);
            $stmt->bindParam(":telefono",        $datos['telefono']);
            $stmt->execute();

            return true;

        } catch (Exception $e) {
            return "Error al registrar: " . $e->getMessage();
        }
    }

    public function incrementarIntentos($id_usuario, $nuevosIntentos) {
        $sql = "UPDATE " . $this->tabla . " SET intentos_fallidos = :intentos, actualizado_en = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':intentos' => $nuevosIntentos, ':id' => $id_usuario]);
    }

    public function registrarBloqueo($id_usuario, $segundos) {
        $sql = "UPDATE " . $this->tabla . " SET intentos_fallidos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :seg SECOND), actualizado_en = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':seg' => $segundos, ':id' => $id_usuario]);
    }

    public function resetearIntentos($id_usuario) {
        $sql = "UPDATE " . $this->tabla . " SET intentos_fallidos = 0, bloqueado_hasta = NULL, actualizado_en = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id_usuario]);
    }

    public function actualizarUltimoAcceso($id_usuario) {
        $sql = "UPDATE " . $this->tabla . " SET ultimo_acceso = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id", $id_usuario);
        $stmt->execute();
    }
}
?>