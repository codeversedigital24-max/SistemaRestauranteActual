<?php
/**
 * =====================================================
 * GET  modelos/usuarios/obtener_usuarios.php
 * -----------------------------------------------------
 * Devuelve SOLO las filas de la tabla de usuarios en
 * JSON plano. Sin sesiones de UI, sin HTML: la tabla la
 * pintan DataTables en el navegador con 1 sola petición.
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

// Cabezas de respuesta
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');

rm_iniciar_sesion();

// Sesión obligatoria
if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'data' => []]);
    exit;
}

// Privilegio: ver la gestión de usuarios
rm_exigir_api('ver', Permisos::ITEM_SEGURIDAD_USUARIOS);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método no permitido', 'data' => []]);
    exit;
}

try {
    require_once __DIR__ . '/Usuario.php';
    require_once __DIR__ . '/../compras/ContextoCompras.php';

    $pdo = Conexion::getInstance()->getConnection();

    // Alcance por sucursal: sólo el SuperAdministrador ve todas.
    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'La sesión ya no es válida', 'data' => []]);
        exit;
    }

    $idSucursal = null;

    if (!$usuario['es_superadmin']) {
        // Usuario normal: SIEMPRE su propia sucursal (ignora lo que pida).
        $idSucursal = (int) $usuario['id_sucursal'];
    } elseif (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '') {
        $idSucursal = (int) $_GET['id_sucursal'];
    }

    $modelo = new Usuario();
    $filas  = $modelo->listar($idSucursal);

    echo json_encode(
        [
            'success'   => true,
            'message'   => 'ok',
            'data'      => $filas,
            'total'     => count($filas),
            'timestamp' => date('c'),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    error_log('obtener_usuarios.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al leer los usuarios', 'data' => []]);
}