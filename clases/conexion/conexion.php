<?php
/**
 * Clase de Conexión a Base de Datos
 */
class Conexion {
    
    private static $instancia = null;
    private $conn;
    private $host = 'localhost';
    private $dbname = 'restauratemoderno';
    private $username = 'root';
    private $password = '';
    
    private $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, $this->opciones);

            // PHP y MySQL deben usar la MISMA zona horaria. Si no se
            // alinean, NOW()/CURRENT_TIMESTAMP guardan una hora distinta
            // a la que muestra PHP (caso típico al pasar a producción).
            $this->configurarZonaHoraria();
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    /**
     * Toma la zona horaria de configuracion_empresa (si existe),
     * la aplica a PHP y fija el mismo offset en la sesión MySQL.
     */
    private function configurarZonaHoraria(): void
    {
        $zona = defined('RM_ZONA_HORARIA') ? RM_ZONA_HORARIA : 'America/La_Paz';

        try {
            $fila = $this->conn->query(
                "SELECT zona_horaria
                   FROM configuracion_empresa
                  ORDER BY id_config ASC
                  LIMIT 1"
            )->fetchColumn();

            if (is_string($fila) && trim($fila) !== '') {
                $zona = trim($fila);
            }
        } catch (Throwable $e) {
            // Instalación nueva o tabla aún no creada: se usa el respaldo.
        }

        try {
            new DateTimeZone($zona);
            date_default_timezone_set($zona);
        } catch (Throwable $e) {
            $zona = 'America/La_Paz';
            date_default_timezone_set($zona);
        }

        try {
            $offset = date('P'); // Ej.: -04:00
            $this->conn->exec('SET time_zone = ' . $this->conn->quote($offset));
        } catch (Throwable $e) {
            error_log('Conexion::configurarZonaHoraria - ' . $e->getMessage());
        }
    }
    
    public static function getInstance(): self {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }
    
    public function getConnection(): PDO {
        return $this->conn;
    }
    
    private function __clone() {}
    public function __wakeup() {}
}