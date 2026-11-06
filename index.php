<?php
/**
 * =====================================================
 * PUNTO DE ENTRADA
 * -----------------------------------------------------
 * La raíz nunca se queda en blanco:
 *   - sesión válida  -> dashboard
 *   - sin sesión      -> login
 * =====================================================
 */

require_once 'config/config.php';

rm_iniciar_sesion();

$destino = 'login.php';

if (!empty($_SESSION['token'])) {
    try {
        $login = new Login();
        if ($login->validarToken($_SESSION['token'])) {
            $destino = 'sistema/dashboard.php';
        }
    } catch (Throwable $e) {
        error_log('index.php: sesión previa inválida - ' . $e->getMessage());
    }
}

header('Location: ' . $destino, true, 302);
exit;