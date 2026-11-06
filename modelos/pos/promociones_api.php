<?php
/**
 * =====================================================
 * API · PROMOCIONES  ·  modelos/pos/promociones_api.php
 * -----------------------------------------------------
 * GET  ?accion=listar             → listado + catálogos
 * POST {accion: crear}            → nueva promoción
 * POST {accion: actualizar, ...}  → modificar promoción
 * POST {accion: cambiar_estado}   → activar / desactivar
 * POST {accion: eliminar}         → borrar promoción
 *
 * Privilegios (ítem 19 · Promociones):
 *   ver · registrar · modificar · eliminar
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
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

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    require_once __DIR__ . '/Promocion.php';

    $pdo = Conexion::getInstance()->getConnection();
    $modelo = new Promocion($pdo);

    // -------------------------------------------------
    // LECTURA (GET)
    // -------------------------------------------------
    if ($metodo === 'GET') {
        rm_exigir_api('ver', Permisos::ITEM_VENTAS_PROMOCIONES);

        $accion = (string) ($_GET['accion'] ?? 'listar');

        if ($accion === 'obtener') {
            $id = (int) ($_GET['id'] ?? 0);
            $promo = $modelo->obtener($id);

            if (!$promo) {
                $responder(404, false, 'Promoción no encontrada');
            }

            $responder(200, true, 'ok', ['promocion' => $promo]);
        }

        $promociones = $modelo->listar();

        $resumen = [
            'total'       => count($promociones),
            'activas'     => 0,
            'inactivas'   => 0,
            'vigentes'    => 0,
            'programadas' => 0,
            'vencidas'    => 0,
        ];

        foreach ($promociones as $p) {
            $situacion = (string) ($p['situacion'] ?? 'inactiva');

            if ($situacion === 'inactiva') {
                $resumen['inactivas']++;
                continue;
            }

            $resumen['activas']++;

            if (isset($resumen[$situacion])) {
                $resumen[$situacion]++;
            }
        }

        $responder(200, true, 'ok', [
            'promociones' => $promociones,
            'productos'   => $modelo->productos(),
            'categorias'  => $modelo->categorias(),
            'resumen'     => $resumen,
        ]);
    }

    // -------------------------------------------------
    // ESCRITURA (POST)
    // -------------------------------------------------
    if ($metodo !== 'POST') {
        header('Allow: GET, POST');
        $responder(405, false, 'Método no permitido');
    }

    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!rm_csrf_valido($csrf)) {
        $responder(403, false, 'Token de seguridad inválido. Recarga la página.');
    }

    $entrada = json_decode(file_get_contents('php://input') ?: '', true);

    if (!is_array($entrada)) {
        $responder(400, false, 'Datos malformados');
    }

    $accion = (string) ($entrada['accion'] ?? '');
    $datos  = (array) ($entrada['datos'] ?? []);

    switch ($accion) {
        case 'crear':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_PROMOCIONES);
            $r = $modelo->crear($datos);
            break;

        case 'actualizar':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_PROMOCIONES);
            $id = (int) ($datos['id'] ?? $datos['id_promocion'] ?? 0);

            if ($id <= 0) {
                $responder(422, false, 'Promoción no válida');
            }

            $r = $modelo->actualizar($id, $datos);
            break;

        case 'cambiar_estado':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_PROMOCIONES);
            $id = (int) ($datos['id'] ?? $datos['id_promocion'] ?? 0);
            $activo = (int) ($datos['activo'] ?? 0) === 1 ? 1 : 0;
            $r = $modelo->cambiarEstado($id, $activo);
            $r['id'] = $id;
            break;

        case 'eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_PROMOCIONES);
            $id = (int) ($datos['id'] ?? $datos['id_promocion'] ?? 0);
            $r = $modelo->eliminar($id);
            $r['id'] = $id;
            break;

        default:
            $responder(400, false, 'Acción desconocida');
    }

    if (!empty($r['exito'])) {
        $responder(200, true, (string) ($r['mensaje'] ?? 'ok'), [
            'id' => (int) ($r['id'] ?? 0),
        ]);
    }

    $responder(422, false, (string) ($r['mensaje'] ?? 'No se pudo completar la operación'));
} catch (PDOException $e) {
    error_log('promociones_api.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('promociones_api.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}
