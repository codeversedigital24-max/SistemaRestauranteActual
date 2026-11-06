<?php
/**
 * =====================================================
 * API - Autenticación (POST /api/login.php)
 * =====================================================
 * Protegida contra:
 *  - peticiones cruza-sitio (CSRF: token por sesión)
 *  - métodos que no sean POST
 *  - cuerpos malformados
 *  - ráfagas repetidas desde la misma sesión
 *
 * Respuesta siempre en JSON.
 * =====================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

// En producción nunca se pintan errores en pantalla.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/config.php';

// Devuelve JSON y termina.
$falla = static function (int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(
        ['success' => false, 'message' => $mensaje, 'data' => (object) []],
        JSON_UNESCAPED_UNICODE
    );
    exit;
};

// -------------------------------------------------
// 1. Sesion endurecida (cookie SameSite + httponly)
// -------------------------------------------------
rm_iniciar_sesion();

// -------------------------------------------------
// 2. Sólo POST
// -------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $falla(405, 'Método no permitido');
}

// -------------------------------------------------
// 3. Token CSRF: debe coincidir con el de la sesión
// -------------------------------------------------
$csrfSesion = $_SESSION['csrf_token'] ?? '';
$csrfCliente = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    !is_string($csrfSesion)
    || $csrfSesion === ''
    || !is_string($csrfCliente)
    || !hash_equals($csrfSesion, $csrfCliente)
) {
    $falla(403, 'Sesión no válida. Recarga la página e inténtalo de nuevo.');
}

// -------------------------------------------------
// 4. Control de ráfagas (simple y sin dependencias)
//    Max 10 intentos por minuto desde esta sesión.
// -------------------------------------------------
$ahora = time();
$ventana = $_SESSION['rm_login_ventana'] ?? ['inicio' => $ahora, 'intentos' => 0];

if ($ahora - (int) $ventana['inicio'] >= 60) {
    $ventana = ['inicio' => $ahora, 'intentos' => 0];
}

if ((int) $ventana['intentos'] >= 10) {
    $_SESSION['rm_login_ventana'] = $ventana;
    $falla(429, 'Demasiados intentos. Espera un minuto antes de reintentar.');
}

$ventana['intentos'] = (int) $ventana['intentos'] + 1;
$_SESSION['rm_login_ventana'] = $ventana;

// -------------------------------------------------
// 5. Cuerpo JSON
// -------------------------------------------------
$contenidoTipo = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contenidoTipo, 'application/json') === false) {
    $falla(415, 'Contenido no soportado');
}

$crudo = file_get_contents('php://input');
$entrada = json_decode($crudo ?: '', true);

if (!is_array($entrada)) {
    $falla(400, 'Cuerpo JSON malformado');
}

$login    = trim((string) ($entrada['email'] ?? ''));
$password = (string) ($entrada['password'] ?? '');

if ($login === '' || $password === '') {
    $falla(400, 'Usuario y contraseña son obligatorios');
}

if (mb_strlen($login) > 100 || mb_strlen($password) > 72) {
    $falla(422, 'Datos fuera de rango');
}

// -------------------------------------------------
// 6. IP y agente
// -------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// Sólo se presta atención a X-Forwarded-For si viene de una red interna conocida.
if (
    isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    && filter_var($ip, FILTER_VALIDATE_IP)
    && (strpos($ip, '127.') === 0 || $ip === '::1' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE))
) {
    $primer = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    if (filter_var($primer, FILTER_VALIDATE_IP)) {
        $ip = $primer;
    }
}

$userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

// -------------------------------------------------
// 7. Autenticación
// -------------------------------------------------
try {
    $loginService = new Login();
    $resultado = $loginService->autenticar($login, $password, $ip, $userAgent);

    if (empty($resultado['success'])) {
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = (string) ($resultado['data']['token'] ?? '');
    $destino = (string) ($resultado['data']['redirect'] ?? 'sistema/dashboard.php');

    if ($token === '') {
        $falla(500, 'No se pudo crear la sesión. Inténtalo de nuevo.');
    }

    // ------------------------------------------------
    // Sesión de PHP: identidad + token de control
    // ------------------------------------------------
    session_regenerate_id(true); // impide fijación de sesión

    $_SESSION['token']       = $token;
    $_SESSION['usuario']     = $resultado['data']['usuario'] ?? [];
    $_SESSION['id_usuario']  = (int) ($resultado['data']['usuario']['id'] ?? 0);
    $_SESSION['rol']         = $resultado['data']['usuario']['rol']['nombre'] ?? '';
    $_SESSION['nombre']      = $resultado['data']['usuario']['nombre_completo'] ?? '';
    $_SESSION['csrf_token']  = bin2hex(random_bytes(32)); // rota tras cada login

    // Recuento de intentos se reinicia
    unset($_SESSION['rm_login_ventana']);

    error_log('Login OK: ' . ($resultado['data']['usuario']['email'] ?? ''));

    // El token NUNCA viaja al navegador: la cookie de sesión lo cargó.
    $resultado['data'] = [
        'redirect' => $destino,
        'usuario'  => [
            'nombre' => $_SESSION['nombre'],
            'rol'    => $_SESSION['rol'],
        ],
    ];

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('api/login.php PDO: ' . $e->getMessage());
    $falla(503, 'Servicio de base de datos no disponible. Verifica que la BD exista.');
} catch (Throwable $e) {
    error_log('api/login.php: ' . $e->getMessage());
    $falla(500, 'Error interno del servidor');
}