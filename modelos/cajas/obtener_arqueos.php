<?php
/**
 * =====================================================
 * GET  modelos/cajas/obtener_arqueos.php
 * -----------------------------------------------------
 * Devuelve, en una sola petición:
 *   · mi_caja          → caja asignada al usuario
 *   · arqueo_abierto   → arqueo abierto de esa caja
 *   · arqueos          → historial de SU sucursal
 *   · resumen          → contadores
 *   · sucursales       → filtro (sólo SuperAdmin)
 *
 * Un usuario normal sólo ve los arqueos de su sucursal.
 * El SuperAdmin puede ver todas y filtrar.
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');

rm_iniciar_sesion();

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'data' => []]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método no permitido', 'data' => []]);
    exit;
}

// Privilegio del ítem: consultar arqueos
rm_exigir_api('ver', Permisos::ITEM_VENTAS_ARQUEO);

require_once __DIR__ . '/ArqueoCaja.php';

try {
    $pdo = Conexion::getInstance()->getConnection();
    $idUsuario = (int) $_SESSION['id_usuario'];

    // Rol y sucursal del usuario en sesión
    $st = $pdo->prepare(
        "SELECT u.id_sucursal, r.nombre_rol, s.nombre_sucursal
         FROM usuarios u
         INNER JOIN roles r      ON r.id_rol = u.id_rol
         LEFT  JOIN sucursales s ON s.id_sucursal = u.id_sucursal
         WHERE u.id_usuario = :id AND u.estado = 1
         LIMIT 1"
    );
    $st->execute([':id' => $idUsuario]);
    $usuario = $st->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Usuario no válido', 'data' => []]);
        exit;
    }

    $esSuperAdmin = rm_es_superadmin();
    $sucursalUsuario = (int) ($usuario['id_sucursal'] ?? 0);

    // Sucursal que se consulta: el SuperAdmin puede cambiarla, el resto sólo la suya.
    $idSucursal = null;

    if ($esSuperAdmin) {
        if (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '') {
            $idSucursal = (int) $_GET['id_sucursal'];
        }
    } else {
        $idSucursal = $sucursalUsuario;
    }

    if (!$esSuperAdmin && $idSucursal <= 0) {
        echo json_encode(
            [
                'success'    => true,
                'message'    => 'Tu usuario no tiene sucursal asignada',
                'data'       => [],
                'mi_caja'    => null,
                'arqueo_abierto' => null,
                'arqueos'    => [],
                'resumen'    => ['abiertos' => 0, 'cerrados' => 0, 'hoy' => 0, 'monto_en_cajas' => 0],
                'movimientos' => [],
                'resumen_cierre' => null,
                'permisos' => [
                    'arqueo' => rm_acciones(Permisos::ITEM_VENTAS_ARQUEO),
                    'cajas'  => rm_acciones(Permisos::ITEM_VENTAS_CAJAS),
                ],
                'puede_ver_sucursal' => false,
                'es_superadmin'      => false,
                'sucursales' => [],
                'alcance'    => 'sucursal',
                'timestamp'  => date('c'),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    $estado = (string) ($_GET['estado'] ?? '');
    if ($estado !== 'abierto' && $estado !== 'cerrado') {
        $estado = '';
    }

    $desde = (string) ($_GET['desde'] ?? '');
    $hasta = (string) ($_GET['hasta'] ?? '');

    if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $desde = '';
    }

    if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $hasta = '';
    }

    $modelo = new ArqueoCaja($pdo);

    $miCaja = $modelo->cajaDeUsuario($idUsuario);
    $abierto = $miCaja ? $modelo->abiertoDeCaja((int) $miCaja['id']) : null;

    // Listado con alcance por privilegios: SuperAdmin todo, usuario
    // normal su sucursal y cajero sin privilegio de modificar su caja.
    $arqueos = $modelo->listar($idSucursal, $estado, $desde, $hasta, 300, $idUsuario);
    $resumen = $modelo->resumen($idSucursal, $idUsuario);

    // Egresos / gastos y totales del turno abierto.
    $movimientos = $abierto ? $modelo->movimientosDeArqueo((int) $abierto['id']) : [];
    $resumenCierre = $abierto ? $modelo->resumenCierre((int) $abierto['id']) : null;

    // Privilegios para mostrar / ocultar acciones en la UI.
    $accionesArqueo = rm_acciones(Permisos::ITEM_VENTAS_ARQUEO);
    $accionesCajas = rm_acciones(Permisos::ITEM_VENTAS_CAJAS);

    $sucursalesFiltro = [];

    if ($esSuperAdmin) {
        $sucursalesFiltro = $pdo
            ->query("SELECT id_sucursal, nombre_sucursal, ciudad
                     FROM sucursales
                     WHERE estado = 1
                     ORDER BY nombre_sucursal")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(
        [
            'success'        => true,
            'message'        => 'ok',
            'data'           => $arqueos,
            'mi_caja'        => $miCaja,
            'arqueo_abierto' => $abierto,
            'arqueos'        => $arqueos,
            'resumen'        => $resumen,
            'movimientos'    => $movimientos,
            'resumen_cierre' => $resumenCierre,
            'permisos'       => [
                'arqueo' => $accionesArqueo,
                'cajas'  => $accionesCajas,
            ],
            'puede_ver_sucursal' => $esSuperAdmin,
            'es_superadmin'      => $esSuperAdmin,
            'sucursales'     => $sucursalesFiltro,
            'alcance'        => $esSuperAdmin ? 'global' : 'sucursal',
            'sucursal'       => [
                'id'     => $idSucursal,
                'nombre' => (string) ($usuario['nombre_sucursal'] ?? ''),
            ],
            'usuario'        => [
                'id'            => $idUsuario,
                'es_superadmin' => $esSuperAdmin,
                'id_sucursal'   => $sucursalUsuario,
            ],
            'timestamp'      => date('c'),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    error_log('obtener_arqueos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al leer los arqueos', 'data' => []]);
}
