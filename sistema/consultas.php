<?php
/**
 * =====================================================
 * CONSULTAS DE PERMISOS  ·  clase Consulta
 * -----------------------------------------------------
 * Fachada compatible con el código existente. Toda la
 * decisión real vive en la clase Permisos (modelo de
 * 3 niveles + acción):
 *
 *   permisos          → módulo   (menú)
 *   sub_permisos      → submenú  (desplegable)
 *   detalle_permisos  → ítem     (página)
 *   usuarios_permisos → concesiones por usuario y acción:
 *                       ver · registrar · modificar · eliminar
 *
 * Ya NO se consulta roles.nivel_permiso.
 * =====================================================
 */

class Consulta
{
    private PDO $conn;
    private int $idUsuario = 0;
    private Permisos $permisos;

    public function __construct(?PDO $conexion)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
        $this->permisos = Permisos::instancia($this->conn);
    }

    /** Fija el usuario al que se le consultan permisos. */
    public function setUsuario(int $idUsuario): void
    {
        $this->idUsuario = $idUsuario;
    }

    // =================================================
    // ¿Es SuperAdministrador? (cuenta de arranque)
    // =================================================
    public function esSuperAdmin(int $idUsuario): bool
    {
        return $this->permisos->esSuperAdmin($idUsuario);
    }

    // =================================================
    // NIVEL 1: ¿tiene alguna acción en el módulo?
    // =================================================
    public function tieneModulo(int $idUsuario, int $idPermiso, string $accion = 'ver'): bool
    {
        return $this->permisos->tieneModulo($idUsuario, $idPermiso, $accion);
    }

    // =================================================
    // NIVEL 2: ¿tiene alguna acción en el submenú?
    // =================================================
    public function tieneSubMenu(int $idUsuario, int $idPermiso, int $idSub, string $accion = 'ver'): bool
    {
        return $this->permisos->tieneSubMenu($idUsuario, $idPermiso, $idSub, $accion);
    }

    // Alias por si algún menú lo llama así
    public function tieneSubPermiso(int $idUsuario, int $idPermiso, int $idSub, string $accion = 'ver'): bool
    {
        return $this->tieneSubMenu($idUsuario, $idPermiso, $idSub, $accion);
    }

    // =================================================
    // NIVEL 3: ¿tiene la acción sobre el ítem?
    // -------------------------------------------------
    // Los parámetros de módulo/submenú se conservan sólo
    // por compatibilidad de firma: el ítem es único.
    // =================================================
    public function tieneItem(int $idUsuario, int $idPermiso, int $idSub, int $idItem, string $accion = 'ver'): bool
    {
        return $this->permisos->tiene($idUsuario, $idItem, $accion);
    }

    public function tieneDetalle(int $idUsuario, int $idItem, string $accion = 'ver'): bool
    {
        return $this->permisos->tiene($idUsuario, $idItem, $accion);
    }

    public function tieneDesglose(int $idUsuario, int $idItem, string $accion = 'ver'): bool
    {
        return $this->permisos->tiene($idUsuario, $idItem, $accion);
    }

    // =================================================
    // Acciones del usuario sobre un ítem (para la UI)
    // =================================================
    public function acciones(int $idUsuario, int $idItem): array
    {
        return $this->permisos->accionesItem($idUsuario, $idItem);
    }

    public function tieneAccion(int $idUsuario, int $idItem, string $accion): bool
    {
        return $this->permisos->tiene($idUsuario, $idItem, $accion);
    }

    // =================================================
    // ¿El usuario (en sesión) tiene la acción?
    // =================================================
    public function puede(string $accion, int $idItem): bool
    {
        return $this->permisos->tiene(rm_usuario_id(), $idItem, $accion);
    }
}

// Alias: algunos fragments lo escriben sin acento
if (!class_exists('Consultas', false)) {
    class_alias('Consulta', 'Consultas');
}
