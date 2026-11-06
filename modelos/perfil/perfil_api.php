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
    $datos  = (array)  ($entrada['datos']  ?? []);
}

$idUsuario = (int) $_SESSION['id_usuario'];

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/MiPerfil.php';

    $modelo = new MiPerfil($pdo);

    switch ($accion) {
        case 'actualizar':
            $r = $modelo->actualizar($idUsuario, $datos);
            if (!$r['exito']) $resp(422, false, $r['mensaje']);
            $resp(200, true, $r['mensaje'], ['perfil' => $r['perfil'] ?? null]);
            break;

        case 'subir_foto':
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $resp(422, false, 'No se recibió la imagen');
            }

            $file = $_FILES['foto'];

            if ($file['size'] > 2 * 1024 * 1024) {
                $resp(422, false, 'La imagen supera los 2 MB');
            }

            $info = @getimagesize($file['tmp_name']);
            $mime = $info['mime'] ?? '';

            $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($permitidos[$mime])) {
                $resp(422, false, 'Formato no válido (JPG, PNG o WEBP)');
            }

            $dir = BASE_PATH . '/uploads/usuarios/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombre = 'u_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
            $destino = $dir . $nombre;

            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                $resp(500, false, 'No se pudo guardar la imagen');
            }

            $r = $modelo->actualizarFoto($idUsuario, $nombre);
            if (!$r['exito']) {
                @unlink($destino);
                $resp(500, false, $r['mensaje']);
            }

            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            $url = $base !== '' ? $base . '/uploads/usuarios/' . $nombre : '/uploads/usuarios/' . $nombre;

            $resp(200, true, 'Foto actualizada', [
                'ruta' => $nombre,
                'url'  => $url,
                'perfil' => $modelo->obtener($idUsuario),
            ]);
            break;

        case 'quitar_foto':
            $r = $modelo->quitarFoto($idUsuario);
            if (!$r['exito']) $resp(422, false, $r['mensaje']);
            $resp(200, true, 'Foto eliminada', ['perfil' => $modelo->obtener($idUsuario)]);
            break;

        default:
            $resp(400, false, 'Acción desconocida');
    }
} catch (PDOException $e) {
    error_log('perfil_api.php PDO: ' . $e->getMessage());
    $resp(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('perfil_api.php: ' . $e->getMessage());
    $resp(500, false, 'Error interno del servidor');
}