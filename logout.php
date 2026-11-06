<?php
/**
 * Logout - Cerrar sesión (API)
 * Responde JSON con redirect a login.php?logout=success
 */
require_once 'config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

if (session_status() !== PHP_SESSION_ACTIVE) {
    rm_iniciar_sesion();
}

// Invalidar token en BD (si tienes la clase Login cargada)
if (!empty($_SESSION['token'])) {
    try {
        if (class_exists('Login')) {
            $login = new Login();
            $login->logout($_SESSION['token']);
        }
    } catch (Throwable $e) {
        error_log("Error en logout: " . $e->getMessage());
    }
}

// Limpiar sesión
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Destino relativo a `sistema/` (donde está el menú)
$destino = 'login.php?logout=success';

echo json_encode([
    'success'  => true,
    'message'  => 'Sesión cerrada correctamente',
    'redirect' => $destino
]);
exit;