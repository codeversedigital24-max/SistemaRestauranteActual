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
    require_once __DIR__ . '/MesaGestion.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');

    // Las mesas alimentan también POS y pedidos: basta ver mesas (2),
    // POS (6) o pedidos (7).
    if (
        !rm_puede('ver', Permisos::ITEM_CONFIG_MESAS)
        && !rm_puede('ver', Permisos::ITEM_VENTAS_POS)
        && !rm_puede('ver', Permisos::ITEM_VENTAS_PEDIDOS)
    ) {
        $responder(403, false, 'No tienes privilegios para ver las mesas');
    }

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    // Usuario normal SIN sucursal asignada: no se devuelven pisos ni mesas.
    if (!$usuario['es_superadmin'] && (int) $idSucursal <= 0) {
        $responder(403, false, 'Tu usuario no tiene sucursal asignada');
    }

    $filtros = [
        'id_sucursal' => $idSucursal,
        'id_piso'     => (int) ($_GET['id_piso'] ?? 0),
        'estado_mesa' => (string) ($_GET['estado_mesa'] ?? ''),
    ];

    $modelo = new MesaGestion($pdo);

    $responder(200, true, 'ok', [
        'usuario'    => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $usuario['es_superadmin'],
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'sucursales' => $modelo->sucursalesDisponibles($usuario),
        'pisos'      => $modelo->pisos($idSucursal),
        'mesas'      => $modelo->mesas($filtros),
    ]);
} catch (PDOException $e) {
    error_log('obtener_mesas.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_mesas.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}