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

// Privilegio del ítem: ver pedidos
rm_exigir_api('ver', Permisos::ITEM_VENTAS_PEDIDOS);

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/../cajas/ArqueoCaja.php';
    require_once __DIR__ . '/../productos/Producto.php';
    require_once __DIR__ . '/../pos/Promocion.php';
    require_once __DIR__ . '/../pos/PrecioPos.php';
    require_once __DIR__ . '/Pedido.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');

    // Acceso global: rol SuperAdministrador (modelo de privilegios)
    $esSuperAdmin = rm_es_superadmin();

    $idSucursal = $esSuperAdmin
        ? (int) ($_GET['id_sucursal'] ?? 0)
        : (int) $usuario['id_sucursal'];

    if ($idSucursal <= 0 && $esSuperAdmin) {
        $primera = $pdo->query("SELECT id_sucursal FROM sucursales WHERE estado = 1 ORDER BY id_sucursal LIMIT 1")->fetchColumn();
        $idSucursal = (int) ($primera ?: 0);
    }
    if ($idSucursal <= 0) $responder(422, false, 'Selecciona una sucursal');

    $sucursales = $esSuperAdmin
        ? ContextoCompras::sucursales($pdo)
        : (($s = ContextoCompras::sucursalActiva($pdo, $usuario['id_sucursal'])) ? [$s] : []);

    $pedidoModel = new Pedido($pdo);
    $caja = new ArqueoCaja($pdo);
    $miCaja = $caja->cajaDeUsuario((int) $usuario['id']);
    $arqueo = $miCaja ? $caja->abiertoDeCaja((int) $miCaja['id']) : null;

    // ---- Catálogo para el modal de tomar pedido ----
    $productos = (new Producto($pdo))->listar();
    $productos = array_values(array_filter(
        $productos,
        fn($p) => (int)$p['estado'] === 1 && (int)$p['disponible'] === 1
    ));

    $st = $pdo->prepare(
        "SELECT id_producto, precio_venta FROM productos_sucursal WHERE id_sucursal = :s"
    );
    $st->execute([':s' => $idSucursal]);
    $map = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $map[(int) $r['id_producto']] = $r;

    $promos = (new Promocion($pdo))->vigentes();

    foreach ($productos as &$p) {
        $ps = $map[$p['id']] ?? null;
        // Stock GLOBAL del producto (productos.stock), igual que el POS.
        // `stock_sucursal` se conserva como alias para pedidos.js.
        $stock = (float) ($p['stock'] ?? 0);
        $base = ($ps && (float) $ps['precio_venta'] > 0)
            ? (float) $ps['precio_venta']
            : (float) $p['precio'];
        $p['oferta_vigente'] = ($p['oferta_vigente'] ?? 0) ? 1 : 0;
        $calc = PrecioPos::calcular($base, $p, $promos);

        $p['stock_pos'] = $stock;
        $p['stock_sucursal'] = $stock;
        $p['precio_base'] = $calc['precio_base'];
        $p['precio_pos'] = $calc['precio_unitario'];
        $p['descuento_pos'] = $calc['descuento_unitario'];
        $p['descuento_pct_pos'] = $calc['descuento_porcentaje'];
        $p['promocion'] = $calc['promocion'];
        $p['promocion_etiqueta'] = $calc['promocion_etiqueta'];
        $p['impuesto_pos'] = $calc['impuesto_porcentaje'];
        $p['impuesto_unitario'] = $calc['impuesto_unitario'];
    }
    unset($p);

    $categorias = [];
    foreach ($productos as $p) {
        $idc = (int) $p['id_categoria'];
        if (!isset($categorias[$idc])) {
            $categorias[$idc] = [
                'id'     => $idc,
                'nombre' => (string) ($p['categoria'] ?: 'Sin categoría'),
                'icono'  => (string) ($p['categoria_icono'] ?: 'fa-utensils'),
            ];
        }
    }
    $categorias = array_values($categorias);
    usort($categorias, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

    $responder(200, true, 'ok', [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $esSuperAdmin,
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal'   => $idSucursal,
        'sucursales'    => $sucursales,
        'mi_caja'       => $miCaja,
        'arqueo_abierto'=> $arqueo,
        'mesas'         => $pedidoModel->mesasConPedido($idSucursal),
        'online'        => $pedidoModel->listarOnline($idSucursal),
        'cocina'        => $pedidoModel->colaCocina($idSucursal),
        'por_cobrar'    => $pedidoModel->mesasPorCobrar($idSucursal),
        'productos'     => $productos,
        'categorias'    => $categorias,
    ]);
} catch (PDOException $e) {
    error_log('obtener_pedidos.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_pedidos.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}