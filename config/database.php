<?php
class Database
{
    private string $host;
    private string $port;
    private string $db_name;
    private string $username;
    private string $password;

    public ?PDO $conn = null;

    public function __construct()
    {
        // Cargar .env si aún no se ha cargado en esta ejecución
        $this->cargarEnv();

        $this->host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $this->port     = $_ENV['DB_PORT'] ?? '3306';
        $this->db_name  = $_ENV['DB_NAME'] ?? 'agro_db';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }

    /** Lee el archivo .env de la raíz y puebla $_ENV y getenv(). */
    private function cargarEnv(): void
    {
        // Solo cargar una vez por ejecución
        if (!empty($_ENV['_ENV_LOADED'])) {
            return;
        }

        $rutaEnv = dirname(__DIR__) . '/.env';

        if (!file_exists($rutaEnv)) {
            return; // Sin .env → usa los defaults del constructor
        }

        $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            // Ignorar comentarios y líneas vacías
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            // Separar clave=valor (solo en el primer "=")
            [$clave, $valor] = array_pad(explode('=', $linea, 2), 2, '');
            $clave = trim($clave);
            $valor = trim($valor);

            // Quitar comillas opcionales del valor
            if (
                strlen($valor) >= 2 &&
                (($valor[0] === '"' && $valor[-1] === '"') ||
                 ($valor[0] === "'" && $valor[-1] === "'"))
            ) {
                $valor = substr($valor, 1, -1);
            }

            $_ENV[$clave]    = $valor;
            $_SERVER[$clave] = $valor;
            putenv("{$clave}={$valor}");
        }

        $_ENV['_ENV_LOADED'] = '1';
    }

    /** Crea y retorna la conexión PDO. */
    public function conectar(): PDO
    {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Registrar el error real en el log del servidor (no se muestra al usuario)
            error_log('[SIGVOS] Error de conexión BD: ' . $e->getMessage());

            $esDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

            // Mensaje genérico al usuario: sin detalles internos
            http_response_code(500);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
                  <title>Error — SIGVOS</title>
                  <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#f0fdf4;}
                  .box{text-align:center;padding:2rem;border-radius:1rem;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.08);}
                  h1{color:#065f46;}p{color:#6b7280;}.debug{font-size:.8rem;background:#fef2f2;border:1px solid #fee2e2;
                  padding:.5rem 1rem;border-radius:.5rem;color:#b91c1c;margin-top:1rem;text-align:left;}</style></head>
                  <body><div class="box">
                    <h1>⚠️ Servicio no disponible</h1>
                    <p>No se pudo conectar a la base de datos.<br>Por favor intenta más tarde o contacta al administrador.</p>'
                  . ($esDebug ? '<p class="debug"><strong>Debug:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' : '')
                  . '</div></body></html>';
            exit;
        }

        return $this->conn;
    }
}