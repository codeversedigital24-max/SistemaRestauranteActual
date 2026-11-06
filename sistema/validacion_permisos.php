<?php
/**
 * =====================================================
 * VALIDACIÓN DE PERMISOS  ·  sistema/validacion_permisos.php
 * -----------------------------------------------------
 * Guardián común de las páginas de /sistema.
 *
 * Modelo de privilegios (3 niveles + acción), sin usar
 * roles.nivel_permiso:
 *
 *   permisos          → módulo   (menú principal)
 *   sub_permisos      → submenú  (desplegables)
 *   detalle_permisos  → ítem     (página / recurso)
 *
 * Cada página llama después:
 *   rm_exigir_permiso('ver', Permisos::ITEM_XXX);
 *
 * Y para operar (registrar / modificar / eliminar) las APIs
 * usan rm_exigir_api(). Si no hay privilegio, no hay acceso.
 * =====================================================
 */

// Verificar sesión
if (session_status() === PHP_SESSION_NONE) {
    rm_iniciar_sesion();
}

if (!isset($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    header('Location: ../login.php');
    exit;
}

$id_usuario = (int) $_SESSION['id_usuario'];

// Verificar que el usuario siga activo en la base de datos
$pdo = null;

try {
    $pdo = Conexion::getInstance()->getConnection();
    $sql = "SELECT estado FROM usuarios WHERE id_usuario = :id_usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_usuario' => $id_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || $usuario['estado'] != 1) {
        $_SESSION = [];
        session_destroy();
        header('Location: ../login.php');
        exit;
    }
} catch (Throwable $e) {
    // Base de datos caída u otro fallo crítico: salir con aviso
    // explícito para que el login pueda mostrarlo.
    error_log("validacion_permisos: " . $e->getMessage());
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: ../login.php?aviso=bd');
    exit;
}

// Servicio de privilegios + fachada heredada
require_once __DIR__ . '/consultas.php';   // clase Consulta (no está en /clases)

$seguridad = Permisos::instancia($pdo);
$consulta  = new Consulta($pdo);
$consulta->setUsuario($id_usuario);

// Banderas de compatibilidad con las plantillas antiguas
$esSuperAdmin   = $seguridad->esSuperAdmin($id_usuario);
$esAdministrador = false; // el nivel jerárquico ya no otorga permisos

// =====================================================
// IDs de MÓDULOS (compatibilidad con el menú y páginas)
// =====================================================
if (!defined('ID_PERMISO_CONFIGURACION')) {
    define('ID_PERMISO_CONFIGURACION', Permisos::MOD_CONFIGURACION);
    define('ID_PERMISO_SEGURIDAD', Permisos::MOD_SEGURIDAD);
    define('ID_PERMISO_VENTAS', Permisos::MOD_VENTAS);
    define('ID_PERMISO_INVENTARIO', Permisos::MOD_INVENTARIO);
    define('ID_PERMISO_REPORTES', Permisos::MOD_REPORTES);
}

// =====================================================
// Compatibilidad: verificarAcceso() (API antigua)
// -----------------------------------------------------
// Los ids de módulo/submenú ya no son necesarios: el ítem
// es único en toda la base.
// =====================================================
if (!function_exists('verificarAcceso')) {
    function verificarAcceso($id_usuario, $id_permiso, $id_sub_permiso, $id_detalle_permiso, $redirect = true)
    {
        $tieneAcceso = rm_puede('ver', (int) $id_detalle_permiso);

        if (!$tieneAcceso && $redirect) {
            $_SESSION['mensaje_error'] = 'No tienes permisos para acceder a esta página';
            header('Location: dashboard.php');
            exit;
        }

        return $tieneAcceso;
    }
}
