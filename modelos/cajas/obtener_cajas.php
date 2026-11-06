<?php
/**
 * =====================================================
 * GET  modelos/cajas/obtener_cajas.php
 * -----------------------------------------------------
 * Índice de cajas + sucursales + resumen en JSON plano
 * para la tabla y el modal (1 sola petición).
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

// Privilegio del ítem: consultar cajas
rm_exigir_api('ver', Permisos::ITEM_VENTAS_CAJAS);

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/Caja.php';
    require_once __DIR__ . '/../compras/ContextoCompras.php';

    // Alcance por sucursal/caja del usuario en sesión.
    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'La sesión ya no es válida', 'data' => []]);
        exit;
    }

    $modelo = new Caja($pdo);
    $esSuperAdmin = $usuario['es_superadmin'];

    // Sucursal: SuperAdmin puede filtrar; el resto ve sólo la propia.
    $idSucursal = $esSuperAdmin
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    // Cajero sin privilegio `modificar` en cajas ni arqueos: sólo SU caja.
    $puedeModificarCajas = rm_puede('modificar', Permisos::ITEM_VENTAS_CAJAS)
        || rm_puede('modificar', Permisos::ITEM_VENTAS_ARQUEO);

    $idUsuarioAsignado = null;

    if (!$esSuperAdmin && !$puedeModificarCajas) {
        $idUsuarioAsignado = (int) $usuario['id'];
    }

    $filas = $modelo->listar($idSucursal, $idUsuarioAsignado);

    echo json_encode(
        [
            'success'    => true,
            'message'    => 'ok',
            'data'       => $filas,
            'sucursales' => $modelo->sucursales($idSucursal),
            'resumen'    => $modelo->resumen($idSucursal, $idUsuarioAsignado),
            'total'      => count($filas),
            'timestamp'  => date('c'),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    error_log('obtener_cajas.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al leer las cajas', 'data' => []]);
}
