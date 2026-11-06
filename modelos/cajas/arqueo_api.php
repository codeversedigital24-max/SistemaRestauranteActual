<?php
/**
 * =====================================================
 * POST  modelos/cajas/arqueo_api.php
 * -----------------------------------------------------
 * Acciones del arqueo de caja:
 *
 *   abrir                → apertura de la caja del usuario
 *   cerrar               → cierre con conteo y diferencia
 *   movimiento_registrar → egreso / gasto (arqueo abierto)
 *   movimiento_eliminar  → borra un egreso / gasto
 *   apertura_actualizar  → corrige monto / observación de apertura
 *   arqueo_reabrir       → SuperAdmin vuelve a abrir un arqueo cerrado
 *   arqueo_eliminar      → borra un arqueo (cerrado o SuperAdmin)
 *   resumen_cierre       → totales del cierre por medio de pago
 *   movimientos          → egresos / gastos de un arqueo
 *
 * El privilegio se exige POR ACCIÓN con rm_exigir_api()
 * sobre el ítem Arqueo de caja (9):
 *   ver · registrar · modificar · eliminar
 *
 * Alcance: SuperAdmin ve todo; el resto sólo su sucursal y,
 * si no tiene privilegio de modificar en arqueos ni en
 * cajas, sólo la caja que tiene asignada.
 *
 * La caja NO viaja desde el cliente en abrir/cerrar: el
 * servidor la resuelve por el usuario en sesión.
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
// 5. Acciones (el privilegio se exige en cada case)
// -------------------------------------------------
try {
    $pdo = Conexion::getInstance()->getConnection();
    $idUsuario = (int) $_SESSION['id_usuario'];

    require_once __DIR__ . '/ArqueoCaja.php';

    $modelo = new ArqueoCaja($pdo);

    $contexto = $modelo->contextoUsuario($idUsuario);
    if (!$contexto) {
        $respuesta(403, false, 'Usuario no válido');
    }

    /**
     * Carga un arqueo validando el alcance (sucursal / caja).
     * No devuelve si el arqueo no existe o está fuera de alcance.
     */
    $cargarArqueo = static function (int $idArqueo, bool $exigirCaja = true) use ($modelo, $idUsuario, $respuesta): array {
        if ($idArqueo <= 0) {
            $respuesta(422, false, 'Arqueo no válido');
        }

        $arqueo = $modelo->obtener($idArqueo);

        if (!$arqueo) {
            $respuesta(404, false, 'Ese arqueo no existe');
        }

        $acceso = $modelo->validarAccesoArqueo($idUsuario, $arqueo, $exigirCaja);

        if (!$acceso['permitido']) {
            $respuesta((int) ($acceso['codigo'] ?? 403), false, (string) $acceso['mensaje']);
        }

        return $arqueo;
    };

    switch ($accion) {
        // ---------------------------------------------
        // Apertura de la caja asignada
        // ---------------------------------------------
        case 'abrir':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_ARQUEO);

            $r = $modelo->abrir(
                $idUsuario,
                (float) ($datos['monto'] ?? 0),
                trim((string) ($datos['observacion'] ?? ''))
            );

            if ($r['exito']) {
                $respuesta(201, true, $r['mensaje'], [
                    'id'     => (int) $r['id'],
                    'arqueo' => $r['arqueo'] ?? null,
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        // ---------------------------------------------
        // Cierre del arqueo abierto
        // ---------------------------------------------
        case 'cerrar':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id'] ?? $datos['id_arqueo'] ?? 0);
            $cargarArqueo($idArqueo);

            $r = $modelo->cerrar(
                $idUsuario,
                $idArqueo,
                (float) ($datos['monto'] ?? 0),
                trim((string) ($datos['observacion'] ?? ''))
            );

            if ($r['exito']) {
                $respuesta(200, true, $r['mensaje'], [
                    'id'      => (int) $r['id'],
                    'arqueo'  => $r['arqueo'] ?? null,
                    'resumen' => $modelo->resumenCierre((int) $r['id']),
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        // ---------------------------------------------
        // Egresos / gastos del turno
        // ---------------------------------------------
        case 'movimiento_registrar':
            rm_exigir_api('registrar', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id_arqueo'] ?? $datos['id'] ?? 0);
            $cargarArqueo($idArqueo);

            $r = $modelo->registrarMovimiento($idArqueo, $datos, $idUsuario);

            if ($r['exito']) {
                $respuesta(201, true, $r['mensaje'], [
                    'id'          => (int) $r['id'],
                    'movimiento'  => $r['movimiento'] ?? null,
                    'arqueo'      => $r['arqueo'] ?? null,
                    'movimientos' => $modelo->movimientosDeArqueo($idArqueo),
                    'resumen'     => $modelo->resumenCierre($idArqueo),
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        case 'movimiento_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id_arqueo'] ?? $datos['id'] ?? 0);
            $idMovimiento = (int) ($datos['id_movimiento'] ?? 0);
            $cargarArqueo($idArqueo);

            $r = $modelo->eliminarMovimiento($idArqueo, $idMovimiento, $idUsuario);

            if ($r['exito']) {
                $respuesta(200, true, $r['mensaje'], [
                    'id'          => $idArqueo,
                    'movimientos' => $modelo->movimientosDeArqueo($idArqueo),
                    'resumen'     => $modelo->resumenCierre($idArqueo),
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        case 'movimientos':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id'] ?? $datos['id_arqueo'] ?? 0);
            $arqueo = $cargarArqueo($idArqueo);

            $respuesta(200, true, 'ok', [
                'id'          => $idArqueo,
                'arqueo'      => $arqueo,
                'ventas'      => $modelo->ventasDeArqueo($idArqueo),
                'movimientos' => $modelo->movimientosDeArqueo($idArqueo),
                'resumen'     => $modelo->resumenCierre($idArqueo),
            ]);
            break;

        // ---------------------------------------------
        // Corrección de la apertura
        // ---------------------------------------------
        case 'apertura_actualizar':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id'] ?? $datos['id_arqueo'] ?? 0);
            $cargarArqueo($idArqueo);

            $r = $modelo->actualizarApertura(
                $idArqueo,
                (float) ($datos['monto'] ?? 0),
                trim((string) ($datos['observacion'] ?? '')),
                $idUsuario
            );

            if ($r['exito']) {
                $respuesta(200, true, $r['mensaje'], [
                    'id'      => $idArqueo,
                    'arqueo'  => $r['arqueo'] ?? null,
                    'resumen' => $modelo->resumenCierre($idArqueo),
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        // ---------------------------------------------
        // Reapertura de un arqueo cerrado (SuperAdmin)
        // ---------------------------------------------
        case 'arqueo_reabrir':
            rm_exigir_api('modificar', Permisos::ITEM_VENTAS_ARQUEO);

            if (!rm_es_superadmin()) {
                $respuesta(403, false, 'Sólo el SuperAdministrador puede reabrir una caja cerrada');
            }

            $idArqueo = (int) ($datos['id'] ?? $datos['id_arqueo'] ?? 0);
            $cargarArqueo($idArqueo);

            $r = $modelo->reabrirArqueo($idArqueo, $idUsuario);

            if ($r['exito']) {
                $respuesta(200, true, $r['mensaje'], [
                    'id'      => $idArqueo,
                    'arqueo'  => $r['arqueo'] ?? null,
                    'resumen' => $modelo->resumenCierre($idArqueo),
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        // ---------------------------------------------
        // Eliminación del arqueo
        // ---------------------------------------------
        case 'arqueo_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id'] ?? $datos['id_arqueo'] ?? 0);
            $cargarArqueo($idArqueo);

            $r = $modelo->eliminarArqueo($idArqueo, $idUsuario);

            if ($r['exito']) {
                $respuesta(200, true, $r['mensaje'], [
                    'id'        => $idArqueo,
                    'eliminado' => true,
                ]);
            }

            $respuesta(422, false, $r['mensaje']);
            break;

        // ---------------------------------------------
        // Resumen de cierre (totales por medio de pago)
        // ---------------------------------------------
        case 'resumen_cierre':
            rm_exigir_api('ver', Permisos::ITEM_VENTAS_ARQUEO);

            $idArqueo = (int) ($datos['id'] ?? $datos['id_arqueo'] ?? 0);
            $arqueo = $cargarArqueo($idArqueo);

            $respuesta(200, true, 'ok', [
                'id'      => $idArqueo,
                'arqueo'  => $arqueo,
                'resumen' => $modelo->resumenCierre($idArqueo),
            ]);
            break;

        default:
            $respuesta(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    error_log('arqueo_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('arqueo_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}
