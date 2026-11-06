<?php
/**
 * =====================================================
 * GET  modelos/compras/nuevacompra_datos.php
 * -----------------------------------------------------
 * Catálogo que alimenta el formulario de nueva compra:
 *   · sucursal de trabajo (o selector para SuperAdmin)
 *   · productos con stock global (productos.stock), costo y
 *     precio de venta de ESA sucursal
 *   · proveedores activos
 *   · configuración de la empresa (impuesto por defecto)
 *
 * Query: id_sucursal (sólo SuperAdmin)
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

    // Nueva compra (13) o edición de una compra existente (12)
    if (!rm_puede('ver', Permisos::ITEM_INV_NUEVA_COMPRA) && !rm_puede('ver', Permisos::ITEM_INV_COMPRAS)) {
        rm_exigir_api('ver', Permisos::ITEM_INV_NUEVA_COMPRA);
    }

    if ($usuario['es_superadmin']) {
        $sucursales = ContextoCompras::sucursales($pdo);
    } else {
        $propia = ContextoCompras::sucursalActiva($pdo, $usuario['id_sucursal']);
        $sucursales = $propia ? [$propia] : [];
    }

    $solicitada = isset($_GET['id_sucursal']) ? (int) $_GET['id_sucursal'] : 0;
    $idSucursal = $usuario['es_superadmin'] ? $solicitada : $usuario['id_sucursal'];

    $idCompra = isset($_GET['id_compra']) ? (int) $_GET['id_compra'] : 0;
    $compraEdicion = null;

    $modeloCompra = new Compra($pdo);

    if ($idCompra > 0) {
        $compraEdicion = $modeloCompra->obtener($idCompra);

        if (!$compraEdicion) {
            $responder(404, false, 'La compra que quieres modificar no existe');
        }

        if ($compraEdicion['estado'] !== 'registrada') {
            $responder(422, false, 'Solo se pueden modificar compras registradas');
        }

        if (!$usuario['es_superadmin'] && (int) $compraEdicion['id_sucursal'] !== (int) $usuario['id_sucursal']) {
            $responder(403, false, 'No puedes modificar compras de otra sucursal');
        }

        $idSucursal = (int) $compraEdicion['id_sucursal'];
    }

    if ($usuario['es_superadmin'] && $idSucursal <= 0 && $sucursales !== []) {
        $idSucursal = (int) $sucursales[0]['id_sucursal'];
    }

    if ($idSucursal <= 0) {
        $responder(422, false, 'Selecciona la sucursal donde se registrará la compra');
    }

    if (!ContextoCompras::sucursalActiva($pdo, $idSucursal)) {
        $responder(404, false, 'La sucursal seleccionada no existe o está inactiva');
    }

    // Impuesto sugerido para la compra (editable en el formulario).
    $impuesto = 0.0;

    $responder(200, true, 'ok', [
        'usuario'      => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'username'      => $usuario['username'],
            'sucursal'      => $usuario['sucursal'],
            'id_sucursal'   => $usuario['id_sucursal'],
            'es_superadmin' => $usuario['es_superadmin'],
        ],
        'id_sucursal'  => $idSucursal,
        'sucursales'   => $sucursales,
        'productos'    => $modeloCompra->productosParaCompra($idSucursal),
        'proveedores'  => (new Proveedor($pdo))->listar(true),
        'compra'       => $compraEdicion,
        'impuesto'     => round($impuesto, 2),
        'fecha_hoy'    => date('Y-m-d\TH:i'),
        'tipos_documento' => [
            ['valor' => 'factura', 'texto' => 'Factura'],
            ['valor' => 'recibo', 'texto' => 'Recibo'],
            ['valor' => 'nota', 'texto' => 'Nota de entrega'],
            ['valor' => 'otro', 'texto' => 'Otro'],
        ],
        'formas_pago' => [
            ['valor' => 'contado', 'texto' => 'Contado'],
            ['valor' => 'credito', 'texto' => 'Crédito'],
            ['valor' => 'transferencia', 'texto' => 'Transferencia'],
            ['valor' => 'qr', 'texto' => 'QR'],
            ['valor' => 'otro', 'texto' => 'Otro'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('nuevacompra_datos.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('nuevacompra_datos.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}
