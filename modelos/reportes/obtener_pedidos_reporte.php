<?php
/**
 * =====================================================
 * GET  modelos/reportes/obtener_pedidos_reporte.php
 * -----------------------------------------------------
 * Datos del reporte "Pedidos por fechas".
 *
 * Query:
 *   id_sucursal · tipo (mesa|online|llevar|delivery) ·
 *   estado · desde · hasta
 *
 * Privilegio: VER · Ventas por fechas (16), igual que los
 * demás reportes de ventas/productos.
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
rm_iniciar_sesion();

$responder = static function (int $c, bool $ok, string $m, array $d = []): void {
    http_response_code($c);
    echo json_encode(
        ['success' => $ok, 'message' => $m, 'data' => $d, 'timestamp' => date('c')],
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
    require_once __DIR__ . '/PedidoReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $responder(401, false, 'La sesión ya no es válida');
    }

    rm_exigir_api('ver', Permisos::ITEM_REP_VENTAS_FECHAS);

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    if (!$usuario['es_superadmin'] && $idSucursal <= 0) {
        $responder(403, false, 'Tu usuario no tiene sucursal asignada');
    }

    $filtros = [
        'id_sucursal' => $idSucursal,
        'tipo'        => (string) ($_GET['tipo'] ?? ''),
        'estado'      => (string) ($_GET['estado'] ?? ''),
        'desde'       => (string) ($_GET['desde'] ?? ''),
        'hasta'       => (string) ($_GET['hasta'] ?? ''),
    ];

    $modelo = new PedidoReporte($pdo);

    $responder(200, true, 'ok', [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $usuario['es_superadmin'],
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal' => $idSucursal,
        'sucursales'  => $modelo->sucursalesDisponibles($usuario),
        'pedidos'     => $modelo->listar($filtros),
        'resumen'     => $modelo->resumen($filtros),
        'por_dia'     => $modelo->porDia($filtros),
    ]);
} catch (PDOException $e) {
    error_log('obtener_pedidos_reporte.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_pedidos_reporte.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}
