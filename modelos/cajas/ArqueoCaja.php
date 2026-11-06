<?php
/**
 * =====================================================
 * MODELO: ArqueoCaja  (modelos/cajas/ArqueoCaja.php)
 * -----------------------------------------------------
 * Apertura y cierre de la caja asignada a cada usuario.
 *
 * Reglas de negocio:
 *   · Sólo el usuario responsable de la caja puede abrirla.
 *   · Una caja no puede tener dos arqueos abiertos.
 *   · El arqueo copia la sucursal al abrirse: la historia
 *     de una sucursal no se mezcla con otra.
 *   · El cierre calcula diferencia = contado - esperado.
 *   · Los totales de venta quedan en 0 hasta que el módulo
 *     de ventas empiece a registrar cobros.
 * =====================================================
 */

class ArqueoCaja
{
    private PDO $conn;

    /** Cache de columnas reales de `arqueos_caja` (compatibilidad sin migrar). */
    private ?array $columnasArqueo = null;

    /** Cache de existencia de la tabla `arqueo_movimientos`. */
    private ?bool $tablaMovimientos = null;

    /** Tipos de movimiento de caja permitidos (egresos / gastos). */
    public const TIPOS_MOVIMIENTO = ['egreso', 'gasto'];

    /** Medios de pago de un movimiento de caja. */
    public const METODOS_MOVIMIENTO = ['efectivo', 'transferencia', 'qr', 'tarjeta', 'otro'];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    /**
     * Arqueos filtrados.
     *
     * @param int|null $idSucursal null = todas (sólo SuperAdmin)
     * @param int      $idUsuario  Si se indica, aplica el alcance por
     *                             privilegios: SuperAdmin ve todo;
     *                             usuario normal, su sucursal; cajero
     *                             sin privilegio de modificar, sólo su
     *                             caja asignada.
     */
    public function listar(?int $idSucursal, string $estado = '', string $desde = '', string $hasta = '', int $limite = 300, int $idUsuario = 0): array
    {
        $where = [];
        $params = [];

        $alcance = $idUsuario > 0 ? $this->resolverAlcance($idUsuario) : null;

        if ($alcance !== null && !$alcance['es_superadmin']) {
            // El usuario no elige sucursal: queda atado a la suya.
            if ($alcance['id_sucursal'] > 0) {
                $where[] = 'a.id_sucursal = :sucursal';
                $params[':sucursal'] = $alcance['id_sucursal'];
            } else {
                $where[] = '1 = 0';
            }

            // Cajero sin privilegio de modificar: sólo ve su propia caja.
            // Si no tiene caja asignada, se queda con el alcance de sucursal.
            if ($alcance['solo_su_caja'] && $alcance['id_caja'] > 0) {
                $where[] = 'a.id_caja = :caja_alcance';
                $params[':caja_alcance'] = $alcance['id_caja'];
            }
        } elseif ($idSucursal !== null && $idSucursal > 0) {
            $where[] = 'a.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        if ($estado === 'abierto' || $estado === 'cerrado') {
            $where[] = 'a.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($desde !== '') {
            $where[] = 'a.fecha_apertura >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }

        if ($hasta !== '') {
            $where[] = 'a.fecha_apertura <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $sql = "SELECT a.*,
                       {$this->exprEgresos('a')},
                       c.codigo AS caja_codigo,
                       c.nombre AS caja_nombre,
                       s.nombre_sucursal,
                       s.ciudad,
                       ua.username AS username_apertura,
                       ua_r.nombre_rol AS rol_apertura,
                       TRIM(CONCAT(COALESCE(ua_d.nombres, ''), ' ', COALESCE(ua_d.apellidos, ''))) AS nombre_apertura,
                       uc.username AS username_cierre,
                       TRIM(CONCAT(COALESCE(uc_d.nombres, ''), ' ', COALESCE(uc_d.apellidos, ''))) AS nombre_cierre
                FROM arqueos_caja a
                INNER JOIN cajas c           ON c.id_caja = a.id_caja
                INNER JOIN sucursales s      ON s.id_sucursal = a.id_sucursal
                INNER JOIN usuarios ua       ON ua.id_usuario = a.id_usuario_apertura
                LEFT  JOIN usuarios_datos ua_d ON ua_d.id_usuario = ua.id_usuario
                LEFT  JOIN roles ua_r        ON ua_r.id_rol = ua.id_rol
                LEFT  JOIN usuarios uc       ON uc.id_usuario = a.id_usuario_cierre
                LEFT  JOIN usuarios_datos uc_d ON uc_d.id_usuario = uc.id_usuario";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.estado ASC, a.fecha_apertura DESC';
        $sql .= ' LIMIT ' . max(1, min(1000, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    /** Caja activa asignada a un usuario (si tiene una). */
    public function cajaDeUsuario(int $idUsuario): ?array
    {
        if ($idUsuario <= 0) {
            return null;
        }

        $sql = "SELECT c.*, s.nombre_sucursal, s.ciudad, s.codigo_sucursal
                FROM cajas c
                INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                WHERE c.id_usuario_asignado = :usuario
                  AND c.estado = 1
                  AND s.estado = 1
                LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':usuario' => $idUsuario]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::cajaDeUsuario - ' . $e->getMessage());
            return null;
        }

        if (!$fila) {
            return null;
        }

        return [
            'id'                      => (int) $fila['id_caja'],
            'id_sucursal'             => (int) $fila['id_sucursal'],
            'codigo'                  => (string) $fila['codigo'],
            'nombre'                  => (string) $fila['nombre'],
            'descripcion'             => (string) ($fila['descripcion'] ?? ''),
            'sucursal'                => (string) $fila['nombre_sucursal'],
            'sucursal_codigo'         => (string) ($fila['codigo_sucursal'] ?? ''),
            'ciudad'                  => (string) ($fila['ciudad'] ?? ''),
            'monto_inicial_sugerido'  => (float) ($fila['monto_inicial_sugerido'] ?? 0),
            'id_usuario_asignado'     => (int) ($fila['id_usuario_asignado'] ?? 0),
            'estado'                  => (int) ($fila['estado'] ?? 1),
        ];
    }

    /** Arqueo abierto de una caja (o null). */
    public function abiertoDeCaja(int $idCaja): ?array
    {
        if ($idCaja <= 0) {
            return null;
        }

        $filas = $this->listarPorCaja($idCaja, 'abierto', 1);

        return $filas[0] ?? null;
    }

    /** Arqueo por id (para validar cierre). */
    public function obtener(int $idArqueo): ?array
    {
        if ($idArqueo <= 0) {
            return null;
        }

        $sql = "SELECT a.*,
                       {$this->exprEgresos('a')},
                       c.codigo AS caja_codigo,
                       c.nombre AS caja_nombre,
                       c.id_usuario_asignado,
                       s.nombre_sucursal,
                       s.ciudad,
                       ua.username AS username_apertura,
                       ua_r.nombre_rol AS rol_apertura,
                       TRIM(CONCAT(COALESCE(ua_d.nombres, ''), ' ', COALESCE(ua_d.apellidos, ''))) AS nombre_apertura,
                       uc.username AS username_cierre,
                       TRIM(CONCAT(COALESCE(uc_d.nombres, ''), ' ', COALESCE(uc_d.apellidos, ''))) AS nombre_cierre
                FROM arqueos_caja a
                INNER JOIN cajas c           ON c.id_caja = a.id_caja
                INNER JOIN sucursales s      ON s.id_sucursal = a.id_sucursal
                INNER JOIN usuarios ua       ON ua.id_usuario = a.id_usuario_apertura
                LEFT  JOIN usuarios_datos ua_d ON ua_d.id_usuario = ua.id_usuario
                LEFT  JOIN roles ua_r        ON ua_r.id_rol = ua.id_rol
                LEFT  JOIN usuarios uc       ON uc.id_usuario = a.id_usuario_cierre
                LEFT  JOIN usuarios_datos uc_d ON uc_d.id_usuario = uc.id_usuario
                WHERE a.id_arqueo = :id
                LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idArqueo]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::obtener - ' . $e->getMessage());
            return null;
        }

        if (!$fila) {
            return null;
        }

        $fila['id_usuario_asignado'] = (int) $fila['id_usuario_asignado'];

        return $this->normalizar($fila);
    }

    /** Contadores de cabecera para la sucursal indicada. */
    public function resumen(?int $idSucursal, int $idUsuario = 0): array
    {
        $r = [
            'abiertos'        => 0,
            'cerrados'        => 0,
            'hoy'             => 0,
            'monto_en_cajas'  => 0.0,
            'total_egresos'   => 0.0,
            'efectivo_esperado' => 0.0,
        ];

        $filtro = '';
        $params = [];

        // Compatibilidad: si aún no se ejecutó sql_arqueo_movimientos.sql
        // las columnas nuevas no existen y se usan expresiones alternas.
        $colEgresos = $this->columnaArqueo('total_egresos')
            ? 'COALESCE(SUM(total_egresos), 0)'
            : '0';

        $colEsperado = $this->columnaArqueo('efectivo_esperado')
            ? "COALESCE(SUM(CASE WHEN estado = 'abierto' THEN efectivo_esperado ELSE 0 END), 0)"
            : "COALESCE(SUM(CASE WHEN estado = 'abierto' THEN monto_apertura + total_efectivo ELSE 0 END), 0)";

        $alcance = $idUsuario > 0 ? $this->resolverAlcance($idUsuario) : null;

        if ($alcance !== null && !$alcance['es_superadmin']) {
            if ($alcance['id_sucursal'] > 0) {
                $filtro = ' AND id_sucursal = :sucursal';
                $params[':sucursal'] = $alcance['id_sucursal'];
            } else {
                $filtro = ' AND 1 = 0';
            }

            if ($alcance['solo_su_caja'] && $alcance['id_caja'] > 0) {
                $filtro .= ' AND id_caja = :caja_alcance';
                $params[':caja_alcance'] = $alcance['id_caja'];
            }
        } elseif ($idSucursal !== null && $idSucursal > 0) {
            $filtro = ' AND id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT
                    COALESCE(SUM(estado = 'abierto'), 0) AS abiertos,
                    COALESCE(SUM(estado = 'cerrado'), 0) AS cerrados,
                    COALESCE(SUM(DATE(fecha_apertura) = CURDATE()), 0) AS hoy,
                    COALESCE(SUM(CASE WHEN estado = 'abierto' THEN monto_apertura ELSE 0 END), 0) AS monto_en_cajas,
                    {$colEgresos} AS total_egresos,
                    {$colEsperado} AS efectivo_esperado
                 FROM arqueos_caja
                 WHERE 1 = 1" . $filtro
            );
            $stmt->execute($params);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $r['abiertos'] = (int) $fila['abiertos'];
                $r['cerrados'] = (int) $fila['cerrados'];
                $r['hoy'] = (int) $fila['hoy'];
                $r['monto_en_cajas'] = (float) $fila['monto_en_cajas'];
                $r['total_egresos'] = (float) $fila['total_egresos'];
                $r['efectivo_esperado'] = (float) $fila['efectivo_esperado'];
            }
        } catch (PDOException $e) {
            error_log('ArqueoCaja::resumen - ' . $e->getMessage());
        }

        return $r;
    }

    // =================================================
    //  APERTURA
    // =================================================

    /**
     * Abre la caja asignada al usuario.
     * La caja se busca en el servidor: el cliente no puede
     * abrir la caja de otra persona ni de otra sucursal.
     */
    public function abrir(int $idUsuario, float $monto, string $observacion): array
    {
        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        $monto = round(max(0, $monto), 2);

        $caja = $this->cajaDeUsuario($idUsuario);
        if (!$caja) {
            return self::err('No tienes una caja activa asignada. Pide a un administrador que te asigne una.');
        }

        $usuario = $this->usuario($idUsuario);
        if (!$usuario) {
            return self::err('Ese usuario ya no existe');
        }

        if ((int) ($usuario['estado'] ?? 0) !== 1) {
            return self::err('Tu usuario está inactivo: no puedes aperturar caja');
        }

        if ((int) ($usuario['id_sucursal'] ?? 0) !== (int) $caja['id_sucursal']) {
            return self::err('Tu usuario no pertenece a la sucursal de esta caja');
        }

        $abierto = $this->abiertoDeCaja((int) $caja['id']);
        if ($abierto) {
            return self::err(
                'La caja ' . $caja['codigo'] . ' ya está abierta desde el ' . $abierto['fecha_apertura']
            );
        }

        if (mb_strlen($observacion) > 255) {
            return self::err('La observación no puede superar los 255 caracteres');
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO arqueos_caja
                    (id_caja, id_sucursal, id_usuario_apertura, estado,
                     monto_apertura, observacion_apertura, fecha_apertura)
                 VALUES
                    (:caja, :sucursal, :usuario, 'abierto',
                     :monto, :observacion, NOW())"
            );

            $stmt->execute([
                ':caja'        => (int) $caja['id'],
                ':sucursal'    => (int) $caja['id_sucursal'],
                ':usuario'     => $idUsuario,
                ':monto'       => $monto,
                ':observacion' => $observacion !== '' ? $observacion : null,
            ]);

            $id = (int) $this->conn->lastInsertId();

            return [
                'exito'   => true,
                'mensaje' => 'Caja aperturada correctamente',
                'id'      => $id,
                'arqueo'  => $this->obtener($id),
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return self::err('Esa caja ya tiene un arqueo abierto');
            }

            error_log('ArqueoCaja::abrir - ' . $e->getMessage());
            return self::err('No se pudo aperturar la caja');
        }
    }

    // =================================================
    //  CIERRE
    // =================================================

    public function cerrar(int $idUsuario, int $idArqueo, float $montoCierre, string $observacion): array
    {
        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        $arqueo = $this->obtener($idArqueo);
        if (!$arqueo) {
            return self::err('Ese arqueo no existe');
        }

        if ($arqueo['estado'] !== 'abierto') {
            return self::err('Ese arqueo ya está cerrado');
        }

        $esResponsable = (int) ($arqueo['id_usuario_asignado'] ?? 0) === $idUsuario;
        $esQuienAbrio = $arqueo['id_usuario_apertura'] === $idUsuario;
        $esSuper = $this->esSuperadmin($idUsuario);

        if (!$esResponsable && !$esQuienAbrio && !$esSuper) {
            return self::err('Sólo el responsable de la caja puede cerrarla');
        }

        $usuario = $this->usuario($idUsuario);
        if (!$usuario) {
            return self::err('Ese usuario ya no existe');
        }

        if ((int) ($usuario['estado'] ?? 0) !== 1) {
            return self::err('Tu usuario está inactivo: no puedes cerrar caja');
        }

        if (!$esSuper && (int) ($usuario['id_sucursal'] ?? 0) !== $arqueo['id_sucursal']) {
            return self::err('Tu usuario no pertenece a la sucursal de este arqueo');
        }

        if ($montoCierre < 0) {
            return self::err('El monto contado no puede ser negativo');
        }

        if (mb_strlen($observacion) > 255) {
            return self::err('La observación no puede superar los 255 caracteres');
        }

        $montoCierre = round($montoCierre, 2);

        // Deja al día total_egresos / efectivo_esperado antes de cerrar.
        $this->recalcularTotales($idArqueo);

        // Efectivo que DEBE haber: apertura + ventas en efectivo - egresos en efectivo.
        $esperado = round($arqueo['monto_apertura'] + $arqueo['total_efectivo'] - $this->egresosEfectivo($idArqueo), 2);
        $diferencia = round($montoCierre - $esperado, 2);

        try {
            $this->conn
                ->prepare(
                    "UPDATE arqueos_caja
                     SET estado = 'cerrado',
                         id_usuario_cierre = :usuario,
                         monto_cierre = :contado,
                         monto_esperado = :esperado,
                         diferencia = :diferencia,
                         observacion_cierre = :observacion,
                         fecha_cierre = NOW()
                     WHERE id_arqueo = :id
                       AND estado = 'abierto'"
                )
                ->execute([
                    ':usuario'     => $idUsuario,
                    ':contado'     => $montoCierre,
                    ':esperado'    => $esperado,
                    ':diferencia'  => $diferencia,
                    ':observacion' => $observacion !== '' ? $observacion : null,
                    ':id'          => $idArqueo,
                ]);

            // Guarda el efectivo esperado aunque el arqueo se cierre con una
            // diferencia (permite reimprimir el ticket de cierre exacto).
            $this->actualizarEfectivoEsperado($idArqueo, $esperado);

            return [
                'exito'   => true,
                'mensaje' => self::mensajeCierre($diferencia),
                'id'      => $idArqueo,
                'arqueo'  => $this->obtener($idArqueo),
            ];
        } catch (PDOException $e) {
            error_log('ArqueoCaja::cerrar - ' . $e->getMessage());
            return self::err('No se pudo cerrar la caja');
        }
    }

    /**
     * Deja actualizados los totales del arqueo abierto de una caja.
     * La usará el módulo de ventas al registrar cada cobro.
     */
   public function acumularVenta(int $idArqueo, float $total, string $formaPago): array
{
    $formas = [
        'efectivo'      => 'total_efectivo',
        'transferencia' => 'total_transferencia',
        'tarjeta'       => 'total_tarjeta',
        'qr'            => 'total_qr',
        'otros'         => 'total_otros',
    ];

    if (!isset($formas[$formaPago])) {
        return self::err('Forma de pago no válida');
    }

    if ($idArqueo <= 0 || abs($total) < 0.005) {
        return self::err('Datos de venta no válidos');
    }

    $columna = $formas[$formaPago];
    $total   = round($total, 2);   // puede ser negativo (anulación)

    try {
        // GREATEST evita que las columnas queden negativas si algo descuadra
        $this->conn->prepare(
            "UPDATE arqueos_caja
                SET total_ventas = GREATEST(0, total_ventas + :total),
                    {$columna}   = GREATEST(0, {$columna} + :total2)
              WHERE id_arqueo = :id AND estado = 'abierto'"
        )->execute([
            ':total'  => $total,
            ':total2' => $total,
            ':id'     => $idArqueo,
        ]);

        // Mantiene el efectivo esperado al día tras cada cobro.
        $this->recalcularTotales($idArqueo);

        return ['exito' => true, 'mensaje' => 'Totales del arqueo actualizados', 'id' => $idArqueo];
    } catch (PDOException $e) {
        error_log('ArqueoCaja::acumularVenta - ' . $e->getMessage());
        return self::err('No se pudieron actualizar los totales');
    }
}
    // =================================================
    /**
     * Recalcula los totales de un arqueo desde las ventas reales
     * (excluyendo las anuladas). Se usa al registrar y al anular
     * una venta para que nunca queden cifras duplicadas o viejas.
     *
     * Actualiza incluso arqueos cerrados: si una venta se anula
     * después del cierre, el "esperado" y la diferencia deben
     * reflejar el cambio.
     */
    public function recalcularVentas(int $idArqueo): array
    {
        if ($idArqueo <= 0) {
            return self::err('Arqueo no válido');
        }

        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return self::err('Arqueo no encontrado');
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) AS filas,
                        COALESCE(SUM(CASE WHEN estado = 'registrada' THEN 1 ELSE 0 END), 0) AS registradas,
                        COALESCE(SUM(CASE WHEN estado = 'registrada' THEN total ELSE 0 END), 0) AS total,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'efectivo' THEN total
                            WHEN estado = 'registrada' AND metodo_pago = 'mixto' THEN mixto_efectivo
                            ELSE 0 END), 0) AS efectivo,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'qr' THEN total
                            WHEN estado = 'registrada' AND metodo_pago = 'mixto' THEN mixto_qr
                            ELSE 0 END), 0) AS qr,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'tarjeta' THEN total
                            WHEN estado = 'registrada' AND metodo_pago = 'mixto' THEN mixto_tarjeta
                            ELSE 0 END), 0) AS tarjeta,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'otro' THEN total
                            ELSE 0 END), 0) AS otros
                   FROM ventas
                  WHERE id_arqueo = :id"
            );
            $stmt->execute([':id' => $idArqueo]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('ArqueoCaja::recalcularVentas - ' . $e->getMessage());
            return self::err('No se pudieron recalcular las ventas del arqueo');
        }

        // Sin filas de ventas (arqueo antiguo): se conserva lo cacheado.
        if ((int) ($r['filas'] ?? 0) === 0) {
            return [
                'exito'       => true,
                'mensaje'     => 'Sin ventas registradas para recalcular',
                'id'          => $idArqueo,
                'sin_cambios' => true,
            ];
        }

        $totalVentas = round((float) $r['total'], 2);
        $efectivo    = round((float) $r['efectivo'], 2);
        $qr          = round((float) $r['qr'], 2);
        $tarjeta     = round((float) $r['tarjeta'], 2);
        $otros       = round((float) $r['otros'], 2);
        $egresosEf   = $this->egresosEfectivo($idArqueo);

        $esperado = round((float) $arqueo['monto_apertura'] + $efectivo - $egresosEf, 2);

        $sets = [
            'total_ventas = :total_ventas',
            'total_efectivo = :total_efectivo',
            'total_qr = :total_qr',
            'total_tarjeta = :total_tarjeta',
            'total_otros = :total_otros',
            'efectivo_esperado = :efectivo_esperado',
        ];

        $params = [
            ':total_ventas'      => $totalVentas,
            ':total_efectivo'    => $efectivo,
            ':total_qr'          => $qr,
            ':total_tarjeta'     => $tarjeta,
            ':total_otros'       => $otros,
            ':efectivo_esperado' => $esperado,
            ':id'                => $idArqueo,
        ];

        if ($this->columnaArqueo('total_transferencia')) {
            $sets[] = 'total_transferencia = 0';
        }

        if ($arqueo['estado'] === 'cerrado' && $arqueo['monto_cierre'] !== null) {
            $contado = (float) $arqueo['monto_cierre'];

            $sets[] = 'monto_esperado = :monto_esperado';
            $sets[] = 'diferencia = :diferencia';
            $params[':monto_esperado'] = $esperado;
            $params[':diferencia']     = round($contado - $esperado, 2);
        }

        try {
            $this->conn->prepare(
                'UPDATE arqueos_caja SET ' . implode(', ', $sets) . ' WHERE id_arqueo = :id'
            )->execute($params);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::recalcularVentas(update) - ' . $e->getMessage());
            return self::err('No se pudieron actualizar los totales del arqueo');
        }

        return [
            'exito'             => true,
            'mensaje'           => 'Totales del arqueo recalculados',
            'id'                => $idArqueo,
            'total_ventas'      => $totalVentas,
            'total_efectivo'    => $efectivo,
            'total_qr'          => $qr,
            'total_tarjeta'     => $tarjeta,
            'total_otros'       => $otros,
            'efectivo_esperado' => $esperado,
        ];
    }

    //  MOVIMIENTOS DE CAJA (EGRESOS / GASTOS)
    // -------------------------------------------------
    // Mientras el arqueo está abierto se pueden registrar
    // egresos y gastos. Los que salen en EFECTIVO reducen
    // el efectivo esperado del cierre; los demás medios
    // quedan sólo como información del turno.
    // =================================================

    /** ¿La tabla `arqueo_movimientos` ya fue creada? */
    public function movimientosDisponibles(): bool
    {
        return $this->tablaMovimientosExiste();
    }

    /**
     * Contexto de alcance del usuario:
     *   es_superadmin · id_sucursal · id_caja · solo_su_caja · acciones
     */
    public function contextoUsuario(int $idUsuario): ?array
    {
        $usuario = $this->usuario($idUsuario);

        if (!$usuario) {
            return null;
        }

        $caja = $this->cajaDeUsuario($idUsuario);
        $acciones = $this->accionesItem($idUsuario, $this->itemArqueo());
        $accionesCajas = $this->accionesItem($idUsuario, $this->itemCajas());
        $esSuper = $this->esSuperadmin($idUsuario);

        return [
            'id'             => (int) $usuario['id_usuario'],
            'username'       => (string) $usuario['username'],
            'id_sucursal'    => (int) ($usuario['id_sucursal'] ?? 0),
            'estado'         => (int) ($usuario['estado'] ?? 0),
            'id_caja'        => $caja ? (int) $caja['id'] : 0,
            'caja'           => $caja ? $caja['codigo'] . ' · ' . $caja['nombre'] : '',
            'es_superadmin'  => $esSuper,
            'solo_su_caja'   => !$esSuper && empty($acciones['modificar']) && empty($accionesCajas['modificar']),
            'acciones'       => $acciones,
            'acciones_cajas' => $accionesCajas,
        ];
    }

    /**
     * Alcance de consulta:
     *   · SuperAdmin: todos los arqueos.
     *   · Usuario normal: los de su sucursal.
     *   · Cajero sin privilegio de modificar (arqueos y cajas):
     *     sólo los de su caja asignada.
     */
    public function resolverAlcance(int $idUsuario): array
    {
        $r = [
            'id_usuario'    => $idUsuario,
            'es_superadmin' => $this->esSuperadmin($idUsuario),
            'id_sucursal'   => 0,
            'id_caja'       => 0,
            'solo_su_caja'  => false,
        ];

        $usuario = $this->usuario($idUsuario);

        if ($usuario) {
            $r['id_sucursal'] = (int) ($usuario['id_sucursal'] ?? 0);
        }

        $caja = $this->cajaDeUsuario($idUsuario);

        if ($caja) {
            $r['id_caja'] = (int) $caja['id'];
            $r['id_sucursal'] = (int) $caja['id_sucursal'];
        }

        if (!$r['es_superadmin']) {
            $acciones = $this->accionesItem($idUsuario, $this->itemArqueo());
            $accionesCajas = $this->accionesItem($idUsuario, $this->itemCajas());

            $r['solo_su_caja'] = empty($acciones['modificar']) && empty($accionesCajas['modificar']);
        }

        return $r;
    }

    /**
     * ¿Puede el usuario operar / consultar este arqueo?
     * Devuelve ['permitido'=>bool, 'codigo'=>int, 'mensaje'=>string, 'contexto'=>array].
     */
    public function validarAccesoArqueo(int $idUsuario, array $arqueo, bool $exigirCaja = true): array
    {
        $ctx = $this->contextoUsuario($idUsuario);

        if (!$ctx) {
            return ['permitido' => false, 'codigo' => 401, 'mensaje' => 'Sesión no válida', 'contexto' => []];
        }

        if ((int) ($ctx['estado'] ?? 0) !== 1) {
            return ['permitido' => false, 'codigo' => 403, 'mensaje' => 'Tu usuario está inactivo', 'contexto' => $ctx];
        }

        if ($ctx['es_superadmin']) {
            return ['permitido' => true, 'codigo' => 200, 'mensaje' => 'ok', 'contexto' => $ctx];
        }

        if ((int) $arqueo['id_sucursal'] !== (int) $ctx['id_sucursal']) {
            return [
                'permitido' => false,
                'codigo'    => 403,
                'mensaje'   => 'No puedes operar arqueos de otra sucursal',
                'contexto'  => $ctx,
            ];
        }

        if ($exigirCaja && $ctx['solo_su_caja'] && (int) $ctx['id_caja'] > 0) {
            if ((int) $arqueo['id_caja'] !== (int) $ctx['id_caja']) {
                return [
                    'permitido' => false,
                    'codigo'    => 403,
                    'mensaje'   => 'Sólo puedes operar la caja asignada a tu usuario',
                    'contexto'  => $ctx,
                ];
            }
        }

        return ['permitido' => true, 'codigo' => 200, 'mensaje' => 'ok', 'contexto' => $ctx];
    }

    /**
     * Ventas cobradas en un arqueo (turno).
     * No incluye compras: sólo el detalle del punto de venta.
     */
    public function ventasDeArqueo(int $idArqueo, int $limite = 2000): array
    {
        if ($idArqueo <= 0) {
            return [];
        }

        $sql = "SELECT v.*,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_usuario
                FROM ventas v
                LEFT JOIN usuarios u        ON u.id_usuario = v.id_usuario
                LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                WHERE v.id_arqueo = :id
                ORDER BY v.fecha_venta DESC, v.id_venta DESC
                LIMIT " . max(1, min(5000, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idArqueo]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::ventasDeArqueo - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizarVenta($f), $filas);
    }

    /**
     * Detalle completo para el modal "Movimientos de caja":
     * ventas, egresos/gastos y resumen del turno.
     */
    public function movimientosCaja(int $idArqueo): array
    {
        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return [];
        }

        return [
            'arqueo'      => $arqueo,
            'ventas'      => $this->ventasDeArqueo($idArqueo),
            'movimientos' => $this->movimientosDeArqueo($idArqueo),
            'resumen'     => $this->resumenCierre($idArqueo),
        ];
    }

    /** Egresos / gastos de un arqueo (más antiguos primero). */
    public function movimientosDeArqueo(int $idArqueo, int $limite = 1000): array
    {
        if ($idArqueo <= 0 || !$this->tablaMovimientosExiste()) {
            return [];
        }

        $sql = "SELECT m.*,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_usuario
                FROM arqueo_movimientos m
                INNER JOIN usuarios u            ON u.id_usuario = m.id_usuario
                LEFT  JOIN usuarios_datos ud     ON ud.id_usuario = u.id_usuario
                WHERE m.id_arqueo = :id
                ORDER BY m.fecha ASC, m.id_movimiento ASC
                LIMIT " . max(1, min(3000, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idArqueo]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::movimientosDeArqueo - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizarMovimiento($f), $filas);
    }

    /**
     * Registra un egreso / gasto del turno abierto.
     * Sólo arqueos en estado 'abierto'.
     */
    public function registrarMovimiento(int $idArqueo, array $datos, int $idUsuario): array
    {
        if ($idArqueo <= 0) {
            return self::err('Arqueo no válido');
        }

        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        if (!$this->tablaMovimientosExiste()) {
            return self::err('Falta ejecutar sql_arqueo_movimientos.sql: la tabla de egresos/gastos no existe');
        }

        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return self::err('Ese arqueo no existe');
        }

        if ($arqueo['estado'] !== 'abierto') {
            return self::err('Sólo puedes registrar egresos o gastos con el arqueo abierto');
        }

        $tipo = strtolower(trim((string) ($datos['tipo'] ?? 'egreso')));

        if (!in_array($tipo, self::TIPOS_MOVIMIENTO, true)) {
            return self::err('Tipo de movimiento no válido: usa egreso o gasto');
        }

        $concepto = trim(strip_tags((string) ($datos['concepto'] ?? '')));

        if ($concepto === '') {
            return self::err('Escribe el concepto del egreso o gasto');
        }

        if (mb_strlen($concepto) > 200) {
            return self::err('El concepto no puede superar los 200 caracteres');
        }

        $monto = round((float) ($datos['monto'] ?? 0), 2);

        if ($monto <= 0) {
            return self::err('El monto del movimiento debe ser mayor que cero');
        }

        $metodo = strtolower(trim((string) ($datos['metodo_pago'] ?? 'efectivo')));

        if (!in_array($metodo, self::METODOS_MOVIMIENTO, true)) {
            return self::err('Medio de pago no válido para el movimiento');
        }

        $referencia = trim(strip_tags((string) ($datos['referencia'] ?? '')));

        if (mb_strlen($referencia) > 100) {
            $referencia = mb_substr($referencia, 0, 100);
        }

        $observacion = trim(strip_tags((string) ($datos['observacion'] ?? '')));

        if (mb_strlen($observacion) > 255) {
            $observacion = mb_substr($observacion, 0, 255);
        }

        try {
            $this->conn
                ->prepare(
                    "INSERT INTO arqueo_movimientos
                        (id_arqueo, id_sucursal, id_usuario, tipo, concepto, monto,
                         metodo_pago, referencia, observacion, fecha)
                     VALUES
                        (:arqueo, :sucursal, :usuario, :tipo, :concepto, :monto,
                         :metodo, :referencia, :observacion, NOW())"
                )
                ->execute([
                    ':arqueo'      => $idArqueo,
                    ':sucursal'    => (int) $arqueo['id_sucursal'],
                    ':usuario'     => $idUsuario,
                    ':tipo'        => $tipo,
                    ':concepto'    => $concepto,
                    ':monto'       => $monto,
                    ':metodo'      => $metodo,
                    ':referencia'  => $referencia !== '' ? $referencia : null,
                    ':observacion' => $observacion !== '' ? $observacion : null,
                ]);

            $idMovimiento = (int) $this->conn->lastInsertId();

            $this->recalcularTotales($idArqueo);

            $movimiento = $this->buscarMovimiento($idArqueo, $idMovimiento);

            return [
                'exito'      => true,
                'mensaje'    => ($tipo === 'gasto' ? 'Gasto' : 'Egreso') . ' registrado correctamente',
                'id'         => $idMovimiento,
                'movimiento' => $movimiento,
                'arqueo'     => $this->obtener($idArqueo),
            ];
        } catch (PDOException $e) {
            error_log('ArqueoCaja::registrarMovimiento - ' . $e->getMessage());
            return self::err('No se pudo registrar el movimiento');
        }
    }

    /** Elimina un egreso / gasto del arqueo abierto. */
    public function eliminarMovimiento(int $idArqueo, int $idMovimiento, int $idUsuario): array
    {
        if ($idArqueo <= 0 || $idMovimiento <= 0) {
            return self::err('Movimiento no válido');
        }

        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        if (!$this->tablaMovimientosExiste()) {
            return self::err('Falta ejecutar sql_arqueo_movimientos.sql: la tabla de egresos/gastos no existe');
        }

        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return self::err('Ese arqueo no existe');
        }

        if ($arqueo['estado'] !== 'abierto') {
            return self::err('Sólo puedes eliminar egresos o gastos con el arqueo abierto');
        }

        $movimiento = $this->buscarMovimiento($idArqueo, $idMovimiento);

        if (!$movimiento) {
            return self::err('Ese movimiento ya no existe en este arqueo');
        }

        try {
            $this->conn
                ->prepare('DELETE FROM arqueo_movimientos WHERE id_movimiento = :mov AND id_arqueo = :arqueo')
                ->execute([':mov' => $idMovimiento, ':arqueo' => $idArqueo]);

            $this->recalcularTotales($idArqueo);

            return [
                'exito'   => true,
                'mensaje' => 'Movimiento eliminado correctamente',
                'id'      => $idMovimiento,
                'arqueo'  => $this->obtener($idArqueo),
            ];
        } catch (PDOException $e) {
            error_log('ArqueoCaja::eliminarMovimiento - ' . $e->getMessage());
            return self::err('No se pudo eliminar el movimiento');
        }
    }

    /**
     * Corrige el monto / observación de apertura (sólo arqueo abierto).
     * Recalcula el efectivo esperado del turno.
     */
    public function actualizarApertura(int $idArqueo, float $monto, string $observacion, int $idUsuario): array
    {
        if ($idArqueo <= 0) {
            return self::err('Arqueo no válido');
        }

        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return self::err('Ese arqueo no existe');
        }

        if ($arqueo['estado'] !== 'abierto') {
            return self::err('Sólo puedes modificar la apertura de un arqueo abierto');
        }

        if ($monto < 0) {
            return self::err('El monto de apertura no puede ser negativo');
        }

        $monto = round($monto, 2);
        $observacion = trim(strip_tags($observacion));

        if (mb_strlen($observacion) > 255) {
            return self::err('La observación no puede superar los 255 caracteres');
        }

        try {
            $this->conn
                ->prepare(
                    "UPDATE arqueos_caja
                        SET monto_apertura = :monto,
                            observacion_apertura = :observacion
                      WHERE id_arqueo = :id
                        AND estado = 'abierto'"
                )
                ->execute([
                    ':monto'       => $monto,
                    ':observacion' => $observacion !== '' ? $observacion : null,
                    ':id'          => $idArqueo,
                ]);

            $this->recalcularTotales($idArqueo);

            return [
                'exito'   => true,
                'mensaje' => 'Apertura del arqueo actualizada correctamente',
                'id'      => $idArqueo,
                'arqueo'  => $this->obtener($idArqueo),
            ];
        } catch (PDOException $e) {
            error_log('ArqueoCaja::actualizarApertura - ' . $e->getMessage());
            return self::err('No se pudo modificar la apertura del arqueo');
        }
    }

    /**
     * Reabre un arqueo cerrado para corregirlo.
     * Sólo el SuperAdministrador puede hacerlo.
     */
    public function reabrirArqueo(int $idArqueo, int $idUsuario): array
    {
        if ($idArqueo <= 0) {
            return self::err('Arqueo no válido');
        }

        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        if (!$this->esSuperadmin($idUsuario)) {
            return self::err('Sólo el SuperAdministrador puede reabrir una caja cerrada');
        }

        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return self::err('Ese arqueo no existe');
        }

        if ($arqueo['estado'] !== 'cerrado') {
            return self::err('Ese arqueo ya está abierto');
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM arqueos_caja
                  WHERE id_caja = :caja AND estado = 'abierto' AND id_arqueo <> :id"
            );
            $stmt->execute([
                ':caja' => (int) $arqueo['id_caja'],
                ':id'   => $idArqueo,
            ]);

            if ((int) $stmt->fetchColumn() > 0) {
                return self::err('No se puede reabrir: esa caja ya tiene otro turno abierto. Ciérralo primero.');
            }
        } catch (PDOException $e) {
            error_log('ArqueoCaja::reabrirArqueo(verificar) - ' . $e->getMessage());
            return self::err('No se pudo verificar el estado de la caja');
        }

        $sets = [
            "estado = 'abierto'",
            'id_usuario_cierre = NULL',
            'monto_cierre = NULL',
            'monto_esperado = NULL',
            'diferencia = NULL',
            'fecha_cierre = NULL',
            'observacion_cierre = NULL',
        ];

        if ($this->columnaArqueo('efectivo_esperado')) {
            $sets[] = 'efectivo_esperado = NULL';
        }

        if ($this->columnaArqueo('ticket_cierre_generado')) {
            $sets[] = 'ticket_cierre_generado = 0';
        }

        try {
            $this->conn
                ->prepare(
                    'UPDATE arqueos_caja SET ' . implode(', ', $sets) . '
                      WHERE id_arqueo = :id AND estado = :estado'
                )
                ->execute([
                    ':id'     => $idArqueo,
                    ':estado' => 'cerrado',
                ]);

            // Deja el efectivo esperado al día para el turno reabierto.
            $this->recalcularTotales($idArqueo);

            return [
                'exito'   => true,
                'mensaje' => 'Caja reabierta. Corrige lo necesario y vuelve a cerrarla.',
                'id'      => $idArqueo,
                'arqueo'  => $this->obtener($idArqueo),
            ];
        } catch (PDOException $e) {
            error_log('ArqueoCaja::reabrirArqueo - ' . $e->getMessage());
            return self::err('No se pudo reabrir la caja');
        }
    }

    /**
     * Elimina un arqueo.
     *   · Sólo si está cerrado (el SuperAdmin también puede abierto).
     *   · Nunca si tiene ventas asociadas.
     *   · Borra antes sus egresos / gastos.
     */
    public function eliminarArqueo(int $idArqueo, int $idUsuario): array
    {
        if ($idArqueo <= 0) {
            return self::err('Arqueo no válido');
        }

        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return self::err('Ese arqueo no existe');
        }

        $esSuper = $this->esSuperadmin($idUsuario);

        if ($arqueo['estado'] === 'abierto' && !$esSuper) {
            return self::err('Cierra el arqueo antes de eliminarlo (sólo el SuperAdmin puede borrar un arqueo abierto)');
        }

        $ventas = $this->contarVentasDeArqueo($arqueo);

        if ($ventas > 0) {
            return self::err('No se puede eliminar: el arqueo tiene ' . $ventas . ' venta(s) asociadas');
        }

        try {
            $this->conn->beginTransaction();

            if ($this->tablaMovimientosExiste()) {
                $this->conn
                    ->prepare('DELETE FROM arqueo_movimientos WHERE id_arqueo = :id')
                    ->execute([':id' => $idArqueo]);
            }

            $this->conn
                ->prepare('DELETE FROM arqueos_caja WHERE id_arqueo = :id')
                ->execute([':id' => $idArqueo]);

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Arqueo eliminado correctamente',
                'id'      => $idArqueo,
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('ArqueoCaja::eliminarArqueo - ' . $e->getMessage());

            return self::err('No se pudo eliminar el arqueo');
        }
    }

    /**
     * Resumen completo del cierre: totales por medio de pago,
     * egresos / gastos, efectivo esperado y diferencia.
     */
    public function resumenCierre(int $idArqueo): array
    {
        $arqueo = $this->obtener($idArqueo);

        if (!$arqueo) {
            return [];
        }

        $movimientos = $this->movimientosDeArqueo($idArqueo);

        $porMetodo = [
            'efectivo'      => 0.0,
            'transferencia' => 0.0,
            'qr'            => 0.0,
            'tarjeta'       => 0.0,
            'otro'          => 0.0,
        ];

        $totalEgresos = 0.0;
        $egresosEfectivo = 0.0;
        $totalGastos = 0.0;

        foreach ($movimientos as $m) {
            $monto = (float) $m['monto'];
            $totalEgresos += $monto;
            $porMetodo[$m['metodo_pago']] = ($porMetodo[$m['metodo_pago']] ?? 0) + $monto;

            if ($m['metodo_pago'] === 'efectivo') {
                $egresosEfectivo += $monto;
            }

            if ($m['tipo'] === 'gasto') {
                $totalGastos += $monto;
            }
        }

        $apertura       = (float) $arqueo['monto_apertura'];
        $totalVentas    = (float) $arqueo['total_ventas'];
        $totalEfectivo  = (float) $arqueo['total_efectivo'];
        $totalTransf    = (float) $arqueo['total_transferencia'];
        $totalQr        = (float) $arqueo['total_qr'];
        $totalTarjeta   = (float) $arqueo['total_tarjeta'];
        $totalOtros     = (float) $arqueo['total_otros'];

        // Fuente de verdad: las ventas reales del arqueo, excluyendo
        // las anuladas. Si no hay filas (arqueo antiguo), se conserva
        // lo cacheado en arqueos_caja.
        try {
            $stVentas = $this->conn->prepare(
                "SELECT COUNT(*) AS filas,
                        COALESCE(SUM(CASE WHEN estado = 'registrada' THEN total ELSE 0 END), 0) AS total,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'efectivo' THEN total
                            WHEN estado = 'registrada' AND metodo_pago = 'mixto' THEN mixto_efectivo
                            ELSE 0 END), 0) AS efectivo,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'qr' THEN total
                            WHEN estado = 'registrada' AND metodo_pago = 'mixto' THEN mixto_qr
                            ELSE 0 END), 0) AS qr,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'tarjeta' THEN total
                            WHEN estado = 'registrada' AND metodo_pago = 'mixto' THEN mixto_tarjeta
                            ELSE 0 END), 0) AS tarjeta,
                        COALESCE(SUM(CASE
                            WHEN estado = 'registrada' AND metodo_pago = 'otro' THEN total
                            ELSE 0 END), 0) AS otros
                   FROM ventas
                  WHERE id_arqueo = :id"
            );
            $stVentas->execute([':id' => $idArqueo]);
            $v = $stVentas->fetch(PDO::FETCH_ASSOC) ?: [];

            if ((int) ($v['filas'] ?? 0) > 0) {
                $totalVentas   = round((float) $v['total'], 2);
                $totalEfectivo = round((float) $v['efectivo'], 2);
                $totalQr       = round((float) $v['qr'], 2);
                $totalTarjeta  = round((float) $v['tarjeta'], 2);
                $totalOtros    = round((float) $v['otros'], 2);
                $totalTransf   = 0.0;
            }
        } catch (PDOException $e) {
            error_log('ArqueoCaja::resumenCierre(ventas) - ' . $e->getMessage());
        }

        // Egresos: si la tabla de movimientos existe, manda la suma real
        // (una eliminación también debe reflejarse). Si no existe, se
        // conserva el total cacheado.
        $totalEgresos = $this->movimientosDisponibles()
            ? round($totalEgresos, 2)
            : round((float) ($arqueo['total_egresos'] ?? 0), 2);

        $efectivoEsperado = round($apertura + $totalEfectivo - $egresosEfectivo, 2);

        return [
            'id'                  => $idArqueo,
            'estado'              => $arqueo['estado'],
            'id_caja'             => (int) $arqueo['id_caja'],
            'id_sucursal'         => (int) $arqueo['id_sucursal'],
            'caja_codigo'         => (string) $arqueo['caja_codigo'],
            'caja_nombre'         => (string) $arqueo['caja_nombre'],
            'sucursal'            => (string) $arqueo['sucursal'],
            'ciudad'              => (string) $arqueo['ciudad'],
            'usuario_apertura'    => (string) $arqueo['usuario_apertura'],
            'usuario_cierre'      => (string) $arqueo['usuario_cierre'],
            'fecha_apertura'      => (string) $arqueo['fecha_apertura'],
            'fecha_cierre'        => $arqueo['fecha_cierre'],
            'monto_apertura'      => round($apertura, 2),
            'total_ventas'        => round($totalVentas, 2),
            'total_efectivo'      => round($totalEfectivo, 2),
            'total_transferencia' => round($totalTransf, 2),
            'total_qr'            => round($totalQr, 2),
            'total_tarjeta'       => round($totalTarjeta, 2),
            'total_otros'         => round($totalOtros, 2),
            'total_egresos'       => round($totalEgresos, 2),
            'total_egresos_efectivo' => round($egresosEfectivo, 2),
            'total_gastos'        => round($totalGastos, 2),
            'egresos_por_metodo'  => array_map(static fn(float $v): float => round($v, 2), $porMetodo),
            'efectivo_esperado'   => $efectivoEsperado,
            'esperado_por_metodo' => [
                'efectivo'      => $efectivoEsperado,
                'transferencia' => round($totalTransf, 2),
                'qr'            => round($totalQr, 2),
                'tarjeta'       => round($totalTarjeta, 2),
                'otros'         => round($totalOtros, 2),
            ],
            'monto_esperado'      => $arqueo['estado'] === 'cerrado'
                ? round($efectivoEsperado, 2)
                : $arqueo['monto_esperado'],
            'monto_cierre'        => $arqueo['monto_cierre'],
            'diferencia'          => $arqueo['monto_cierre'] !== null
                ? round((float) $arqueo['monto_cierre'] - $efectivoEsperado, 2)
                : $arqueo['diferencia'],
            'movimientos'         => $movimientos,
            'movimientos_total'   => count($movimientos),
        ];
    }

    /** Marca que el ticket de cierre ya fue emitido (si la columna existe). */
    public function marcarTicketCierre(int $idArqueo): void
    {
        if ($idArqueo <= 0 || !$this->columnaArqueo('ticket_cierre_generado')) {
            return;
        }

        try {
            $this->conn
                ->prepare('UPDATE arqueos_caja SET ticket_cierre_generado = 1 WHERE id_arqueo = :id')
                ->execute([':id' => $idArqueo]);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::marcarTicketCierre - ' . $e->getMessage());
        }
    }

    // ---- Auxiliares privados de movimientos / alcance ----

    private function tablaMovimientosExiste(): bool
    {
        if ($this->tablaMovimientos !== null) {
            return $this->tablaMovimientos;
        }

        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'arqueo_movimientos'");
            $this->tablaMovimientos = (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('ArqueoCaja::tablaMovimientosExiste - ' . $e->getMessage());
            $this->tablaMovimientos = false;
        }

        return $this->tablaMovimientos;
    }

    /**
     * Expresión SQL que devuelve el total real de egresos/gastos
     * del arqueo, aunque la columna cacheada esté desactualizada.
     */
    private function exprEgresos(string $alias = 'a'): string
    {
        if ($this->tablaMovimientosExiste()) {
            $respaldo = $this->columnaArqueo('total_egresos')
                ? $alias . '.total_egresos'
                : '0';

            return 'COALESCE(NULLIF((SELECT COALESCE(SUM(m.monto), 0)
                                      FROM arqueo_movimientos m
                                     WHERE m.id_arqueo = ' . $alias . '.id_arqueo), 0), '
                . $respaldo . ', 0) AS total_egresos_calc';
        }

        if ($this->columnaArqueo('total_egresos')) {
            return $alias . '.total_egresos AS total_egresos_calc';
        }

        return '0 AS total_egresos_calc';
    }

    /** ¿La columna existe en `arqueos_caja`? (compatibilidad sin migrar). */
    private function columnaArqueo(string $columna): bool
    {
        if ($this->columnasArqueo === null) {
            $this->columnasArqueo = [];

            try {
                $filas = $this->conn->query('SHOW COLUMNS FROM arqueos_caja')->fetchAll(PDO::FETCH_ASSOC);

                foreach ($filas as $fila) {
                    if (!empty($fila['Field'])) {
                        $this->columnasArqueo[strtolower((string) $fila['Field'])] = true;
                    }
                }
            } catch (PDOException $e) {
                error_log('ArqueoCaja::columnaArqueo - ' . $e->getMessage());
            }
        }

        return !empty($this->columnasArqueo[strtolower($columna)]);
    }

    private function esSuperadmin(int $idUsuario): bool
    {
        if ($idUsuario <= 0) {
            return false;
        }

        if (class_exists('Permisos')) {
            try {
                return Permisos::instancia()->esSuperAdmin($idUsuario);
            } catch (Throwable $e) {
                error_log('ArqueoCaja::esSuperadmin - ' . $e->getMessage());

                return false;
            }
        }

        return function_exists('rm_es_superadmin') && rm_es_superadmin();
    }

    private function tienePermiso(int $idUsuario, int $idItem, string $accion): bool
    {
        if ($idUsuario <= 0 || $idItem <= 0) {
            return false;
        }

        if (class_exists('Permisos')) {
            try {
                return Permisos::instancia()->tiene($idUsuario, $idItem, $accion);
            } catch (Throwable $e) {
                error_log('ArqueoCaja::tienePermiso - ' . $e->getMessage());

                return false;
            }
        }

        return function_exists('rm_puede') && rm_puede($accion, $idItem);
    }

    private function accionesItem(int $idUsuario, int $idItem): array
    {
        if ($idUsuario > 0 && class_exists('Permisos')) {
            try {
                return Permisos::instancia()->accionesItem($idUsuario, $idItem);
            } catch (Throwable $e) {
                error_log('ArqueoCaja::accionesItem - ' . $e->getMessage());
            }
        }

        if (function_exists('rm_acciones')) {
            return rm_acciones($idItem);
        }

        return ['ver' => false, 'registrar' => false, 'modificar' => false, 'eliminar' => false];
    }

    private function itemArqueo(): int
    {
        return class_exists('Permisos') ? (int) Permisos::ITEM_VENTAS_ARQUEO : 9;
    }

    private function itemCajas(): int
    {
        return class_exists('Permisos') ? (int) Permisos::ITEM_VENTAS_CAJAS : 8;
    }

    /** Recalcula total_egresos y efectivo_esperado del arqueo. */
    private function recalcularTotales(int $idArqueo): void
    {
        if ($idArqueo <= 0 || !$this->tablaMovimientosExiste()) {
            return;
        }

        $totalEgresos = 0.0;
        $egresosEfectivo = 0.0;

        try {
            $stmt = $this->conn->prepare(
                "SELECT COALESCE(SUM(monto), 0) AS total,
                        COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END), 0) AS efectivo
                   FROM arqueo_movimientos
                  WHERE id_arqueo = :id"
            );
            $stmt->execute([':id' => $idArqueo]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'efectivo' => 0];
            $totalEgresos = round((float) $fila['total'], 2);
            $egresosEfectivo = round((float) $fila['efectivo'], 2);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::recalcularTotales - ' . $e->getMessage());

            return;
        }

        $sets = [];
        $params = [':id' => $idArqueo];

        if ($this->columnaArqueo('total_egresos')) {
            $sets[] = 'total_egresos = :egresos';
            $params[':egresos'] = $totalEgresos;
        }

        if ($this->columnaArqueo('efectivo_esperado')) {
            $sets[] = 'efectivo_esperado = ROUND(monto_apertura + total_efectivo - :efectivo, 2)';
            $params[':efectivo'] = $egresosEfectivo;
        }

        if ($sets === []) {
            return;
        }

        try {
            $this->conn
                ->prepare('UPDATE arqueos_caja SET ' . implode(', ', $sets) . ' WHERE id_arqueo = :id')
                ->execute($params);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::recalcularTotales(update) - ' . $e->getMessage());
        }
    }

    /** Total de egresos pagados en efectivo del arqueo. */
    private function egresosEfectivo(int $idArqueo): float
    {
        if ($idArqueo <= 0 || !$this->tablaMovimientosExiste()) {
            return 0.0;
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT COALESCE(SUM(monto), 0)
                   FROM arqueo_movimientos
                  WHERE id_arqueo = :id AND metodo_pago = 'efectivo'"
            );
            $stmt->execute([':id' => $idArqueo]);

            return round((float) $stmt->fetchColumn(), 2);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::egresosEfectivo - ' . $e->getMessage());

            return 0.0;
        }
    }

    private function actualizarEfectivoEsperado(int $idArqueo, float $esperado): void
    {
        if ($idArqueo <= 0 || !$this->columnaArqueo('efectivo_esperado')) {
            return;
        }

        try {
            $this->conn
                ->prepare('UPDATE arqueos_caja SET efectivo_esperado = :esperado WHERE id_arqueo = :id')
                ->execute([':esperado' => round($esperado, 2), ':id' => $idArqueo]);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::actualizarEfectivoEsperado - ' . $e->getMessage());
        }
    }

    /** Cuenta las ventas asociadas al arqueo (incluye dato legado = id_caja). */
    private function contarVentasDeArqueo(array $arqueo): int
    {
        try {
            $stmt = $this->conn->prepare(
                'SELECT COUNT(*) FROM ventas WHERE id_arqueo = :arqueo OR id_arqueo = :caja'
            );
            $stmt->execute([
                ':arqueo' => (int) $arqueo['id'],
                ':caja'   => (int) $arqueo['id_caja'],
            ]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('ArqueoCaja::contarVentasDeArqueo - ' . $e->getMessage());

            return 0;
        }
    }

    private function buscarMovimiento(int $idArqueo, int $idMovimiento): ?array
    {
        if (!$this->tablaMovimientosExiste()) {
            return null;
        }

        $sql = "SELECT m.*,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_usuario
                FROM arqueo_movimientos m
                INNER JOIN usuarios u        ON u.id_usuario = m.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                WHERE m.id_movimiento = :mov AND m.id_arqueo = :arqueo
                LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':mov' => $idMovimiento, ':arqueo' => $idArqueo]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::buscarMovimiento - ' . $e->getMessage());

            return null;
        }

        return $fila ? $this->normalizarMovimiento($fila) : null;
    }

    private function normalizarVenta(array $f): array
    {
        $metodos = [
            'efectivo' => 'Efectivo',
            'qr'       => 'QR',
            'tarjeta'  => 'Tarjeta',
            'mixto'    => 'Mixto',
            'otro'     => 'Otro',
        ];

        $tipos = [
            'mesa'     => 'Mesa',
            'llevar'   => 'Para llevar',
            'delivery' => 'Delivery',
        ];

        $metodo = (string) ($f['metodo_pago'] ?? 'efectivo');
        $tipo = (string) ($f['tipo_pedido'] ?? 'mesa');
        $estado = (string) ($f['estado'] ?? 'registrada');
        $nombre = trim((string) ($f['nombre_usuario'] ?? ''));

        $pagos = [];

        if ($metodo === 'mixto') {
            if ((float) ($f['mixto_efectivo'] ?? 0) > 0.004) {
                $pagos[] = 'Efectivo ' . number_format((float) $f['mixto_efectivo'], 2, ',', '.');
            }
            if ((float) ($f['mixto_qr'] ?? 0) > 0.004) {
                $pagos[] = 'QR ' . number_format((float) $f['mixto_qr'], 2, ',', '.');
            }
            if ((float) ($f['mixto_tarjeta'] ?? 0) > 0.004) {
                $pagos[] = 'Tarjeta ' . number_format((float) $f['mixto_tarjeta'], 2, ',', '.');
            }
        }

        return [
            'id'                 => (int) $f['id_venta'],
            'codigo'             => (string) ($f['codigo'] ?? ''),
            'fecha'              => !empty($f['fecha_venta']) ? date('d/m/Y H:i', strtotime($f['fecha_venta'])) : '—',
            'fecha_iso'          => (string) ($f['fecha_venta'] ?? ''),
            'hora'               => !empty($f['fecha_venta']) ? date('H:i', strtotime($f['fecha_venta'])) : '—',
            'subtotal'           => (float) ($f['subtotal'] ?? 0),
            'descuento'          => (float) ($f['descuento'] ?? 0),
            'impuesto'           => (float) ($f['impuesto'] ?? 0),
            'total'              => (float) ($f['total'] ?? 0),
            'metodo_pago'        => $metodo,
            'metodo_pago_label'  => $metodos[$metodo] ?? ucfirst($metodo),
            'tipo_pedido'        => $tipo,
            'tipo_pedido_label'  => $tipos[$tipo] ?? ucfirst($tipo),
            'estado'             => $estado,
            'estado_label'       => $estado === 'anulada' ? 'Anulada' : 'Registrada',
            'anulada'            => $estado === 'anulada',
            'referencia'         => (string) ($f['referencia_pago'] ?? ''),
            'recibido'           => (float) ($f['recibido'] ?? 0),
            'cambio'             => (float) ($f['cambio'] ?? 0),
            'mixto_efectivo'     => (float) ($f['mixto_efectivo'] ?? 0),
            'mixto_qr'           => (float) ($f['mixto_qr'] ?? 0),
            'mixto_tarjeta'      => (float) ($f['mixto_tarjeta'] ?? 0),
            'pago_detalle'       => implode(' · ', $pagos),
            'delivery_nombre'    => (string) ($f['delivery_nombre'] ?? ''),
            'usuario'            => $nombre !== '' ? $nombre : (string) ($f['username'] ?? ''),
        ];
    }

    private function normalizarMovimiento(array $f): array
    {
        $metodos = [
            'efectivo'      => 'Efectivo',
            'transferencia' => 'Transferencia',
            'qr'            => 'QR',
            'tarjeta'       => 'Tarjeta',
            'otro'          => 'Otro',
        ];

        $nombre = trim((string) ($f['nombre_usuario'] ?? ''));
        $tipo = (string) ($f['tipo'] ?? 'egreso');
        $metodo = (string) ($f['metodo_pago'] ?? 'efectivo');

        return [
            'id'                => (int) $f['id_movimiento'],
            'id_arqueo'         => (int) $f['id_arqueo'],
            'id_sucursal'       => (int) $f['id_sucursal'],
            'id_usuario'        => (int) $f['id_usuario'],
            'tipo'              => $tipo,
            'tipo_label'        => $tipo === 'gasto' ? 'Gasto' : 'Egreso',
            'concepto'          => (string) $f['concepto'],
            'monto'             => (float) $f['monto'],
            'metodo_pago'       => $metodo,
            'metodo_pago_label' => $metodos[$metodo] ?? ucfirst($metodo),
            'referencia'        => (string) ($f['referencia'] ?? ''),
            'observacion'       => (string) ($f['observacion'] ?? ''),
            'fecha'             => !empty($f['fecha']) ? date('d/m/Y H:i', strtotime($f['fecha'])) : '—',
            'fecha_iso'         => (string) ($f['fecha'] ?? ''),
            'usuario'           => $nombre !== '' ? $nombre : (string) ($f['username'] ?? ''),
        ];
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0];
    }

    private static function mensajeCierre(float $diferencia): string
    {
        if (abs($diferencia) < 0.005) {
            return 'Caja cerrada correctamente: el arqueo cuadra';
        }

        return $diferencia > 0
            ? 'Caja cerrada: sobrante de Bs ' . number_format($diferencia, 2, ',', '.')
            : 'Caja cerrada: faltante de Bs ' . number_format(abs($diferencia), 2, ',', '.');
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

    private function listarPorCaja(int $idCaja, string $estado, int $limite): array
    {
        $sql = "SELECT a.*,
                       {$this->exprEgresos('a')},
                       c.codigo AS caja_codigo,
                       c.nombre AS caja_nombre,
                       s.nombre_sucursal,
                       s.ciudad,
                       ua.username AS username_apertura,
                       TRIM(CONCAT(COALESCE(ua_d.nombres, ''), ' ', COALESCE(ua_d.apellidos, ''))) AS nombre_apertura,
                       uc.username AS username_cierre,
                       TRIM(CONCAT(COALESCE(uc_d.nombres, ''), ' ', COALESCE(uc_d.apellidos, ''))) AS nombre_cierre
                FROM arqueos_caja a
                INNER JOIN cajas c           ON c.id_caja = a.id_caja
                INNER JOIN sucursales s      ON s.id_sucursal = a.id_sucursal
                INNER JOIN usuarios ua       ON ua.id_usuario = a.id_usuario_apertura
                LEFT  JOIN usuarios_datos ua_d ON ua_d.id_usuario = ua.id_usuario
                LEFT  JOIN usuarios uc       ON uc.id_usuario = a.id_usuario_cierre
                LEFT  JOIN usuarios_datos uc_d ON uc_d.id_usuario = uc.id_usuario
                WHERE a.id_caja = :caja AND a.estado = :estado
                ORDER BY a.fecha_apertura DESC
                LIMIT " . max(1, min(20, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':caja' => $idCaja, ':estado' => $estado]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ArqueoCaja::listarPorCaja - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    private function normalizar(array $f): array
    {
        $nombreApertura = trim((string) ($f['nombre_apertura'] ?? ''));
        $nombreCierre = trim((string) ($f['nombre_cierre'] ?? ''));

        return [
            'id'                     => (int) $f['id_arqueo'],
            'id_caja'                => (int) $f['id_caja'],
            'id_sucursal'            => (int) $f['id_sucursal'],
            'caja_codigo'            => (string) ($f['caja_codigo'] ?? ''),
            'caja_nombre'            => (string) ($f['caja_nombre'] ?? ''),
            'sucursal'               => (string) ($f['nombre_sucursal'] ?? ''),
            'ciudad'                 => (string) ($f['ciudad'] ?? ''),
            'estado'                 => (string) $f['estado'],
            'id_usuario_asignado'    => (int) ($f['id_usuario_asignado'] ?? 0),
            'id_usuario_apertura'    => (int) $f['id_usuario_apertura'],
            'id_usuario_cierre'      => $f['id_usuario_cierre'] !== null ? (int) $f['id_usuario_cierre'] : 0,
            'usuario_apertura'       => $nombreApertura !== '' ? $nombreApertura : (string) ($f['username_apertura'] ?? ''),
            'username_apertura'      => (string) ($f['username_apertura'] ?? ''),
            'rol_apertura'           => (string) ($f['rol_apertura'] ?? ''),
            'usuario_cierre'         => $nombreCierre !== '' ? $nombreCierre : (string) ($f['username_cierre'] ?? ''),
            'username_cierre'        => (string) ($f['username_cierre'] ?? ''),
            'monto_apertura'         => (float) $f['monto_apertura'],
            'monto_cierre'           => $f['monto_cierre'] !== null ? (float) $f['monto_cierre'] : null,
            'monto_esperado'         => $f['monto_esperado'] !== null ? (float) $f['monto_esperado'] : null,
            'diferencia'             => $f['diferencia'] !== null ? (float) $f['diferencia'] : null,
            'total_ventas'           => (float) $f['total_ventas'],
            'total_efectivo'         => (float) $f['total_efectivo'],
            'total_tarjeta'          => (float) $f['total_tarjeta'],
            'total_qr'               => (float) $f['total_qr'],
            'total_otros'            => (float) $f['total_otros'],
            'total_transferencia'    => (float) ($f['total_transferencia'] ?? 0),
            'total_egresos'          => (float) ($f['total_egresos_calc'] ?? $f['total_egresos'] ?? 0),
            'efectivo_esperado'      => isset($f['efectivo_esperado']) && $f['efectivo_esperado'] !== null
                ? (float) $f['efectivo_esperado']
                : round(
                    (float) $f['monto_apertura'] + (float) $f['total_efectivo'] - (float) ($f['total_egresos'] ?? 0),
                    2
                ),
            'ticket_cierre_generado' => (int) ($f['ticket_cierre_generado'] ?? 0),
            'observacion_apertura'   => (string) ($f['observacion_apertura'] ?? ''),
            'observacion_cierre'     => (string) ($f['observacion_cierre'] ?? ''),
            'fecha_apertura'         => !empty($f['fecha_apertura']) ? date('d/m/Y H:i', strtotime($f['fecha_apertura'])) : '—',
            'fecha_cierre'           => !empty($f['fecha_cierre']) ? date('d/m/Y H:i', strtotime($f['fecha_cierre'])) : null,
            'fecha_apertura_iso'     => (string) ($f['fecha_apertura'] ?? ''),
            'fecha_cierre_iso'       => $f['fecha_cierre'] ?? null,
        ];
    }
}
