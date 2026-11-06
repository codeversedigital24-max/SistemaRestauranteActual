<?php
/**
 * =====================================================
 * POST  modelos/pos/pos_api.php
 * -----------------------------------------------------
 * Acciones del Punto de venta:
 *   registrar_venta | obtener_venta | anular_venta
 *
 * Entrada: { accion, datos }
 * Respuesta: { success, message, data, timestamp }
 * Protegido por sesión + token CSRF.
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
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

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $resp(401, false, 'No autorizado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $resp(405, false, 'Método no permitido');
}

if (!rm_csrf_valido($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    $resp(403, false, 'Token de seguridad inválido. Recarga la página.');
}

$entrada = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($entrada)) {
    $resp(400, false, 'Datos malformados');
}

$accion = (string) ($entrada['accion'] ?? '');
$datos  = (array) ($entrada['datos'] ?? []);

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/../cajas/ArqueoCaja.php';
    require_once __DIR__ . '/Venta.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) {
        $resp(401, false, 'La sesión ya no es válida');
    }

    // Acceso global: rol SuperAdministrador (modelo de privilegios)
    $esSuperAdmin = rm_es_superadmin();

    $idSucursal = $esSuperAdmin
        ? (int) ($datos['id_sucursal'] ?? 0)
        : (int) $usuario['id_sucursal'];

    $arqueo = new ArqueoCaja($pdo);
    $venta = new Venta($pdo);

    switch ($accion) {
        case 'registrar_venta':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_POS);

            if ($idSucursal <= 0) {
                $resp(422, false, 'Sucursal no válida');
            }

            $miCaja = $arqueo->cajaDeUsuario((int) $usuario['id']);
            if (!$miCaja) {
                $resp(422, false, 'No tienes caja asignada');
            }

            if ((int) $miCaja['id_sucursal'] !== $idSucursal) {
                $resp(422, false, 'Tu caja pertenece a otra sucursal. Actualiza el POS y vuelve a intentarlo.');
            }

            $abierto = $arqueo->abiertoDeCaja((int) $miCaja['id']);
            if (!$abierto) {
                $resp(422, false, 'La caja está cerrada: abre el arqueo antes de vender');
            }

            $r = $venta->registrar($datos, (int) $usuario['id'], $idSucursal, (int) $abierto['id']);
            if (!$r['exito']) {
                $resp(422, false, $r['mensaje']);
            }

            $resp(201, true, $r['mensaje'], [
                'id'    => $r['id'],
                'venta' => $r['venta'],
            ]);
            break;

        case 'obtener_venta':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_POS);

            $idVenta = (int) ($datos['id'] ?? 0);
            if ($idVenta <= 0) {
                $resp(422, false, 'Venta no válida');
            }

            $v = $venta->obtener($idVenta);
            if (!$v) {
                $resp(404, false, 'Venta no encontrada');
            }

            if (!$esSuperAdmin && (int) $v['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $resp(403, false, 'No puedes consultar ventas de otra sucursal');
            }

            $resp(200, true, 'ok', ['venta' => $v]);
            break;

        case 'anular_venta':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_POS);

            $idVenta = (int) ($datos['id'] ?? 0);
            if ($idVenta <= 0) {
                $resp(422, false, 'Venta no válida');
            }

            $r = $venta->anular($idVenta, $usuario);
            if (!$r['exito']) {
                $resp(422, false, $r['mensaje']);
            }

            $resp(200, true, $r['mensaje'], ['id' => $idVenta]);
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('pos_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('pos_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}
