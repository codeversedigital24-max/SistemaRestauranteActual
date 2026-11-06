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

$esMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($esMultipart) {
    $accion = (string) ($_POST['accion'] ?? '');
    $datos  = $_POST;
} else {
    $entrada = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($entrada)) $resp(400, false, 'Datos malformados');
    $accion = (string) ($entrada['accion'] ?? '');
    $datos  = (array) ($entrada['datos'] ?? []);
}

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/MesaGestion.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $resp(401, false, 'La sesión ya no es válida');

    $modelo = new MesaGestion($pdo);

    switch ($accion) {
        case 'piso_guardar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_MESAS);
            $r = $modelo->pisoGuardar($datos);
            break;

        case 'piso_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_MESAS);
            $r = $modelo->pisoEliminar((int) ($datos['id'] ?? 0), !empty($datos['definitivo']));
            break;

        case 'mesa_guardar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_MESAS);
            $r = $modelo->mesaGuardar($datos);
            break;

        case 'mesa_eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_MESAS);
            $r = $modelo->mesaEliminar((int) ($datos['id'] ?? 0), !empty($datos['definitivo']));
            break;

        case 'mesa_imagen':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_MESAS);
            $idMesa = (int) ($datos['id_mesa'] ?? 0);
            if ($idMesa <= 0) $resp(422, false, 'Mesa no válida');

            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                $resp(422, false, 'No se recibió la imagen');
            }

            $file = $_FILES['archivo'];
            if ($file['size'] > 3 * 1024 * 1024) {
                $resp(422, false, 'La imagen supera los 3 MB');
            }

            $info = @getimagesize($file['tmp_name']);
            $mime = $info['mime'] ?? '';

            $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($permitidos[$mime])) {
                $resp(422, false, 'Formato no válido (JPG, PNG o WEBP)');
            }

            $dir = BASE_PATH . '/assets/img/mesas/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombre = 'mesa_' . $idMesa . '_' . bin2hex(random_bytes(5)) . '.' . $permitidos[$mime];
            if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
                $resp(500, false, 'No se pudo guardar la imagen');
            }

            $ruta = 'assets/img/mesas/' . $nombre;
            $r = $modelo->mesaImagen($idMesa, $ruta);
            if (!$r['exito']) {
                @unlink($dir . $nombre);
                $resp(500, false, $r['mensaje']);
            }

            $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            $url = $baseUrl !== '' ? $baseUrl . '/' . $ruta : '/' . $ruta;

            $resp(200, true, 'Imagen actualizada', ['ruta' => $ruta, 'url' => $url]);
            break;

        case 'mesa_imagen_quitar':
            rm_exigir_api('eliminar', Permisos::ITEM_CONFIG_MESAS);
            $idMesa = (int) ($datos['id_mesa'] ?? 0);
            if ($idMesa <= 0) $resp(422, false, 'Mesa no válida');
            $r = $modelo->mesaImagen($idMesa, '');
            if (!$r['exito']) $resp(422, false, $r['mensaje']);
            $resp(200, true, 'Imagen eliminada', ['ruta' => '']);
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }

    if (!empty($r['exito'])) {
        $resp(200, true, $r['mensaje'], ['id' => $r['id'] ?? 0]);
    }
    $resp(422, false, $r['mensaje'] ?? 'No se pudo completar');
} catch (PDOException $e) {
    error_log('mesas_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('mesas_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}