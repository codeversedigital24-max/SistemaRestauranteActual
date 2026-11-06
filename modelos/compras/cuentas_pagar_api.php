<?php
/**
 * =====================================================
 * POST  modelos/compras/cuentas_pagar_api.php
 * -----------------------------------------------------
 * Acciones de Cuentas por pagar:
 *   registrar_pago            → abono a una compra
 *   registrar_pago_proveedor  → abono global distribuido
 *   pagos_compra              → historial de abonos
 *   eliminar_pago             → reversa de un abono
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

$respuesta = static function (int $codigo, bool $exito, string $mensaje, array $datos = []): void {
    http_response_code($codigo);
    echo json_encode(
        ['success' => $exito, 'message' => $mensaje, 'data' => $datos, 'timestamp' => date('c')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $respuesta(401, false, 'No autorizado');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respuesta(405, false, 'Método no permitido');
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!rm_csrf_valido($csrf)) {
    $respuesta(403, false, 'Token de seguridad inválido. Recarga la página.');
}

$entrada = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($entrada)) {
    $respuesta(400, false, 'Datos malformados');
}

$accion = (string) ($entrada['accion'] ?? '');
$datos  = (array) ($entrada['datos'] ?? []);

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/ContextoCompras.php';
    require_once __DIR__ . '/CuentaPagar.php';
    require_once __DIR__ . '/Compra.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $respuesta(401, false, 'La sesión ya no es válida');
    }

    $cuenta = new CuentaPagar($pdo);
    $compra = new Compra($pdo);

    switch ($accion) {
        case 'registrar_pago':
            rm_exigir_api('registrar', Permisos::ITEM_INV_CUENTAS_PAGAR);
            $idCompra = (int) ($datos['id_compra'] ?? 0);
            $detalle = $compra->obtener($idCompra);

            if (!$detalle) {
                $respuesta(404, false, 'Compra no encontrada');
            }

            if (!$usuario['es_superadmin'] && $detalle['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $respuesta(403, false, 'No puedes abonar compras de otra sucursal');
            }

            $datos['id_compra'] = $idCompra;
            $r = $cuenta->registrarPago($datos, $usuario);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(201, true, $r['mensaje'], [
                'id_pago' => $r['id'],
                'saldo'   => $r['saldo'],
                'monto'   => $r['monto'],
                'compra'  => $compra->obtener($idCompra),
            ]);

            break; // inalcanzable

        case 'registrar_pago_proveedor':
            rm_exigir_api('registrar', Permisos::ITEM_INV_CUENTAS_PAGAR);
            $r = $cuenta->registrarPagoProveedor($datos, $usuario);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(201, true, $r['mensaje'], [
                'saldo'   => $r['saldo'],
                'monto'   => $r['monto'],
                'compras' => $r['compras'],
                'detalle' => $r['detalle'] ?? [],
            ]);

            break; // inalcanzable

        case 'pagos_compra':
            rm_exigir_api('ver', Permisos::ITEM_INV_CUENTAS_PAGAR);
            $idCompra = (int) ($datos['id'] ?? 0);
            $detalle = $compra->obtener($idCompra);

            if (!$detalle) {
                $respuesta(404, false, 'Compra no encontrada');
            }

            if (!$usuario['es_superadmin'] && $detalle['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $respuesta(403, false, 'No puedes consultar compras de otra sucursal');
            }

            $respuesta(200, true, 'ok', [
                'compra' => $detalle,
                'pagos'  => $cuenta->pagos($idCompra),
            ]);

            break; // inalcanzable

        case 'eliminar_pago':
            rm_exigir_api('eliminar', Permisos::ITEM_INV_CUENTAS_PAGAR);
            $r = $cuenta->eliminarPago((int) ($datos['id'] ?? 0), $usuario);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(200, true, $r['mensaje'], [
                'id_pago' => $r['id'],
                'saldo'   => $r['saldo'],
            ]);

            break; // inalcanzable

        default:
            $respuesta(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    error_log('cuentas_pagar_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('cuentas_pagar_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}
