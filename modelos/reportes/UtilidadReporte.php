<?php
/**
 * =====================================================
 * MODELO: UtilidadReporte  (modelos/reportes/UtilidadReporte.php)
 * -----------------------------------------------------
 * Reporte de utilidad bruta por producto, sucursal, caja y vendedor.
 *
 * Snapshot ya guardado en venta_detalle:
 *   utilidad_bruta  = costo unitario
 *   total_utilidad  = costo * cantidad
 *
 * Utilidad bruta línea    = subtotal - total_utilidad
 * Utilidad bruta venta    = sum(líneas) por id_venta
 * Utilidad bruta global   = sum(venta_detalle.subtotal - venta_detalle.total_utilidad)
 *
 * IMPORTANTE:
 *   · Sólo se consideran ventas con estado "registrada"; las
 *     anuladas nunca suman en tarjetas, tablas, gráficas ni PDF.
 *   · Los totales se calculan desde venta_detalle para que la
 *     tabla por producto, la tabla por venta y el resumen
 *     coincidan exactamente.
 * =====================================================
 */

class UtilidadReporte
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =====================================================
    // LECTURA
    // =====================================================

    /**
     * Detalle por producto vendido. Cada fila es un producto agregado
     * en el rango: cantidad, venta, costo, utilidad, margen.
     */
    public function detallePorProducto(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT d.id_producto,
                       p.codigo,
                       p.nombre,
                       c.nombre_categoria,
                       SUM(d.cantidad) AS cantidad,
                       SUM(d.subtotal) AS venta,
                       SUM(d.total_utilidad) AS costo,
                       SUM(d.subtotal - d.total_utilidad) AS utilidad,
                       COUNT(DISTINCT d.id_venta) AS ventas,
                       CASE WHEN SUM(d.subtotal) > 0
                            THEN ROUND(SUM(d.subtotal - d.total_utilidad) / SUM(d.subtotal) * 100, 2)
                            ELSE 0 END AS margen
                  FROM venta_detalle d
                  INNER JOIN ventas v ON v.id_venta = d.id_venta
                  LEFT  JOIN productos p ON p.id_producto = d.id_producto
                  LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= ' GROUP BY d.id_producto ORDER BY utilidad DESC';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('UtilidadReporte::detallePorProducto - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'id'          => (int) $r['id_producto'],
            'codigo'      => (string) (($r['codigo'] ?? '') !== '' ? $r['codigo'] : 'PROD-' . $r['id_producto']),
            'producto'    => (string) (($r['nombre'] ?? '') !== '' ? $r['nombre'] : 'Producto #' . $r['id_producto']),
            'categoria'   => (string) ($r['nombre_categoria'] ?? ''),
            'cantidad'    => (float) $r['cantidad'],
            'ventas'      => (int) $r['ventas'],
            'venta'       => (float) $r['venta'],
            'costo'       => (float) $r['costo'],
            'utilidad'    => (float) $r['utilidad'],
            'margen'      => (float) $r['margen'],
        ], $filas);
    }

    /**
     * Detalle de ventas individuales con su utilidad. Para el modal
     * de "ver venta" y para el detalle completo por venta.
     */
    public function detallePorVenta(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT v.id_venta, v.codigo, v.fecha_venta,
                       v.metodo_pago, v.estado, v.total,
                       v.id_usuario, v.id_sucursal,
                       COALESCE(x.subtotal, 0) AS subtotal,
                       COALESCE(x.costo, 0) AS total_utilidad,
                       s.nombre_sucursal, s.ciudad,
                       cj.codigo AS caja_codigo, cj.nombre AS caja_nombre,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre
                  FROM ventas v
                  LEFT JOIN (
                        SELECT id_venta,
                               SUM(subtotal) AS subtotal,
                               SUM(total_utilidad) AS costo
                          FROM venta_detalle
                         GROUP BY id_venta
                  ) x ON x.id_venta = v.id_venta
                  LEFT JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                  LEFT JOIN arqueos_caja a ON a.id_arqueo = v.id_arqueo
                  LEFT JOIN cajas cj ON cj.id_caja = a.id_caja";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= ' ORDER BY v.fecha_venta DESC, v.id_venta DESC';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('UtilidadReporte::detallePorVenta - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($v) {
            $nombre = trim((string) ($v['usuario_nombre'] ?? ''));
            $subtotal = (float) $v['subtotal'];
            $costo = (float) $v['total_utilidad'];
            $utilidad = round($subtotal - $costo, 2);
            $sucursal = trim((string) ($v['nombre_sucursal'] ?? ''));
            $usuario = $nombre !== '' ? $nombre : (string) ($v['username'] ?? '');

            return [
                'id'          => (int) $v['id_venta'],
                'codigo'      => (string) $v['codigo'],
                'fecha'       => date('d/m/Y H:i', strtotime($v['fecha_venta'])),
                'fecha_iso'   => (string) $v['fecha_venta'],
                'sucursal'    => $sucursal !== '' ? $sucursal : 'Sucursal #' . (int) $v['id_sucursal'],
                'ciudad'      => (string) ($v['ciudad'] ?? ''),
                'caja_codigo' => (string) ($v['caja_codigo'] ?? ''),
                'caja_nombre' => (string) ($v['caja_nombre'] ?? ''),
                'usuario'     => $usuario !== '' ? $usuario : 'Usuario #' . (int) $v['id_usuario'],
                'subtotal'    => $subtotal,
                'total'       => (float) $v['total'],
                'costo'       => $costo,
                'utilidad'    => $utilidad,
                'margen'      => $subtotal > 0 ? round(($utilidad / $subtotal) * 100, 2) : 0.0,
                'metodo'      => (string) $v['metodo_pago'],
            ];
        }, $filas);
    }

    public function resumen(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT
                    COUNT(DISTINCT v.id_venta) AS ventas,
                    COUNT(DISTINCT v.id_usuario) AS vendedores,
                    COUNT(DISTINCT v.id_sucursal) AS sucursales,
                    COUNT(DISTINCT v.id_arqueo) AS cajas,
                    COALESCE(SUM(x.subtotal), 0) AS venta_neta,
                    COALESCE(SUM(x.costo), 0) AS costo,
                    COALESCE(SUM(x.subtotal - x.costo), 0) AS utilidad,
                    CASE WHEN SUM(x.subtotal) > 0
                         THEN ROUND(SUM(x.subtotal - x.costo) / SUM(x.subtotal) * 100, 2)
                         ELSE 0 END AS margen
                  FROM ventas v
                  LEFT JOIN (
                        SELECT id_venta,
                               SUM(subtotal) AS subtotal,
                               SUM(total_utilidad) AS costo
                          FROM venta_detalle
                         GROUP BY id_venta
                  ) x ON x.id_venta = v.id_venta";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('UtilidadReporte::resumen - ' . $e->getMessage());
            return [];
        }

        return [
            'ventas'      => (int) ($r['ventas'] ?? 0),
            'vendedores'  => (int) ($r['vendedores'] ?? 0),
            'sucursales'  => (int) ($r['sucursales'] ?? 0),
            'cajas'       => (int) ($r['cajas'] ?? 0),
            'venta_neta'  => (float) ($r['venta_neta'] ?? 0),
            'costo'       => (float) ($r['costo'] ?? 0),
            'utilidad'    => (float) ($r['utilidad'] ?? 0),
            'margen'      => (float) ($r['margen'] ?? 0),
        ];
    }

    /** Utilidad agrupada por sucursal (para gráfica). */
    public function utilidadPorSucursal(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT v.id_sucursal,
                       COALESCE(s.nombre_sucursal, CONCAT('Sucursal #', v.id_sucursal)) AS nombre_sucursal,
                       COALESCE(s.ciudad, '') AS ciudad,
                       COALESCE(SUM(x.subtotal), 0) AS venta_neta,
                       COALESCE(SUM(x.costo), 0) AS costo,
                       COALESCE(SUM(x.subtotal - x.costo), 0) AS utilidad,
                       COUNT(DISTINCT v.id_venta) AS ventas
                  FROM ventas v
                  LEFT JOIN (
                        SELECT id_venta,
                               SUM(subtotal) AS subtotal,
                               SUM(total_utilidad) AS costo
                          FROM venta_detalle
                         GROUP BY id_venta
                  ) x ON x.id_venta = v.id_venta
                  LEFT JOIN sucursales s ON s.id_sucursal = v.id_sucursal";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= ' GROUP BY v.id_sucursal, s.nombre_sucursal, s.ciudad ORDER BY utilidad DESC';

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('UtilidadReporte::utilidadPorSucursal - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'id'         => (int) $r['id_sucursal'],
            'nombre'     => (string) $r['nombre_sucursal'],
            'ciudad'     => (string) ($r['ciudad'] ?? ''),
            'ventas'     => (int) $r['ventas'],
            'venta_neta' => (float) $r['venta_neta'],
            'costo'      => (float) $r['costo'],
            'utilidad'   => (float) $r['utilidad'],
        ], $filas);
    }

    /** Utilidad por vendedor. */
    public function utilidadPorVendedor(array $f, int $limite = 8): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT v.id_usuario,
                       COALESCE(u.username, CONCAT('usuario-', v.id_usuario)) AS username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre,
                       COUNT(DISTINCT v.id_venta) AS ventas,
                       COALESCE(SUM(x.subtotal), 0) AS venta_neta,
                       COALESCE(SUM(x.costo), 0) AS costo,
                       COALESCE(SUM(x.subtotal - x.costo), 0) AS utilidad
                  FROM ventas v
                  LEFT JOIN (
                        SELECT id_venta,
                               SUM(subtotal) AS subtotal,
                               SUM(total_utilidad) AS costo
                          FROM venta_detalle
                         GROUP BY id_venta
                  ) x ON x.id_venta = v.id_venta
                  LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= ' GROUP BY v.id_usuario, u.username, nombre ORDER BY utilidad DESC LIMIT ' . max(1, min(20, $limite));

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('UtilidadReporte::utilidadPorVendedor - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'id'         => (int) $r['id_usuario'],
            'nombre'     => trim((string) $r['nombre']) ?: ((string) $r['username'] ?: 'Usuario #' . (int) $r['id_usuario']),
            'username'   => (string) $r['username'],
            'ventas'     => (int) $r['ventas'],
            'venta_neta' => (float) $r['venta_neta'],
            'costo'      => (float) $r['costo'],
            'utilidad'   => (float) $r['utilidad'],
        ], $filas);
    }

    /** Detalle de UNA venta (para el botón ver del listado). */
    public function detalleVenta(int $idVenta): ?array
    {
        if ($idVenta <= 0) return null;

        $st = $this->conn->prepare(
            "SELECT v.*, s.nombre_sucursal, s.ciudad,
                    cj.codigo AS caja_codigo, cj.nombre AS caja_nombre,
                    u.username,
                    TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre
               FROM ventas v
               LEFT JOIN sucursales s ON s.id_sucursal = v.id_sucursal
               LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
               LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
               LEFT JOIN arqueos_caja a ON a.id_arqueo = v.id_arqueo
               LEFT JOIN cajas cj ON cj.id_caja = a.id_caja
              WHERE v.id_venta = :id AND v.estado = 'registrada' LIMIT 1"
        );
        $st->execute([':id' => $idVenta]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $st = $this->conn->prepare(
            "SELECT d.*, p.codigo, p.nombre
               FROM venta_detalle d
               LEFT JOIN productos p ON p.id_producto = d.id_producto
              WHERE d.id_venta = :id
              ORDER BY d.id_detalle ASC"
        );
        $st->execute([':id' => $idVenta]);
        $detalle = $st->fetchAll(PDO::FETCH_ASSOC);

        $nombre = trim((string) $fila['usuario_nombre']);

        return [
            'id'          => (int) $fila['id_venta'],
            'codigo'      => (string) $fila['codigo'],
            'fecha'       => date('d/m/Y H:i', strtotime($fila['fecha_venta'])),
            'sucursal'    => trim((string) ($fila['nombre_sucursal'] ?? '')) ?: 'Sucursal #' . (int) $fila['id_sucursal'],
            'ciudad'      => (string) ($fila['ciudad'] ?? ''),
            'caja_codigo' => (string) ($fila['caja_codigo'] ?? ''),
            'caja_nombre' => (string) ($fila['caja_nombre'] ?? ''),
            'usuario'     => $nombre !== '' ? $nombre : ((string) ($fila['username'] ?? '') ?: 'Usuario #' . (int) $fila['id_usuario']),
            'subtotal'    => (float) $fila['subtotal'],
            'descuento'   => (float) $fila['descuento'],
            'impuesto'    => (float) $fila['impuesto'],
            'total'       => (float) $fila['total'],
            'costo'       => (float) $fila['total_utilidad'],
            'utilidad'    => round((float) $fila['subtotal'] - (float) $fila['total_utilidad'], 2),
            'metodo'      => (string) $fila['metodo_pago'],
            'detalle'     => array_map(function ($d) {
                $cant = (float) $d['cantidad'];
                $subtotal = (float) $d['subtotal'];
                $costo = (float) $d['total_utilidad'];
                $utilidad = round($subtotal - $costo, 2);

                return [
                    'id_producto'    => (int) $d['id_producto'],
                    'codigo'         => (string) (($d['codigo'] ?? '') !== '' ? $d['codigo'] : 'PROD-' . $d['id_producto']),
                    'producto'       => (string) (($d['nombre'] ?? '') !== '' ? $d['nombre'] : 'Producto #' . $d['id_producto']),
                    'cantidad'       => $cant,
                    'precio_unitario'=> (float) $d['precio_unitario'],
                    'costo_unitario' => (float) $d['utilidad_bruta'],
                    'subtotal'       => $subtotal,
                    'costo'          => $costo,
                    'utilidad'       => $utilidad,
                    'margen'         => $subtotal > 0 ? round(($utilidad / $subtotal) * 100, 2) : 0.0,
                ];
            }, $detalle),
        ];
    }

    // =====================================================
    // CATÁLOGOS PARA FILTROS
    // =====================================================

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
            error_log('UtilidadReporte::sucursalesDisponibles - ' . $e->getMessage());
            return [];
        }
    }

    public function usuariosDisponibles(?int $idSucursal = null): array
    {
        $sql = "SELECT DISTINCT u.id_usuario, u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre
                  FROM ventas v
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                 WHERE v.estado = 'registrada'";
        $params = [];
        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= " AND v.id_sucursal = :s";
            $params[':s'] = $idSucursal;
        }
        $sql .= " ORDER BY nombre ASC, u.username ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('UtilidadReporte::usuariosDisponibles - ' . $e->getMessage());
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
                       s.nombre_sucursal, a.fecha_apertura
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
            error_log('UtilidadReporte::cajasDisponibles - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($a) => [
            'id'       => (int) $a['id_arqueo'],
            'codigo'   => (string) $a['caja_codigo'],
            'nombre'   => (string) $a['caja_nombre'],
            'sucursal' => (string) $a['nombre_sucursal'],
            'fecha'    => date('d/m/Y H:i', strtotime($a['fecha_apertura'])),
        ], $filas);
    }

    // =====================================================
    // AUXILIARES
    // =====================================================

    private function armarFiltros(array $f): array
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
        if (!empty($f['id_categoria'])) {
            $where[] = 'EXISTS (
                SELECT 1
                  FROM venta_detalle dx
                  INNER JOIN productos px ON px.id_producto = dx.id_producto
                 WHERE dx.id_venta = v.id_venta
                   AND px.id_categoria = :categoria
            )';
            $params[':categoria'] = (int) $f['id_categoria'];
        }
        if (!empty($f['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['desde'])) {
            $where[] = 'v.fecha_venta >= :desde';
            $params[':desde'] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['hasta'])) {
            $where[] = 'v.fecha_venta <= :hasta';
            $params[':hasta'] = $f['hasta'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }
}