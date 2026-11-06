<?php
/**
 * =====================================================
 * GET  modelos/pos/obtener_pos.php
 * -----------------------------------------------------
 * Bootstrap del Punto de venta:
 *   · usuario, sucursales y caja / arqueo abierto
 *   · catálogo con precio de sucursal, oferta, promoción
 *     vigente e IVA ya calculados
 *   · categorías para los filtros
 *   · datos de la empresa para el ticket
 *
 * Query: id_sucursal (sólo SuperAdmin)
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

// Privilegio del ítem: ver el punto de venta
rm_exigir_api('ver', Permisos::ITEM_VENTAS_POS);

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/../cajas/ArqueoCaja.php';
    require_once __DIR__ . '/../productos/Producto.php';
    require_once __DIR__ . '/Promocion.php';
    require_once __DIR__ . '/PrecioPos.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) {
        $responder(401, false, 'La sesión ya no es válida');
    }

    // Acceso global: rol SuperAdministrador (modelo de privilegios)
    $esSuperAdmin = rm_es_superadmin();

    $idSucursal = $esSuperAdmin
        ? (int) ($_GET['id_sucursal'] ?? 0)
        : (int) $usuario['id_sucursal'];

    if ($idSucursal <= 0 && $esSuperAdmin) {
        $primera = $pdo->query(
            "SELECT id_sucursal FROM sucursales WHERE estado = 1 ORDER BY id_sucursal LIMIT 1"
        )->fetchColumn();
        $idSucursal = (int) ($primera ?: 0);
    }

    if ($idSucursal <= 0) {
        $responder(422, false, 'Selecciona una sucursal');
    }

    $sucursales = $esSuperAdmin
        ? ContextoCompras::sucursales($pdo)
        : (($s = ContextoCompras::sucursalActiva($pdo, $usuario['id_sucursal'])) ? [$s] : []);

    // ---- Caja + arqueo abierto del usuario ----
    $caja = new ArqueoCaja($pdo);
    $miCaja = $caja->cajaDeUsuario((int) $usuario['id']);
    $arqueoAbierto = $miCaja ? $caja->abiertoDeCaja((int) $miCaja['id']) : null;

    // ---- Catálogo ----
    $productos = (new Producto($pdo))->listar();
    $productos = array_values(array_filter(
        $productos,
        fn($p) => (int) $p['estado'] === 1 && (int) $p['disponible'] === 1
    ));

    $stmt = $pdo->prepare(
        "SELECT id_producto, stock, precio_venta
           FROM productos_sucursal
          WHERE id_sucursal = :s"
    );
    $stmt->execute([':s' => $idSucursal]);

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[(int) $r['id_producto']] = $r;
    }

    $promociones = (new Promocion($pdo))->vigentes();

    foreach ($productos as &$p) {
        $ps = $map[$p['id']] ?? null;

        // El POS opera con el stock global del producto (productos.stock);
        // el precio de venta sigue saliendo de la sucursal / promociones.
        $stock = (float) $p['stock'];
        $base = ($ps && (float) $ps['precio_venta'] > 0)
            ? (float) $ps['precio_venta']
            : (float) $p['precio'];

        $calc = PrecioPos::calcular($base, $p, $promociones);

        $p['stock_sucursal']    = $stock;   // compatibilidad con el POS anterior
        $p['stock_pos']         = $stock;   // stock global de productos
        $p['precio_base']       = $calc['precio_base'];
        $p['precio_sucursal']   = $calc['precio_unitario'];
        $p['precio_pos']        = $calc['precio_unitario'];
        $p['descuento_pos']     = $calc['descuento_unitario'];
        $p['descuento_pct_pos'] = $calc['descuento_porcentaje'];
        $p['oferta_pos']        = $calc['oferta_aplicada'];
        $p['promocion_id']      = $calc['promocion_id'];
        $p['promocion']         = $calc['promocion'];
        $p['promocion_etiqueta']= $calc['promocion_etiqueta'];
        $p['impuesto_pos']      = $calc['impuesto_porcentaje'];
        $p['impuesto_unitario'] = $calc['impuesto_unitario'];
        $p['total_unitario']    = $calc['total_unitario'];
    }
    unset($p);

    // ---- Categorías (sólo las que tienen productos visibles) ----
    $categorias = [];
    foreach ($productos as $p) {
        $id = (int) $p['id_categoria'];
        if (!isset($categorias[$id])) {
            $categorias[$id] = [
                'id'     => $id,
                'nombre' => (string) ($p['categoria'] ?: 'Sin categoría'),
                'icono'  => (string) ($p['categoria_icono'] ?: 'fa-utensils'),
            ];
        }
    }
    $categorias = array_values($categorias);
    usort($categorias, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));

    // ---- Empresa (para el ticket) ----
    $empresa = $pdo->query(
        "SELECT razon_social, nombre_comercial, nit, direccion, telefono1, email_principal,
                ciudad, departamento, moneda_principal, logo
           FROM configuracion_empresa
          ORDER BY id_config ASC
          LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $responder(200, true, 'ok', [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $esSuperAdmin,
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal'    => $idSucursal,
        'sucursales'     => $sucursales,
        'mi_caja'        => $miCaja,
        'arqueo_abierto' => $arqueoAbierto,
        'empresa'        => [
            'razon_social'     => (string) ($empresa['razon_social'] ?? 'Restaurante Moderno'),
            'nombre_comercial' => (string) ($empresa['nombre_comercial'] ?? ''),
            'nit'              => (string) ($empresa['nit'] ?? ''),
            'moneda'           => (string) ($empresa['moneda_principal'] ?? 'BOB') ?: 'BOB',
            'logo'             => (string) ($empresa['logo'] ?? ''),
        ],
        'categorias'     => $categorias,
        'promociones'    => array_map(fn($pr) => [
            'id'       => (int) $pr['id'],
            'nombre'   => (string) $pr['nombre'],
            'alcance'  => (string) $pr['alcance'],
            'objetivo' => (string) $pr['objetivo'],
            'etiqueta' => (string) $pr['etiqueta'],
        ], $promociones),
        'productos'      => $productos,
    ]);
} catch (PDOException $e) {
    error_log('obtener_pos.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_pos.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}
