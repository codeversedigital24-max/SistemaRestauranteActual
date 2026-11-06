<?php
/**
 * =====================================================
 * CONFIGURACIÓN GENERAL DEL PROYECTO
 * -----------------------------------------------------
 * Debe cargarse SIEMPRE primero, antes de cualquier
 * header()/session_start() de este proyecto.
 * =====================================================
 */

// =====================================================
// CONFIGURACIÓN DE SESIÓN
// =====================================================
// Duración de la sesión: 12 horas (mínimo pedido: 3 h).
// El token de la BD y la cookie del navegador usan el mismo
// valor; además se renueva al navegar (sesión deslizante).
if (!defined('RM_SESION_SEGUNDOS')) {
    define('RM_SESION_SEGUNDOS', 43200); // 12 horas
}

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', RM_SESION_SEGUNDOS);

ini_set(
    'session.cookie_secure',
    isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
);

// =====================================================
// INICIO DE SESIÓN ESTANDARIZADO
// -----------------------------------------------------
// Las páginas llaman rm_iniciar_sesion() en lugar de
// session_start(), para que la cookie sea siempre
// httponly + SameSite=Lax (+ secure en HTTPS) y dure
// el mismo tiempo que el token de la base de datos.
// =====================================================
function rm_iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $seguro = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => RM_SESION_SEGUNDOS,   // cookie persistente (12 h)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $seguro,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Renueva la cookie de sesión para que el tiempo de 12 h
 * se cuente desde la última actividad (sesión deslizante).
 */
function rm_refrescar_cookie_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }

    $seguro = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie(session_name(), session_id(), [
        'expires'  => time() + RM_SESION_SEGUNDOS,
        'path'     => '/',
        'secure'   => $seguro,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// =====================================================
// TOKEN CSRF (para formularios y APIs)
// =====================================================
function rm_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function rm_csrf_valido(?string $token): bool
{
    $esperado = $_SESSION['csrf_token'] ?? '';

    return is_string($esperado)
        && $esperado !== ''
        && is_string($token)
        && hash_equals($esperado, $token);
}

// =====================================================
// CONFIGURACIÓN DE ERRORES
// =====================================================
$esLocal = (
    ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
    || ($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1'
    || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost:8080'
);

if ($esLocal) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// =====================================================
// ZONA HORARIA
// -----------------------------------------------------
// Valor de respaldo. Si en configuracion_empresa existe
// una zona_horaria válida, Conexion la aplica a PHP y a
// MySQL para que ambas guarden exactamente la misma hora.
// =====================================================
if (!defined('RM_ZONA_HORARIA')) {
    define('RM_ZONA_HORARIA', 'America/La_Paz');
}

date_default_timezone_set(RM_ZONA_HORARIA);

// =====================================================
// RUTA FÍSICA DEL PROYECTO
// =====================================================
// config/config.php  ->  BASE_PATH =  <raíz del proyecto>
define('BASE_PATH', dirname(__DIR__));

// =====================================================
// URL BASE DEL PROYECTO
// -----------------------------------------------------
// protocolo + dominio + carpeta del proyecto.
// Sin rutas físicas de Windows en el HTML.
// Local : http://localhost/restaurantemoderno
// Producción: https://cliente.com
// =====================================================
$protocol = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
) ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detectar la carpeta del proyecto dentro del DocumentRoot
$relativePath = '';

if (isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['SCRIPT_NAME'])) {

    $documentRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT']));
    $projectPath  = str_replace('\\', '/', (string) realpath(BASE_PATH));

    if ($documentRoot !== '' && $projectPath !== '') {
        $relativePath = str_replace($documentRoot, '', $projectPath);
        $relativePath = '/' . trim($relativePath, '/');
    }
}

// Nunca se acepta una ruta física como URLBase
if (
    strpos($relativePath, ':') !== false ||
    stripos($relativePath, 'xampp') !== false ||
    stripos($relativePath, 'htdocs') !== false
) {
    $relativePath = '';
}

$relativePath = '/' . trim($relativePath, '/');
if ($relativePath === '/') {
    $relativePath = '';
}

define('BASE_URL', $protocol . '://' . $host . $relativePath);

// =====================================================
// AUTOLOAD DE CLASES
// -----------------------------------------------------
// Indexa todo el árbol /clases de forma insensible a
// mayúsculas: funciona con Login/login.php en Windows
// y en Linux (producción).
// =====================================================
spl_autoload_register(function ($clase) {

    static $indice = null;

    if ($indice === null) {
        $indice = [];
        $raiz = BASE_PATH . '/clases';

        if (is_dir($raiz)) {
            $archivos = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($archivos as $archivo) {
                if ($archivo->isFile() && strtolower($archivo->getExtension()) === 'php') {
                    $clave = strtolower(
                        pathinfo($archivo->getFilename(), PATHINFO_FILENAME)
                    );

                    if (!isset($indice[$clave])) {
                        $indice[$clave] = $archivo->getPathname();
                    }
                }
            }
        }
    }

    $ruta = $indice[strtolower($clase)] ?? null;

    if ($ruta !== null) {
        require_once $ruta;
        return true;
    }

    return false;
});

// =====================================================
// AYUDAS DE PRIVILEGIOS
// -----------------------------------------------------
// rm_puede() / rm_acciones() / rm_exigir_permiso() /
// rm_exigir_api() para el modelo de 3 niveles + acción.
// =====================================================
require_once __DIR__ . '/permisos.php';