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

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) $responder(401, false, 'No autorizado');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); $responder(405, false, 'Método no permitido'); }

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/VentaReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');
    rm_exigir_api('ver', Permisos::ITEM_REP_VENTAS_FECHAS);

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    if (!$usuario['es_superadmin'] && $idSucursal <= 0) {
        $responder(403, false, 'Tu usuario no tiene sucursal asignada');
    }

    $filtros = [
        'id_sucursal' => $idSucursal,
        'id_usuario'  => (int) ($_GET['id_usuario'] ?? 0),
        'id_arqueo'   => (int) ($_GET['id_arqueo'] ?? 0),
        'desde'       => (string) ($_GET['desde'] ?? ''),
        'hasta'       => (string) ($_GET['hasta'] ?? ''),
        'metodo_pago' => (string) ($_GET['metodo_pago'] ?? ''),
        'estado'      => (string) ($_GET['estado'] ?? ''),
    ];

    $modelo = new VentaReporte($pdo);

    $responder(200, true, 'ok', [
        'usuario'    => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $usuario['es_superadmin'],
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal' => $idSucursal,
        'sucursales'  => $modelo->sucursalesDisponibles($usuario),
        'usuarios'    => $modelo->usuariosDisponibles($idSucursal),
        'cajas'       => $modelo->cajasDisponibles($idSucursal),
        'ventas'      => $modelo->listar($filtros),
        'resumen'     => $modelo->resumen($filtros),
        'por_dia'     => $modelo->porDia($filtros),
        'por_metodo'  => $modelo->porMetodo($filtros),
        'por_sucursal'=> $modelo->porSucursal($filtros),
        'top_vend'    => $modelo->topVendedores($filtros, 8),
    ]);
} catch (PDOException $e) {
    error_log('obtener_ventas_reporte.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_ventas_reporte.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}