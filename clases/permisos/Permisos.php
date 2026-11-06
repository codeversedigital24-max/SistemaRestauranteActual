<?php
/**
 * =====================================================
 * SERVICIO CENTRAL DE PRIVILEGIOS  ·  clase Permisos
 * -----------------------------------------------------
 * Modelo de privilegios del sistema (3 niveles + acción):
 *
 *   permisos          → nivel 1: MÓDULO   (menú principal)
 *   sub_permisos      → nivel 2: SUBMENÚ  (desplegables)
 *   detalle_permisos  → nivel 3: ÍTEM     (página / recurso)
 *   usuarios_permisos → concesión por usuario:
 *                       (id_usuario, id_permiso, id_sub_permiso,
 *                        id_detalle_permiso, accion, permitir, estado)
 *
 * Acciones posibles sobre un ítem:
 *   ver       → puede abrir la página y ver el menú
 *   registrar → puede crear registros (venta, compra, caja, arqueo…)
 *   modificar → puede editar / actualizar registros
 *   eliminar  → puede anular / borrar registros
 *
 * REGLA DE ORO: si el usuario no tiene la fila con la acción
 * pedida (permitir = 1 y estado = 1), NO puede hacer nada.
 *
 * NO se usa `roles.nivel_permiso`. El único caso especial es el
 * rol llamado "SuperAdministrador", que conserva acceso total
 * (cuenta de arranque / llave maestra) para no bloquear el
 * sistema cuando todavía no se asignaron privilegios.
 * =====================================================
 */

class Permisos
{
    // =================================================
    // NIVEL 1 · MÓDULOS (tabla permisos)
    // =================================================
    public const MOD_CONFIGURACION = 1;
    public const MOD_SEGURIDAD     = 2;
    public const MOD_VENTAS        = 3;
    public const MOD_INVENTARIO    = 4;
    public const MOD_REPORTES      = 5;

    // =================================================
    // NIVEL 2 · SUBMENÚS (tabla sub_permisos)
    // =================================================
    public const SUB_CONFIG_GENERAL  = 1;
    public const SUB_CONFIG_FINANZAS = 2;
    public const SUB_SEGURIDAD       = 3;
    public const SUB_VENTAS_OPERACION = 4;
    public const SUB_VENTAS_HISTORIAL = 5;
    public const SUB_INV_PRODUCTOS   = 6;
    public const SUB_INV_COMPRAS     = 7;
    public const SUB_REP_VENTAS      = 8;
    public const SUB_REP_COMPRAS     = 9;

    // =================================================
    // NIVEL 3 · ÍTEMS / RECURSOS (tabla detalle_permisos)
    // -------------------------------------------------
    // Cada ítem equivale a una página del sistema.
    // =================================================
    public const ITEM_CONFIG_EMPRESA     = 1;   // configuracion.php
    public const ITEM_CONFIG_MESAS       = 2;   // mesas.php
    public const ITEM_CONFIG_SUCURSALES  = 3;   // sucursales.php
    public const ITEM_CONFIG_FINANZAS    = 4;   // finanzas.php
    public const ITEM_SEGURIDAD_USUARIOS = 5;   // usuarios.php
    public const ITEM_VENTAS_POS         = 6;   // pos.php
    public const ITEM_VENTAS_PEDIDOS     = 7;   // pedidos.php
    public const ITEM_VENTAS_CAJAS       = 8;   // caja.php
    public const ITEM_VENTAS_ARQUEO      = 9;   // arqueocaja.php
    public const ITEM_VENTAS_HISTORIAL   = 10;  // ventas.php
    public const ITEM_INV_PRODUCTOS      = 11;  // productos.php
    public const ITEM_INV_COMPRAS        = 12;  // compras.php
    public const ITEM_INV_NUEVA_COMPRA   = 13;  // nuevacompra.php
    public const ITEM_INV_CUENTAS_PAGAR  = 14;  // cuentas_pagar.php
    public const ITEM_INV_KARDEX         = 15;  // kardex.php
    public const ITEM_REP_VENTAS_FECHAS  = 16;  // ventasxfechas.php
    public const ITEM_REP_UTILIDADES     = 17;  // ventasutilidades.php
    public const ITEM_REP_COMPRAS_FECHAS = 18;  // comprasxfechas.php
    public const ITEM_VENTAS_PROMOCIONES = 19;  // promociones.php

    /** Acciones válidas (en este orden se pintan en la interfaz). */
    public const ACCIONES = ['ver', 'registrar', 'modificar', 'eliminar'];

    /** ¿La tabla usuarios_permisos ya tiene la columna `accion`? */
    private ?bool $columnaAccion = null;

    private PDO $db;

    private static ?self $instancia = null;

    /** cache: id_usuario => ['items'=>[], 'mods'=>[], 'subs'=>[]] */
    private array $cachePermisos = [];

    /** cache: id_usuario => bool */
    private array $cacheSuper = [];

    public function __construct(?PDO $conexion = null)
    {
        $this->db = $conexion ?? Conexion::getInstance()->getConnection();
    }

    public static function instancia(?PDO $conexion = null): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self($conexion);
        }

        return self::$instancia;
    }

    // =================================================
    // ¿El rol del usuario es SuperAdministrador?
    // -------------------------------------------------
    // Única excepción al modelo de privilegios: cuenta de
    // arranque con acceso total. NUNCA se usa nivel_permiso.
    // =================================================
    public function esSuperAdmin(int $idUsuario): bool
    {
        if ($idUsuario <= 0) {
            return false;
        }

        if (isset($this->cacheSuper[$idUsuario])) {
            return $this->cacheSuper[$idUsuario];
        }

        // Llave de arranque: el usuario 1 nunca se queda sin acceso
        // (evita un bloqueo total si aún no hay privilegios asignados).
        if ($idUsuario === 1) {
            return $this->cacheSuper[$idUsuario] = true;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT r.nombre_rol
                 FROM usuarios u
                 INNER JOIN roles r ON r.id_rol = u.id_rol
                 WHERE u.id_usuario = :id AND u.estado = 1
                 LIMIT 1"
            );
            $stmt->execute([':id' => $idUsuario]);
            $nombre = $stmt->fetchColumn();

            $esSuper = is_string($nombre)
                && strcasecmp(trim($nombre), 'SuperAdministrador') === 0;
        } catch (Throwable $e) {
            error_log('Permisos::esSuperAdmin - ' . $e->getMessage());
            $esSuper = false;
        }

        return $this->cacheSuper[$idUsuario] = $esSuper;
    }

    // =================================================
    // ¿La tabla usuarios_permisos soporta la columna `accion`?
    // -------------------------------------------------
    // Mientras no se ejecute sql_permisos_v2.sql, el sistema
    // sigue funcionando en modo conservador: cualquier fila
    // antigua sólo concede `ver`.
    // =================================================
    public function columnaAccion(): bool
    {
        if ($this->columnaAccion !== null) {
            return $this->columnaAccion;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM usuarios_permisos LIKE 'accion'");
            $this->columnaAccion = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Permisos::columnaAccion - ' . $e->getMessage());
            $this->columnaAccion = false;
        }

        return $this->columnaAccion;
    }

    // =================================================
    // Carga (una sola consulta) todos los privilegios del usuario
    // =================================================
    private function cargar(int $idUsuario): array
    {
        if (isset($this->cachePermisos[$idUsuario])) {
            return $this->cachePermisos[$idUsuario];
        }

        $datos = ['items' => [], 'mods' => [], 'subs' => []];

        try {
            $conAccion = $this->columnaAccion();

            $sql = $conAccion
                ? "SELECT id_permiso, id_sub_permiso, id_detalle_permiso, accion
                   FROM usuarios_permisos
                   WHERE id_usuario = :u AND permitir = 1 AND estado = 1"
                : "SELECT id_permiso, id_sub_permiso, id_detalle_permiso
                   FROM usuarios_permisos
                   WHERE id_usuario = :u AND permitir = 1 AND estado = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':u' => $idUsuario]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $accion = $conAccion
                    ? strtolower(trim((string) ($fila['accion'] ?? '')))
                    : 'ver';

                if (!in_array($accion, self::ACCIONES, true)) {
                    $accion = 'ver';
                }

                $perm = (int) ($fila['id_permiso'] ?? 0);
                $sub  = (int) ($fila['id_sub_permiso'] ?? 0);
                $item = (int) ($fila['id_detalle_permiso'] ?? 0);

                if ($item > 0) {
                    $datos['items'][$item][$accion] = true;
                }

                if ($perm > 0) {
                    $datos['mods'][$perm][$accion] = true;

                    if ($sub > 0) {
                        $datos['subs'][$perm . '-' . $sub][$accion] = true;
                    }
                }
            }
        } catch (Throwable $e) {
            // Sin tabla o sin datos: se niega todo (fail closed).
            error_log('Permisos::cargar - ' . $e->getMessage());
        }

        return $this->cachePermisos[$idUsuario] = $datos;
    }

    // =================================================
    // NIVEL 3 · ¿tiene la acción sobre este ítem?
    // =================================================
    public function tiene(int $idUsuario, int $idItem, string $accion = 'ver'): bool
    {
        $accion = strtolower(trim($accion));

        if (!in_array($accion, self::ACCIONES, true) || $idItem <= 0 || $idUsuario <= 0) {
            return false;
        }

        if ($this->esSuperAdmin($idUsuario)) {
            return true;
        }

        $datos = $this->cargar($idUsuario);

        return isset($datos['items'][$idItem][$accion]);
    }

    // =================================================
    // NIVEL 2 · ¿tiene alguna acción en el submenú?
    // =================================================
    public function tieneSubMenu(int $idUsuario, int $idPermiso, int $idSub, string $accion = 'ver'): bool
    {
        $accion = strtolower(trim($accion));

        if (!in_array($accion, self::ACCIONES, true) || $idPermiso <= 0 || $idSub <= 0 || $idUsuario <= 0) {
            return false;
        }

        if ($this->esSuperAdmin($idUsuario)) {
            return true;
        }

        $datos = $this->cargar($idUsuario);

        return isset($datos['subs'][$idPermiso . '-' . $idSub][$accion]);
    }

    // =================================================
    // NIVEL 1 · ¿tiene alguna acción en el módulo?
    // =================================================
    public function tieneModulo(int $idUsuario, int $idPermiso, string $accion = 'ver'): bool
    {
        $accion = strtolower(trim($accion));

        if (!in_array($accion, self::ACCIONES, true) || $idPermiso <= 0 || $idUsuario <= 0) {
            return false;
        }

        if ($this->esSuperAdmin($idUsuario)) {
            return true;
        }

        $datos = $this->cargar($idUsuario);

        return isset($datos['mods'][$idPermiso][$accion]);
    }

    // =================================================
    // Acciones que tiene el usuario sobre un ítem
    // =================================================
    public function accionesItem(int $idUsuario, int $idItem): array
    {
        $resultado = array_fill_keys(self::ACCIONES, false);

        if ($this->esSuperAdmin($idUsuario)) {
            return array_fill_keys(self::ACCIONES, true);
        }

        $datos = $this->cargar($idUsuario);

        foreach (self::ACCIONES as $accion) {
            $resultado[$accion] = isset($datos['items'][$idItem][$accion]);
        }

        return $resultado;
    }

    // =================================================
    // Catálogo (árbol módulo ▸ submenú ▸ ítem) para la UI
    // =================================================
    public function catalogo(): array
    {
        $catalogo = require dirname(__DIR__, 2) . '/modelos/usuarios/permisos_catalogo.php';

        return is_array($catalogo) ? $catalogo : [];
    }

    // =================================================
    // Filas concedidas (para precargar el modal de permisos)
    // -------------------------------------------------
    // Devuelve [{perm, sub, item, accion}, ...] o null si
    // la tabla no existe.
    // =================================================
    public function filasConcedidas(int $idUsuario): ?array
    {
        if ($idUsuario <= 0) {
            return null;
        }

        try {
            $conAccion = $this->columnaAccion();

            $sql = $conAccion
                ? 'SELECT id_permiso AS perm, id_sub_permiso AS sub,
                          id_detalle_permiso AS item, accion
                   FROM usuarios_permisos
                   WHERE id_usuario = :id AND permitir = 1 AND estado = 1
                   ORDER BY id_permiso, id_sub_permiso, id_detalle_permiso, accion'
                : 'SELECT id_permiso AS perm, id_sub_permiso AS sub,
                          id_detalle_permiso AS item, \'ver\' AS accion
                   FROM usuarios_permisos
                   WHERE id_usuario = :id AND permitir = 1 AND estado = 1
                   ORDER BY id_permiso, id_sub_permiso, id_detalle_permiso';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $idUsuario]);

            return array_map(
                static function (array $f): array {
                    return [
                        'perm'   => (int) $f['perm'],
                        'sub'    => $f['sub'] === null ? null : (int) $f['sub'],
                        'item'   => $f['item'] === null ? null : (int) $f['item'],
                        'accion' => in_array($f['accion'], self::ACCIONES, true) ? $f['accion'] : 'ver',
                    ];
                },
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (Throwable $e) {
            if (
                stripos($e->getMessage(), "doesn't exist") !== false
                || stripos($e->getMessage(), 'no existe') !== false
            ) {
                error_log('Permisos::filasConcedidas - falta la tabla usuarios_permisos');

                return null;
            }

            error_log('Permisos::filasConcedidas - ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Invalida las cachés internas (tras guardar privilegios).
     */
    public function limpiarCache(?int $idUsuario = null): void
    {
        if ($idUsuario === null) {
            $this->cachePermisos = [];
            $this->cacheSuper = [];

            return;
        }

        unset($this->cachePermisos[$idUsuario], $this->cacheSuper[$idUsuario]);
    }
}
