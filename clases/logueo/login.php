<?php
/**
 * Clase Login - Sistema de Autenticación Seguro
 * Soporte para login con email o username
 */
require_once dirname(__DIR__) . '/conexion/conexion.php';

class Login {
    
    private $conn;
    private $max_intentos = 5;
    private $tiempo_bloqueo = 15; // minutos
    private $tiempo_expiracion_token = 43200; // 12 horas (mínimo 3 h)
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->conn = Conexion::getInstance()->getConnection();

        // Usa la misma duración definida en config/config.php.
        if (defined('RM_SESION_SEGUNDOS')) {
            $this->tiempo_expiracion_token = (int) RM_SESION_SEGUNDOS;
        }
    }
    
    public function autenticar(string $login, string $password, string $ip, string $userAgent): array {
        
        // Validar entrada
        $login = trim($login);
        if (empty($login) || empty($password)) {
            return $this->respuesta(false, 'Usuario o contraseña incorrectos');
        }

        // Mensaje único para TODOS los fallos de credencial (evita
        // descubrir qué usuarios existen en el sistema).
        $mensajeCredencial = 'Usuario o contraseña incorrectos';

        // Determinar si es email o username
        $esEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        $campo = $esEmail ? 'email' : 'username';
        $valor = $login;

        // Buscar usuario por email o username
        $usuario = $this->obtenerUsuarioPorLogin($campo, $valor);

        // Si no existe usuario: mismo mensaje que una contraseña errada
        if (!$usuario) {
            // Se verifica contra un hash falso para que el tiempo de
            // respuesta no delate si el usuario existe o no.
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
            $this->registrarLog(null, $login, $ip, $userAgent, 'login_fallido', 'Usuario no encontrado');
            return $this->respuesta(false, $mensajeCredencial);
        }

        // Verificar si el usuario está activo
        if ($usuario['estado'] == 0) {
            $this->registrarLog($usuario['id_usuario'], $usuario['email'], $ip, $userAgent, 'login_fallido', 'Cuenta desactivada');
            return $this->respuesta(false, $mensajeCredencial);
        }
        
        // Verificar si está bloqueado
        if ($usuario['estado'] == 2) {
            $bloqueo = $this->verificarBloqueo($usuario);
            if ($bloqueo['bloqueado']) {
                return $this->respuesta(false, $bloqueo['mensaje']);
            } else {
                // Desbloquear si ya pasó el tiempo
                $this->desbloquearUsuario($usuario['id_usuario']);
            }
        }
        
        // Verificar contraseña
        if (!password_verify($password, $usuario['password_hash'])) {
            // Registrar intento fallido
            $this->registrarIntentoFallido($usuario['id_usuario'], $usuario['email'], $ip, $userAgent);
            $intentos_restantes = $this->max_intentos - ($usuario['intentos_fallidos'] + 1);
            
            if ($intentos_restantes <= 0) {
                $this->bloquearUsuario($usuario['id_usuario']);
                return $this->respuesta(false, 'Cuenta bloqueada por múltiples intentos. Espere ' . $this->tiempo_bloqueo . ' minutos.');
            }

            return $this->respuesta(false, $mensajeCredencial);
        }
        
        // Verificar si la contraseña necesita actualización
        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
            $this->actualizarPassword($usuario['id_usuario'], $password);
        }
        
        // Login exitoso - Resetear intentos
        $this->resetearIntentos($usuario['id_usuario']);
        
        // Generar token de sesión
        $token = $this->generarToken($usuario['id_usuario']);
        
        // Registrar login exitoso
        $this->registrarLog($usuario['id_usuario'], $usuario['email'], $ip, $userAgent, 'login_exitoso', 'Acceso correcto');
        
        // Actualizar último login
        $this->actualizarUltimoLogin($usuario['id_usuario'], $ip);
        
        // Obtener datos completos del usuario
        $datosUsuario = $this->obtenerDatosCompletos($usuario['id_usuario']);
        
        return $this->respuesta(true, 'Login exitoso', [
            'token' => $token,
            'usuario' => $datosUsuario,
            'redirect' => $this->determinarRedireccion($datosUsuario['rol'])
        ]);
    }
    
    /**
     * Obtener usuario por email o username
     * MODIFICADO: Ahora acepta ambos campos
     */
    private function obtenerUsuarioPorLogin(string $campo, string $valor): ?array {
        // Validar que el campo sea permitido
        if (!in_array($campo, ['email', 'username'])) {
            return null;
        }
        
        $sql = "SELECT u.*, r.nombre_rol
                FROM usuarios u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.{$campo} = :valor AND u.estado != 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':valor' => $valor]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Método alternativo: obtener usuario por email (mantener compatibilidad)
     */
    private function obtenerUsuarioPorEmail(string $email): ?array {
        return $this->obtenerUsuarioPorLogin('email', $email);
    }
    
    /**
     * Método alternativo: obtener usuario por username
     */
    private function obtenerUsuarioPorUsername(string $username): ?array {
        return $this->obtenerUsuarioPorLogin('username', $username);
    }
    
    /**
     * Verificar bloqueo
     */
    private function verificarBloqueo(array $usuario): array {
        if (empty($usuario['bloqueado_hasta'])) {
            return ['bloqueado' => false, 'mensaje' => ''];
        }
        
        $bloqueo_hasta = new DateTime($usuario['bloqueado_hasta']);
        $ahora = new DateTime();
        
        if ($ahora < $bloqueo_hasta) {
            $diferencia = $ahora->diff($bloqueo_hasta);
            $minutos_restantes = $diferencia->i + ($diferencia->h * 60);
            return [
                'bloqueado' => true,
                'mensaje' => "Cuenta bloqueada. Espere {$minutos_restantes} minutos."
            ];
        }
        
        return ['bloqueado' => false, 'mensaje' => ''];
    }
    
    /**
     * Registrar intento fallido
     */
    private function registrarIntentoFallido(int $idUsuario, string $email, string $ip, string $userAgent): void {
        try {
            // Incrementar intentos
            $sql = "UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id_usuario = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idUsuario]);
            
            // Registrar en log
            $this->registrarLog($idUsuario, $email, $ip, $userAgent, 'login_fallido', 'Contraseña incorrecta');
        } catch (PDOException $e) {
            error_log("Error al registrar intento fallido: " . $e->getMessage());
        }
    }
    
    /**
     * Bloquear usuario
     */
    private function bloquearUsuario(int $idUsuario): void {
        try {
            $fecha_bloqueo = new DateTime();
            $fecha_bloqueo->modify("+{$this->tiempo_bloqueo} minutes");
            $fecha_str = $fecha_bloqueo->format('Y-m-d H:i:s');
            
            $sql = "UPDATE usuarios SET estado = 2, bloqueado_hasta = :fecha_bloqueo, intentos_fallidos = 0 
                    WHERE id_usuario = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => $idUsuario,
                ':fecha_bloqueo' => $fecha_str
            ]);
            
            // Registrar bloqueo
            $this->registrarLog($idUsuario, null, null, null, 'bloqueo', "Usuario bloqueado por {$this->tiempo_bloqueo} minutos");
        } catch (PDOException $e) {
            error_log("Error al bloquear usuario: " . $e->getMessage());
        }
    }
    
    /**
     * Desbloquear usuario
     */
    private function desbloquearUsuario(int $idUsuario): void {
        try {
            $sql = "UPDATE usuarios SET estado = 1, bloqueado_hasta = NULL, intentos_fallidos = 0 
                    WHERE id_usuario = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idUsuario]);
        } catch (PDOException $e) {
            error_log("Error al desbloquear usuario: " . $e->getMessage());
        }
    }
    
    /**
     * Resetear intentos
     */
    private function resetearIntentos(int $idUsuario): void {
        try {
            $sql = "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL 
                    WHERE id_usuario = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idUsuario]);
        } catch (PDOException $e) {
            error_log("Error al resetear intentos: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar último login
     */
    private function actualizarUltimoLogin(int $idUsuario, string $ip): void {
        try {
            $sql = "UPDATE usuarios SET ultimo_login = NOW(), ultima_ip = :ip 
                    WHERE id_usuario = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => $idUsuario,
                ':ip' => $ip
            ]);
        } catch (PDOException $e) {
            error_log("Error al actualizar último login: " . $e->getMessage());
        }
    }
    
    /**
     * Actualizar contraseña (cuando se necesita rehash)
     */
    private function actualizarPassword(int $idUsuario, string $password): void {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':hash' => $hash,
                ':id' => $idUsuario
            ]);
        } catch (PDOException $e) {
            error_log("Error al actualizar password: " . $e->getMessage());
        }
    }
    
    /**
     * Generar token de sesión
     */
    private function generarToken(int $idUsuario): string {
        $token = bin2hex(random_bytes(64)) . hash('sha256', uniqid(mt_rand(), true));
        $expiracion = new DateTime();
        $expiracion->modify("+{$this->tiempo_expiracion_token} seconds");
        $expiracion_str = $expiracion->format('Y-m-d H:i:s');
        
        try {
            // Insertar token en la tabla tokens_sesion
            $sql = "INSERT INTO tokens_sesion (id_usuario, token, fecha_expiracion, activo) 
                    VALUES (:id_usuario, :token, :expiracion, 1)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':token' => $token,
                ':expiracion' => $expiracion_str
            ]);
            
            return $token;
        } catch (PDOException $e) {
            error_log("Error al generar token: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Obtener datos completos del usuario
     */
    /**
     * Obtener datos completos del usuario
     * (pública: la usa validacionSesion.php para reconstruir $_SESSION)
     */
    public function obtenerDatosCompletos(int $idUsuario): array {
        $sql = "SELECT 
                    u.id_usuario,
                    u.username,
                    u.email,
                    u.estado,
                    u.ultimo_login,
                    r.id_rol,
                    r.nombre_rol,
                    s.id_sucursal,
                    s.nombre_sucursal,
                    s.codigo_sucursal,
                    d.nombres,
                    d.apellidos,
                    d.cedula,
                    d.telefono_movil,
                    d.foto_perfil
                FROM usuarios u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal
                LEFT JOIN usuarios_datos d ON u.id_usuario = d.id_usuario
                WHERE u.id_usuario = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'id' => (int)$result['id_usuario'],
                'id_usuario' => (int)$result['id_usuario'],
                'username' => $result['username'],
                'email' => $result['email'],
                'nombre_completo' => $result['nombres'] . ' ' . $result['apellidos'],
                'rol' => [
                    'id' => (int)$result['id_rol'],
                    'nombre' => $result['nombre_rol']
                ],
                'sucursal' => $result['id_sucursal'] ? [
                    'id' => (int)$result['id_sucursal'],
                    'nombre' => $result['nombre_sucursal'],
                    'codigo' => $result['codigo_sucursal']
                ] : null,
                'cedula' => $result['cedula'],
                'telefono' => $result['telefono_movil'],
                'foto' => $result['foto_perfil']
            ];
        }
        
        return [];
    }
    
    /**
     * Determinar redirección según rol
     */
    private function determinarRedireccion(array $rol): string {
        $nombre_rol = $rol['nombre'] ?? '';
        
        switch ($nombre_rol) {
            case 'SuperAdministrador':
            case 'AdministradorSucursal':
            case 'SupervisorEncargado':
            case 'Cajero':
            case 'Repartidor':
                return 'sistema/dashboard.php';
            default:
                return 'sistema/dashboard.php';
        }
    }
    
    /**
     * Registrar log de acceso
     */
    private function registrarLog(?int $idUsuario, ?string $email, ?string $ip, ?string $userAgent, string $tipo, string $descripcion): void {
        try {
            $sql = "INSERT INTO logs_acceso (id_usuario, email, ip_cliente, user_agent, tipo_evento, descripcion) 
                    VALUES (:id_usuario, :email, :ip, :user_agent, :tipo, :descripcion)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':email' => $email,
                ':ip' => $ip,
                ':user_agent' => $userAgent,
                ':tipo' => $tipo,
                ':descripcion' => $descripcion
            ]);
        } catch (PDOException $e) {
            error_log("Error al registrar log: " . $e->getMessage());
        }
    }
    
    /**
     * Validar token de sesión
     */
    public function validarToken(string $token): ?array {
        try {
            $sql = "SELECT ts.*, u.id_usuario, u.estado, u.id_rol, u.id_sucursal 
                    FROM tokens_sesion ts
                    INNER JOIN usuarios u ON ts.id_usuario = u.id_usuario
                    WHERE ts.token = :token AND ts.activo = 1 AND ts.fecha_expiracion > NOW()";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':token' => $token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['estado'] == 1) {

                // Sesión deslizante: si al token le queda menos de la
                // mitad de su vida, se renueva completo (12 h).
                $queda = strtotime($result['fecha_expiracion']) - time();
                $umbral = max(1800, (int) floor($this->tiempo_expiracion_token / 2));

                if ($queda < $umbral) {
                    $vence = date('Y-m-d H:i:s', time() + $this->tiempo_expiracion_token);

                    $renovar = $this->conn->prepare(
                        "UPDATE tokens_sesion SET fecha_expiracion = :vence WHERE id_token = :id"
                    );
                    $renovar->execute([':vence' => $vence, ':id' => $result['id_token']]);

                    $result['fecha_expiracion'] = $vence;
                }

                // Mantiene viva también la cookie del navegador.
                if (function_exists('rm_refrescar_cookie_sesion')) {
                    rm_refrescar_cookie_sesion();
                }

                return $result;
            }

            return null;
        } catch (PDOException $e) {
            error_log("Error al validar token: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cerrar sesión (invalidar token)
     */
    public function logout(string $token): bool {
        try {
            $sql = "UPDATE tokens_sesion SET activo = 0 WHERE token = :token";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':token' => $token]);
            
            // Registrar logout
            $this->registrarLog(null, null, null, null, 'logout', 'Cierre de sesión');
            
            return true;
        } catch (PDOException $e) {
            error_log("Error al cerrar sesión: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Preparar respuesta JSON
     */
    private function respuesta(bool $success, string $message, array $data = []): array {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}