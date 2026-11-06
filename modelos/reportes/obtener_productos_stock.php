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
    require_once __DIR__ . '/ProductoStockReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');
    if (!ContextoCompras::puedeInventario($pdo, $usuario)) {
        $responder(403, false, 'No tienes permisos');
    }

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : 0)
        : (int) $usuario['id_sucursal'];

    if ($idSucursal <= 0 && $usuario['es_superadmin']) {
        $primera = $pdo->query("SELECT id_sucursal FROM sucursales WHERE estado = 1 ORDER BY id_sucursal LIMIT 1")->fetchColumn();
        $idSucursal = (int) ($primera ?: 0);
    }

    if ($idSucursal <= 0) $responder(422, false, 'Selecciona una sucursal');

    $estado      = (string) ($_GET['estado'] ?? '');
    $idCategoria = (int) ($_GET['id_categoria'] ?? 0);
    $buscar      = (string) ($_GET['buscar'] ?? '');

    if (!in_array($estado, ['', 'sin', 'bajo', 'optimo', 'sin_control'], true)) $estado = '';

    $modelo = new ProductoStockReporte($pdo);

    $responder(200, true, 'ok', [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $usuario['es_superadmin'],
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal' => $idSucursal,
        'sucursales'  => $modelo->sucursales($usuario),
        'categorias'  => $modelo->categorias(),
        'productos'   => $modelo->listar($idSucursal, $estado, $idCategoria, $buscar),
        'resumen'     => $modelo->resumen($idSucursal),
        'filtros'     => ['estado' => $estado, 'id_categoria' => $idCategoria, 'buscar' => $buscar],
    ]);
} catch (PDOException $e) {
    error_log('obtener_productos_stock.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_productos_stock.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}