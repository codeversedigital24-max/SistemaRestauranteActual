<?php
/**
 * =====================================================
 * GET  modelos/productos/obtener_productos.php
 * -----------------------------------------------------
 * Índice de productos + categorías en JSON plano para
 * la tabla y el modal (1 sola petición).
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

rm_exigir_api('ver', Permisos::ITEM_INV_PRODUCTOS);

try {
    require_once __DIR__ . '/Producto.php';

    $modelo = new Producto();
    $filas  = $modelo->listar();

    echo json_encode(
        [
            'success'    => true,
            'message'    => 'ok',
            'data'       => $filas,
            'categorias' => $modelo->categorias(),
            'total'      => count($filas),
            'timestamp'  => date('c'),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    error_log('obtener_productos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al leer los productos', 'data' => []]);
}
