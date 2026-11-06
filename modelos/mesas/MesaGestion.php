<?php

/**
 * =====================================================
 * MODELO: MesaGestion  (modelos/mesas/MesaGestion.php)
 * -----------------------------------------------------
 * CRUD de pisos y mesas. Baja lógica + borrado físico
 * protegido. Filtros por sucursal, piso y estado.
 * =====================================================
 */

class MesaGestion
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =====================================================
    // PISOS
    // =====================================================

    public function pisos(?int $idSucursal = null): array
    {
        $sql = "SELECT p.*, s.nombre_sucursal, s.ciudad,
                       (SELECT COUNT(*) FROM mesas m WHERE m.id_piso = p.id_piso) AS mesas
                  FROM pisos p
                  INNER JOIN sucursales s ON s.id_sucursal = p.id_sucursal";
        $params = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= " WHERE p.id_sucursal = :s";
            $params[':s'] = $idSucursal;
        }
        $sql .= " ORDER BY p.estado DESC, p.orden ASC, p.nombre ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('MesaGestion::pisos - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($p) => [
            'id'          => (int) $p['id_piso'],
            'id_sucursal' => (int) $p['id_sucursal'],
            'sucursal'    => (string) $p['nombre_sucursal'],
            'ciudad'      => (string) ($p['ciudad'] ?? ''),
            'nombre'      => (string) $p['nombre'],
            'icono'       => (string) ($p['icono'] ?: 'fa-layer-group'),
            'orden'       => (int) $p['orden'],
            'mesas'       => (int) $p['mesas'],
            'estado'      => (int) $p['estado'],
        ], $filas);
    }

    public function pisoGuardar(array $d): array
    {
        $id       = (int) ($d['id'] ?? 0);
        $sucursal = (int) ($d['id_sucursal'] ?? 0);
        $nombre   = trim(strip_tags($d['nombre'] ?? ''));
        $icono    = trim(strip_tags($d['icono'] ?? 'fa-layer-group'));
        $orden    = (int) ($d['orden'] ?? 0);
        $estado   = (int) ($d['estado'] ?? 1) === 0 ? 0 : 1;

        if ($sucursal <= 0) return self::err('Selecciona la sucursal');
        if (mb_strlen($nombre) < 2) return self::err('El nombre debe tener al menos 2 caracteres');

        try {
            if ($id > 0) {
                $this->conn->prepare(
                    "UPDATE pisos SET id_sucursal = :s, nombre = :n, icono = :i, orden = :o, estado = :e
                      WHERE id_piso = :id"
                )->execute([
                    ':s' => $sucursal,
                    ':n' => $nombre,
                    ':i' => $icono,
                    ':o' => $orden,
                    ':e' => $estado,
                    ':id' => $id,
                    ':e' => $estado,
                    ':id' => $id,
                ]);
                return ['exito' => true, 'mensaje' => 'Piso actualizado', 'id' => $id];
            }

            $this->conn->prepare(
                "INSERT INTO pisos (id_sucursal, nombre, icono, orden, estado)
                 VALUES (:s, :n, :i, :o, :e)"
            )->execute([
                ':s' => $sucursal,
                ':n' => $nombre,
                ':i' => $icono,
                ':o' => $orden,
                ':e' => $estado,
            ]);

            return ['exito' => true, 'mensaje' => 'Piso creado', 'id' => (int) $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            error_log('MesaGestion::pisoGuardar - ' . $e->getMessage());
            return self::err('No se pudo guardar el piso');
        }
    }

    public function pisoEliminar(int $id, bool $definitivo = false): array
    {
        if ($id <= 0) return self::err('Piso no válido');

        try {
            $st = $this->conn->prepare("SELECT COUNT(*) FROM mesas WHERE id_piso = :id");
            $st->execute([':id' => $id]);
            $mesas = (int) $st->fetchColumn();

            if ($definitivo && $mesas > 0) {
                return self::err('Este piso tiene ' . $mesas . ' mesa(s) asignada(s). Desactívalo en lugar de borrarlo.');
            }

            if ($definitivo) {
                $this->conn->prepare("DELETE FROM pisos WHERE id_piso = :id")->execute([':id' => $id]);
                return ['exito' => true, 'mensaje' => 'Piso eliminado definitivamente', 'id' => $id];
            }

            $this->conn->prepare("UPDATE pisos SET estado = 0 WHERE id_piso = :id")->execute([':id' => $id]);
            return ['exito' => true, 'mensaje' => 'Piso desactivado', 'id' => $id];
        } catch (PDOException $e) {
            error_log('MesaGestion::pisoEliminar - ' . $e->getMessage());
            return self::err('No se pudo eliminar el piso');
        }
    }

    // =====================================================
    // MESAS
    // =====================================================

    public function mesas(array $f = []): array
    {
        $where = [];
        $params = [];

        if (!empty($f['id_sucursal'])) {
            $where[] = 'm.id_sucursal = :sucursal';
            $params[':sucursal'] = (int) $f['id_sucursal'];
        }
        if (!empty($f['id_piso'])) {
            $where[] = 'm.id_piso = :piso';
            $params[':piso'] = (int) $f['id_piso'];
        }
        if (in_array($f['estado_mesa'] ?? '', ['libre', 'ocupada', 'reservada', 'inactiva'], true)) {
            $where[] = 'm.estado_mesa = :estado_mesa';
            $params[':estado_mesa'] = $f['estado_mesa'];
        }
        if (!empty($f['solo_activas'])) {
            $where[] = 'm.estado = 1';
        }

        $sql = "SELECT m.*,
                       s.nombre_sucursal, s.ciudad,
                       p.nombre AS piso_nombre, p.icono AS piso_icono
                  FROM mesas m
                  INNER JOIN sucursales s ON s.id_sucursal = m.id_sucursal
                  LEFT  JOIN pisos p ON p.id_piso = m.id_piso";

        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY m.estado DESC, s.nombre_sucursal ASC, p.orden ASC, m.orden ASC, m.codigo ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('MesaGestion::mesas - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($m) => $this->normalizarMesa($m), $filas);
    }

    public function mesaGuardar(array $d): array
    {
        $id          = (int) ($d['id'] ?? 0);
        $sucursal    = (int) ($d['id_sucursal'] ?? 0);
        $piso        = (int) ($d['id_piso'] ?? 0);
        $codigo      = mb_strtoupper(trim(strip_tags($d['codigo'] ?? '')));
        $nombre      = trim(strip_tags($d['nombre'] ?? ''));
        $capacidad   = max(1, min(50, (int) ($d['capacidad'] ?? 4)));
        $forma       = in_array($d['forma'] ?? '', ['redonda', 'cuadrada', 'rectangular'], true) ? $d['forma'] : 'redonda';
        $icono       = trim(strip_tags($d['icono'] ?? 'fa-chair'));
        $imagen      = trim(strip_tags($d['imagen'] ?? ''));
        $estadoMesa  = in_array($d['estado_mesa'] ?? '', ['libre', 'ocupada', 'reservada', 'inactiva'], true)
            ? $d['estado_mesa'] : 'libre';
        $orden       = (int) ($d['orden'] ?? 0);
        $estado      = (int) ($d['estado'] ?? 1) === 0 ? 0 : 1;

        if ($sucursal <= 0) return self::err('Selecciona la sucursal');
        if (mb_strlen($codigo) < 1) return self::err('El código es obligatorio');
        if (mb_strlen($codigo) > 20) return self::err('El código no puede superar los 20 caracteres');
        if (mb_strlen($nombre) < 2) return self::err('El nombre es obligatorio');

        if ($piso > 0) {
            $st = $this->conn->prepare("SELECT id_sucursal FROM pisos WHERE id_piso = :id LIMIT 1");
            $st->execute([':id' => $piso]);
            $sucPiso = (int) $st->fetchColumn();
            if ($sucPiso !== $sucursal) return self::err('El piso seleccionado es de otra sucursal');
        }

        try {
            if ($id > 0) {
                $this->conn->prepare(
                    "UPDATE mesas
                        SET id_sucursal = :s, id_piso = :p, codigo = :c, nombre = :n,
                            capacidad = :cap, forma = :f, icono = :i, imagen = :img,
                            estado_mesa = :em, orden = :o, estado = :e
                      WHERE id_mesa = :id"
                )->execute([
                    ':s' => $sucursal,
                    ':p' => $piso > 0 ? $piso : null,
                    ':c' => $codigo,
                    ':n' => $nombre,
                    ':cap' => $capacidad,
                    ':f' => $forma,
                    ':i' => $icono,
                    ':img' => $imagen !== '' ? $imagen : null,
                    ':em' => $estadoMesa,
                    ':o' => $orden,
                    ':e' => $estado,
                    ':id' => $id,
                ]);
                return ['exito' => true, 'mensaje' => 'Mesa actualizada', 'id' => $id];
            }

            $this->conn->prepare(
                "INSERT INTO mesas
                    (id_sucursal, id_piso, codigo, nombre, capacidad, forma,
                     icono, imagen, estado_mesa, orden, estado)
                 VALUES
                    (:s, :p, :c, :n, :cap, :f, :i, :img, :em, :o, :e)"
            )->execute([
                ':s' => $sucursal,
                ':p' => $piso > 0 ? $piso : null,
                ':c' => $codigo,
                ':n' => $nombre,
                ':cap' => $capacidad,
                ':f' => $forma,
                ':i' => $icono,
                ':img' => $imagen !== '' ? $imagen : null,
                ':em' => $estadoMesa,
                ':o' => $orden,
                ':e' => $estado,
            ]);

            return ['exito' => true, 'mensaje' => 'Mesa creada', 'id' => (int) $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return self::err('Ya existe una mesa con ese código en esta sucursal');
            }
            error_log('MesaGestion::mesaGuardar - ' . $e->getMessage());
            return self::err('No se pudo guardar la mesa');
        }
    }

    public function mesaEliminar(int $id, bool $definitivo = false): array
    {
        if ($id <= 0) return self::err('Mesa no válida');

        try {
            if ($definitivo) {
                // Futuro: validar pedidos asociados
                $this->conn->prepare("DELETE FROM mesas WHERE id_mesa = :id")->execute([':id' => $id]);
                return ['exito' => true, 'mensaje' => 'Mesa eliminada definitivamente', 'id' => $id];
            }

            $this->conn->prepare("UPDATE mesas SET estado = 0, estado_mesa = 'inactiva' WHERE id_mesa = :id")
                ->execute([':id' => $id]);

            return ['exito' => true, 'mensaje' => 'Mesa desactivada', 'id' => $id];
        } catch (PDOException $e) {
            error_log('MesaGestion::mesaEliminar - ' . $e->getMessage());
            return self::err('No se pudo eliminar la mesa');
        }
    }

    public function mesaImagen(int $idMesa, string $ruta): array
    {
        if ($idMesa <= 0) return self::err('Mesa no válida');

        try {
            $st = $this->conn->prepare("SELECT imagen FROM mesas WHERE id_mesa = :id LIMIT 1");
            $st->execute([':id' => $idMesa]);
            $anterior = (string) $st->fetchColumn();

            if ($anterior !== '') {
                $abs = BASE_PATH . '/' . ltrim($anterior, '/');
                if (is_file($abs)) @unlink($abs);
            }

            $this->conn->prepare("UPDATE mesas SET imagen = :r WHERE id_mesa = :id")
                ->execute([':r' => $ruta !== '' ? $ruta : null, ':id' => $idMesa]);

            return ['exito' => true, 'mensaje' => 'Imagen actualizada', 'ruta' => $ruta];
        } catch (PDOException $e) {
            error_log('MesaGestion::mesaImagen - ' . $e->getMessage());
            return self::err('No se pudo guardar la imagen');
        }
    }

    public function sucursalesDisponibles(array $usuario): array
    {
        try {
            if ($usuario['es_superadmin'] ?? false) {
                return $this->conn->query(
                    "SELECT id_sucursal, nombre_sucursal, ciudad
                       FROM sucursales WHERE estado = 1 ORDER BY nombre_sucursal"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            $st = $this->conn->prepare(
                "SELECT id_sucursal, nombre_sucursal, ciudad
                   FROM sucursales WHERE id_sucursal = :id AND estado = 1"
            );
            $st->execute([':id' => (int) ($usuario['id_sucursal'] ?? 0)]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('MesaGestion::sucursalesDisponibles - ' . $e->getMessage());
            return [];
        }
    }

    // =====================================================
    // AUXILIAR
    // =====================================================
    private static function err(string $m): array
    {
        return ['exito' => false, 'mensaje' => $m, 'id' => 0];
    }

    private function normalizarMesa(array $m): array
    {
        $imagen = (string) ($m['imagen'] ?? '');
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

        return [
            'id'            => (int) $m['id_mesa'],
            'id_sucursal'   => (int) $m['id_sucursal'],
            'sucursal'      => (string) $m['nombre_sucursal'],
            'ciudad'        => (string) ($m['ciudad'] ?? ''),
            'id_piso'       => $m['id_piso'] ? (int) $m['id_piso'] : 0,
            'piso'          => (string) ($m['piso_nombre'] ?? ''),
            'piso_icono'    => (string) ($m['piso_icono'] ?? 'fa-layer-group'),
            'codigo'        => (string) $m['codigo'],
            'nombre'        => (string) $m['nombre'],
            'capacidad'     => (int) $m['capacidad'],
            'forma'         => (string) $m['forma'],
            'icono'         => (string) ($m['icono'] ?: 'fa-chair'),
            'imagen'        => $imagen,
            'imagen_url'    => $imagen !== ''
                ? ($baseUrl !== '' ? $baseUrl . '/' . ltrim($imagen, '/') : '/' . ltrim($imagen, '/'))
                : '',
            'estado_mesa'   => (string) $m['estado_mesa'],
            'orden'         => (int) $m['orden'],
            'estado'        => (int) $m['estado'],
        ];
    }
}
