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
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/ProductoVendidoReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');

    if (!ContextoCompras::puedeInventario($pdo, $usuario)) {
        $responder(403, false, 'No tienes permisos para ver este reporte');
    }

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    $filtros = [
        'id_sucursal'  => $idSucursal,
        'id_categoria' => (int) ($_GET['id_categoria'] ?? 0),
        'id_usuario'   => (int) ($_GET['id_usuario'] ?? 0),
        'desde'        => (string) ($_GET['desde'] ?? ''),
        'hasta'        => (string) ($_GET['hasta'] ?? ''),
    ];

    // Utilidades: SuperAdmin o rol 2. Si no, se ocultan costo/utilidad/margen.
    $verUtilidad = $usuario['es_superadmin'] || in_array((int) $usuario['nivel_permiso'], [1, 2], true);

    $modelo = new ProductoVendidoReporte($pdo, $verUtilidad);

    $responder(200, true, 'ok', [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $usuario['es_superadmin'],
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal' => $idSucursal,
        'ver_utilidad'=> $verUtilidad,
        'sucursales'  => $modelo->sucursalesDisponibles($usuario),
        'categorias'  => $modelo->categoriasDisponibles(),
        'ranking'     => $modelo->ranking($filtros),
        'resumen'     => $modelo->resumen($filtros),
        'top_cantidad'=> $modelo->topCantidad($filtros, 10),
        'por_categoria' => $modelo->porCategoria($filtros),
        'top_venta'   => $modelo->topVenta($filtros, 5),
    ]);
} catch (PDOException $e) {
    error_log('obtener_productos_vendidos.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_productos_vendidos.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}