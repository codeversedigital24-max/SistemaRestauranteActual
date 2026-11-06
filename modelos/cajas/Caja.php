<?php
/**
 * =====================================================
 * MODELO: Caja  (modelos/cajas/Caja.php)
 * -----------------------------------------------------
 * Puerta única a las tablas `cajas` y
 * `cajas_asignaciones`.
 *
 * Reglas de negocio:
 *   · Una sucursal puede tener N cajas.
 *   · Código y nombre son únicos DENTRO de la sucursal.
 *   · Una caja se asigna a un usuario activo de su
 *     misma sucursal (normalmente un cajero).
 *   · Un usuario no puede tener dos cajas activas a la
 *     vez: el arqueo necesita saber cuál abrir.
 *   · Toda asignación / liberación queda en el historial.
 * =====================================================
 */

class Caja
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    /**
     * Índice para la tabla del módulo.
     *
     * @param int|null $idSucursal        null = todas (sólo SuperAdministrador);
     *                                    0 = sin sucursal: sin datos;
     *                                    >0 = forzar esa sucursal.
     * @param int|null $idUsuarioAsignado  >0 = sólo la caja asignada a ese usuario.
     */
    public function listar(?int $idSucursal = null, ?int $idUsuarioAsignado = null): array
    {
        if ($idSucursal !== null && $idSucursal <= 0) {
            return [];
        }

        $sql = "SELECT c.*,
                       s.nombre_sucursal,
                       s.codigo_sucursal,
                       s.ciudad,
                       u.username,
                       r.nombre_rol,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS responsable
                FROM cajas c
                INNER JOIN sucursales s        ON s.id_sucursal = c.id_sucursal
                LEFT  JOIN usuarios u          ON u.id_usuario  = c.id_usuario_asignado
                LEFT  JOIN usuarios_datos ud   ON ud.id_usuario = u.id_usuario
                LEFT  JOIN roles r             ON r.id_rol      = u.id_rol";

        $where  = [];
        $params = [];

        if ($idSucursal !== null) {
            $where[] = 'c.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        if ($idUsuarioAsignado !== null && $idUsuarioAsignado > 0) {
            $where[] = 'c.id_usuario_asignado = :usuario';
            $params[':usuario'] = $idUsuarioAsignado;
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY s.nombre_sucursal ASC, c.estado DESC, c.codigo ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Caja::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    /**
     * Detalle de una caja.
     *
     * @param int|null $idSucursal  null = sin restricción (SuperAdministrador);
     *                              0    = sin acceso: null;
     *                              >0   = sólo si la caja es de esa sucursal.
     */
    public function obtener(int $id, ?int $idSucursal = null): ?array
    {
        if ($id <= 0 || ($idSucursal !== null && $idSucursal <= 0)) {
            return null;
        }

        $sql = "SELECT c.*,
                       s.nombre_sucursal,
                       s.codigo_sucursal,
                       s.ciudad,
                       u.username,
                       r.nombre_rol,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS responsable
                FROM cajas c
                INNER JOIN sucursales s        ON s.id_sucursal = c.id_sucursal
                LEFT  JOIN usuarios u          ON u.id_usuario  = c.id_usuario_asignado
                LEFT  JOIN usuarios_datos ud   ON ud.id_usuario = u.id_usuario
                LEFT  JOIN roles r             ON r.id_rol      = u.id_rol
                WHERE c.id_caja = :id
                LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Caja::obtener - ' . $e->getMessage());
            return null;
        }

        if ($fila && $idSucursal !== null && (int) $fila['id_sucursal'] !== $idSucursal) {
            return null;
        }

        return $fila ? $this->normalizar($fila) : null;
    }

    /**
     * Sucursales activas para el combo del modal.
     *
     * @param int|null $idSucursal  null = todas; >0 = sólo esa; 0 = ninguna.
     */
    public function sucursales(?int $idSucursal = null): array
    {
        if ($idSucursal !== null && $idSucursal <= 0) {
            return [];
        }

        $sql = "SELECT id_sucursal, codigo_sucursal, nombre_sucursal, ciudad
                FROM sucursales
                WHERE estado = 1";
        $params = [];

        if ($idSucursal !== null) {
            $sql .= ' AND id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        $sql .= ' ORDER BY nombre_sucursal';

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Caja::sucursales - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Personal que puede recibir la caja: usuarios activos de
     * la sucursal, indicando si ya tienen otra caja asignada.
     *
     * @param int|null $sucursalPermitida  null = sin restricción (SuperAdmin);
     *                                     0 = sin acceso; >0 = debe coincidir.
     */
    public function usuariosDisponibles(int $idSucursal, int $idCajaActual = 0, ?int $sucursalPermitida = null): array
    {
        if ($idSucursal <= 0) {
            return [];
        }

        if ($sucursalPermitida !== null && ($sucursalPermitida <= 0 || $idSucursal !== $sucursalPermitida)) {
            return [];
        }

        $sql = "SELECT u.id_usuario,
                       u.username,
                       u.estado,
                       r.nombre_rol,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_completo,
                       c2.id_caja   AS caja_actual_id,
                       c2.codigo    AS caja_actual_codigo,
                       s2.nombre_sucursal AS caja_actual_sucursal
                FROM usuarios u
                INNER JOIN roles r        ON r.id_rol = u.id_rol
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                LEFT  JOIN cajas c2       ON c2.id_usuario_asignado = u.id_usuario
                                          AND c2.estado = 1
                LEFT  JOIN sucursales s2  ON s2.id_sucursal = c2.id_sucursal
                WHERE u.id_sucursal = :sucursal
                  AND u.estado = 1
                ORDER BY r.nombre_rol ASC, u.username ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':sucursal' => $idSucursal]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Caja::usuariosDisponibles - ' . $e->getMessage());
            return [];
        }

        return array_map(function (array $f) use ($idCajaActual): array {
            $cajaOcupada = (int) ($f['caja_actual_id'] ?? 0);
            $esEstaCaja = $cajaOcupada > 0 && $cajaOcupada === $idCajaActual;

            return [
                'id'          => (int) $f['id_usuario'],
                'username'    => (string) $f['username'],
                'nombre'      => trim((string) $f['nombre_completo']) !== ''
                    ? trim((string) $f['nombre_completo'])
                    : (string) $f['username'],
                'rol'         => (string) $f['nombre_rol'],
                'nivel'       => null,
                'disponible'  => $cajaOcupada === 0 || $esEstaCaja,
                'caja_actual' => $cajaOcupada > 0 && !$esEstaCaja
                    ? (string) $f['caja_actual_codigo'] . ' · ' . (string) $f['caja_actual_sucursal']
                    : '',
            ];
        }, $filas);
    }

    /**
     * Historial de asignaciones de una caja (más recientes primero).
     *
     * @param int|null $idSucursal  null = sin restricción; 0 = sin acceso;
     *                              >0 = sólo si la caja es de esa sucursal.
     */
    public function historial(int $idCaja, ?int $idSucursal = null): array
    {
        if ($idCaja <= 0) {
            return [];
        }

        // Validar alcance de la caja antes de exponer su historial.
        if ($this->obtener($idCaja, $idSucursal) === null) {
            return [];
        }

        $sql = "SELECT a.*,
                       u.username,
                       r.nombre_rol,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_completo,
                       ua.username AS asignado_por_username
                FROM cajas_asignaciones a
                INNER JOIN usuarios u        ON u.id_usuario = a.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                LEFT  JOIN roles r           ON r.id_rol = u.id_rol
                LEFT  JOIN usuarios ua       ON ua.id_usuario = a.id_asignado_por
                WHERE a.id_caja = :id
                ORDER BY a.activa DESC, a.fecha_desde DESC
                LIMIT 20";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idCaja]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Caja::historial - ' . $e->getMessage());
            return [];
        }

        return array_map(static function (array $f): array {
            $nombre = trim((string) $f['nombre_completo']);

            return [
                'usuario'     => $nombre !== '' ? $nombre : (string) $f['username'],
                'username'    => (string) $f['username'],
                'rol'         => (string) ($f['nombre_rol'] ?? ''),
                'desde'       => !empty($f['fecha_desde']) ? date('d/m/Y H:i', strtotime($f['fecha_desde'])) : '—',
                'hasta'       => !empty($f['fecha_hasta']) ? date('d/m/Y H:i', strtotime($f['fecha_hasta'])) : null,
                'activa'      => (int) $f['activa'],
                'observacion' => (string) ($f['observacion'] ?? ''),
                'asignado_por' => (string) ($f['asignado_por_username'] ?? ''),
            ];
        }, $filas);
    }

    /**
     * Contadores de cabecera.
     *
     * @param int|null $idSucursal        null = todas (SuperAdministrador);
     *                                    0 = sin sucursal: contadores en 0;
     *                                    >0 = forzar esa sucursal.
     * @param int|null $idUsuarioAsignado  >0 = sólo la caja de ese usuario.
     */
    public function resumen(?int $idSucursal = null, ?int $idUsuarioAsignado = null): array
    {
        $r = [
            'total'               => 0,
            'activas'             => 0,
            'asignadas'           => 0,
            'libres'              => 0,
            'sucursales_con_caja' => 0,
        ];

        if ($idSucursal !== null && $idSucursal <= 0) {
            return $r;
        }

        $where  = [];
        $params = [];

        if ($idSucursal !== null) {
            $where[] = 'id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        if ($idUsuarioAsignado !== null && $idUsuarioAsignado > 0) {
            $where[] = 'id_usuario_asignado = :usuario';
            $params[':usuario'] = $idUsuarioAsignado;
        }

        $base = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        try {
            $stmtTotal = $this->conn->prepare('SELECT COUNT(*) FROM cajas' . $base);
            $stmtTotal->execute($params);
            $r['total'] = (int) $stmtTotal->fetchColumn();

            $stmtActivas = $this->conn->prepare(
                'SELECT COUNT(*) FROM cajas' . ($base === '' ? ' WHERE estado = 1' : $base . ' AND estado = 1')
            );
            $stmtActivas->execute($params);
            $r['activas'] = (int) $stmtActivas->fetchColumn();

            $stmtAsignadas = $this->conn->prepare(
                'SELECT COUNT(*) FROM cajas'
                . ($base === '' ? ' WHERE estado = 1 AND id_usuario_asignado IS NOT NULL' : $base . ' AND estado = 1 AND id_usuario_asignado IS NOT NULL')
            );
            $stmtAsignadas->execute($params);
            $r['asignadas'] = (int) $stmtAsignadas->fetchColumn();

            $r['libres'] = max(0, $r['activas'] - $r['asignadas']);

            $stmtSucursales = $this->conn->prepare(
                'SELECT COUNT(DISTINCT id_sucursal) FROM cajas'
                . ($base === '' ? ' WHERE estado = 1' : $base . ' AND estado = 1')
            );
            $stmtSucursales->execute($params);
            $r['sucursales_con_caja'] = (int) $stmtSucursales->fetchColumn();
        } catch (PDOException $e) {
            error_log('Caja::resumen - ' . $e->getMessage());
        }

        return $r;
    }

    // =================================================
    //  ESCRITURA
    // =================================================

    /** Alta de caja. */
    public function crear(array $datos): array
    {
        $d = $this->sanear($datos);

        $error = $this->validar($d, null);
        if ($error !== '') {
            return self::err($error);
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO cajas
                    (id_sucursal, codigo, nombre, descripcion,
                     monto_inicial_sugerido, estado)
                 VALUES
                    (:sucursal, :codigo, :nombre, :descripcion,
                     :monto, :estado)"
            );

            $stmt->execute([
                ':sucursal'    => $d['id_sucursal'],
                ':codigo'      => $d['codigo'],
                ':nombre'      => $d['nombre'],
                ':descripcion' => $d['descripcion'] !== '' ? $d['descripcion'] : null,
                ':monto'       => $d['monto_inicial_sugerido'],
                ':estado'      => $d['estado'],
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Caja creada correctamente',
                'id'      => (int) $this->conn->lastInsertId(),
            ];
        } catch (PDOException $e) {
            return $this->errorBaseDatos($e, 'No se pudo crear la caja');
        }
    }

    /** Modificación de caja. */
    public function actualizar(array $datos): array
    {
        $d = $this->sanear($datos);

        if ($d['id'] <= 0) {
            return self::err('Caja no válida para modificar');
        }

        $actual = $this->obtener($d['id']);
        if (!$actual) {
            return self::err('Esa caja ya no existe');
        }

        $error = $this->validar($d, $d['id']);
        if ($error !== '') {
            return self::err($error);
        }

        // Si la caja está asignada, el responsable debe pertenecer a la nueva sucursal.
        if ($d['id_sucursal'] !== (int) $actual['id_sucursal'] && (int) $actual['id_usuario_asignado'] > 0) {
            $sucursalUsuario = $this->sucursalDeUsuario((int) $actual['id_usuario_asignado']);

            if ($sucursalUsuario !== $d['id_sucursal']) {
                return self::err('La caja tiene un responsable de otra sucursal: libérala antes de cambiarla de sucursal');
            }
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE cajas SET
                    id_sucursal            = :sucursal,
                    codigo                 = :codigo,
                    nombre                 = :nombre,
                    descripcion            = :descripcion,
                    monto_inicial_sugerido = :monto,
                    estado                 = :estado
                 WHERE id_caja = :id"
            );

            $stmt->execute([
                ':sucursal'    => $d['id_sucursal'],
                ':codigo'      => $d['codigo'],
                ':nombre'      => $d['nombre'],
                ':descripcion' => $d['descripcion'] !== '' ? $d['descripcion'] : null,
                ':monto'       => $d['monto_inicial_sugerido'],
                ':estado'      => $d['estado'],
                ':id'          => $d['id'],
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Caja actualizada correctamente',
                'id'      => $d['id'],
            ];
        } catch (PDOException $e) {
            return $this->errorBaseDatos($e, 'No se pudo actualizar la caja');
        }
    }

    /** Baja lógica: sólo si está libre (sin responsable). */
    public function eliminar(int $id): array
    {
        $caja = $this->obtener($id);

        if (!$caja) {
            return self::err('Esa caja ya no existe');
        }

        if ((int) $caja['id_usuario_asignado'] > 0) {
            return self::err('La caja tiene un responsable asignado: libérala antes de darla de baja');
        }

        try {
            $this->conn
                ->prepare('UPDATE cajas SET estado = 0 WHERE id_caja = :id')
                ->execute([':id' => $id]);

            return ['exito' => true, 'mensaje' => 'Caja dada de baja correctamente', 'id' => $id];
        } catch (PDOException $e) {
            error_log('Caja::eliminar - ' . $e->getMessage());
            return self::err('No se pudo dar de baja la caja');
        }
    }

    /**
     * Asigna la caja a un usuario y deja rastro en el historial.
     */
    public function asignar(int $idCaja, int $idUsuario, string $observacion, int $idAsignadoPor): array
    {
        $caja = $this->obtener($idCaja);
        if (!$caja) {
            return self::err('Esa caja ya no existe');
        }

        if ((int) $caja['estado'] !== 1) {
            return self::err('La caja está inactiva: actívala antes de asignarla');
        }

        if ($idUsuario <= 0) {
            return self::err('Selecciona un responsable');
        }

        $usuario = $this->usuario($idUsuario);
        if (!$usuario) {
            return self::err('Ese usuario ya no existe');
        }

        if ((int) $usuario['estado'] !== 1) {
            return self::err('Ese usuario está inactivo');
        }

        if ((int) ($usuario['id_sucursal'] ?? 0) !== (int) $caja['id_sucursal']) {
            return self::err('El responsable debe pertenecer a la misma sucursal de la caja');
        }

        if ((int) $caja['id_usuario_asignado'] === $idUsuario) {
            return self::err('Esa caja ya está asignada a ese usuario');
        }

        $otra = $this->cajaDeUsuario($idUsuario);
        if ($otra !== null && (int) $otra['id_caja'] !== $idCaja) {
            return self::err(
                'Ese usuario ya tiene la caja ' . $otra['codigo'] . ' (' . $otra['nombre_sucursal'] . '): libérala primero'
            );
        }

        try {
            $this->conn->beginTransaction();

            $this->cerrarAsignaciones($idCaja);

            $this->conn
                ->prepare('UPDATE cajas SET id_usuario_asignado = :usuario WHERE id_caja = :caja')
                ->execute([':usuario' => $idUsuario, ':caja' => $idCaja]);

            $this->conn
                ->prepare(
                    'INSERT INTO cajas_asignaciones
                        (id_caja, id_usuario, id_asignado_por, observacion, fecha_desde, activa)
                     VALUES
                        (:caja, :usuario, :por, :observacion, NOW(), 1)'
                )
                ->execute([
                    ':caja'        => $idCaja,
                    ':usuario'     => $idUsuario,
                    ':por'         => $idAsignadoPor > 0 ? $idAsignadoPor : null,
                    ':observacion' => $observacion !== '' ? $observacion : null,
                ]);

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Caja asignada a ' . $usuario['username'] . ' correctamente',
                'id'      => $idCaja,
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Caja::asignar - ' . $e->getMessage());
            return self::err('No se pudo asignar la caja');
        }
    }

    /** Quita el responsable actual y cierra el historial abierto. */
    public function liberar(int $idCaja): array
    {
        $caja = $this->obtener($idCaja);

        if (!$caja) {
            return self::err('Esa caja ya no existe');
        }

        if ((int) $caja['id_usuario_asignado'] <= 0) {
            return self::err('Esa caja ya está libre');
        }

        try {
            $this->conn->beginTransaction();

            $this->cerrarAsignaciones($idCaja);

            $this->conn
                ->prepare('UPDATE cajas SET id_usuario_asignado = NULL WHERE id_caja = :caja')
                ->execute([':caja' => $idCaja]);

            $this->conn->commit();

            return ['exito' => true, 'mensaje' => 'Caja liberada correctamente', 'id' => $idCaja];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Caja::liberar - ' . $e->getMessage());
            return self::err('No se pudo liberar la caja');
        }
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0];
    }

    private function sanear(array $d): array
    {
        $limpio = static fn(string $v): string => trim(strip_tags($v));

        return [
            'id'                    => (int) ($d['id'] ?? 0),
            'id_sucursal'           => (int) ($d['id_sucursal'] ?? 0),
            'codigo'                => mb_strtoupper($limpio((string) ($d['codigo'] ?? ''))),
            'nombre'                => $limpio((string) ($d['nombre'] ?? '')),
            'descripcion'           => $limpio((string) ($d['descripcion'] ?? '')),
            'monto_inicial_sugerido' => round(max(0, (float) ($d['monto_inicial_sugerido'] ?? 0)), 2),
            'estado'                => ((int) ($d['estado'] ?? 1)) === 0 ? 0 : 1,
        ];
    }

    private function validar(array $d, ?int $excluirId): string
    {
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-_]{1,19}$/', $d['codigo'])) {
            return 'El código debe tener 2-20 caracteres: letras, números, guion o guion bajo';
        }

        if (mb_strlen($d['nombre']) < 3) {
            return 'El nombre de la caja debe tener al menos 3 caracteres';
        }

        if (mb_strlen($d['nombre']) > 80) {
            return 'El nombre de la caja no puede superar los 80 caracteres';
        }

        if (mb_strlen($d['descripcion']) > 255) {
            return 'La descripción no puede superar los 255 caracteres';
        }

        if (!$this->sucursalActiva($d['id_sucursal'])) {
            return 'La sucursal seleccionada no existe o está inactiva';
        }

        $dup = $this->duplicado($d, $excluirId);
        if ($dup !== null) {
            return $dup;
        }

        return '';
    }

    private function duplicado(array $d, ?int $excluirId): ?string
    {
        $sql = "SELECT codigo, nombre
                FROM cajas
                WHERE id_sucursal = :sucursal
                  AND (codigo = :codigo OR nombre = :nombre)";
        $params = [
            ':sucursal' => $d['id_sucursal'],
            ':codigo'   => $d['codigo'],
            ':nombre'   => $d['nombre'],
        ];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id_caja != :excluir';
            $params[':excluir'] = $excluirId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        if (mb_strtoupper((string) $fila['codigo']) === $d['codigo']) {
            return 'Ya existe una caja con ese código en esta sucursal';
        }

        return 'Ya existe una caja con ese nombre en esta sucursal';
    }

    private function sucursalActiva(int $idSucursal): bool
    {
        if ($idSucursal <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare(
            'SELECT 1 FROM sucursales WHERE id_sucursal = :id AND estado = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $idSucursal]);

        return (bool) $stmt->fetchColumn();
    }

    private function usuario(int $idUsuario): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id_usuario, username, id_sucursal, estado
             FROM usuarios WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute([':id' => $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function sucursalDeUsuario(int $idUsuario): int
    {
        $usuario = $this->usuario($idUsuario);

        return $usuario ? (int) ($usuario['id_sucursal'] ?? 0) : 0;
    }

    /** Caja activa que ya tiene asignada un usuario (si existe). */
    public function cajaDeUsuario(int $idUsuario): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT c.id_caja, c.codigo, c.nombre, s.nombre_sucursal
             FROM cajas c
             INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
             WHERE c.id_usuario_asignado = :usuario AND c.estado = 1
             LIMIT 1"
        );
        $stmt->execute([':usuario' => $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function cerrarAsignaciones(int $idCaja): void
    {
        $this->conn
            ->prepare(
                'UPDATE cajas_asignaciones
                 SET fecha_hasta = NOW(), activa = 0
                 WHERE id_caja = :caja AND activa = 1'
            )
            ->execute([':caja' => $idCaja]);
    }

    private function errorBaseDatos(PDOException $e, string $mensaje): array
    {
        if ($e->getCode() === '23000') {
            return self::err('Ya existe una caja con ese código o nombre en la sucursal');
        }

        error_log('Caja - ' . $e->getMessage());

        return self::err($mensaje);
    }

    private function normalizar(array $f): array
    {
        $responsable = trim((string) ($f['responsable'] ?? ''));

        return [
            'id'                    => (int) $f['id_caja'],
            'id_sucursal'           => (int) $f['id_sucursal'],
            'sucursal'              => (string) ($f['nombre_sucursal'] ?? ''),
            'sucursal_codigo'       => (string) ($f['codigo_sucursal'] ?? ''),
            'ciudad'                => (string) ($f['ciudad'] ?? ''),
            'codigo'                => (string) $f['codigo'],
            'nombre'                => (string) $f['nombre'],
            'descripcion'           => (string) ($f['descripcion'] ?? ''),
            'id_usuario_asignado'   => $f['id_usuario_asignado'] !== null ? (int) $f['id_usuario_asignado'] : 0,
            'responsable'           => $responsable,
            'username'              => (string) ($f['username'] ?? ''),
            'rol'                   => (string) ($f['nombre_rol'] ?? ''),
            'monto_inicial_sugerido' => (float) ($f['monto_inicial_sugerido'] ?? 0),
            'estado'                => (int) ($f['estado'] ?? 1),
            'asignada'              => $f['id_usuario_asignado'] !== null,
            'creada'                => !empty($f['created_at']) ? date('d/m/Y', strtotime($f['created_at'])) : '—',
            'actualizada'           => !empty($f['updated_at']) ? date('d/m/Y H:i', strtotime($f['updated_at'])) : '—',
        ];
    }
}
