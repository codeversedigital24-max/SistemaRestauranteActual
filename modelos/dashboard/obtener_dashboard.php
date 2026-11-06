<?php
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

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/Dashboard.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) {
        $responder(401, false, 'La sesión ya no es válida');
    }

    $esSuperAdmin = !empty($usuario['es_superadmin']);
    $rol = (string) ($usuario['rol'] ?? '');

    // Perfil del panel. Cada perfil decide qué bloques se envían.
    $perfil = 'operativo';

    if ($esSuperAdmin) {
        $perfil = 'superadmin';
    } elseif (in_array($rol, ['AdministradorSucursal', 'SupervisorEncargado'], true)) {
        $perfil = 'sucursal';
    } elseif ($rol === 'Cajero') {
        $perfil = 'cajero';
    }

    $soloPropio = $perfil === 'cajero';

    // El SuperAdmin puede elegir sucursal; el resto queda atado a la suya.
    $solicitada = isset($_GET['id_sucursal']) ? (int) $_GET['id_sucursal'] : null;
    $idSucursal = ContextoCompras::sucursalConsulta($usuario, $solicitada);

    $verVentas      = in_array($perfil, ['superadmin', 'sucursal', 'cajero'], true);
    $verUtilidades  = $esSuperAdmin;
    $verCompras     = $esSuperAdmin;
    $verStock       = in_array($perfil, ['superadmin', 'sucursal'], true);
    $verVendedores  = in_array($perfil, ['superadmin', 'sucursal'], true);
    $verCajas       = in_array($perfil, ['superadmin', 'sucursal', 'cajero'], true);
    $verComparativa = $esSuperAdmin && ($idSucursal === null || $idSucursal <= 0);

    $modelo = new Dashboard(
        $pdo,
        (int) ($idSucursal ?? 0),
        $esSuperAdmin,
        (int) $usuario['id'],
        $soloPropio
    );

    $permisos = [
        'ventas'        => $verVentas,
        'utilidades'    => $verUtilidades,
        'compras'       => $verCompras,
        'stock'         => $verStock,
        'vendedores'    => $verVendedores,
        'cajas'         => $verCajas,
        'comparativa'   => $verComparativa,
        'historial'     => rm_puede('ver', Permisos::ITEM_VENTAS_HISTORIAL),
        'pedidos'       => rm_puede('ver', Permisos::ITEM_VENTAS_PEDIDOS),
        'productos'     => rm_puede('ver', Permisos::ITEM_INV_PRODUCTOS),
        'arqueo'        => rm_puede('ver', Permisos::ITEM_VENTAS_ARQUEO),
        'cajas_modulo'  => rm_puede('ver', Permisos::ITEM_VENTAS_CAJAS),
        'reportes'      => rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS),
        'pos'           => rm_puede('ver', Permisos::ITEM_VENTAS_POS),
    ];

    $payload = [
        'usuario' => [
            'id'            => $usuario['id'],
            'nombre'        => $usuario['nombre'],
            'es_superadmin' => $esSuperAdmin,
            'rol'           => $usuario['rol'],
            'perfil'        => $perfil,
            'solo_propio'   => $soloPropio,
            'id_sucursal'   => $usuario['id_sucursal'],
            'sucursal'      => $usuario['sucursal'],
        ],
        'perfil'       => $perfil,
        'permite'      => $permisos,
        'filtro'       => [
            'id_sucursal' => $idSucursal,
            'todas'       => $esSuperAdmin && ($idSucursal === null || $idSucursal <= 0),
        ],
        'sucursales'   => $esSuperAdmin ? ContextoCompras::sucursales($pdo) : [],
        'mesas'        => $modelo->mesasEstado(),
        'pedidos'      => $modelo->pedidosEstado(),
        'pedidos_activos' => $modelo->pedidosActivos(8),
    ];

    if ($verVentas) {
        $ventas = $modelo->ventasResumen();

        if ($soloPropio) {
            // El cajero sólo recibe su día: nada de semana/mes ni comparativas.
            $ventas = [
                'hoy_cant'        => $ventas['hoy_cant'],
                'hoy_total'       => $ventas['hoy_total'],
                'ticket_prom'     => $ventas['ticket_hoy_prom'],
                'ticket_hoy_prom' => $ventas['ticket_hoy_prom'],
            ];
        }

        $payload['ventas'] = $ventas;
        $payload['por_metodo'] = $modelo->metodosPagoMes($soloPropio);
        $payload['top_productos'] = $modelo->topProductos(5, $soloPropio);
        $payload['recientes'] = $modelo->ventasRecientes(8, $soloPropio);

        if ($soloPropio) {
            // El cajero sólo ve su día: gráfico por hora, no histórico.
            $payload['por_hora'] = $modelo->ventasPorHoraHoy();
        } else {
            $payload['por_dia'] = $modelo->ventasUltimos7Dias();
        }
    }

    if ($verUtilidades) {
        $payload['utilidades'] = $modelo->utilidadesResumen();
    }

    if ($verCompras) {
        $payload['compras'] = $modelo->comprasResumen();
    }

    if ($verStock) {
        $payload['stock_bajo'] = $modelo->stockBajo(6);
    }

    if ($verVendedores) {
        $payload['top_vendedores'] = $modelo->topVendedores(5);
    }

    if ($verCajas) {
        if ($perfil === 'cajero') {
            $payload['mi_caja'] = $modelo->miCajaAbierta((int) $usuario['id']);
        } else {
            $payload['arqueos_abiertos'] = $modelo->arqueosAbiertos();
        }
    }

    if ($verComparativa) {
        $payload['ventas_por_sucursal'] = $modelo->ventasPorSucursalMes();
    }

    $responder(200, true, 'ok', $payload);
} catch (PDOException $e) {
    error_log('obtener_dashboard.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_dashboard.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}
