<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
rm_iniciar_sesion();

$responder = static function (int $c, bool $ok, string $m, array $d = []): void {
    http_response_code($c);
    echo json_encode(
        ['success'=>$ok,'message'=>$m,'data'=>$d,'timestamp'=>date('c')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $responder(401, false, 'No autorizado');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $responder(405, false, 'Método no permitido');
}

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/MiPerfil.php';

    $modelo = new MiPerfil($pdo);
    $perfil = $modelo->obtener((int) $_SESSION['id_usuario']);

    if (!$perfil) {
        $responder(404, false, 'Perfil no encontrado');
    }

    $responder(200, true, 'ok', ['perfil' => $perfil]);
} catch (PDOException $e) {
    error_log('obtener_perfil.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_perfil.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}