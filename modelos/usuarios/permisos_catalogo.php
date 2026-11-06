<?php
/**
 * =====================================================
 * CATÁLOGO DE PERMISOS (dinámico, desde la base real)
 * -----------------------------------------------------
 * Une las tres tablas reales del sistema:
 *
 *   permisos         → módulos
 *   sub_permisos     → submenús
 *   detalle_permisos → ítems (con ruta al recurso)
 *
 * Devuelve el árbol:
 *   [ id_modulo => [nombre, icono,
 *        submenus => [ id_sub => [nombre, icono,
 *             items => [ id_item => 'Nombre' ]]]]]
 *
 * Como sale de la BD, siempre coincide con lo que muestran
 * el menú y la tabla de usuarios.
 * =====================================================
 */

$catalogo = [];

try {
    $pdo = Conexion::getInstance()->getConnection();

    $mods = $pdo->query(
        "SELECT id_permiso, nombre_permiso, icono
         FROM permisos
         WHERE estado = 1
         ORDER BY orden, id_permiso"
    )->fetchAll(PDO::FETCH_ASSOC);

    $subs = $pdo->query(
        "SELECT id_sub_permiso, id_permiso, nombre_sub_permiso, icono
         FROM sub_permisos
         WHERE estado = 1
         ORDER BY orden, id_sub_permiso"
    )->fetchAll(PDO::FETCH_ASSOC);

    $dets = $pdo->query(
        "SELECT id_detalle_permiso, id_sub_permiso, nombre_detalle, ruta
         FROM detalle_permisos
         WHERE estado = 1
         ORDER BY orden, id_detalle_permiso"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Módulos
    foreach ($mods as $m) {
        $catalogo[(int) $m['id_permiso']] = [
            'nombre'   => (string) $m['nombre_permiso'],
            'icono'    => (string) ($m['icono'] ?: 'fa-cube'),
            'submenus' => [],
        ];
    }

    // Submenús (con su módulo padre)
    $padreDeSub = [];

    foreach ($subs as $s) {
        $pid = (int) $s['id_permiso'];
        $sid = (int) $s['id_sub_permiso'];

        if (!isset($catalogo[$pid])) {
            continue;   // submenú huérfano: se ignora
        }

        $padreDeSub[$sid] = $pid;

        $catalogo[$pid]['submenus'][$sid] = [
            'nombre' => (string) $s['nombre_sub_permiso'],
            'icono'  => (string) ($s['icono'] ?: 'fa-folder'),
            'items'  => [],
        ];
    }

    // Ítems
    foreach ($dets as $d) {
        $sid = (int) $d['id_sub_permiso'];

        if (!isset($padreDeSub[$sid])) {
            continue;   // detalle de un submenú inexistente/inactivo
        }

        $pid = $padreDeSub[$sid];

        $catalogo[$pid]['submenus'][$sid]['items'][(int) $d['id_detalle_permiso']] =
            (string) $d['nombre_detalle'];
    }
} catch (Throwable $e) {
    // Sin catálogo no se puede conceder nada: el modal avisará.
    error_log('permisos_catalogo: ' . $e->getMessage());
}

return $catalogo;