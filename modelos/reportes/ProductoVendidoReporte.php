<?php
/**
 * =====================================================
 * MODELO: ProductoVendidoReporte
 * -----------------------------------------------------
 * Ranking de productos vendidos por rango de fechas,
 * acotado por sucursal / categoría / usuario.
 *
 * Datos sensibles (costo, utilidad) solo se calculan
 * si $verUtilidad = true (permiso de SuperAdmin o
 * permiso explícito de utilidades).
 * =====================================================
 */

class ProductoVendidoReporte
{
    private PDO $conn;
    private bool $verUtilidad;

    public function __construct(?PDO $conexion = null, bool $verUtilidad = false)
    {
        $this->conn        = $conexion ?? Conexion::getInstance()->getConnection();
        $this->verUtilidad = $verUtilidad;
    }

    // =====================================================
    // LECTURA
    // =====================================================

    /**
     * Ranking de productos vendidos.
     * @return array<int, array> ya agregado por id_producto
     */
    public function ranking(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $colsUtilidad = $this->verUtilidad
            ? ", SUM(d.total_utilidad) AS costo
               , SUM(d.subtotal - d.total_utilidad) AS utilidad
               , CASE WHEN SUM(d.subtotal) > 0
                      THEN ROUND(SUM(d.subtotal - d.total_utilidad) / SUM(d.subtotal) * 100, 2)
                      ELSE 0 END AS margen"
            : ", NULL AS costo, NULL AS utilidad, NULL AS margen";

        $sql = "SELECT d.id_producto,
                       p.codigo,
                       p.nombre,
                       p.imagen,
                       c.nombre_categoria,
                       SUM(d.cantidad)             AS cantidad,
                       SUM(d.subtotal)             AS venta,
                       SUM(d.descuento)            AS descuento,
                       COUNT(DISTINCT d.id_venta)  AS veces_vendido,
                       AVG(d.precio_unitario)      AS precio_promedio
                       {$colsUtilidad}
                  FROM venta_detalle d
                  INNER JOIN ventas v   ON v.id_venta  = d.id_venta
                  INNER JOIN productos p ON p.id_producto = d.id_producto
                  LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";

        $sql .= ($where !== '' ? ' WHERE ' . $where : ' WHERE 1=1');
        $sql .= " AND v.estado = 'registrada'";
        $sql .= " GROUP BY d.id_producto
                  ORDER BY cantidad DESC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoVendidoReporte::ranking - ' . $e->getMessage());
            return [];
        }

        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $totalVenta = 0.0;
        foreach ($filas as $f2) {
            $totalVenta += (float) $f2['venta'];
        }

        return array_map(function (array $f2) use ($base, $totalVenta) {
            $img = (string) ($f2['imagen'] ?? '');
            $venta = (float) $f2['venta'];

            return [
                'id'             => (int) $f2['id_producto'],
                'codigo'         => (string) $f2['codigo'],
                'nombre'         => (string) $f2['nombre'],
                'categoria'      => (string) ($f2['nombre_categoria'] ?? ''),
                'imagen'         => $img,
                'imagen_url'     => $img !== ''
                    ? ($base !== '' ? $base . '/' . ltrim($img, '/') : '/' . ltrim($img, '/'))
                    : '',
                'cantidad'       => (float) $f2['cantidad'],
                'veces_vendido'  => (int) $f2['veces_vendido'],
                'precio_prom'    => round((float) $f2['precio_promedio'], 2),
                'venta'          => $venta,
                'descuento'      => (float) $f2['descuento'],
                'participacion'  => $totalVenta > 0 ? round(($venta / $totalVenta) * 100, 2) : 0.0,
                'costo'          => $f2['costo'] !== null ? (float) $f2['costo'] : null,
                'utilidad'       => $f2['utilidad'] !== null ? (float) $f2['utilidad'] : null,
                'margen'         => $f2['margen'] !== null ? (float) $f2['margen'] : null,
            ];
        }, $filas);
    }

    public function resumen(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT
                    COUNT(DISTINCT d.id_producto) AS productos,
                    COUNT(DISTINCT d.id_venta)    AS ventas,
                    COUNT(DISTINCT v.id_usuario)  AS vendedores,
                    COALESCE(SUM(d.cantidad), 0)  AS unidades,
                    COALESCE(SUM(d.subtotal), 0)  AS venta,
                    COALESCE(SUM(d.descuento), 0) AS descuento
                  FROM venta_detalle d
                  INNER JOIN ventas v ON v.id_venta = d.id_venta
                  INNER JOIN productos p ON p.id_producto = d.id_producto
                  LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('ProductoVendidoReporte::resumen - ' . $e->getMessage());
            return [];
        }

        $venta = (float) ($r['venta'] ?? 0);
        $costo = 0.0;
        $utilidad = 0.0;

        if ($this->verUtilidad) {
            $sql2 = "SELECT COALESCE(SUM(d.total_utilidad),0) AS costo,
                            COALESCE(SUM(d.subtotal - d.total_utilidad),0) AS utilidad
                       FROM venta_detalle d
                       INNER JOIN ventas v ON v.id_venta = d.id_venta
                       INNER JOIN productos p ON p.id_producto = d.id_producto
                       LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";
            if ($where !== '') $sql2 .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
            else $sql2 .= " WHERE v.estado = 'registrada'";

            try {
                $st2 = $this->conn->prepare($sql2);
                $st2->execute($params);
                $r2 = $st2->fetch(PDO::FETCH_ASSOC) ?: [];
                $costo    = (float) ($r2['costo'] ?? 0);
                $utilidad = (float) ($r2['utilidad'] ?? 0);
            } catch (PDOException $e) {
                error_log('ProductoVendidoReporte::resumen utilidad - ' . $e->getMessage());
            }
        }

        return [
            'productos'  => (int) ($r['productos'] ?? 0),
            'ventas'     => (int) ($r['ventas'] ?? 0),
            'vendedores' => (int) ($r['vendedores'] ?? 0),
            'unidades'   => (float) ($r['unidades'] ?? 0),
            'venta'      => $venta,
            'descuento'  => (float) ($r['descuento'] ?? 0),
            'costo'      => $this->verUtilidad ? $costo : null,
            'utilidad'   => $this->verUtilidad ? $utilidad : null,
            'margen'     => $this->verUtilidad && $venta > 0
                ? round(($utilidad / $venta) * 100, 2) : null,
        ];
    }

    /** Top N por cantidad (para gráfica de barras). */
    public function topCantidad(array $f, int $limite = 10): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT p.nombre, p.codigo, SUM(d.cantidad) AS cantidad, SUM(d.subtotal) AS venta
                  FROM venta_detalle d
                  INNER JOIN ventas v ON v.id_venta = d.id_venta
                  INNER JOIN productos p ON p.id_producto = d.id_producto
                  LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= " GROUP BY d.id_producto ORDER BY cantidad DESC LIMIT " . max(1, min(30, $limite));

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoVendidoReporte::topCantidad - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'nombre'   => (string) $r['nombre'],
            'codigo'   => (string) $r['codigo'],
            'cantidad' => (float) $r['cantidad'],
            'venta'    => (float) $r['venta'],
        ], $filas);
    }

    /** Dona por categoría. */
    public function porCategoria(array $f): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT COALESCE(c.nombre_categoria, 'Sin categoría') AS categoria,
                       SUM(d.cantidad) AS cantidad,
                       SUM(d.subtotal) AS venta
                  FROM venta_detalle d
                  INNER JOIN ventas v ON v.id_venta = d.id_venta
                  INNER JOIN productos p ON p.id_producto = d.id_producto
                  LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= " GROUP BY c.id_categoria ORDER BY venta DESC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoVendidoReporte::porCategoria - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'categoria' => (string) $r['categoria'],
            'cantidad'  => (float) $r['cantidad'],
            'venta'     => (float) $r['venta'],
        ], $filas);
    }

    /** Top N por venta (para mostrar la columna "más rentable" si aplica). */
    public function topVenta(array $f, int $limite = 5): array
    {
        [$where, $params] = $this->armarFiltros($f);

        $sql = "SELECT p.nombre, p.codigo, SUM(d.subtotal) AS venta
                  FROM venta_detalle d
                  INNER JOIN ventas v ON v.id_venta = d.id_venta
                  INNER JOIN productos p ON p.id_producto = d.id_producto
                  LEFT  JOIN categorias_productos c ON c.id_categoria = p.id_categoria";

        if ($where !== '') $sql .= ' WHERE ' . $where . " AND v.estado = 'registrada'";
        else $sql .= " WHERE v.estado = 'registrada'";

        $sql .= " GROUP BY d.id_producto ORDER BY venta DESC LIMIT " . max(1, min(20, $limite));

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoVendidoReporte::topVenta - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($r) => [
            'nombre' => (string) $r['nombre'],
            'codigo' => (string) $r['codigo'],
            'venta'  => (float) $r['venta'],
        ], $filas);
    }

    // =====================================================
    // CATÁLOGOS
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
            error_log('ProductoVendidoReporte::sucursalesDisponibles - ' . $e->getMessage());
            return [];
        }
    }

    public function categoriasDisponibles(): array
    {
        try {
            return $this->conn->query(
                "SELECT id_categoria, nombre_categoria, icono
                   FROM categorias_productos
                  WHERE estado = 1
                  ORDER BY orden, nombre_categoria"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoVendidoReporte::categoriasDisponibles - ' . $e->getMessage());
            return [];
        }
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
        if (!empty($f['id_categoria'])) {
            $where[] = 'p.id_categoria = :categoria';
            $params[':categoria'] = (int) $f['id_categoria'];
        }
        if (!empty($f['id_usuario'])) {
            $where[] = 'v.id_usuario = :usuario';
            $params[':usuario'] = (int) $f['id_usuario'];
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