<?php
/**
 * =====================================================
 * MODELO: CompraReporte  (modelos/comprasxfechas/CompraReporte.php)
 * -----------------------------------------------------
 * Consultas de solo lectura para el reporte de compras
 * por rango de fechas, sucursal, usuario y forma de pago.
 *
 * Tablas usadas:
 *   · compras
 *   · compra_detalle
 *   · compras_pagos
 *   · proveedores / sucursales / usuarios
 * =====================================================
 */

class CompraReporte
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    /**
     * Lista compras según filtros.
     * @return array compras con campos ya formateados
     */
    public function listar(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT c.id_compra,
                       c.id_sucursal, c.id_proveedor, c.id_usuario,
                       c.tipo_documento, c.numero_documento,
                       c.fecha_compra, c.forma_pago, c.estado,
                       c.subtotal, c.descuento, c.impuesto_porcentaje, c.impuesto,
                       c.total, c.abono, c.observaciones, c.created_at,
                       s.nombre_sucursal, s.ciudad,
                       pr.nombre AS proveedor_nombre, pr.nit AS proveedor_nit,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                       (SELECT COUNT(*) FROM compra_detalle d WHERE d.id_compra = c.id_compra) AS items,
                       COALESCE((SELECT SUM(pg.monto) FROM compras_pagos pg WHERE pg.id_compra = c.id_compra), 0) AS pagado,
                       GREATEST(0, c.total - c.abono -
                           COALESCE((SELECT SUM(pg.monto) FROM compras_pagos pg WHERE pg.id_compra = c.id_compra), 0)
                       ) AS saldo
                  FROM compras c
                  INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
                  INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                  INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== '') $sql .= " WHERE " . $where;
        $sql .= " ORDER BY c.fecha_compra DESC, c.id_compra DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CompraReporte::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($f) => $this->normalizar($f), $filas);
    }

    /**
     * Resumen general del reporte (tarjetas + gráficas).
     */
    public function resumen(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT
                    COUNT(*) AS compras,
                    COALESCE(SUM(c.total), 0) AS total,
                    COALESCE(SUM(CASE WHEN c.forma_pago = 'contado' THEN c.total ELSE 0 END), 0) AS contado,
                    COALESCE(SUM(CASE WHEN c.forma_pago = 'credito' THEN c.total ELSE 0 END), 0) AS credito,
                    COALESCE(SUM(CASE WHEN c.forma_pago = 'contado' THEN 1 ELSE 0 END), 0) AS compras_contado,
                    COALESCE(SUM(CASE WHEN c.forma_pago = 'credito' THEN 1 ELSE 0 END), 0) AS compras_credito,
                    COUNT(DISTINCT c.id_proveedor) AS proveedores,
                    COALESCE(SUM(c.abono), 0) AS abonos,
                    COALESCE(SUM(CASE WHEN c.estado = 'anulada' THEN 1 ELSE 0 END), 0) AS anuladas
                FROM compras c
                INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== '') $sql .= " WHERE " . $where;

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('CompraReporte::resumen - ' . $e->getMessage());
            return [];
        }

        $pagado = $this->totalPagado($where, $params);

        return [
            'compras'          => (int) ($r['compras'] ?? 0),
            'total'            => (float) ($r['total'] ?? 0),
            'contado'          => (float) ($r['contado'] ?? 0),
            'credito'          => (float) ($r['credito'] ?? 0),
            'compras_contado'  => (int) ($r['compras_contado'] ?? 0),
            'compras_credito'  => (int) ($r['compras_credito'] ?? 0),
            'proveedores'      => (int) ($r['proveedores'] ?? 0),
            'abonos'           => (float) ($r['abonos'] ?? 0),
            'pagado_posterior' => $pagado,
            'saldo_pendiente'  => round(max(0, (float) ($r['total'] ?? 0) - (float) ($r['abonos'] ?? 0) - $pagado), 2),
            'anuladas'         => (int) ($r['anuladas'] ?? 0),
        ];
    }

    /**
     * Agrupado por día (para gráfica de líneas).
     */
    public function porDia(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT DATE(c.fecha_compra) AS fecha,
                       COUNT(*) AS compras,
                       COALESCE(SUM(c.total), 0) AS total
                  FROM compras c
                  INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                  INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== '') $sql .= " WHERE " . $where;
        $sql .= " GROUP BY DATE(c.fecha_compra) ORDER BY fecha ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CompraReporte::porDia - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'fecha'   => $r['fecha'],
            'compras' => (int) $r['compras'],
            'total'   => (float) $r['total'],
        ], $filas);
    }

    /**
     * Agrupado por forma de pago (para dona/barras).
     */
    public function porFormaPago(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT c.forma_pago,
                       COUNT(*) AS compras,
                       COALESCE(SUM(c.total), 0) AS total
                  FROM compras c
                  INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                  INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== '') $sql .= " WHERE " . $where;
        $sql .= " GROUP BY c.forma_pago";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CompraReporte::porFormaPago - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'forma'   => (string) $r['forma_pago'],
            'compras' => (int) $r['compras'],
            'total'   => (float) $r['total'],
        ], $filas);
    }

    /**
     * Top proveedores por monto.
     */
    public function topProveedores(array $f, int $limite = 5): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT pr.id_proveedor, pr.nombre,
                       COUNT(*) AS compras,
                       COALESCE(SUM(c.total), 0) AS total
                  FROM compras c
                  INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                  INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== '') $sql .= " WHERE " . $where;
        $sql .= " GROUP BY pr.id_proveedor ORDER BY total DESC LIMIT " . max(1, min(20, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CompraReporte::topProveedores - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'id'      => (int) $r['id_proveedor'],
            'nombre'  => (string) $r['nombre'],
            'compras' => (int) $r['compras'],
            'total'   => (float) $r['total'],
        ], $filas);
    }

    /**
     * Detalle completo de una compra (para el ojo).
     */
    public function detalle(int $idCompra): ?array
    {
        if ($idCompra <= 0) return null;

        $stmt = $this->conn->prepare(
            "SELECT c.*, s.nombre_sucursal, s.ciudad,
                    pr.nombre AS proveedor_nombre, pr.nit AS proveedor_nit,
                    pr.telefono AS proveedor_telefono, pr.contacto AS proveedor_contacto,
                    u.username,
                    TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre
               FROM compras c
               INNER JOIN sucursales s ON s.id_sucursal = c.id_sucursal
               INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
               INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
               LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
              WHERE c.id_compra = :id LIMIT 1"
        );
        $stmt->execute([':id' => $idCompra]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $stmt = $this->conn->prepare(
            "SELECT d.*, p.codigo, p.nombre
               FROM compra_detalle d
               INNER JOIN productos p ON p.id_producto = d.id_producto
              WHERE d.id_compra = :id ORDER BY d.id_detalle"
        );
        $stmt->execute([':id' => $idCompra]);
        $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->conn->prepare(
            "SELECT * FROM compras_pagos WHERE id_compra = :id ORDER BY fecha_pago"
        );
        $stmt->execute([':id' => $idCompra]);
        $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pagado = 0.0;
        foreach ($pagos as $p) $pagado += (float) $p['monto'];

        $usuario = trim((string) $fila['usuario_nombre']);
        $nombreUsuario = $usuario !== '' ? $usuario : $fila['username'];

        return [
            'id'               => (int) $fila['id_compra'],
            'id_sucursal'      => (int) $fila['id_sucursal'],
            'sucursal'         => (string) $fila['nombre_sucursal'],
            'ciudad'           => (string) ($fila['ciudad'] ?? ''),
            'id_proveedor'     => (int) $fila['id_proveedor'],
            'proveedor'        => (string) $fila['proveedor_nombre'],
            'proveedor_nit'    => (string) ($fila['proveedor_nit'] ?? ''),
            'proveedor_telefono' => (string) ($fila['proveedor_telefono'] ?? ''),
            'proveedor_contacto' => (string) ($fila['proveedor_contacto'] ?? ''),
            'usuario'          => $nombreUsuario,
            'username'         => (string) $fila['username'],
            'tipo_documento'   => (string) $fila['tipo_documento'],
            'numero_documento' => (string) ($fila['numero_documento'] ?? ''),
            'fecha_compra'     => date('d/m/Y H:i', strtotime($fila['fecha_compra'])),
            'fecha_iso'        => (string) $fila['fecha_compra'],
            'forma_pago'       => (string) $fila['forma_pago'],
            'estado'           => (string) $fila['estado'],
            'subtotal'         => (float) $fila['subtotal'],
            'descuento'        => (float) $fila['descuento'],
            'impuesto_porcentaje' => (float) $fila['impuesto_porcentaje'],
            'impuesto'         => (float) $fila['impuesto'],
            'total'            => (float) $fila['total'],
            'abono'            => (float) $fila['abono'],
            'pagado'           => round($pagado, 2),
            'saldo'            => round(max(0, (float) $fila['total'] - (float) $fila['abono'] - $pagado), 2),
            'observaciones'    => (string) ($fila['observaciones'] ?? ''),
            'detalle'          => array_map(fn($d) => [
                'codigo'          => (string) $d['codigo'],
                'producto'        => (string) $d['nombre'],
                'cantidad'        => (float) $d['cantidad'],
                'costo_unitario'  => (float) $d['costo_unitario'],
                'precio_venta'    => (float) $d['precio_venta_unitario'],
                'subtotal'        => (float) $d['subtotal'],
            ], $detalle),
            'pagos'            => array_map(fn($p) => [
                'monto'        => (float) $p['monto'],
                'metodo_pago'  => (string) $p['metodo_pago'],
                'referencia'   => (string) ($p['referencia'] ?? ''),
                'fecha_pago'   => !empty($p['fecha_pago']) ? date('d/m/Y H:i', strtotime($p['fecha_pago'])) : '—',
            ], $pagos),
        ];
    }

    /** Sucursales permitidas según usuario. */
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
            error_log('CompraReporte::sucursalesDisponibles - ' . $e->getMessage());
            return [];
        }
    }

    /** Usuarios que han hecho compras (para filtro). */
    public function usuariosDisponibles(?int $idSucursal = null): array
    {
        $sql = "SELECT DISTINCT u.id_usuario, u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre
                  FROM compras c
                  INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";
        $params = [];
        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= " WHERE c.id_sucursal = :s";
            $params[':s'] = $idSucursal;
        }
        $sql .= " ORDER BY nombre ASC, u.username ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CompraReporte::usuariosDisponibles - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($u) => [
            'id'       => (int) $u['id_usuario'],
            'username' => (string) $u['username'],
            'nombre'   => trim((string) $u['nombre']) ?: $u['username'],
        ], $filas);
    }

    // =================================================
    // AUXILIARES
    // =================================================

    private function armarFiltros(array $f): array
    {
        $where = [];
        $params = [];

        if (!empty($f['id_sucursal'])) {
            $where[] = 'c.id_sucursal = :sucursal';
            $params[':sucursal'] = (int) $f['id_sucursal'];
        }
        if (!empty($f['id_usuario'])) {
            $where[] = 'c.id_usuario = :usuario';
            $params[':usuario'] = (int) $f['id_usuario'];
        }
        if (!empty($f['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['desde'])) {
            $where[] = 'c.fecha_compra >= :desde';
            $params[':desde'] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['hasta'])) {
            $where[] = 'c.fecha_compra <= :hasta';
            $params[':hasta'] = $f['hasta'] . ' 23:59:59';
        }
        if (in_array($f['forma_pago'] ?? '', ['contado','credito'], true)) {
            $where[] = 'c.forma_pago = :forma';
            $params[':forma'] = $f['forma_pago'];
        }
        if (in_array($f['estado'] ?? '', ['registrada','anulada'], true)) {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = $f['estado'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function totalPagado(string $where, array $params): float
    {
        $sql = "SELECT COALESCE(SUM(pg.monto), 0)
                  FROM compras_pagos pg
                  INNER JOIN compras c ON c.id_compra = pg.id_compra
                  INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                  INNER JOIN usuarios u ON u.id_usuario = c.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";
        if ($where !== '') $sql .= " WHERE " . $where;

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            return round((float) $st->fetchColumn(), 2);
        } catch (PDOException $e) {
            error_log('CompraReporte::totalPagado - ' . $e->getMessage());
            return 0.0;
        }
    }

    private function normalizar(array $f): array
    {
        $usuario = trim((string) ($f['usuario_nombre'] ?? ''));
        $total = (float) $f['total'];
        $abono = (float) $f['abono'];
        $pagado = (float) $f['pagado'];
        $saldo = $f['forma_pago'] === 'credito'
            ? round(max(0, $total - $abono - $pagado), 2)
            : 0.0;

        return [
            'id'               => (int) $f['id_compra'],
            'id_sucursal'      => (int) $f['id_sucursal'],
            'sucursal'         => (string) $f['nombre_sucursal'],
            'ciudad'           => (string) ($f['ciudad'] ?? ''),
            'id_proveedor'     => (int) $f['id_proveedor'],
            'proveedor'        => (string) $f['proveedor_nombre'],
            'proveedor_nit'    => (string) ($f['proveedor_nit'] ?? ''),
            'usuario'          => $usuario !== '' ? $usuario : (string) $f['username'],
            'username'         => (string) $f['username'],
            'tipo_documento'   => (string) $f['tipo_documento'],
            'numero_documento' => (string) ($f['numero_documento'] ?? ''),
            'fecha_compra'     => date('d/m/Y H:i', strtotime($f['fecha_compra'])),
            'fecha_iso'        => (string) $f['fecha_compra'],
            'forma_pago'       => (string) $f['forma_pago'],
            'estado'           => (string) $f['estado'],
            'subtotal'         => (float) $f['subtotal'],
            'descuento'        => (float) $f['descuento'],
            'impuesto'         => (float) $f['impuesto'],
            'total'            => $total,
            'abono'            => $abono,
            'pagado'           => round($pagado, 2),
            'saldo'            => $saldo,
            'items'            => (int) $f['items'],
        ];
    }
}