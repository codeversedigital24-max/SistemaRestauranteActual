<?php
/**
 * =====================================================
 * POST  modelos/usuarios/usuarios_api.php
 * -----------------------------------------------------
 * Acciones del módulo de usuarios:
 *   - crear            (alta)
 *   - obtener          (detalle para edición)
 *   - actualizar       (modificación, incluido estado)
 *   - cambiar_estado   (dar de baja / dar de alta)
 *   - eliminar         (baja definitiva; bloqueada si hay historial)
 *   - permisos / guardarPermisos
 *
 * Todas las respuestas: {success, message, data}
 * Protegido por sesión + token CSRF en cabecera.
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
// 3. CSRF (misma cabecera que envía usuarios_api.js)
// -------------------------------------------------
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!rm_csrf_valido($csrf)) {
    $respuesta(403, false, 'Token de seguridad inválido. Recarga la página.');
}

// -------------------------------------------------
// 4. Cuerpo JSON
// -------------------------------------------------
$entrada = json_decode(file_get_contents('php://input') ?: '', true);
// -------------------------------------------------
// 4. Cuerpo (JSON o multipart/form-data)
// -------------------------------------------------
$esMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($esMultipart) {
    $entrada = [
        'accion' => (string) ($_POST['accion'] ?? 'crear'),
        'datos'  => $_POST,
    ];
} else {
    $entrada = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($entrada)) {
        $respuesta(400, false, 'Datos malformados');
    }
}

$accion = (string) ($entrada['accion'] ?? 'crear');
$datos  = (array)  ($entrada['datos']  ?? []);

// -------------------------------------------------
// 4.b Foto de perfil (sólo crear/actualizar)
// -------------------------------------------------
if (in_array($accion, ['crear', 'actualizar'], true)) {

    $datos['foto']        = null;
    $datos['quitar_foto'] = 0;

    if (isset($_POST['quitar_foto']) && (string) $_POST['quitar_foto'] === '1') {
        $datos['quitar_foto'] = 1;
    }

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto'];

        if ($file['size'] > 2 * 1024 * 1024) {
            $respuesta(422, false, 'La imagen supera los 2 MB');
        }

        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';

        $permitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($permitidos[$mime])) {
            $respuesta(422, false, 'Formato no válido (usa JPG, PNG o WEBP)');
        }

        // Carpeta física: <raíz-proyecto>/uploads/usuarios/
        $dir = dirname(__DIR__, 2) . '/uploads/usuarios/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $nombreArchivo = 'u_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];

        if (!move_uploaded_file($file['tmp_name'], $dir . $nombreArchivo)) {
            $respuesta(500, false, 'No se pudo guardar la imagen');
        }

        // En BD guardamos SÓLO el nombre
        $datos['foto'] = $nombreArchivo;
    }
}
// -------------------------------------------------
// 5. Acciones
// -------------------------------------------------
try {
    require_once __DIR__ . '/Usuario.php';
    require_once __DIR__ . '/../compras/ContextoCompras.php';

    $pdo = Conexion::getInstance()->getConnection();

    $idOperador    = (int) $_SESSION['id_usuario'];
    $seguridad     = Permisos::instancia($pdo);
    $superOperador = $seguridad->esSuperAdmin($idOperador);
    $nivelOperador = $superOperador ? 1 : 0; // compatibilidad: 1 = acceso total

    // Alcance por sucursal del operador.
    $operador = ContextoCompras::usuario($pdo, $idOperador);

    if (!$operador) {
        $respuesta(401, false, 'La sesión ya no es válida');
    }

    $sucursalOperador = (int) $operador['id_sucursal'];

    // ¿El usuario objetivo cae fuera del alcance del operador?
    $fueraDeAlcance = static function (?array $ficha) use ($superOperador, $sucursalOperador): bool {
        if ($superOperador) {
            return false;
        }

        if (!$ficha) {
            return false; // el llamador responde 404
        }

        return $sucursalOperador <= 0
            || (int) ($ficha['id_sucursal'] ?? 0) !== $sucursalOperador;
    };

    $modelo = new Usuario();
    $r = ['exito' => false, 'mensaje' => 'Acción no ejecutada', 'id' => 0];

    switch ($accion) {
        // ---------------------------------------------
        // ALTA
        // ---------------------------------------------
        case 'crear':
            rm_exigir_api('registrar', Permisos::ITEM_SEGURIDAD_USUARIOS);

            if (!$superOperador) {
                // Un usuario normal sólo puede crear usuarios de SU sucursal.
                if ($sucursalOperador <= 0) {
                    $respuesta(403, false, 'Tu usuario no tiene sucursal asignada');
                }

                $datos['id_sucursal'] = $sucursalOperador;
            }

            $datos['_usuario_logueado_rol'] = $nivelOperador;
            $datos['_usuario_logueado_id']  = $idOperador;
            $r = $modelo->crear($datos);

            break;

        // ---------------------------------------------
        // DETALLE (para precargar el modal de edición)
        // ---------------------------------------------
        case 'obtener':
            rm_exigir_api('ver', Permisos::ITEM_SEGURIDAD_USUARIOS);
            $id = (int) ($datos['id'] ?? 0);
            $ficha = $modelo->obtener($id);

            if (!$ficha) {
                $respuesta(404, false, 'Usuario no encontrado');
            }

            if ($fueraDeAlcance($ficha)) {
                $respuesta(403, false, 'No puedes ver usuarios de otra sucursal');
            }

            $respuesta(200, true, 'ok', ['usuario' => $ficha]);

            break; // inalcanzable

        // ---------------------------------------------
        // MODIFICACIÓN
        // ---------------------------------------------
        case 'actualizar':
            rm_exigir_api('modificar', Permisos::ITEM_SEGURIDAD_USUARIOS);

            $fichaObjetivo = $modelo->obtener((int) ($datos['id'] ?? 0));

            if (!$fichaObjetivo) {
                $respuesta(404, false, 'Usuario no encontrado');
            }

            if ($fueraDeAlcance($fichaObjetivo)) {
                $respuesta(403, false, 'No puedes modificar usuarios de otra sucursal');
            }

            if (!$superOperador) {
                // No puede mover usuarios a otra sucursal ni crearlos fuera de la suya.
                $datos['id_sucursal'] = $sucursalOperador;
            }

            // Si el cliente no envía estado, se conserva el actual.
            if (!isset($datos['estado']) || $datos['estado'] === '') {
                $datos['estado'] = (int) ($fichaObjetivo['estado'] ?? 1);
            }

            $datos['_usuario_logueado_rol'] = $nivelOperador;
            $datos['_usuario_logueado_id']  = $idOperador;
            $r = $modelo->actualizar($datos);

            break;

        // ---------------------------------------------
        // ALTA / BAJA LÓGICA (sin borrar historial)
        // ---------------------------------------------
        case 'cambiar_estado':
            rm_exigir_api('modificar', Permisos::ITEM_SEGURIDAD_USUARIOS);

            $idEstado = (int) ($datos['id'] ?? 0);
            $fichaEstado = $modelo->obtener($idEstado);

            if (!$fichaEstado) {
                $respuesta(404, false, 'Usuario no encontrado');
            }

            if ($fueraDeAlcance($fichaEstado)) {
                $respuesta(403, false, 'No puedes cambiar el estado de usuarios de otra sucursal');
            }

            $r = $modelo->cambiarEstado(
                $idEstado,
                (int) ($datos['estado'] ?? 1),
                $idOperador,
                $nivelOperador
            );

            break;

        // ---------------------------------------------
        // BAJA LÓGICA
        // ---------------------------------------------
        case 'eliminar':
            rm_exigir_api('eliminar', Permisos::ITEM_SEGURIDAD_USUARIOS);

            $idEliminar = (int) ($datos['id'] ?? 0);
            $fichaEliminar = $modelo->obtener($idEliminar);

            if (!$fichaEliminar) {
                $respuesta(404, false, 'Usuario no encontrado');
            }

            if ($fueraDeAlcance($fichaEliminar)) {
                $respuesta(403, false, 'No puedes eliminar usuarios de otra sucursal');
            }

            $r = $modelo->eliminar($idEliminar, $idOperador, $nivelOperador);

            break;

        // ---------------------------------------------
        // PERMISOS: catálogo + cargas vigentes del usuario
        // ---------------------------------------------
        case 'permisos':
            rm_exigir_api('ver', Permisos::ITEM_SEGURIDAD_USUARIOS);
            $idPerm = (int) ($datos['id'] ?? 0);
            $ficha  = $modelo->obtener($idPerm);

            if (!$ficha) {
                $respuesta(404, false, 'Usuario no encontrado');
            }

            if ($fueraDeAlcance($ficha)) {
                $respuesta(403, false, 'No puedes ver los privilegios de usuarios de otra sucursal');
            }

            $esSuperObjetivo = $seguridad->esSuperAdmin($idPerm);

            $catalogo  = require __DIR__ . '/permisos_catalogo.php';
            $asignados = $modelo->permisosDe($idPerm);   // null => falta la tabla

            $respuesta(200, true, 'ok', [
                'usuario' => [
                    'id'       => (int) $ficha['id'],
                    'nombre'   => $ficha['nombre'],
                    'username' => $ficha['username'],
                    'rol'      => $ficha['rol'],
                ],
                'es_superadmin' => $esSuperObjetivo,
                'catalogo'      => $catalogo,
                'permisos'      => $asignados,
                'tabla_ok'      => $asignados !== null,
            ]);

            break;

        // ---------------------------------------------
        // PERMISOS: guardar (reemplaza la lista completa)
        // ---------------------------------------------
        case 'guardarPermisos':
            rm_exigir_api('modificar', Permisos::ITEM_SEGURIDAD_USUARIOS);

            $idPermisoObjetivo = (int) ($datos['id'] ?? 0);
            $fichaPermisos = $modelo->obtener($idPermisoObjetivo);

            if (!$fichaPermisos) {
                $respuesta(404, false, 'Usuario no encontrado');
            }

            if ($fueraDeAlcance($fichaPermisos)) {
                $respuesta(403, false, 'No puedes modificar privilegios de usuarios de otra sucursal');
            }

            $lista = $datos['permisos'] ?? [];
            if (!is_array($lista)) {
                $respuesta(422, false, 'Lista de permisos inválida');
            }

            $catalogo = require __DIR__ . '/permisos_catalogo.php';
            $r = $modelo->guardarPermisos($idPermisoObjetivo, $lista, $catalogo);

            break;

        default:
            $respuesta(400, false, 'Acción desconocida');
    }

    if ($r['exito']) {
        $extra = isset($r['guardados']) ? ['guardados' => $r['guardados']] : [];
        $respuesta(200, true, $r['mensaje'], ['id' => $r['id']] + $extra);
    }

    $respuesta(422, false, $r['mensaje']);
} catch (PDOException $e) {
    error_log('usuarios_api.php PDO: ' . $e->getMessage());
    $respuesta(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('usuarios_api.php: ' . $e->getMessage());
    $respuesta(500, false, 'Error interno del servidor');
}