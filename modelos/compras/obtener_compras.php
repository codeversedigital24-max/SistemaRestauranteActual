<?php
/**
 * =====================================================
 * GET  modelos/compras/obtener_compras.php
 * -----------------------------------------------------
 * Datos de la página de compras en una sola petición:
 *   · contexto del usuario y sucursales permitidas
 *   · tarjetas de resumen
 *   · listado de compras filtrable
 *   · proveedores para el filtro y el modal
 *
 * Filtros: id_sucursal, id_proveedor, desde, hasta, estado
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');

rm_iniciar_sesion();

$responder = static function (int $codigo, bool $exito, string $mensaje, array $datos = []): void {
    http_response_code($codigo);
    echo json_encode(
        ['success' => $exito, 'message' => $mensaje, 'data' => $datos, 'timestamp' => date('c')],
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

    require_once __DIR__ . '/ContextoCompras.php';
    require_once __DIR__ . '/Compra.php';
    require_once __DIR__ . '/Proveedor.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $responder(401, false, 'La sesión ya no es válida');
    }

    rm_exigir_api('ver', Permisos::ITEM_INV_COMPRAS);

    $idSucursal = ContextoCompras::sucursalConsulta(
        $usuario,
        isset($_GET['id_sucursal']) ? (int) $_GET['id_sucursal'] : null
    );

    // Usuario normal SIN sucursal asignada: no se devuelven compras.
    if (!$usuario['es_superadmin'] && $idSucursal === null) {
        $responder(403, false, 'Tu usuario no tiene sucursal asignada');
    }

    $idProveedor = (int) ($_GET['id_proveedor'] ?? 0);
    $desde       = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : '';
    $hasta       = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : '';
    $estado      = (string) ($_GET['estado'] ?? '');

    $compras = (new Compra($pdo))->listar($idSucursal, $idProveedor, $desde, $hasta, $estado);

    if ($usuario['es_superadmin']) {
        $sucursales = ContextoCompras::sucursales($pdo);
    } else {
        $propia = ContextoCompras::sucursalActiva($pdo, $usuario['id_sucursal']);
        $sucursales = $propia ? [$propia] : [];
    }

    $responder(200, true, 'ok', [
        'usuario'    => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'username'      => $usuario['username'],
            'sucursal'      => $usuario['sucursal'],
            'id_sucursal'   => $usuario['id_sucursal'],
            'es_superadmin' => $usuario['es_superadmin'],
            'rol'           => $usuario['rol'],
        ],
        'id_sucursal' => $idSucursal,
        'sucursales'  => $sucursales,
        'resumen'     => (new Compra($pdo))->resumen($idSucursal),
        'compras'     => $compras,
        'proveedores' => (new Proveedor($pdo))->listar(),
    ]);
} catch (PDOException $e) {
    error_log('obtener_compras.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_compras.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}
