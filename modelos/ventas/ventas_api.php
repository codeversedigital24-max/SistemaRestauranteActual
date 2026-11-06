<?php
/**
 * =====================================================
 * POST  modelos/ventas/ventas_api.php
 * -----------------------------------------------------
 * Acciones del historial de ventas:
 *   venta_detalle   → venta con líneas
 *   anular_venta    → anula y devuelve stock
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
        ['success'=>$ok,'message'=>$m,'data'=>$d,'timestamp'=>date('c')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) $resp(401, false, 'No autorizado');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); $resp(405, false, 'Método no permitido'); }
if (!rm_csrf_valido($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $resp(403, false, 'Token inválido');

$entrada = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($entrada)) $resp(400, false, 'Datos malformados');

$accion = (string) ($entrada['accion'] ?? '');
$datos  = (array) ($entrada['datos'] ?? []);

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/VentaListado.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $resp(401, false, 'La sesión ya no es válida');

    // Acceso global: rol SuperAdministrador (modelo de privilegios)
    $esSuperAdmin = rm_es_superadmin();

    $lista = new VentaListado($pdo);

    switch ($accion) {
        case 'venta_detalle':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_HISTORIAL);

            $id = (int) ($datos['id'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Venta no válida');

            $cab = $pdo->prepare(
                "SELECT v.*, s.nombre_sucursal, s.ciudad,
                        u.username,
                        TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre
                   FROM ventas v
                   INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                   INNER JOIN usuarios u   ON u.id_usuario  = v.id_usuario
                   LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  WHERE v.id_venta = :id
                  LIMIT 1"
            );
            $cab->execute([':id' => $id]);
            $fila = $cab->fetch(PDO::FETCH_ASSOC);

            if (!$fila) $resp(404, false, 'Venta no encontrada');

            if (!$esSuperAdmin && (int) $fila['id_sucursal'] !== (int) $usuario['id_sucursal']) {
                $resp(403, false, 'No puedes ver ventas de otra sucursal');
            }

            $nombre = trim((string) $fila['usuario_nombre']);

            $resp(200, true, 'ok', [
                'venta' => [
                    'id'                  => (int) $fila['id_venta'],
                    'codigo'              => (string) $fila['codigo'],
                    'id_sucursal'         => (int) $fila['id_sucursal'],
                    'id_arqueo'           => (int) $fila['id_arqueo'],
                    'sucursal'            => (string) $fila['nombre_sucursal'],
                    'ciudad'              => (string) ($fila['ciudad'] ?? ''),
                    'usuario'             => $nombre !== '' ? $nombre : (string) $fila['username'],
                    'username'            => (string) $fila['username'],
                    'fecha_venta'         => date('d/m/Y H:i', strtotime($fila['fecha_venta'])),
                    'subtotal'            => (float) $fila['subtotal'],
                    'descuento'           => (float) $fila['descuento'],
                    'impuesto_porcentaje' => (float) $fila['impuesto_porcentaje'],
                    'impuesto'            => (float) $fila['impuesto'],
                    'total'               => (float) $fila['total'],
                    'metodo_pago'         => (string) $fila['metodo_pago'],
                    'recibido'            => (float) $fila['recibido'],
                    'cambio'              => (float) $fila['cambio'],
                    'estado'              => (string) $fila['estado'],
                    'observaciones'       => (string) ($fila['observaciones'] ?? ''),
                    'detalle'             => $lista->detalle($id),
                ],
            ]);
            break;

        case 'anular_venta':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_HISTORIAL);

            $id = (int) ($datos['id'] ?? 0);
            if ($id <= 0) $resp(422, false, 'Venta no válida');

            // Una sola ruta de anulación: Venta::anular se encarga del
            // stock, del estado y de recalcular los totales del arqueo.
            require_once __DIR__ . '/../pos/Venta.php';

            $ventaModel = new Venta($pdo);
            $r = $ventaModel->anular($id, $usuario);

            if (!$r['exito']) {
                $resp(422, false, $r['mensaje']);
            }

            $resp(200, true, $r['mensaje'], ['id' => $id]);
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    error_log('ventas_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('ventas_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}