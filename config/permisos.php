<?php
/**
 * =====================================================
 * AYUDAS DE PRIVILEGIOS  ·  config/permisos.php
 * -----------------------------------------------------
 * Funciones cortas que usan las páginas y los endpoints
 * para exigir privilegios. Se cargan desde config.php.
 *
 * Acciones válidas: ver · registrar · modificar · eliminar
 * =====================================================
 */

if (!function_exists('rm_permisos')) {
    /** Instancia única del servicio de privilegios. */
    function rm_permisos(): Permisos
    {
        return Permisos::instancia();
    }
}

if (!function_exists('rm_usuario_id')) {
    /** Id del usuario en sesión (0 si no hay sesión). */
    function rm_usuario_id(): int
    {
        return (int) ($_SESSION['id_usuario'] ?? 0);
    }
}

if (!function_exists('rm_es_superadmin')) {
    /** ¿El usuario en sesión es el SuperAdministrador? */
    function rm_es_superadmin(): bool
    {
        return rm_permisos()->esSuperAdmin(rm_usuario_id());
    }
}

if (!function_exists('rm_puede')) {
    /**
     * ¿El usuario en sesión tiene la acción sobre el ítem?
     */
    function rm_puede(string $accion, int $idItem): bool
    {
        return rm_permisos()->tiene(rm_usuario_id(), $idItem, $accion);
    }
}

if (!function_exists('rm_acciones')) {
    /**
     * Acciones del usuario en sesión sobre un ítem.
     * Devuelve ['ver'=>bool, 'registrar'=>bool, 'modificar'=>bool, 'eliminar'=>bool]
     */
    function rm_acciones(int $idItem): array
    {
        return rm_permisos()->accionesItem(rm_usuario_id(), $idItem);
    }
}

if (!function_exists('rm_exigir_permiso')) {
    /**
     * Guardián de PÁGINAS: si no hay privilegio, vuelve al
     * dashboard con un aviso. No devuelve si niega.
     */
    function rm_exigir_permiso(string $accion, int $idItem): void
    {
        if (rm_puede($accion, $idItem)) {
            return;
        }

        $mensaje = match (strtolower($accion)) {
            'registrar' => 'No tienes privilegios para registrar en esta sección',
            'modificar' => 'No tienes privilegios para modificar en esta sección',
            'eliminar'  => 'No tienes privilegios para eliminar en esta sección',
            default     => 'No tienes privilegios para acceder a esta sección',
        };

        $_SESSION['mensaje_error'] = $mensaje;
        header('Location: dashboard.php');
        exit;
    }
}

if (!function_exists('rm_exigir_api')) {
    /**
     * Guardián de ENDPOINTS (JSON): si no hay privilegio,
     * responde 403 y termina. No devuelve si niega.
     */
    function rm_exigir_api(string $accion, int $idItem): void
    {
        if (rm_puede($accion, $idItem)) {
            return;
        }

        $mensaje = match (strtolower($accion)) {
            'registrar' => 'No tienes privilegios para registrar',
            'modificar' => 'No tienes privilegios para modificar',
            'eliminar'  => 'No tienes privilegios para eliminar',
            default     => 'No tienes privilegios para ver esta información',
        };

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            [
                'success'   => false,
                'message'   => $mensaje,
                'data'      => [],
                'timestamp' => date('c'),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}

if (!function_exists('rm_puede_alguno')) {
    /**
     * ¿Tiene la acción sobre AL MENOS UNO de los ítems indicados?
     * Útil cuando un endpoint se usa desde varias páginas.
     */
    function rm_puede_alguno(string $accion, array $idItems): bool
    {
        foreach ($idItems as $idItem) {
            if (rm_puede($accion, (int) $idItem)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('rm_exigir_api_alguno')) {
    /**
     * Guardián de endpoints cuando vale cualquiera de varios ítems.
     */
    function rm_exigir_api_alguno(string $accion, array $idItems): void
    {
        if (rm_puede_alguno($accion, $idItems)) {
            return;
        }

        $mensaje = match (strtolower($accion)) {
            'registrar' => 'No tienes privilegios para registrar',
            'modificar' => 'No tienes privilegios para modificar',
            'eliminar'  => 'No tienes privilegios para eliminar',
            default     => 'No tienes privilegios para ver esta información',
        };

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            [
                'success'   => false,
                'message'   => $mensaje,
                'data'      => [],
                'timestamp' => date('c'),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}

if (!function_exists('rm_acciones_js')) {
    /**
     * Arreglo listo para exponer al JavaScript de una página:
     *   <script>window.RM_ACCIONES = <?= json_encode(rm_acciones_js(ITEM)) ?>;</script>
     */
    function rm_acciones_js(int $idItem): array
    {
        return rm_acciones($idItem);
    }
}
