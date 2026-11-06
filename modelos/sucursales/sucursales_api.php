<?php
/**
 * =====================================================
 * POST  modelos/sucursales/sucursales_api.php
 * -----------------------------------------------------
 * Acciones del módulo de sucursales:
 *   obtener | crear | actualizar | eliminar
 * Respuesta: {success, message, data}
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
// 3. CSRF (cabecera X-CSRF-Token de sucursales_api.js)
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
    require_once __DIR__ . '/Sucursal.php';
    require_once __DIR__ . '/../compras/ContextoCompras.php';

    $pdo = Conexion::getInstance()->getConnection();

    // Alcance por sucursal del operador.
    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $respuesta(401, false, 'La sesión ya no es válida');
    }

    $esSuperadmin   = $usuario['es_superadmin'];
    $sucursalPropia = (int) $usuario['id_sucursal'];

    $modelo = new Sucursal();
    $r = ['exito' => false, 'mensaje' => 'Acción no ejecutada', 'id' => 0];

    switch ($accion) {
        // Detalle para precargar el modal
        case 'obtener':
            rm_exigir_api('ver', Permisos::ITEM_CONFIG_SUCURSALES);
            $suc = $modelo->obtener((int) ($datos['id'] ?? 0));

            if (!$suc) {
                $respuesta(404, false, 'Sucursal no encontrada');
            }

            if (!$esSuperadmin && ($sucursalPropia <= 0 || (int) $suc['id'] !== $sucursalPropia)) {
                $respuesta(403, false, 'No puedes ver otra sucursal');
            }

            $respuesta(200, true, 'ok', ['sucursal' => $suc]);

            break;

        // Alta
        case 'crear':
            rm_exigir_api('registrar', Permisos::ITEM_CONFIG_SUCURSALES);

            if (!$esSuperadmin) {
                $respuesta(403, false, 'Sólo el SuperAdministrador puede crear sucursales');
            }

            $r = $modelo->crear($datos);
            break;

        // Edición
        case 'actualizar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_SUCURSALES);

            if (!$esSuperadmin) {
                $respuesta(403, false, 'Sólo el SuperAdministrador puede modificar sucursales');
            }

            $r = $modelo->actualizar($datos);
            break;

        // Baja lógica
        case 'eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_SUCURSALES);

            if (!$esSuperadmin) {
                $respuesta(403, false, 'Sólo el SuperAdministrador puede eliminar sucursales');
            }

            $r = $modelo->eliminar((int) ($datos['id'] ?? 0));
            break;

        default:
            $respuesta(400, false, 'Acción desconocida');
    }

    if ($r['exito']) {
        $respuesta(200, true, $r['mensaje'], ['id' => $r['id']]);
    }

    $respuesta(422, false, $r['mensaje']);
} catch (PDOException $e) {
    error_log('sucursales_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('sucursales_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}