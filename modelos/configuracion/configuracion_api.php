<?php
/**
 * =====================================================
 * POST  modelos/configuracion/configuracion_api.php
 * -----------------------------------------------------
 * Acciones:
 *   guardar         → guarda todos los campos
 *   subir_imagen    → sube logo / favicon / logo_blanco / banner
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

$esMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($esMultipart) {
    $accion = (string) ($_POST['accion'] ?? '');
    $datos  = $_POST;
} else {
    $entrada = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($entrada)) $resp(400, false, 'Datos malformados');
    $accion = (string) ($entrada['accion'] ?? '');
    $datos  = (array)  ($entrada['datos']  ?? []);
}

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/Empresa.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $resp(401, false, 'La sesión ya no es válida');

    $modelo = new Empresa($pdo);

    switch ($accion) {
        case 'guardar':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_EMPRESA);
            $r = $modelo->guardar($datos);
            if (!$r['exito']) $resp(422, false, $r['mensaje']);
            $resp(200, true, $r['mensaje'], ['id' => $r['id']]);
            break;

        case 'subir_imagen':
            rm_exigir_api('modificar', Permisos::ITEM_CONFIG_EMPRESA);
            $campo = (string) ($datos['campo'] ?? '');
            if (!in_array($campo, ['logo','logo_favicon','logo_blanco','banner'], true)) {
                $resp(422, false, 'Campo de imagen no válido');
            }

            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                $resp(422, false, 'No se recibió la imagen');
            }

            $file = $_FILES['archivo'];

            if ($file['size'] > 3 * 1024 * 1024) {
                $resp(422, false, 'La imagen supera los 3 MB');
            }

            $info = @getimagesize($file['tmp_name']);
            $mime = $info['mime'] ?? '';

            $permitidos = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                'image/x-icon'  => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
            ];

            if (!isset($permitidos[$mime])) {
                $resp(422, false, 'Formato no válido (JPG, PNG, WEBP, SVG, ICO)');
            }

            $dir = dirname(__DIR__, 2) . '/assets/img/configuracion/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombre = $campo . '_' . bin2hex(random_bytes(6)) . '.' . $permitidos[$mime];
            $destino = $dir . $nombre;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                $resp(500, false, 'No se pudo guardar la imagen');
            }

            $rutaRel = 'assets/img/configuracion/' . $nombre;

            $r = $modelo->guardarArchivo($campo, $rutaRel);
            if (!$r['exito']) {
                @unlink($destino);
                $resp(500, false, $r['mensaje']);
            }

            $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            $url = $baseUrl !== '' ? $baseUrl . '/' . $rutaRel : '/' . $rutaRel;

            $resp(200, true, 'Imagen actualizada', [
                'campo' => $campo,
                'ruta'  => $rutaRel,
                'url'   => $url,
            ]);
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    error_log('configuracion_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('configuracion_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}