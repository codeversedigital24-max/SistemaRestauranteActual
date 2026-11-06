<?php
/**
 * =====================================================
 * POST  modelos/productos/productos_api.php
 * -----------------------------------------------------
 * Acciones del módulo de productos:
 *   obtener | crear | actualizar | eliminar
 *   disponibilidad | categorias
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

    require_once __DIR__ . '/Producto.php';

    $modelo = new Producto($pdo);
    $r = ['exito' => false, 'mensaje' => 'Acción no ejecutada', 'id' => 0];

    switch ($accion) {
        // Detalle para precargar el modal
        case 'obtener':
            rm_exigir_api('ver', Permisos::ITEM_INV_PRODUCTOS);
            $prod = $modelo->obtener((int) ($datos['id'] ?? 0));

            if (!$prod) {
                $respuesta(404, false, 'Producto no encontrado');
            }

            $respuesta(200, true, 'ok', ['producto' => $prod]);

            break; // inalcanzable

        // Alta
        case 'crear':
            rm_exigir_api('registrar', Permisos::ITEM_INV_PRODUCTOS);
            $r = $modelo->crear($datos);
            break;

        // Edición
        case 'actualizar':
            rm_exigir_api('modificar', Permisos::ITEM_INV_PRODUCTOS);
            $r = $modelo->actualizar($datos);
            break;

        // Baja lógica
        case 'eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_INV_PRODUCTOS);
            $r = $modelo->eliminar((int) ($datos['id'] ?? 0));
            break;

        // Cambiar disponibilidad (agotado / disponible)
        case 'disponibilidad':
            rm_exigir_api('modificar', Permisos::ITEM_INV_PRODUCTOS);
            $r = $modelo->cambiarDisponibilidad(
                (int) ($datos['id'] ?? 0),
                (int) ($datos['disponible'] ?? 1)
            );
            break;

        // Catálogo de categorías
        case 'categorias':
            rm_exigir_api('ver', Permisos::ITEM_INV_PRODUCTOS);
            require_once __DIR__ . '/CategoriaProducto.php';

            $categorias = (new CategoriaProducto($pdo))->listar();

            $respuesta(200, true, 'ok', ['categorias' => $categorias]);

            break; // inalcanzable

        default:
            $respuesta(400, false, 'Acción desconocida');
    }

    if ($r['exito']) {
        $respuesta(200, true, $r['mensaje'], ['id' => $r['id']]);
    }

    $respuesta(422, false, $r['mensaje']);
} catch (PDOException $e) {
    error_log('productos_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('productos_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}
