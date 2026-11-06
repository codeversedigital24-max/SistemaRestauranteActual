<?php
/**
 * =====================================================
 * MODELO: Dashboard  (modelos/dashboard/Dashboard.php)
 * -----------------------------------------------------
 * Consultas del panel principal.
 *
 * Alcance:
 *   · SuperAdmin: todas las sucursales o una en concreto.
 *   · Administrador / Supervisor: su sucursal.
 *   · Cajero: únicamente SUS ventas del día.
 *   · Mesero / Cocinero / Repartidor: sólo operación.
 *
 * Los datos sensibles (utilidades, compras) nunca se
 * calculan para usuarios que no sean SuperAdmin.
 * =====================================================
 */

class Dashboard
{
    private PDO $conn;
    private int $idSucursal;
    private bool $esSuperAdmin;
    private int $idUsuario;
    private bool $soloPropio;

    public function __construct(
        ?PDO $conexion = null,
        int $idSucursal = 0,
        bool $esSuperAdmin = false,
        int $idUsuario = 0,
        bool $soloPropio = false
    ) {
        $this->conn         = $conexion ?? Conexion::getInstance()->getConnection();
        $this->idSucursal   = $idSucursal;
        $this->esSuperAdmin = $esSuperAdmin;
        $this->idUsuario    = $idUsuario;
        $this->soloPropio   = $soloPropio;
    }

    // =====================================================
    // FILTROS BASE
    // =====================================================

    /**
     * Filtro por sucursal para una tabla con alias.
     * SuperAdmin con idSucursal = 0 no filtra (todas).
     */
    private function filtroSucursal(string $alias = ''): array
    {
        $col = $alias !== '' ? $alias . '.id_sucursal' : 'id_sucursal';

        if ($this->esSuperAdmin) {
            if ($this->idSucursal > 0) {
                return [' AND ' . $col . ' = :sucursal', [':sucursal' => $this->idSucursal]];
            }

            return ['', []];
        }

        if ($this->idSucursal <= 0) {
            return [' AND 1 = 0', []];
        }

        return [' AND ' . $col . ' = :sucursal', [':sucursal' => $this->idSucursal]];
    }

    /** Filtro de ventas: sucursal + usuario cuando el perfil es personal. */
    private function filtroVentas(string $alias = 'v'): array
    {
        [$sql, $params] = $this->filtroSucursal($alias);

        if ($this->soloPropio && $this->idUsuario > 0) {
            $sql .= ' AND ' . $alias . '.id_usuario = :usuario';
            $params[':usuario'] = $this->idUsuario;
        }

        return [$sql, $params];
    }

    // =====================================================
    // VENTAS
    // =====================================================

    public function ventasResumen(): array
    {
        $hoy    = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('monday this week'));
        $mes    = date('Y-m-01');
        $ayer   = date('Y-m-d', strtotime('yesterday'));

        $r = [
            'hoy_cant'        => 0,
            'hoy_total'       => 0.0,
            'ayer_cant'       => 0,
            'ayer_total'      => 0.0,
            'semana_cant'     => 0,
            'semana_total'    => 0.0,
            'mes_cant'        => 0,
            'mes_total'       => 0.0,
            'ticket_prom'     => 0.0,
            'ticket_hoy_prom' => 0.0,
            'ticket_mes_prom' => 0.0,
            'delta_hoy_pct'   => 0.0,
        ];

        [$filtro, $params] = $this->filtroVentas('v');

        try {
            $sql = "SELECT
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) = :hoy_cant THEN 1 ELSE 0 END), 0) AS hoy_cant,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) = :hoy_total THEN v.total ELSE 0 END), 0) AS hoy_total,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) = :ayer_cant THEN 1 ELSE 0 END), 0) AS ayer_cant,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) = :ayer_total THEN v.total ELSE 0 END), 0) AS ayer_total,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :semana_cant THEN 1 ELSE 0 END), 0) AS semana_cant,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :semana_total THEN v.total ELSE 0 END), 0) AS semana_total,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :mes_cant THEN 1 ELSE 0 END), 0) AS mes_cant,
                        COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :mes_total THEN v.total ELSE 0 END), 0) AS mes_total,
                        COALESCE(AVG(CASE WHEN DATE(v.fecha_venta) = :ticket_hoy THEN v.total END), 0) AS ticket_hoy_prom,
                        COALESCE(AVG(CASE WHEN DATE(v.fecha_venta) >= :ticket_mes THEN v.total END), 0) AS ticket_mes_prom
                    FROM ventas v
                    WHERE v.estado = 'registrada'" . $filtro;

            $st = $this->conn->prepare($sql);
            $st->execute(array_merge([
                ':hoy_cant'    => $hoy,
                ':hoy_total'   => $hoy,
                ':ayer_cant'   => $ayer,
                ':ayer_total'  => $ayer,
                ':semana_cant' => $semana,
                ':semana_total' => $semana,
                ':mes_cant'    => $mes,
                ':mes_total'   => $mes,
                ':ticket_hoy'  => $hoy,
                ':ticket_mes'  => $mes,
            ], $params));
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $r['hoy_cant']        = (int) ($f['hoy_cant'] ?? 0);
            $r['hoy_total']       = (float) ($f['hoy_total'] ?? 0);
            $r['ayer_cant']       = (int) ($f['ayer_cant'] ?? 0);
            $r['ayer_total']      = (float) ($f['ayer_total'] ?? 0);
            $r['semana_cant']     = (int) ($f['semana_cant'] ?? 0);
            $r['semana_total']    = (float) ($f['semana_total'] ?? 0);
            $r['mes_cant']        = (int) ($f['mes_cant'] ?? 0);
            $r['mes_total']       = (float) ($f['mes_total'] ?? 0);
            $r['ticket_hoy_prom'] = (float) ($f['ticket_hoy_prom'] ?? 0);
            $r['ticket_mes_prom'] = (float) ($f['ticket_mes_prom'] ?? 0);
            $r['ticket_prom']     = $this->soloPropio
                ? $r['ticket_hoy_prom']
                : $r['ticket_mes_prom'];

            if ($r['ayer_total'] > 0) {
                $r['delta_hoy_pct'] = round((($r['hoy_total'] - $r['ayer_total']) / $r['ayer_total']) * 100, 1);
            }
        } catch (PDOException $e) {
            error_log('Dashboard::ventasResumen - ' . $e->getMessage());
        }

        return $r;
    }

    public function utilidadesResumen(): array
    {
        if (!$this->esSuperAdmin) {
            return ['hoy' => 0, 'semana' => 0, 'mes' => 0, 'margen_mes' => 0];
        }

        $hoy    = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('monday this week'));
        $mes    = date('Y-m-01');

        [$filtro, $params] = $this->filtroSucursal('v');

        try {
            $st = $this->conn->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) = :hoy THEN v.subtotal - v.total_utilidad ELSE 0 END), 0) AS hoy,
                    COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :semana THEN v.subtotal - v.total_utilidad ELSE 0 END), 0) AS semana,
                    COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :mes_util THEN v.subtotal - v.total_utilidad ELSE 0 END), 0) AS mes,
                    COALESCE(SUM(CASE WHEN DATE(v.fecha_venta) >= :mes_venta THEN v.subtotal ELSE 0 END), 0) AS venta_mes
                 FROM ventas v
                 WHERE v.estado = 'registrada'" . $filtro
            );
            $st->execute(array_merge([
                ':hoy'       => $hoy,
                ':semana'    => $semana,
                ':mes_util'  => $mes,
                ':mes_venta' => $mes,
            ], $params));
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $ventaMes = (float) ($f['venta_mes'] ?? 0);
            $utilMes  = (float) ($f['mes'] ?? 0);

            return [
                'hoy'        => (float) ($f['hoy'] ?? 0),
                'semana'     => (float) ($f['semana'] ?? 0),
                'mes'        => $utilMes,
                'margen_mes' => $ventaMes > 0 ? round(($utilMes / $ventaMes) * 100, 1) : 0.0,
            ];
        } catch (PDOException $e) {
            error_log('Dashboard::utilidadesResumen - ' . $e->getMessage());
        }

        return ['hoy' => 0, 'semana' => 0, 'mes' => 0, 'margen_mes' => 0];
    }

    // =====================================================
    // ESTADO OPERATIVO
    // =====================================================

    public function mesasEstado(): array
    {
        $r = ['libres' => 0, 'ocupadas' => 0, 'pendientes' => 0, 'inactivas' => 0];

        [$filtro, $params] = $this->filtroSucursal('');

        try {
            $st = $this->conn->prepare(
                "SELECT
                    COALESCE(SUM(estado_mesa = 'libre'), 0) AS libres,
                    COALESCE(SUM(estado_mesa IN ('ocupada', 'despachado')), 0) AS ocupadas,
                    COALESCE(SUM(estado_mesa = 'pendiente_pago'), 0) AS pendientes,
                    COALESCE(SUM(estado = 0), 0) AS inactivas
                 FROM mesas
                 WHERE 1 = 1" . $filtro
            );
            $st->execute($params);
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $r['libres']     = (int) ($f['libres'] ?? 0);
            $r['ocupadas']   = (int) ($f['ocupadas'] ?? 0);
            $r['pendientes'] = (int) ($f['pendientes'] ?? 0);
            $r['inactivas']  = (int) ($f['inactivas'] ?? 0);
        } catch (PDOException $e) {
            error_log('Dashboard::mesasEstado - ' . $e->getMessage());
        }

        return $r;
    }

    public function pedidosEstado(): array
    {
        $r = [
            'pendientes'       => 0,
            'despachados'      => 0,
            'por_cobrar'       => 0,
            'en_cocina'        => 0,
            'delivery_activos' => 0,
            'mesa_activos'     => 0,
        ];

        [$filtro, $params] = $this->filtroSucursal('');

        try {
            $st = $this->conn->prepare(
                "SELECT
                    COALESCE(SUM(estado IN ('pendiente', 'preparando')), 0) AS pendientes,
                    COALESCE(SUM(estado = 'despachado'), 0) AS despachados,
                    COALESCE(SUM(estado = 'pendiente_pago'), 0) AS por_cobrar,
                    COALESCE(SUM(estado IN ('pendiente', 'preparando')), 0) AS en_cocina,
                    COALESCE(SUM(tipo_pedido = 'delivery' AND estado IN ('pendiente', 'preparando', 'despachado', 'pendiente_pago')), 0) AS delivery_activos,
                    COALESCE(SUM(tipo_pedido = 'mesa' AND estado IN ('pendiente', 'preparando', 'despachado', 'pendiente_pago')), 0) AS mesa_activos
                 FROM pedidos
                 WHERE 1 = 1" . $filtro
            );
            $st->execute($params);
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $r['pendientes']       = (int) ($f['pendientes'] ?? 0);
            $r['despachados']      = (int) ($f['despachados'] ?? 0);
            $r['por_cobrar']       = (int) ($f['por_cobrar'] ?? 0);
            $r['en_cocina']        = (int) ($f['en_cocina'] ?? 0);
            $r['delivery_activos'] = (int) ($f['delivery_activos'] ?? 0);
            $r['mesa_activos']     = (int) ($f['mesa_activos'] ?? 0);
        } catch (PDOException $e) {
            error_log('Dashboard::pedidosEstado - ' . $e->getMessage());
        }

        return $r;
    }

    /** Lista corta de pedidos activos para el panel operativo. */
    public function pedidosActivos(int $limite = 8): array
    {
        [$filtro, $params] = $this->filtroSucursal('p');

        try {
            $st = $this->conn->prepare(
                "SELECT p.id_pedido, p.codigo, p.tipo_pedido, p.estado, p.total,
                        p.fecha_apertura, p.delivery_nombre,
                        m.nombre AS mesa_nombre,
                        u.username,
                        TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre
                   FROM pedidos p
                   LEFT JOIN mesas m ON m.id_mesa = p.id_mesa
                   INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
                   LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  WHERE p.estado IN ('pendiente', 'preparando', 'despachado', 'pendiente_pago')
                    " . $filtro . "
                  ORDER BY p.fecha_apertura DESC
                  LIMIT " . max(1, min(30, $limite))
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::pedidosActivos - ' . $e->getMessage());
            return [];
        }

        $tipos = [
            'mesa'     => 'Mesa',
            'llevar'   => 'Para llevar',
            'delivery' => 'Delivery',
        ];

        return array_map(function (array $p) use ($tipos) {
            $nombre = trim((string) ($p['usuario_nombre'] ?? ''));
            $tipo = (string) $p['tipo_pedido'];
            $referencia = (string) ($p['mesa_nombre'] ?? '');

            if ($tipo === 'delivery') {
                $referencia = trim((string) ($p['delivery_nombre'] ?? '')) ?: 'Delivery';
            } elseif ($tipo === 'llevar') {
                $referencia = 'Para llevar';
            }

            return [
                'id'       => (int) $p['id_pedido'],
                'codigo'   => (string) $p['codigo'],
                'tipo'     => $tipo,
                'tipo_et'  => $tipos[$tipo] ?? ucfirst($tipo),
                'referencia' => $referencia,
                'estado'   => (string) $p['estado'],
                'total'    => (float) $p['total'],
                'fecha'    => date('d/m H:i', strtotime($p['fecha_apertura'])),
                'usuario'  => $nombre !== '' ? $nombre : (string) $p['username'],
            ];
        }, $filas);
    }

    // =====================================================
    // GRÁFICAS
    // =====================================================

    public function ventasUltimos7Dias(): array
    {
        [$filtro, $params] = $this->filtroVentas('v');

        try {
            $st = $this->conn->prepare(
                "SELECT DATE(v.fecha_venta) AS fecha,
                        COUNT(*) AS ventas,
                        COALESCE(SUM(v.total), 0) AS total
                   FROM ventas v
                  WHERE v.estado = 'registrada'
                    AND v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                    " . $filtro . "
                  GROUP BY DATE(v.fecha_venta)
                  ORDER BY fecha ASC"
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::ventasUltimos7Dias - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'fecha'  => (string) $f['fecha'],
            'ventas' => (int) $f['ventas'],
            'total'  => (float) $f['total'],
        ], $filas);
    }

    /** Ventas agrupadas por hora para el turno de hoy (cajero). */
    public function ventasPorHoraHoy(): array
    {
        [$filtro, $params] = $this->filtroVentas('v');

        try {
            $st = $this->conn->prepare(
                "SELECT HOUR(v.fecha_venta) AS hora,
                        COUNT(*) AS ventas,
                        COALESCE(SUM(v.total), 0) AS total
                   FROM ventas v
                  WHERE v.estado = 'registrada'
                    AND DATE(v.fecha_venta) = CURDATE()
                    " . $filtro . "
                  GROUP BY HOUR(v.fecha_venta)
                  ORDER BY hora ASC"
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::ventasPorHoraHoy - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'hora'   => (int) $f['hora'],
            'ventas' => (int) $f['ventas'],
            'total'  => (float) $f['total'],
        ], $filas);
    }

    /** Desglose por sucursal para el SuperAdmin (mes actual). */
    public function ventasPorSucursalMes(): array
    {
        if (!$this->esSuperAdmin) {
            return [];
        }

        try {
            $st = $this->conn->query(
                "SELECT s.id_sucursal, s.nombre_sucursal, s.ciudad,
                        COUNT(v.id_venta) AS ventas,
                        COALESCE(SUM(v.total), 0) AS total
                   FROM sucursales s
                   LEFT JOIN ventas v
                     ON v.id_sucursal = s.id_sucursal
                    AND v.estado = 'registrada'
                    AND v.fecha_venta >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                  WHERE s.estado = 1
                  GROUP BY s.id_sucursal, s.nombre_sucursal, s.ciudad
                  ORDER BY total DESC, s.nombre_sucursal ASC"
            );
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::ventasPorSucursalMes - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'id'       => (int) $f['id_sucursal'],
            'nombre'   => (string) $f['nombre_sucursal'],
            'ciudad'   => (string) ($f['ciudad'] ?? ''),
            'ventas'   => (int) $f['ventas'],
            'total'    => (float) $f['total'],
        ], $filas);
    }

    public function metodosPagoMes(bool $soloHoy = false): array
    {
        [$filtro, $params] = $this->filtroVentas('v');
        $periodo = $soloHoy
            ? 'DATE(v.fecha_venta) = CURDATE()'
            : "v.fecha_venta >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";

        try {
            $st = $this->conn->prepare(
                "SELECT v.metodo_pago,
                        COUNT(*) AS ventas,
                        COALESCE(SUM(v.total), 0) AS total
                   FROM ventas v
                  WHERE v.estado = 'registrada'
                    AND " . $periodo . "
                    " . $filtro . "
                  GROUP BY v.metodo_pago
                  ORDER BY total DESC"
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::metodosPagoMes - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'metodo' => (string) $f['metodo_pago'],
            'ventas' => (int) $f['ventas'],
            'total'  => (float) $f['total'],
        ], $filas);
    }

    public function topProductos(int $limite = 5, bool $soloHoy = false): array
    {
        [$filtro, $params] = $this->filtroVentas('v');
        $periodo = $soloHoy
            ? 'DATE(v.fecha_venta) = CURDATE()'
            : "v.fecha_venta >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";

        try {
            $st = $this->conn->prepare(
                "SELECT d.id_producto,
                        p.nombre,
                        p.codigo,
                        SUM(d.cantidad) AS cantidad,
                        COALESCE(SUM(d.subtotal), 0) AS venta
                   FROM venta_detalle d
                   INNER JOIN ventas v ON v.id_venta = d.id_venta
                   INNER JOIN productos p ON p.id_producto = d.id_producto
                  WHERE v.estado = 'registrada'
                    AND " . $periodo . "
                    " . $filtro . "
                  GROUP BY d.id_producto, p.nombre, p.codigo
                  ORDER BY cantidad DESC
                  LIMIT " . max(1, min(20, $limite))
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::topProductos - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'id'       => (int) $f['id_producto'],
            'nombre'   => (string) $f['nombre'],
            'codigo'   => (string) $f['codigo'],
            'cantidad' => (float) $f['cantidad'],
            'venta'    => (float) $f['venta'],
        ], $filas);
    }

    public function ventasRecientes(int $limite = 8, bool $soloHoy = false): array
    {
        [$filtro, $params] = $this->filtroVentas('v');
        $periodo = $soloHoy ? ' AND DATE(v.fecha_venta) = CURDATE()' : '';

        try {
            $st = $this->conn->prepare(
                "SELECT v.id_venta, v.codigo, v.fecha_venta, v.total, v.metodo_pago, v.estado,
                        s.nombre_sucursal,
                        u.username,
                        TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre
                   FROM ventas v
                   INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                   INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                   LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  WHERE 1 = 1" . $filtro . $periodo . "
                  ORDER BY v.fecha_venta DESC
                  LIMIT " . max(1, min(50, $limite))
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::ventasRecientes - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($v) {
            $nombre = trim((string) $v['usuario_nombre']);

            return [
                'id'       => (int) $v['id_venta'],
                'codigo'   => (string) $v['codigo'],
                'fecha'    => date('d/m/Y H:i', strtotime($v['fecha_venta'])),
                'total'    => (float) $v['total'],
                'metodo'   => (string) $v['metodo_pago'],
                'estado'   => (string) $v['estado'],
                'sucursal' => (string) $v['nombre_sucursal'],
                'usuario'  => $nombre !== '' ? $nombre : (string) $v['username'],
            ];
        }, $filas);
    }

    // =====================================================
    // CAJAS
    // =====================================================

    /** Caja abierta del usuario (para el panel del cajero). */
    public function miCajaAbierta(int $idUsuario): ?array
    {
        if ($idUsuario <= 0) {
            return null;
        }

        try {
            $st = $this->conn->prepare(
                "SELECT a.id_arqueo, a.monto_apertura, a.total_ventas, a.total_egresos,
                        a.efectivo_esperado, a.fecha_apertura,
                        c.codigo AS caja_codigo, c.nombre AS caja_nombre,
                        s.nombre_sucursal
                   FROM arqueos_caja a
                   INNER JOIN cajas c ON c.id_caja = a.id_caja
                   INNER JOIN sucursales s ON s.id_sucursal = a.id_sucursal
                  WHERE a.id_usuario_apertura = :usuario
                    AND a.estado = 'abierto'
                  ORDER BY a.fecha_apertura DESC
                  LIMIT 1"
            );
            $st->execute([':usuario' => $idUsuario]);
            $f = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::miCajaAbierta - ' . $e->getMessage());
            return null;
        }

        if (!$f) {
            return null;
        }

        return [
            'id'             => (int) $f['id_arqueo'],
            'caja'           => (string) $f['caja_codigo'] . ' · ' . (string) $f['caja_nombre'],
            'sucursal'       => (string) $f['nombre_sucursal'],
            'monto_apertura' => (float) $f['monto_apertura'],
            'ventas'         => (float) $f['total_ventas'],
            'gastos'         => (float) $f['total_egresos'],
            'esperado'       => (float) ($f['efectivo_esperado'] ?? 0),
            'apertura'       => date('d/m/Y H:i', strtotime($f['fecha_apertura'])),
        ];
    }

    public function arqueosAbiertos(): array
    {
        [$filtro, $params] = $this->filtroSucursal('a');

        try {
            $st = $this->conn->prepare(
                "SELECT a.id_arqueo, a.monto_apertura, a.total_ventas, a.total_egresos,
                        a.fecha_apertura,
                        c.codigo AS caja_codigo, c.nombre AS caja_nombre,
                        s.nombre_sucursal,
                        u.username,
                        TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre
                   FROM arqueos_caja a
                   INNER JOIN cajas c ON c.id_caja = a.id_caja
                   INNER JOIN sucursales s ON s.id_sucursal = a.id_sucursal
                   INNER JOIN usuarios u ON u.id_usuario = a.id_usuario_apertura
                   LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  WHERE a.estado = 'abierto'
                    " . $filtro . "
                  ORDER BY a.fecha_apertura DESC"
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::arqueosAbiertos - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($a) {
            $n = trim((string) $a['usuario_nombre']);

            return [
                'id'       => (int) $a['id_arqueo'],
                'caja'     => (string) $a['caja_codigo'] . ' · ' . (string) $a['caja_nombre'],
                'sucursal' => (string) $a['nombre_sucursal'],
                'usuario'  => $n !== '' ? $n : (string) $a['username'],
                'apertura' => date('d/m/Y H:i', strtotime($a['fecha_apertura'])),
                'monto'    => (float) $a['monto_apertura'],
                'ventas'   => (float) $a['total_ventas'],
                'gastos'   => (float) $a['total_egresos'],
            ];
        }, $filas);
    }

    // =====================================================
    // INVENTARIO
    // =====================================================

    public function stockBajo(int $limite = 6): array
    {
        [$filtro, $params] = $this->filtroSucursal('ps');

        try {
            $st = $this->conn->prepare(
                "SELECT p.id_producto, p.nombre, p.codigo,
                        ps.stock, ps.stock_minimo,
                        s.nombre_sucursal
                   FROM productos_sucursal ps
                   INNER JOIN productos p ON p.id_producto = ps.id_producto
                   INNER JOIN sucursales s ON s.id_sucursal = ps.id_sucursal
                  WHERE p.estado = 1
                    AND ps.control_stock = 1
                    AND ps.stock_minimo > 0
                    AND ps.stock <= ps.stock_minimo
                    " . $filtro . "
                  ORDER BY ps.stock ASC
                  LIMIT " . max(1, min(20, $limite))
            );
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::stockBajo - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'id'       => (int) $f['id_producto'],
            'nombre'   => (string) $f['nombre'],
            'codigo'   => (string) $f['codigo'],
            'stock'    => (float) $f['stock'],
            'minimo'   => (float) $f['stock_minimo'],
            'sucursal' => (string) $f['nombre_sucursal'],
        ], $filas);
    }

    public function topVendedores(int $limite = 5): array
    {
        $mes = date('Y-m-01');
        [$filtro, $params] = $this->filtroSucursal('v');

        try {
            $st = $this->conn->prepare(
                "SELECT u.id_usuario, u.username,
                        TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre,
                        COUNT(*) AS ventas,
                        COALESCE(SUM(v.total), 0) AS total
                   FROM ventas v
                   INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                   LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  WHERE v.estado = 'registrada'
                    AND v.fecha_venta >= :mes
                    " . $filtro . "
                  GROUP BY u.id_usuario, u.username, nombre
                  ORDER BY total DESC
                  LIMIT " . max(1, min(20, $limite))
            );
            $st->execute(array_merge([':mes' => $mes], $params));
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Dashboard::topVendedores - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => [
            'id'       => (int) $f['id_usuario'],
            'nombre'   => trim((string) $f['nombre']) ?: (string) $f['username'],
            'username' => (string) $f['username'],
            'ventas'   => (int) $f['ventas'],
            'total'    => (float) $f['total'],
        ], $filas);
    }

    // =====================================================
    // COMPRAS (sólo SuperAdmin)
    // =====================================================

    public function comprasResumen(): array
    {
        if (!$this->esSuperAdmin) {
            return ['mes' => 0, 'cant' => 0, 'por_pagar' => 0];
        }

        $mes = date('Y-m-01');
        [$filtro, $params] = $this->filtroSucursal('c');

        try {
            $st = $this->conn->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN c.fecha_compra >= :mes_total THEN c.total ELSE 0 END), 0) AS mes,
                    COALESCE(SUM(CASE WHEN c.fecha_compra >= :mes_cant THEN 1 ELSE 0 END), 0) AS cant
                 FROM compras c
                 WHERE c.estado = 'registrada'" . $filtro
            );
            $st->execute(array_merge([':mes_total' => $mes, ':mes_cant' => $mes], $params));
            $f = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            $porPagar = 0.0;

            try {
                $st2 = $this->conn->prepare(
                    "SELECT COALESCE(SUM(c.total - c.abono - COALESCE(
                                (SELECT SUM(pg.monto) FROM compras_pagos pg WHERE pg.id_compra = c.id_compra), 0)), 0) AS saldo
                       FROM compras c
                      WHERE c.estado = 'registrada'
                        AND c.forma_pago = 'credito'" . $filtro
                );
                $st2->execute($params);
                $porPagar = (float) $st2->fetchColumn();
            } catch (PDOException $e) {
                // Sin tabla de pagos: se mantiene 0.
            }

            return [
                'mes'       => (float) ($f['mes'] ?? 0),
                'cant'      => (int) ($f['cant'] ?? 0),
                'por_pagar' => $porPagar,
            ];
        } catch (PDOException $e) {
            error_log('Dashboard::comprasResumen - ' . $e->getMessage());
        }

        return ['mes' => 0, 'cant' => 0, 'por_pagar' => 0];
    }
}
