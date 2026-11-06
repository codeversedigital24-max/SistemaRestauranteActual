<?php
/**
 * =====================================================
 * POST  modelos/compras/compras_api.php
 * -----------------------------------------------------
 * Acciones del módulo de compras:
 *   registrar_compra | obtener_compra
 *   proveedores | proveedor_crear | proveedor_actualizar
 *   proveedor_eliminar
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
    require_once __DIR__ . '/Compra.php';
    require_once __DIR__ . '/Proveedor.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $respuesta(401, false, 'La sesión ya no es válida');
    }

    if (!ContextoCompras::puedeInventario($pdo, $usuario)) {
        $respuesta(403, false, 'No tienes permisos sobre el módulo de inventario');
    }

    $compra    = new Compra($pdo);
    $proveedor = new Proveedor($pdo);

    switch ($accion) {
        // ---------------------------------------------
        // Compra
        // ---------------------------------------------
        case 'registrar_compra':
            rm_exigir_api('registrar', Permisos::ITEM_INV_NUEVA_COMPRA);
            $sucursalForzada = ContextoCompras::sucursalOperacion(
                $usuario,
                (int) ($datos['id_sucursal'] ?? 0)
            );

            if ($sucursalForzada <= 0) {
                $respuesta(422, false, 'Selecciona la sucursal de la compra');
            }

            $r = $compra->registrar($datos, $usuario['id'], $sucursalForzada);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(201, true, $r['mensaje'], [
                'id'     => $r['id'],
                'compra' => $r['compra'],
            ]);

            break; // inalcanzable

        case 'actualizar_compra':
            rm_exigir_api_alguno('modificar', [Permisos::ITEM_INV_COMPRAS, Permisos::ITEM_INV_NUEVA_COMPRA]);
            $id = (int) ($datos['id'] ?? 0);
            $actual = $compra->obtener($id);

            if (!$actual) {
                $respuesta(404, false, 'Compra no encontrada');
            }

            if (!$usuario['es_superadmin'] && $actual['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $respuesta(403, false, 'No puedes modificar compras de otra sucursal');
            }

            $r = $compra->actualizar($id, $datos, $usuario['id']);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(200, true, $r['mensaje'], [
                'id'     => $r['id'],
                'compra' => $r['compra'],
            ]);

            break; // inalcanzable

        case 'anular_compra':
            rm_exigir_api('eliminar', Permisos::ITEM_INV_COMPRAS);
            $id = (int) ($datos['id'] ?? 0);
            $actual = $compra->obtener($id);

            if (!$actual) {
                $respuesta(404, false, 'Compra no encontrada');
            }

            if (!$usuario['es_superadmin'] && $actual['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $respuesta(403, false, 'No puedes eliminar compras de otra sucursal');
            }

            $r = $compra->anular($id, $usuario['id']);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(200, true, $r['mensaje'], [
                'id'     => $r['id'],
                'compra' => $r['compra'],
            ]);

            break; // inalcanzable

        case 'obtener_compra':
            rm_exigir_api('ver', Permisos::ITEM_INV_COMPRAS);
            $id = (int) ($datos['id'] ?? 0);
            $detalle = $compra->obtener($id);

            if (!$detalle) {
                $respuesta(404, false, 'Compra no encontrada');
            }

            if (!$usuario['es_superadmin'] && $detalle['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $respuesta(403, false, 'No puedes consultar compras de otra sucursal');
            }

            $respuesta(200, true, 'ok', ['compra' => $detalle]);

            break; // inalcanzable

        // ---------------------------------------------
        // Proveedores
        // ---------------------------------------------
        case 'proveedores':
            rm_exigir_api_alguno('ver', [Permisos::ITEM_INV_COMPRAS, Permisos::ITEM_INV_NUEVA_COMPRA]);
            $respuesta(200, true, 'ok', ['proveedores' => $proveedor->listar()]);

            break; // inalcanzable

        case 'proveedor_obtener':
            rm_exigir_api_alguno('ver', [Permisos::ITEM_INV_COMPRAS, Permisos::ITEM_INV_NUEVA_COMPRA]);
            $fila = $proveedor->obtener((int) ($datos['id'] ?? 0));

            if (!$fila) {
                $respuesta(404, false, 'Proveedor no encontrado');
            }

            $respuesta(200, true, 'ok', ['proveedor' => $fila]);

            break; // inalcanzable

        case 'proveedor_crear':
            rm_exigir_api_alguno('registrar', [Permisos::ITEM_INV_COMPRAS, Permisos::ITEM_INV_NUEVA_COMPRA]);
            $r = $proveedor->crear($datos);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(201, true, $r['mensaje'], ['id' => $r['id']]);

            break; // inalcanzable

        case 'proveedor_actualizar':
            rm_exigir_api_alguno('modificar', [Permisos::ITEM_INV_COMPRAS, Permisos::ITEM_INV_NUEVA_COMPRA]);
            $r = $proveedor->actualizar($datos);

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(200, true, $r['mensaje'], ['id' => $r['id']]);

            break; // inalcanzable

        case 'proveedor_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_INV_COMPRAS);
            $r = $proveedor->eliminar((int) ($datos['id'] ?? 0));

            if (!$r['exito']) {
                $respuesta(422, false, $r['mensaje']);
            }

            $respuesta(200, true, $r['mensaje'], ['id' => $r['id']]);

            break; // inalcanzable

        default:
            $respuesta(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    error_log('compras_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('compras_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}
