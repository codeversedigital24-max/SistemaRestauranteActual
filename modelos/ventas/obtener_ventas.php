<?php
/**
 * =====================================================
 * GET  modelos/pos/obtener_ventas.php
 * -----------------------------------------------------
 * Historial de ventas del POS. Devuelve en una sola
 * petición: ventas, resumen, sucursales, vendedores.
 *
 * Filtros: id_sucursal, id_usuario, desde, hasta,
 *          metodo_pago, estado, buscar
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
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

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $responder(401, false, 'No autorizado');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $responder(405, false, 'Método no permitido');
}

// Privilegio del ítem: ver el historial de ventas
rm_exigir_api('ver', Permisos::ITEM_VENTAS_HISTORIAL);

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');

    // Acceso global: rol SuperAdministrador (modelo de privilegios)
    $esSuperAdmin = rm_es_superadmin();

    // Sucursal: SuperAdmin puede filtrar; el resto ve sólo la propia
    $idSucursal = $esSuperAdmin
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    // Usuario normal SIN sucursal asignada: no se devuelve ninguna venta.
    if (!$esSuperAdmin && (int) $idSucursal <= 0) {
        $responder(403, false, 'Tu usuario no tiene sucursal asignada');
    }

    $idUsuario = (int) ($_GET['id_usuario'] ?? 0);
    $desde     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : '';
    $hasta     = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : '';
    $metodo    = (string) ($_GET['metodo_pago'] ?? '');
    $estado    = (string) ($_GET['estado'] ?? '');
    $buscar    = trim(strip_tags((string) ($_GET['buscar'] ?? '')));

    if (!in_array($metodo, ['', 'efectivo', 'qr', 'tarjeta', 'mixto', 'otro'], true)) $metodo = '';
    if (!in_array($estado, ['', 'registrada', 'anulada'], true)) $estado = '';
    if (mb_strlen($buscar) > 80) $buscar = mb_substr($buscar, 0, 80);

    $where = [];
    $params = [];

    if ($idSucursal !== null && $idSucursal > 0) {
        $where[] = 'v.id_sucursal = :sucursal';
        $params[':sucursal'] = $idSucursal;
    }
    if ($idUsuario > 0) {
        $where[] = 'v.id_usuario = :usuario';
        $params[':usuario'] = $idUsuario;
    }
    if ($desde !== '') {
        $where[] = 'v.fecha_venta >= :desde';
        $params[':desde'] = $desde . ' 00:00:00';
    }
    if ($hasta !== '') {
        $where[] = 'v.fecha_venta <= :hasta';
        $params[':hasta'] = $hasta . ' 23:59:59';
    }
    if ($metodo !== '') {
        $where[] = 'v.metodo_pago = :metodo';
        $params[':metodo'] = $metodo;
    }
    if ($estado !== '') {
        $where[] = 'v.estado = :estado';
        $params[':estado'] = $estado;
    }
    if ($buscar !== '') {
        $where[] = '(v.codigo LIKE :buscar OR u.username LIKE :buscar)';
        $params[':buscar'] = '%' . $buscar . '%';
    }

    $sql = "SELECT v.id_venta,
                   v.codigo,
                   v.id_sucursal,
                   v.id_arqueo,
                   v.id_usuario,
                   v.fecha_venta,
                   v.subtotal,
                   v.descuento,
                   v.impuesto,
                   v.total,
                   v.metodo_pago,
                   v.recibido,
                   v.cambio,
                   v.estado,
                   s.nombre_sucursal,
                   s.ciudad,
                   u.username,
                   TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                   (SELECT COUNT(*) FROM venta_detalle d WHERE d.id_venta = v.id_venta) AS items
              FROM ventas v
              INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
              INNER JOIN usuarios u   ON u.id_usuario  = v.id_usuario
              LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

    if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY v.fecha_venta DESC, v.id_venta DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ventas = array_map(function (array $f) {
        $nombre = trim((string) $f['usuario_nombre']);
        return [
            'id'              => (int) $f['id_venta'],
            'codigo'          => (string) $f['codigo'],
            'id_sucursal'     => (int) $f['id_sucursal'],
            'id_arqueo'       => (int) $f['id_arqueo'],
            'id_usuario'      => (int) $f['id_usuario'],
            'sucursal'        => (string) $f['nombre_sucursal'],
            'ciudad'          => (string) ($f['ciudad'] ?? ''),
            'usuario'         => $nombre !== '' ? $nombre : (string) $f['username'],
            'username'        => (string) $f['username'],
            'fecha_venta'     => date('d/m/Y H:i', strtotime($f['fecha_venta'])),
            'fecha_iso'       => (string) $f['fecha_venta'],
            'subtotal'        => (float) $f['subtotal'],
            'descuento'       => (float) $f['descuento'],
            'impuesto'        => (float) $f['impuesto'],
            'total'           => (float) $f['total'],
            'metodo_pago'     => (string) $f['metodo_pago'],
            'recibido'        => (float) $f['recibido'],
            'cambio'          => (float) $f['cambio'],
            'estado'          => (string) $f['estado'],
            'items'           => (int) $f['items'],
        ];
    }, $filas);

    // Resumen rápido del conjunto filtrado
    $resumen = [
        'ventas'    => count($ventas),
        'total'     => 0.0,
        'efectivo'  => 0.0,
        'qr'        => 0.0,
        'tarjeta'   => 0.0,
        'mixto'     => 0.0,
        'otros'     => 0.0,
        'anuladas'  => 0,
    ];

    foreach ($ventas as $v) {
        if ($v['estado'] !== 'registrada') { $resumen['anuladas']++; continue; }
        $resumen['total'] += $v['total'];
        if ($v['metodo_pago'] === 'efectivo')  $resumen['efectivo'] += $v['total'];
        elseif ($v['metodo_pago'] === 'qr')    $resumen['qr'] += $v['total'];
        elseif ($v['metodo_pago'] === 'tarjeta') $resumen['tarjeta'] += $v['total'];
        elseif ($v['metodo_pago'] === 'mixto') $resumen['mixto'] += $v['total'];
        else $resumen['otros'] += $v['total'];
    }

    // Catálogos para los selectores
    $sucursales = $esSuperAdmin
        ? $pdo->query("SELECT id_sucursal, nombre_sucursal, ciudad FROM sucursales WHERE estado = 1 ORDER BY nombre_sucursal")->fetchAll(PDO::FETCH_ASSOC)
        : (function () use ($pdo, $usuario) {
            $s = $pdo->prepare("SELECT id_sucursal, nombre_sucursal, ciudad FROM sucursales WHERE id_sucursal = :id");
            $s->execute([':id' => $usuario['id_sucursal']]);
            return $s->fetchAll(PDO::FETCH_ASSOC);
        })();

    // Vendedores: todos los usuarios que alguna vez vendieron en la sucursal visible
    $sqlV = "SELECT DISTINCT u.id_usuario, u.username,
                    TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre
               FROM ventas v
               INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
               LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

    $paramsV = [];
    if ($idSucursal !== null && $idSucursal > 0) {
        $sqlV .= " WHERE v.id_sucursal = :sucursal";
        $paramsV[':sucursal'] = $idSucursal;
    }
    $sqlV .= " ORDER BY nombre ASC, u.username ASC";

    $stV = $pdo->prepare($sqlV);
    $stV->execute($paramsV);
    $vendedores = array_map(function ($f) {
        $n = trim((string) $f['nombre']);
        return [
            'id'       => (int) $f['id_usuario'],
            'username' => $f['username'],
            'nombre'   => $n !== '' ? $n : $f['username'],
        ];
    }, $stV->fetchAll(PDO::FETCH_ASSOC));

    $responder(200, true, 'ok', [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $esSuperAdmin,
            'id_sucursal'   => $usuario['id_sucursal'],
        ],
        'id_sucursal' => $idSucursal,
        'sucursales'  => $sucursales,
        'vendedores'  => $vendedores,
        'ventas'      => $ventas,
        'resumen'     => $resumen,
    ]);
} catch (PDOException $e) {
    error_log('obtener_ventas.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_ventas.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}