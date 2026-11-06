<?php
/**
 * =====================================================
 * MODELO: VentaReporte  (modelos/reportes/VentaReporte.php)
 * -----------------------------------------------------
 * Solo lectura. Consultas y agregados del historial de ventas.
 * Filtros: id_sucursal, id_arqueo, id_usuario, desde,
 * hasta, metodo_pago, estado.
 * =====================================================
 */

class VentaReporte
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =====================================================
    // LECTURA
    // =====================================================

    public function listar(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT v.id_venta, v.id_sucursal, v.id_usuario, v.id_arqueo,
                       v.codigo, v.fecha_venta, v.subtotal, v.descuento,
                       v.descuento_productos, v.impuesto_porcentaje, v.impuesto, v.total,
                       v.metodo_pago, v.tipo_pedido, v.delivery_nombre,
                       v.recibido, v.cambio, v.estado, v.observaciones,
                       s.nombre_sucursal, s.ciudad,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                       c.codigo AS caja_codigo, c.nombre AS caja_nombre,
                       (SELECT COUNT(*) FROM venta_detalle d WHERE d.id_venta = v.id_venta) AS items,
                       (SELECT COALESCE(SUM(d.cantidad),0) FROM venta_detalle d WHERE d.id_venta = v.id_venta) AS unidades
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo";

        if ($where !== '') $sql .= ' WHERE ' . $where;
        $sql .= ' ORDER BY v.fecha_venta DESC, v.id_venta DESC';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($v) => $this->normalizar($v), $filas);
    }

    public function resumen(array $f): array
    {
        // Los totales SIEMPRE cuentan solo ventas registradas: las anuladas
        // no son ingreso. El listado puede seguir mostrándolas si se filtra.
        [$where, $params] = $this->armarFiltros($f, true);

        $sql = "SELECT
                    COUNT(*) AS ventas,
                    COUNT(DISTINCT v.id_usuario) AS vendedores,
                    COUNT(DISTINCT v.id_sucursal) AS sucursales,
                    COUNT(DISTINCT v.id_arqueo) AS cajas,
                    COALESCE(SUM(v.total), 0) AS total,
                    COALESCE(SUM(v.descuento + v.descuento_productos), 0) AS descuentos,
                    COALESCE(SUM(v.impuesto), 0) AS impuesto,
                    0 AS anuladas,
                    COALESCE(SUM(CASE WHEN v.metodo_pago = 'efectivo' THEN v.total ELSE 0 END), 0) AS efectivo,
                    COALESCE(SUM(CASE WHEN v.metodo_pago = 'qr' THEN v.total ELSE 0 END), 0) AS qr,
                    COALESCE(SUM(CASE WHEN v.metodo_pago = 'tarjeta' THEN v.total ELSE 0 END), 0) AS tarjeta,
                    COALESCE(SUM(CASE WHEN v.metodo_pago = 'mixto' THEN v.total ELSE 0 END), 0) AS mixto,
                    COALESCE(SUM(CASE WHEN v.metodo_pago NOT IN ('efectivo','qr','tarjeta','mixto') THEN v.total ELSE 0 END), 0) AS otros,
                    COALESCE(AVG(v.total), 0) AS ticket_promedio
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo";

        if ($where !== '') $sql .= ' WHERE ' . $where;

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            // Anuladas del mismo rango (solo informativo, no suman).
            $filtrosAnul = $f;
            unset($filtrosAnul['estado']);
            [$whereAnul, $paramsAnul] = $this->armarFiltros($filtrosAnul);

            $sqlAnul = "SELECT COUNT(*) FROM ventas v";
            $sqlAnul .= $whereAnul !== '' ? ' WHERE ' . $whereAnul . " AND v.estado = 'anulada'" : " WHERE v.estado = 'anulada'";

            $stAnul = $this->conn->prepare($sqlAnul);
            $stAnul->execute($paramsAnul);
            $r['anuladas'] = (int) $stAnul->fetchColumn();
        } catch (PDOException $e) {
            error_log('VentaReporte::resumen - ' . $e->getMessage());
            return [];
        }

        return [
            'ventas'          => (int) ($r['ventas'] ?? 0),
            'vendedores'      => (int) ($r['vendedores'] ?? 0),
            'sucursales'      => (int) ($r['sucursales'] ?? 0),
            'cajas'           => (int) ($r['cajas'] ?? 0),
            'total'           => (float) ($r['total'] ?? 0),
            'descuentos'      => (float) ($r['descuentos'] ?? 0),
            'impuesto'        => (float) ($r['impuesto'] ?? 0),
            'anuladas'        => (int) ($r['anuladas'] ?? 0),
            'efectivo'        => (float) ($r['efectivo'] ?? 0),
            'qr'              => (float) ($r['qr'] ?? 0),
            'tarjeta'         => (float) ($r['tarjeta'] ?? 0),
            'mixto'           => (float) ($r['mixto'] ?? 0),
            'otros'           => (float) ($r['otros'] ?? 0),
            'ticket_promedio' => (float) ($r['ticket_promedio'] ?? 0),
        ];
    }

    public function porDia(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f, true);
        $sql = "SELECT DATE(v.fecha_venta) AS fecha,
                       COUNT(*) AS ventas,
                       COALESCE(SUM(v.total), 0) AS total
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo";
        if ($where !== '') $sql .= ' WHERE ' . $where;
        $sql .= ' GROUP BY DATE(v.fecha_venta) ORDER BY fecha ASC';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::porDia - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'fecha'   => (string) $r['fecha'],
            'ventas'  => (int) $r['ventas'],
            'total'   => (float) $r['total'],
        ], $filas);
    }

    public function porMetodo(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f, true);
        $sql = "SELECT v.metodo_pago,
                       COUNT(*) AS ventas,
                       COALESCE(SUM(v.total), 0) AS total
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo";
        if ($where !== '') $sql .= ' WHERE ' . $where;
        $sql .= ' GROUP BY v.metodo_pago';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::porMetodo - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'metodo' => (string) $r['metodo_pago'],
            'ventas' => (int) $r['ventas'],
            'total'  => (float) $r['total'],
        ], $filas);
    }

    public function topVendedores(array $f, int $limite = 8): array
    {
        [$where, $params] = $this->armarFiltros($f, true);
        $sql = "SELECT u.id_usuario, u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre,
                       COUNT(*) AS ventas,
                       COALESCE(SUM(v.total), 0) AS total
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo";
        if ($where !== '') $sql .= ' WHERE ' . $where;
        $sql .= ' GROUP BY u.id_usuario ORDER BY total DESC LIMIT ' . max(1, min(20, $limite));

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::topVendedores - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'id'      => (int) $r['id_usuario'],
            'nombre'  => trim((string) $r['nombre']) !== '' ? trim((string) $r['nombre']) : (string) $r['username'],
            'username'=> (string) $r['username'],
            'ventas'  => (int) $r['ventas'],
            'total'   => (float) $r['total'],
        ], $filas);
    }

    public function porSucursal(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f, true);
        $sql = "SELECT s.id_sucursal, s.nombre_sucursal, s.ciudad,
                       COUNT(*) AS ventas,
                       COALESCE(SUM(v.total), 0) AS total
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo";
        if ($where !== '') $sql .= ' WHERE ' . $where;
        $sql .= ' GROUP BY s.id_sucursal ORDER BY total DESC';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::porSucursal - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'id'     => (int) $r['id_sucursal'],
            'nombre' => (string) $r['nombre_sucursal'],
            'ciudad' => (string) ($r['ciudad'] ?? ''),
            'ventas' => (int) $r['ventas'],
            'total'  => (float) $r['total'],
        ], $filas);
    }

    public function detalle(int $idVenta): ?array
    {
        if ($idVenta <= 0) return null;

        $st = $this->conn->prepare(
            "SELECT v.*, s.nombre_sucursal, s.ciudad,
                    u.username,
                    TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                    c.codigo AS caja_codigo, c.nombre AS caja_nombre
               FROM ventas v
               INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
               INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
               LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
               LEFT  JOIN cajas c ON c.id_caja = v.id_arqueo
              WHERE v.id_venta = :id LIMIT 1"
        );
        $st->execute([':id' => $idVenta]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $st = $this->conn->prepare(
            "SELECT d.*, p.codigo, p.nombre
               FROM venta_detalle d
               INNER JOIN productos p ON p.id_producto = d.id_producto
              WHERE d.id_venta = :id
              ORDER BY d.id_detalle ASC"
        );
        $st->execute([':id' => $idVenta]);
        $detalle = $st->fetchAll(PDO::FETCH_ASSOC);

        $base = $this->normalizar($fila);
        $base['detalle'] = array_map(fn($d) => [
            'id_producto'         => (int) $d['id_producto'],
            'codigo'              => (string) $d['codigo'],
            'producto'            => (string) $d['nombre'],
            'cantidad'            => (float) $d['cantidad'],
            'precio_unitario'     => (float) $d['precio_unitario'],
            'precio_base'         => (float) $d['precio_base'],
            'descuento_porcentaje'=> (float) $d['descuento_porcentaje'],
            'descuento'           => (float) $d['descuento'],
            'promocion'           => (string) ($d['promocion'] ?? ''),
            'impuesto_porcentaje' => (float) $d['impuesto_porcentaje'],
            'impuesto'            => (float) $d['impuesto'],
            'subtotal'            => (float) $d['subtotal'],
            'total'               => (float) $d['total'],
        ], $detalle);

        return $base;
    }

    public function sucursalesDisponibles(array $usuario): array
    {
        try {
            if ($usuario['es_superadmin'] ?? false) {
                return $this->conn->query(
                    "SELECT id_sucursal, nombre_sucursal, ciudad FROM sucursales
                     WHERE estado = 1 ORDER BY nombre_sucursal"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            $st = $this->conn->prepare(
                "SELECT id_sucursal, nombre_sucursal, ciudad FROM sucursales
                 WHERE id_sucursal = :id AND estado = 1"
            );
            $st->execute([':id' => (int) ($usuario['id_sucursal'] ?? 0)]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::sucursalesDisponibles - ' . $e->getMessage());
            return [];
        }
    }

    public function usuariosDisponibles(?int $idSucursal = null): array
    {
        $sql = "SELECT DISTINCT u.id_usuario, u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre
                  FROM ventas v
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";
        $params = [];
        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= " WHERE v.id_sucursal = :s";
            $params[':s'] = $idSucursal;
        }
        $sql .= " ORDER BY nombre ASC, u.username ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::usuariosDisponibles - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($u) => [
            'id'       => (int) $u['id_usuario'],
            'username' => (string) $u['username'],
            'nombre'   => trim((string) $u['nombre']) ?: (string) $u['username'],
        ], $filas);
    }

    public function cajasDisponibles(?int $idSucursal = null): array
    {
        $sql = "SELECT a.id_arqueo, c.codigo AS caja_codigo, c.nombre AS caja_nombre,
                       s.nombre_sucursal,
                       a.fecha_apertura
                  FROM arqueos_caja a
                  INNER JOIN cajas c ON c.id_caja = a.id_caja
                  INNER JOIN sucursales s ON s.id_sucursal = a.id_sucursal";
        $params = [];
        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= " WHERE a.id_sucursal = :s";
            $params[':s'] = $idSucursal;
        }
        $sql .= " ORDER BY a.fecha_apertura DESC LIMIT 200";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaReporte::cajasDisponibles - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($a) => [
            'id'      => (int) $a['id_arqueo'],
            'codigo'  => (string) $a['caja_codigo'],
            'nombre'  => (string) $a['caja_nombre'],
            'sucursal'=> (string) $a['nombre_sucursal'],
            'fecha'   => date('d/m/Y H:i', strtotime($a['fecha_apertura'])),
        ], $filas);
    }

    // =====================================================
    // AUXILIARES
    // =====================================================

    private function armarFiltros(array $f, bool $soloRegistradas = false): array
    {
        $where = [];
        $params = [];

        if (!empty($f['id_sucursal'])) {
            $where[] = 'v.id_sucursal = :sucursal';
            $params[':sucursal'] = (int) $f['id_sucursal'];
        }
        if (!empty($f['id_usuario'])) {
            $where[] = 'v.id_usuario = :usuario';
            $params[':usuario'] = (int) $f['id_usuario'];
        }
        if (!empty($f['id_arqueo'])) {
            $where[] = 'v.id_arqueo = :arqueo';
            $params[':arqueo'] = (int) $f['id_arqueo'];
        }
        if (!empty($f['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['desde'])) {
            $where[] = 'v.fecha_venta >= :desde';
            $params[':desde'] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['hasta'])) {
            $where[] = 'v.fecha_venta <= :hasta';
            $params[':hasta'] = $f['hasta'] . ' 23:59:59';
        }
        if (in_array($f['metodo_pago'] ?? '', ['efectivo','qr','tarjeta','mixto','otro'], true)) {
            $where[] = 'v.metodo_pago = :metodo';
            $params[':metodo'] = $f['metodo_pago'];
        }

        if ($soloRegistradas) {
            // Totales/gráficas: las anuladas nunca suman.
            $where[] = "v.estado = 'registrada'";
        } elseif (in_array($f['estado'] ?? '', ['registrada','anulada'], true)) {
            $where[] = 'v.estado = :estado';
            $params[':estado'] = $f['estado'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function normalizar(array $v): array
    {
        $nombre = trim((string) ($v['usuario_nombre'] ?? ''));

        return [
            'id'            => (int) $v['id_venta'],
            'codigo'        => (string) $v['codigo'],
            'id_sucursal'   => (int) $v['id_sucursal'],
            'id_usuario'    => (int) $v['id_usuario'],
            'id_arqueo'     => (int) $v['id_arqueo'],
            'sucursal'      => (string) ($v['nombre_sucursal'] ?? ''),
            'ciudad'        => (string) ($v['ciudad'] ?? ''),
            'usuario'       => $nombre !== '' ? $nombre : (string) ($v['username'] ?? ''),
            'username'      => (string) ($v['username'] ?? ''),
            'caja_codigo'   => (string) ($v['caja_codigo'] ?? ''),
            'caja_nombre'   => (string) ($v['caja_nombre'] ?? ''),
            'fecha_venta'   => date('d/m/Y H:i', strtotime($v['fecha_venta'])),
            'fecha_iso'     => (string) $v['fecha_venta'],
            'subtotal'      => (float) $v['subtotal'],
            'descuento'     => (float) $v['descuento'] + (float) ($v['descuento_productos'] ?? 0),
            'impuesto'      => (float) $v['impuesto'],
            'total'         => (float) $v['total'],
            'metodo_pago'   => (string) $v['metodo_pago'],
            'tipo_pedido'   => (string) ($v['tipo_pedido'] ?? 'mesa'),
            'delivery_nombre' => (string) ($v['delivery_nombre'] ?? ''),
            'recibido'      => (float) $v['recibido'],
            'cambio'        => (float) $v['cambio'],
            'estado'        => (string) $v['estado'],
            'observaciones' => (string) ($v['observaciones'] ?? ''),
            'items'         => (int) ($v['items'] ?? 0),
            'unidades'      => (float) ($v['unidades'] ?? 0),
        ];
    }
}