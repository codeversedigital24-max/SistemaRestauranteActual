<?php
/**
 * =====================================================
 * POST  modelos/cajas/cajas_api.php
 * -----------------------------------------------------
 * Acciones del módulo de cajas:
 *   obtener | crear | actualizar | eliminar
 *   asignar | liberar | usuarios | historial
 *
 * Respuesta: {success, message, data, timestamp}
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

// -------------------------------------------------
// 1. Sesión
// -------------------------------------------------
if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $respuesta(401, false, 'No autorizado');
}

// -------------------------------------------------
// 2. Método
// -------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respuesta(405, false, 'Método no permitido');
}

// -------------------------------------------------
// 3. CSRF
// -------------------------------------------------
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!rm_csrf_valido($csrf)) {
    $respuesta(403, false, 'Token de seguridad inválido. Recarga la página.');
}

// -------------------------------------------------
// 4. Cuerpo JSON
// -------------------------------------------------
$entrada = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($entrada)) {
    $respuesta(400, false, 'Datos malformados');
}

$accion = (string) ($entrada['accion'] ?? '');
$datos  = (array) ($entrada['datos'] ?? []);

// -------------------------------------------------
// 5. Acciones
// -------------------------------------------------
try {
    $pdo = Conexion::getInstance()->getConnection();

    // La autorización se exige por acción con rm_exigir_api() en cada case.

    require_once __DIR__ . '/Caja.php';
    require_once __DIR__ . '/../compras/ContextoCompras.php';

    // Alcance por sucursal/caja del operador.
    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $respuesta(401, false, 'La sesión ya no es válida');
    }

    $esSuperAdmin   = $usuario['es_superadmin'];
    $sucursalPropia = (int) $usuario['id_sucursal'];

    $modelo = new Caja($pdo);

    // Cajero sin privilegio `modificar` en cajas ni arqueos: sólo SU caja asignada.
    $esCajeroLimitado = !$esSuperAdmin
        && !rm_puede('modificar', Permisos::ITEM_VENTAS_CAJAS)
        && !rm_puede('modificar', Permisos::ITEM_VENTAS_ARQUEO);

    $cajaPropia = $esCajeroLimitado
        ? $modelo->cajaDeUsuario((int) $usuario['id'])
        : null;

    /** Devuelve la caja si el operador puede verla; si no, responde 403. */
    $cajaDelAlcance = static function (int $idCaja) use ($modelo, $esSuperAdmin, $sucursalPropia, $respuesta): array {
        $caja = $modelo->obtener($idCaja);

        if (!$caja) {
            $respuesta(404, false, 'Caja no encontrada');
        }

        if (!$esSuperAdmin && ($sucursalPropia <= 0 || (int) $caja['id_sucursal'] !== $sucursalPropia)) {
            $respuesta(403, false, 'No puedes acceder a cajas de otra sucursal');
        }

        return $caja;
    };

    /** Como cajaDelAlcance, pero además un cajero sólo ve su caja asignada. */
    $cajaVisible = static function (int $idCaja) use ($cajaDelAlcance, $esCajeroLimitado, $cajaPropia, $respuesta): array {
        $caja = $cajaDelAlcance($idCaja);

        if ($esCajeroLimitado && (int) ($cajaPropia['id_caja'] ?? 0) !== $idCaja) {
            $respuesta(403, false, 'No puedes ver cajas que no tienes asignadas');
        }

        return $caja;
    };

    $r = ['exito' => false, 'mensaje' => 'Acción no ejecutada', 'id' => 0];

    switch ($accion) {
        // Detalle para precargar el modal
        case 'obtener':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_CAJAS);

            $caja = $cajaVisible((int) ($datos['id'] ?? 0));

            $respuesta(200, true, 'ok', ['caja' => $caja]);

            break; // inalcanzable

        // Alta
        case 'crear':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_CAJAS);

            if (!$esSuperAdmin) {
                if ($sucursalPropia <= 0) {
                    $respuesta(403, false, 'Tu usuario no tiene sucursal asignada');
                }

                $datos['id_sucursal'] = $sucursalPropia;
            }

            $r = $modelo->crear($datos);
            break;

        // Edición
        case 'actualizar':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_CAJAS);

            $cajaDelAlcance((int) ($datos['id'] ?? 0));

            if (!$esSuperAdmin) {
                $datos['id_sucursal'] = $sucursalPropia;
            }

            $r = $modelo->actualizar($datos);
            break;

        // Baja lógica
        case 'eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_CAJAS);

            $cajaDelAlcance((int) ($datos['id'] ?? 0));

            $r = $modelo->eliminar((int) ($datos['id'] ?? 0));
            break;

        // Asignar responsable
        case 'asignar':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_CAJAS);

            $cajaDelAlcance((int) ($datos['id'] ?? 0));

            $r = $modelo->asignar(
                (int) ($datos['id'] ?? 0),
                (int) ($datos['id_usuario'] ?? 0),
                trim((string) ($datos['observacion'] ?? '')),
                (int) $_SESSION['id_usuario']
            );
            break;

        // Quitar responsable
        case 'liberar':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_CAJAS);

            $cajaDelAlcance((int) ($datos['id'] ?? 0));

            $r = $modelo->liberar((int) ($datos['id'] ?? 0));
            break;

        // Personal candidato de la sucursal
        case 'usuarios':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_CAJAS);

            $idSucursalUsuarios = $esSuperAdmin
                ? (int) ($datos['id_sucursal'] ?? 0)
                : $sucursalPropia;

            $usuarios = $modelo->usuariosDisponibles(
                $idSucursalUsuarios,
                (int) ($datos['id_caja'] ?? 0),
                $esSuperAdmin ? null : $sucursalPropia
            );

            $respuesta(200, true, 'ok', ['usuarios' => $usuarios]);

            break; // inalcanzable

        // Historial de asignaciones
        case 'historial':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_CAJAS);

            $idCajaHistorial = (int) ($datos['id'] ?? 0);

            $cajaVisible($idCajaHistorial);

            $historial = $modelo->historial($idCajaHistorial, $esSuperAdmin ? null : $sucursalPropia);

            $respuesta(200, true, 'ok', ['historial' => $historial]);

            break; // inalcanzable

        default:
            $respuesta(400, false, 'Acción desconocida');
    }

    if ($r['exito']) {
        $respuesta(200, true, $r['mensaje'], ['id' => $r['id']]);
    }

    $respuesta(422, false, $r['mensaje']);
} catch (PDOException $e) {
    error_log('cajas_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('cajas_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}
