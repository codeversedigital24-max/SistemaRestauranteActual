<?php
/**
 * =====================================================
 * modelos/productos/imagenes_api.php
 * -----------------------------------------------------
 * Galería de imágenes de un producto.
 *
 *   GET  ?id_producto=ID           → lista imágenes
 *   POST multipart                  → guarda cambios
 *        id_producto  int
 *        archivos[]   file(s)       hasta 10 por producto
 *        eliminar[]   int           ids a borrar
 *        principal    '' | e:ID | n:INDICE
 *
 * Protegido por sesión + token CSRF (cabecera X-CSRF-Token).
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

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $respuesta(401, false, 'No autorizado');
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

if ($metodo !== 'GET' && $metodo !== 'POST') {
    header('Allow: GET, POST');
    $respuesta(405, false, 'Método no permitido');
}

if ($metodo === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!rm_csrf_valido($csrf)) {
        $respuesta(403, false, 'Token de seguridad inválido. Recarga la página.');
    }
}

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/ProductoImagen.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        $respuesta(401, false, 'La sesión ya no es válida');
    }

    // Galería: ver con privilegio de lectura; subir/eliminar exige modificar.
    if ($metodo === 'GET') {
        rm_exigir_api('ver', Permisos::ITEM_INV_PRODUCTOS);
    } else {
        rm_exigir_api('modificar', Permisos::ITEM_INV_PRODUCTOS);
    }

    $modelo = new ProductoImagen($pdo);

    if ($metodo === 'GET') {
        $idProducto = (int) ($_GET['id_producto'] ?? 0);

        if ($idProducto <= 0) {
            $respuesta(422, false, 'Producto no válido');
        }

        $respuesta(200, true, 'ok', ['imagenes' => $modelo->listar($idProducto)]);
    }

    // -------------------------------------------------
    // POST: guardar galería
    // -------------------------------------------------
    $idProducto = (int) ($_POST['id_producto'] ?? 0);

    if ($idProducto <= 0) {
        $respuesta(422, false, 'Producto no válido');
    }

    $eliminar = $_POST['eliminar'] ?? [];

    if (is_string($eliminar)) {
        $decodificado = json_decode($eliminar, true);
        $eliminar = is_array($decodificado) ? $decodificado : explode(',', $eliminar);
    }

    $eliminar = is_array($eliminar) ? $eliminar : [];

    $principal = (string) ($_POST['principal'] ?? '');

    // Normalizar $_FILES['archivos'] (uno o varios)
    $archivos = [];
    $subida = $_FILES['archivos'] ?? null;

    if (is_array($subida) && isset($subida['name'])) {
        if (is_array($subida['name'])) {
            $total = count($subida['name']);

            for ($i = 0; $i < $total; $i++) {
                if ((int) $subida['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $archivos[] = [
                    'name'     => $subida['name'][$i],
                    'type'     => $subida['type'][$i],
                    'tmp_name' => $subida['tmp_name'][$i],
                    'error'    => $subida['error'][$i],
                    'size'     => $subida['size'][$i],
                ];
            }
        } elseif ((int) $subida['error'] !== UPLOAD_ERR_NO_FILE) {
            $archivos[] = $subida;
        }
    }

    if ($archivos === [] && $eliminar === [] && $principal === '') {
        $respuesta(422, false, 'No hay cambios en la galería');
    }

    $r = $modelo->guardar($idProducto, $archivos, $eliminar, $principal);

    if (!$r['exito']) {
        $respuesta(422, false, $r['mensaje']);
    }

    $respuesta(200, true, $r['mensaje'], ['imagenes' => $r['imagenes']]);
} catch (PDOException $e) {
    error_log('imagenes_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('imagenes_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}
