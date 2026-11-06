<?php
/**
 * =====================================================
 * MODELO: ProductoStockReporte
 * -----------------------------------------------------
 * Lista de productos activos clasificados por estado de
 * stock:
 *   sin         → control_stock=1 y stock <= 0
 *   bajo        → control_stock=1 y 0 < stock <= stock_minimo
 *   optimo      → control_stock=1 y stock > stock_minimo
 *   sin_control → control_stock=0
 *
 * El STOCK que se informa es el GLOBAL de la tabla
 * `productos` (productos.stock), igual que el POS y que la
 * nueva compra. La sucursal se usa para tomar precio, costo
 * y, si existe, su stock mínimo; si la fila de la sucursal no
 * existe o su mínimo viene en 0, se usa el mínimo del producto.
 *
 * También se incluyen los productos que todavía no tienen
 * fila en `productos_sucursal`: antes quedaban fuera del
 * reporte aunque tuvieran existencia en `productos`.
 * =====================================================
 */

class ProductoStockReporte
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    /** Lista filtrada por estado de stock. */
    public function listar(int $idSucursal, string $estado = '', int $idCategoria = 0, string $buscar = ''): array
    {
        if ($idSucursal <= 0) {
            return [];
        }

        $where  = ['p.estado = 1', 'p.disponible = 1'];
        $params = [
            ':sucursal'      => $idSucursal,
            ':sucursal_join' => $idSucursal,
        ];

        if ($idCategoria > 0) {
            $where[] = 'p.id_categoria = :categoria';
            $params[':categoria'] = $idCategoria;
        }

        $buscar = trim($buscar);
        if ($buscar !== '') {
            if (mb_strlen($buscar) > 80) $buscar = mb_substr($buscar, 0, 80);
            $where[] = '(p.nombre LIKE :b1 OR p.codigo LIKE :b2)';
            $params[':b1'] = '%' . $buscar . '%';
            $params[':b2'] = '%' . $buscar . '%';
        }

        $sql = "SELECT p.id_producto, p.codigo, p.nombre, p.imagen,
                       p.precio, p.costo,
                       p.stock AS stock_global,
                       p.stock_minimo AS stock_minimo_producto,
                       p.control_stock AS control_stock_producto,
                       ps.stock AS stock_sucursal,
                       ps.stock_minimo AS stock_minimo_sucursal,
                       ps.control_stock AS control_stock_sucursal,
                       COALESCE(NULLIF(ps.precio_venta, 0), p.precio) AS precio_venta,
                       COALESCE(NULLIF(ps.costo_promedio, 0), p.costo) AS costo_promedio,
                       c.nombre_categoria, c.icono AS categoria_icono,
                       s.nombre_sucursal, s.ciudad,
                       COALESCE(
                         (SELECT pi.archivo FROM productos_imagenes pi
                           WHERE pi.id_producto = p.id_producto AND pi.principal = 1 LIMIT 1),
                         p.imagen
                       ) AS imagen_principal
                  FROM productos p
                  INNER JOIN categorias_productos c ON c.id_categoria = p.id_categoria
                  INNER JOIN sucursales s ON s.id_sucursal = :sucursal_join
                  LEFT  JOIN productos_sucursal ps
                         ON ps.id_producto = p.id_producto
                        AND ps.id_sucursal = :sucursal
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.stock ASC, p.nombre ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoStockReporte::listar - ' . $e->getMessage());
            return [];
        }

        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $resultado = [];

        foreach ($filas as $f) {
            // ---- Stock global (tabla productos) ----
            $stock = (float) $f['stock_global'];

            // ---- Mínimo: sucursal si tiene valor, si no el del producto ----
            $minimoSucursal = $f['stock_minimo_sucursal'] !== null
                ? (float) $f['stock_minimo_sucursal']
                : 0.0;
            $minimo = $minimoSucursal > 0
                ? $minimoSucursal
                : (float) $f['stock_minimo_producto'];

            // ---- Control de stock: sucursal si existe, si no el producto ----
            $controlSucursal = $f['control_stock_sucursal'];
            $controla = (int) ($controlSucursal !== null ? $controlSucursal : $f['control_stock_producto']) === 1;

            $precio = (float) $f['precio_venta'];
            $costo  = (float) $f['costo_promedio'];
            $img    = (string) ($f['imagen_principal'] ?? '');

            $estadoCalc = 'sin_control';
            $deficit = 0.0;

            if ($controla) {
                if ($stock <= 0)          $estadoCalc = 'sin';
                elseif ($stock <= $minimo) $estadoCalc = 'bajo';
                else                      $estadoCalc = 'optimo';

                if ($minimo > 0 && $stock < $minimo) {
                    $deficit = round($minimo - $stock, 3);
                }
            }

            // Filtro por estado (calculado con el stock global)
            if ($estado !== '' && $estado !== $estadoCalc) {
                continue;
            }

            $resultado[] = [
                'id'              => (int) $f['id_producto'],
                'codigo'          => (string) $f['codigo'],
                'nombre'          => (string) $f['nombre'],
                'categoria'       => (string) $f['nombre_categoria'],
                'categoria_icono' => (string) ($f['categoria_icono'] ?? 'fa-utensils'),
                'sucursal'        => (string) $f['nombre_sucursal'],
                'ciudad'          => (string) ($f['ciudad'] ?? ''),
                'imagen_url'      => $img !== ''
                    ? ($base !== '' ? $base . '/' . ltrim($img, '/') : '/' . ltrim($img, '/'))
                    : '',
                'stock'           => $stock,
                'stock_global'    => $stock,
                'stock_sucursal'  => $f['stock_sucursal'] !== null ? (float) $f['stock_sucursal'] : 0.0,
                'stock_minimo'    => $minimo,
                'deficit'         => $deficit,
                'controla'        => $controla,
                'precio'          => $precio,
                'costo'           => $costo,
                'valor_stock'     => round($stock * $costo, 2),
                'estado_stock'    => $estadoCalc,
            ];
        }

        return $resultado;
    }

    /**
     * Resumen por estado + valor total del inventario.
     * Se calcula sobre la misma lista que ve el reporte para
     * que las tarjetas y el PDF siempre coincidan.
     */
    public function resumen(int $idSucursal): array
    {
        $vacio = [
            'sin' => 0, 'bajo' => 0, 'optimo' => 0, 'sin_control' => 0,
            'total' => 0, 'valor' => 0.0, 'agotados_alerta' => 0,
        ];

        if ($idSucursal <= 0) {
            return $vacio;
        }

        $productos = $this->listar($idSucursal);

        $r = $vacio;

        foreach ($productos as $p) {
            $estado = (string) ($p['estado_stock'] ?? '');

            if (isset($r[$estado])) {
                $r[$estado]++;
            }

            $r['total']++;
            $r['valor'] += (float) ($p['valor_stock'] ?? 0);
        }

        $r['valor'] = round($r['valor'], 2);
        $r['agotados_alerta'] = $r['sin'] + $r['bajo'];

        return $r;
    }

    // =====================================================
    // CATÁLOGOS
    // =====================================================
    public function sucursales(array $usuario): array
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
            error_log('ProductoStockReporte::sucursales - ' . $e->getMessage());
            return [];
        }
    }

    public function categorias(): array
    {
        try {
            return $this->conn->query(
                "SELECT id_categoria, nombre_categoria, icono
                   FROM categorias_productos
                  WHERE estado = 1
                  ORDER BY orden, nombre_categoria"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoStockReporte::categorias - ' . $e->getMessage());
            return [];
        }
    }
}
