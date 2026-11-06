<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
rm_iniciar_sesion();

$resp = static function (int $c, bool $ok, string $m, array $d = []): void {
    http_response_code($c);
    echo json_encode(
        ['success' => $ok, 'message' => $m, 'data' => $d, 'timestamp' => date('c')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) $resp(401, false, 'No autorizado');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $resp(405, false, 'Método no permitido');
}
if (!rm_csrf_valido($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $resp(403, false, 'Token inválido');

$entrada = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($entrada)) $resp(400, false, 'Datos malformados');

$accion = (string) ($entrada['accion'] ?? '');
$datos  = (array)  ($entrada['datos']  ?? []);

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/../cajas/ArqueoCaja.php';
    require_once __DIR__ . '/Pedido.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $resp(401, false, 'La sesión ya no es válida');

    // Acceso global: rol SuperAdministrador (modelo de privilegios)
    $esSuperAdmin = rm_es_superadmin();

    $idSucursal = $esSuperAdmin
        ? (int) ($datos['id_sucursal'] ?? 0)
        : (int) $usuario['id_sucursal'];

    $pedido = new Pedido($pdo);

    // Alcance por sucursal: un pedido de otra sucursal queda fuera del alcance.
    $pedidoDelAlcance = static function (int $idPedido) use ($pedido, $esSuperAdmin, $usuario, $resp): array {
        $p = $pedido->obtener($idPedido);

        if (!$p) {
            $resp(404, false, 'Pedido no encontrado');
        }

        if (!$esSuperAdmin) {
            $sucursalPropia = (int) $usuario['id_sucursal'];

            if ($sucursalPropia <= 0 || (int) $p['id_sucursal'] !== $sucursalPropia) {
                $resp(403, false, 'No puedes acceder a pedidos de otra sucursal');
            }
        }

        return $p;
    };

    switch ($accion) {
        case 'abrir':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_PEDIDOS);

            if ($idSucursal <= 0) $resp(422, false, 'Sucursal no válida');
            $datos['id_sucursal'] = $idSucursal;
            $r = $pedido->abrir($datos, (int) $usuario['id'], $idSucursal);
            break;
        case 'detalle':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_PEDIDOS);

            $id = (int) ($datos['id_pedido'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Pedido no válido');
            $p = $pedidoDelAlcance($id);
            $resp(200, true, 'ok', ['pedido' => $p]);
            break;
        case 'agregar_items':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_PEDIDOS);

            $id = (int) ($datos['id_pedido'] ?? 0);
            $items = $datos['items'] ?? [];
            if ($id <= 0) $resp(422, false, 'Pedido no válido');
            $pedidoDelAlcance($id);
            $r = $pedido->agregarItems($id, $items, (int) $usuario['id'], $idSucursal);
            break;

        case 'despachar':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_PEDIDOS);

            $id = (int) ($datos['id_pedido'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Pedido no válido');
            $pedidoDelAlcance($id);
            $r = $pedido->despachar($id, (int) $usuario['id']);
            break;

        case 'enviar_a_caja':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_PEDIDOS);

            $id = (int) ($datos['id_pedido'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Pedido no válido');
            $pedidoDelAlcance($id);
            $r = $pedido->enviarACaja($id);
            break;

        case 'cancelar':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_PEDIDOS);

            $id = (int) ($datos['id_pedido'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Pedido no válido');
            $pedidoDelAlcance($id);
            $r = $pedido->cancelar($id, (int) $usuario['id']);
            break;

        case 'cobrar':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_PEDIDOS);

            $id = (int) ($datos['id_pedido'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Pedido no válido');

            $pedidoDelAlcance($id);

            $caja = new ArqueoCaja($pdo);
            $miCaja = $caja->cajaDeUsuario((int) $usuario['id']);
            if (!$miCaja) $resp(422, false, 'No tienes caja asignada');
            $abierto = $caja->abiertoDeCaja((int) $miCaja['id']);
            if (!$abierto) $resp(422, false, 'Abre tu arqueo antes de cobrar');

            $r = $pedido->cobrar($id, $datos, (int) $usuario['id'], $idSucursal, (int) $abierto['id']);
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }

    if (!empty($r['exito'])) {
        $extra = [];
        if (!empty($r['pedido'])) $extra['pedido'] = $r['pedido'];
        if (!empty($r['id_venta'])) $extra['id_venta'] = $r['id_venta'];
        if (!empty($r['venta'])) $extra['venta'] = $r['venta'];
        $resp(200, true, $r['mensaje'], array_merge(['id' => $r['id'] ?? 0], $extra));
    }

    $resp(422, false, $r['mensaje'] ?? 'No se pudo completar');
} catch (PDOException $e) {
    error_log('pedidos_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('pedidos_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}
