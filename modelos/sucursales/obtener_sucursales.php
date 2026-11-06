<?php
/**
 * =====================================================
 * GET  modelos/sucursales/obtener_sucursales.php
 * -----------------------------------------------------
 * Índice de sucursales en JSON plano para la tabla
 * (1 sola petición, igual que el módulo de usuarios).
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');

rm_iniciar_sesion();

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'data' => []]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método no permitido', 'data' => []]);
    exit;
}

rm_exigir_api('ver', Permisos::ITEM_CONFIG_SUCURSALES);

try {
    require_once __DIR__ . '/Sucursal.php';
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

    $modelo = new Sucursal();
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
    error_log('obtener_sucursales.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al leer sucursales', 'data' => []]);
}