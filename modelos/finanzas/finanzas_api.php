<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
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
$datos  = (array)  ($entrada['datos']  ?? []);

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/Finanzas.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $resp(401, false, 'La sesión ya no es válida');

    $modelo = new Finanzas($pdo);

    switch ($accion) {
        // ---------- Monedas ----------
        case 'moneda_guardar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_FINANZAS);
            $r = $modelo->monedaGuardar($datos);
            break;
        case 'moneda_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_FINANZAS);
            $r = $modelo->monedaEliminar((int) ($datos['id'] ?? 0), !empty($datos['definitivo']));
            break;

        // ---------- Medios ----------
        case 'medio_guardar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_FINANZAS);
            $r = $modelo->medioGuardar($datos);
            break;
        case 'medio_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_FINANZAS);
            $r = $modelo->medioEliminar((int) ($datos['id'] ?? 0), !empty($datos['definitivo']));
            break;

        // ---------- Bancos ----------
        case 'banco_guardar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_FINANZAS);
            $r = $modelo->bancoGuardar($datos);
            break;
        case 'banco_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_FINANZAS);
            $r = $modelo->bancoEliminar((int) ($datos['id'] ?? 0), !empty($datos['definitivo']));
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }

    if (!empty($r['exito'])) {
        $resp(200, true, $r['mensaje'], ['id' => $r['id'] ?? 0]);
    }
    $resp(422, false, $r['mensaje'] ?? 'No se pudo completar la operación');
} catch (PDOException $e) {
    error_log('finanzas_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('finanzas_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}