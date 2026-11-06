<?php
/**
 * =====================================================
 * GUARDIA DE SESIÓN para las páginas de /sistema
 * -----------------------------------------------------
 * Se incluye DESPUÉS de config/config.php.
 * Si no hay token válido -> redirige al login.
 * =====================================================
 */

// Por seguridad, la sesión debe estar iniciada con las
// cookies endurecidas definidas en config/config.php.
if (session_status() === PHP_SESSION_NONE) {
    rm_iniciar_sesion();
}

if (empty($_SESSION['token'])) {
    header('Location: ../login.php');
    exit;
}

$login = new Login();
$tokenValido = $login->validarToken($_SESSION['token']);

if (!$tokenValido) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: ../login.php');
    exit;
}

// ============================================
// Datos del usuario (de la sesión o desde la BD)
// ============================================
$usuario = $_SESSION['usuario'] ?? [];

if (empty($usuario)) {
    $usuario = $login->obtenerDatosCompletos((int) $tokenValido['id_usuario']);
    $_SESSION['usuario'] = $usuario;
}

// Página actual para resaltar el menú (dashboard, config_empresa, ...)
$paginaActual = isset($_SERVER['PHP_SELF'])
    ? pathinfo(basename($_SERVER['PHP_SELF']), PATHINFO_FILENAME)
    : '';
$pagina_actual = $paginaActual; // se mantiene por si algún menú lo espera

$nombreCompleto = $usuario['nombre_completo'] ?? 'Usuario';
$email          = $usuario['email'] ?? '';
$username       = $usuario['username'] ?? '';
$rolNombre      = $usuario['rol']['nombre'] ?? 'Sin rol';
$sucursal       = $usuario['sucursal'] ?? null;
$idusuario      = $usuario['id_usuario'] ?? ($usuario['id'] ?? null);

// ============================================
// Iniciales para el avatar del menú
// ============================================
$iniciales = '';
$nombres   = explode(' ', trim($nombreCompleto));

if (count($nombres) >= 2) {
    $iniciales = strtoupper(substr($nombres[0], 0, 1) . substr($nombres[1], 0, 1));
} elseif ($nombres[0] !== '') {
    $iniciales = strtoupper(substr($nombres[0], 0, 2));
} else {
    $iniciales = 'U';
}

// ============================================
// Color e ícono según el rol
// ============================================
$rolColors = [
    'SuperAdministrador'  => '#E74C3C',
    'AdministradorSucursal' => '#F39C12',
    'SupervisorEncargado' => '#2ECC71',
    'Cajero'              => '#3498DB',
    'Repartidor'          => '#9B59B6',
    'Mesero'              => '#16A085',
    'Cocinero'            => '#E67E22',
];
$rolColor = $rolColors[$rolNombre] ?? '#d9232d';

$rolIconos = [
    'SuperAdministrador'  => 'fa-crown',
    'AdministradorSucursal' => 'fa-store-alt',
    'SupervisorEncargado' => 'fa-clipboard-list',
    'Cajero'              => 'fa-cash-register',
    'Repartidor'          => 'fa-truck',
    'Mesero'              => 'fa-bell-concierge',
    'Cocinero'            => 'fa-utensils',
];
$rolIcono = $rolIconos[$rolNombre] ?? 'fa-user-shield';