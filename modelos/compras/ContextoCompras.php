<?php
/**
 * =====================================================
 * CONTEXTO DE COMPRAS / INVENTARIO
 * -----------------------------------------------------
 * Utilidades compartidas por los endpoints:
 *   · usuario autenticado + sucursal
 *   · alcance de sucursal (SuperAdmin = global)
 *   · privilegios por ítem (ver/registrar/modificar/eliminar)
 * =====================================================
 */

class ContextoCompras
{
    /** @return array|null */
    public static function usuario(PDO $pdo, int $idUsuario): ?array
    {
        if ($idUsuario <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT u.id_usuario, u.username, u.id_sucursal, u.estado,
                    r.nombre_rol,
                    s.nombre_sucursal, s.ciudad,
                    TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_completo
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             LEFT  JOIN sucursales s ON s.id_sucursal = u.id_sucursal
             LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
             WHERE u.id_usuario = :id AND u.estado = 1
             LIMIT 1"
        );
        $stmt->execute([':id' => $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $nombre = trim((string) ($fila['nombre_completo'] ?? ''));

        return [
            'id'             => (int) $fila['id_usuario'],
            'username'       => (string) $fila['username'],
            'nombre'         => $nombre !== '' ? $nombre : (string) $fila['username'],
            'id_sucursal'    => (int) ($fila['id_sucursal'] ?? 0),
            'sucursal'       => (string) ($fila['nombre_sucursal'] ?? ''),
            'ciudad'         => (string) ($fila['ciudad'] ?? ''),
            'rol'            => (string) $fila['nombre_rol'],
            'es_superadmin'  => Permisos::instancia()->esSuperAdmin((int) $fila['id_usuario']),
        ];
    }

    public static function puedeInventario(PDO $pdo, array $usuario): bool
    {
        // El privilegio real (ver/registrar/modificar/eliminar por ítem)
        // se exige en cada endpoint; aquí solo se confirma la sesión.
        return !empty($usuario['id']);
    }

    /**
     * Cuentas por pagar: se gobierna con el privilegio del ítem 14.
     */
    public static function puedeCuentasPagar(array $usuario): bool
    {
        return rm_puede('ver', Permisos::ITEM_INV_CUENTAS_PAGAR);
    }

    /**
     * Sucursal sobre la que se consulta.
     * SuperAdmin: null = todas; o la solicitada.
     * Resto: siempre la propia.
     */
    public static function sucursalConsulta(array $usuario, ?int $solicitada): ?int
    {
        if (($usuario['es_superadmin'] ?? false) === true) {
            return $solicitada !== null && $solicitada > 0 ? $solicitada : null;
        }

        return (int) $usuario['id_sucursal'] > 0 ? (int) $usuario['id_sucursal'] : null;
    }

    /** Sucursal en la que se puede operar (registrar compras). */
    public static function sucursalOperacion(array $usuario, int $solicitada): int
    {
        if (($usuario['es_superadmin'] ?? false) === true) {
            return $solicitada > 0 ? $solicitada : 0;
        }

        return (int) $usuario['id_sucursal'];
    }

    public static function sucursales(PDO $pdo, bool $todas = true): array
    {
        $sql = 'SELECT id_sucursal, codigo_sucursal, nombre_sucursal, ciudad
                FROM sucursales WHERE estado = 1';

        if (!$todas) {
            $sql .= ' AND 1 = 0';
        }

        $sql .= ' ORDER BY nombre_sucursal ASC';

        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ContextoCompras::sucursales - ' . $e->getMessage());
            return [];
        }
    }

    public static function sucursalActiva(PDO $pdo, int $idSucursal): ?array
    {
        if ($idSucursal <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id_sucursal, codigo_sucursal, nombre_sucursal, ciudad
             FROM sucursales WHERE id_sucursal = :id AND estado = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $idSucursal]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}
