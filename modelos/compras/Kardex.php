<?php
/**
 * =====================================================
 * MODELO: Kardex  (modelos/compras/Kardex.php)
 * -----------------------------------------------------
 * Movimientos valorados de inventario POR SUCURSAL.
 *
 *   · Cada entrada/salida actualiza productos_sucursal.
 *   · El costo promedio se recalcula con cada entrada.
 *   · Cada movimiento guarda su saldo histórico, de modo
 *     que el kardex se pueda auditar sin recalcular.
 *
 * Los métodos registrarEntrada/registrarSalida se llaman
 * dentro de una transacción abierta por Compra/Venta.
 * =====================================================
 */

class Kardex
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  CONSULTAS
    // =================================================

    /**
     * Movimientos filtrados.
     *
     * @param int|null $idSucursal null = todas (sólo SuperAdmin)
     */
    public function listar(
        ?int $idSucursal,
        int $idProducto = 0,
        string $tipo = '',
        string $desde = '',
        string $hasta = '',
        int $limite = 500
    ): array {
        $where = [];
        $params = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $where[] = 'k.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        if ($idProducto > 0) {
            $where[] = 'k.id_producto = :producto';
            $params[':producto'] = $idProducto;
        }

        if ($tipo === 'entrada' || $tipo === 'salida' || $tipo === 'ajuste') {
            $where[] = 'k.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        if ($desde !== '') {
            $where[] = 'k.fecha >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }

        if ($hasta !== '') {
            $where[] = 'k.fecha <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        $sql = "SELECT k.*,
                       p.codigo AS producto_codigo,
                       p.nombre AS producto_nombre,
                       p.imagen AS producto_imagen,
                       s.nombre_sucursal,
                       s.ciudad,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre,
                       c.numero_documento,
                       c.id_compra
                FROM kardex k
                INNER JOIN productos p ON p.id_producto = k.id_producto
                INNER JOIN sucursales s ON s.id_sucursal = k.id_sucursal
                LEFT  JOIN usuarios u ON u.id_usuario = k.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                LEFT  JOIN compras c
                       ON k.origen = 'compra' AND c.id_compra = k.id_referencia";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY k.fecha DESC, k.id_movimiento DESC';
        $sql .= ' LIMIT ' . max(1, min(2000, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Kardex::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    /** Productos con existencia o movimientos en la sucursal (para el filtro). */
    public function productosDisponibles(?int $idSucursal): array
    {
        $sql = "SELECT DISTINCT p.id_producto, p.codigo, p.nombre
                FROM productos p
                INNER JOIN productos_sucursal ps ON ps.id_producto = p.id_producto";

        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= ' AND ps.id_sucursal = :sucursal';
        }

        $sql .= ' ORDER BY p.nombre ASC';

        try {
            $stmt = $this->conn->prepare($sql);

            if ($idSucursal !== null && $idSucursal > 0) {
                $stmt->execute([':sucursal' => $idSucursal]);
            } else {
                $stmt->execute();
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Kardex::productosDisponibles - ' . $e->getMessage());
            return [];
        }
    }

    /** Tarjetas de resumen del kardex. */
    public function resumen(?int $idSucursal): array
    {
        $r = [
            'productos'        => 0,
            'stock_total'      => 0.0,
            'valor_inventario' => 0.0,
            'stock_bajo'       => 0,
            'entradas_mes'     => 0.0,
            'salidas_mes'      => 0.0,
        ];

        $filtroPs = '';
        $filtroK = '';
        $paramsPs = [];
        $paramsK = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $filtroPs = ' AND id_sucursal = :sucursal';
            $filtroK = ' AND id_sucursal = :sucursal';
            $paramsPs[':sucursal'] = $idSucursal;
            $paramsK[':sucursal'] = $idSucursal;
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT
                    COUNT(*) AS productos,
                    COALESCE(SUM(stock), 0) AS stock_total,
                    COALESCE(SUM(valor_inventario), 0) AS valor_inventario,
                    COALESCE(SUM(CASE
                        WHEN control_stock = 1 AND stock_minimo > 0 AND stock <= stock_minimo
                        THEN 1 ELSE 0 END), 0) AS stock_bajo
                 FROM productos_sucursal
                 WHERE estado = 1" . $filtroPs
            );
            $stmt->execute($paramsPs);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $r['productos'] = (int) $fila['productos'];
                $r['stock_total'] = (float) $fila['stock_total'];
                $r['valor_inventario'] = (float) $fila['valor_inventario'];
                $r['stock_bajo'] = (int) $fila['stock_bajo'];
            }

            $stmt = $this->conn->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE 0 END), 0) AS entradas,
                    COALESCE(SUM(CASE WHEN tipo = 'salida' THEN ABS(cantidad) ELSE 0 END), 0) AS salidas
                 FROM kardex
                 WHERE fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')" . $filtroK
            );
            $stmt->execute($paramsK);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $r['entradas_mes'] = (float) $fila['entradas'];
                $r['salidas_mes'] = (float) $fila['salidas'];
            }
        } catch (PDOException $e) {
            error_log('Kardex::resumen - ' . $e->getMessage());
        }

        return $r;
    }

    /** Existencia y precio actuales de un producto en una sucursal. */
    public function existencia(int $idSucursal, int $idProducto): ?array
    {
        if ($idSucursal <= 0 || $idProducto <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare(
                'SELECT stock, stock_minimo, costo_promedio, valor_inventario, precio_venta, control_stock
                 FROM productos_sucursal
                 WHERE id_sucursal = :sucursal AND id_producto = :producto
                 LIMIT 1'
            );
            $stmt->execute([':sucursal' => $idSucursal, ':producto' => $idProducto]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Kardex::existencia - ' . $e->getMessage());
            return null;
        }

        if (!$fila) {
            return null;
        }

        return [
            'stock'            => (float) $fila['stock'],
            'stock_minimo'     => (float) $fila['stock_minimo'],
            'costo_promedio'   => (float) $fila['costo_promedio'],
            'valor_inventario' => (float) $fila['valor_inventario'],
            'precio_venta'     => (float) $fila['precio_venta'],
            'control_stock'    => (int) $fila['control_stock'],
        ];
    }

    // =================================================
    //  MOVIMIENTOS
    // =================================================

    /**
     * Entrada de inventario (compra).
     * Debe invocarse dentro de una transacción.
     */
    public function registrarEntrada(
        int $idSucursal,
        int $idProducto,
        float $cantidad,
        float $costoUnitario,
        float $precioVenta,
        string $origen,
        ?int $idReferencia,
        string $detalle,
        int $idUsuario
    ): array {
        if ($idSucursal <= 0 || $idProducto <= 0 || $cantidad <= 0) {
            return self::err('Datos de entrada no válidos');
        }

        $cantidad = round($cantidad, 3);
        $costoUnitario = round(max(0, $costoUnitario), 4);
        $precioVenta = round(max(0, $precioVenta), 2);

        try {
            $this->prepararFila($idSucursal, $idProducto, $precioVenta);
            $actual = $this->bloquearFila($idSucursal, $idProducto);

            if (!$actual) {
                return self::err('No se encontró el inventario de la sucursal');
            }

            $stockNuevo = round((float) $actual['stock'] + $cantidad, 3);
            $valorNuevo = round((float) $actual['valor_inventario'] + ($cantidad * $costoUnitario), 2);
            $costoPromedio = $stockNuevo > 0 ? round($valorNuevo / $stockNuevo, 4) : 0.0;

            $this->conn->prepare(
                "UPDATE productos_sucursal
                 SET stock = :stock,
                     costo_promedio = :costo,
                     valor_inventario = :valor,
                     precio_venta = CASE WHEN :precio > 0 THEN :precio2 ELSE precio_venta END,
                     control_stock = 1,
                     estado = 1
                 WHERE id_sucursal = :sucursal AND id_producto = :producto"
            )->execute([
                ':stock'    => $stockNuevo,
                ':costo'    => $costoPromedio,
                ':valor'    => $valorNuevo,
                ':precio'   => $precioVenta,
                ':precio2'  => $precioVenta,
                ':sucursal' => $idSucursal,
                ':producto' => $idProducto,
            ]);

            // El stock global del producto es el agregado de sucursales.
            $this->conn->prepare(
                'UPDATE productos
                 SET stock = GREATEST(0, stock + :cantidad),
                     costo = :costo,
                     control_stock = 1
                 WHERE id_producto = :producto'
            )->execute([
                ':cantidad' => $cantidad,
                ':costo'    => $costoUnitario,
                ':producto' => $idProducto,
            ]);

            $idMovimiento = $this->insertarMovimiento(
                $idSucursal,
                $idProducto,
                'entrada',
                $origen,
                $idReferencia,
                $detalle,
                $cantidad,
                $costoUnitario,
                $precioVenta,
                round($cantidad * $costoUnitario, 2),
                $stockNuevo,
                $costoPromedio,
                $valorNuevo,
                $idUsuario
            );

            return [
                'exito'            => true,
                'mensaje'          => 'Entrada registrada',
                'id_movimiento'    => $idMovimiento,
                'saldo_cantidad'   => $stockNuevo,
                'costo_promedio'   => $costoPromedio,
                'saldo_valor'      => $valorNuevo,
            ];
        } catch (PDOException $e) {
            error_log('Kardex::registrarEntrada - ' . $e->getMessage());
            return self::err('No se pudo registrar la entrada al kardex');
        }
    }

    /**
     * Salida de inventario (venta o ajuste negativo).
     * Debe invocarse dentro de una transacción.
     */
    public function registrarSalida(
        int $idSucursal,
        int $idProducto,
        float $cantidad,
        string $origen,
        ?int $idReferencia,
        string $detalle,
        int $idUsuario,
        bool $permitirNegativo = false,
        bool $usarStockGlobal = false
    ): array {
        if ($idSucursal <= 0 || $idProducto <= 0 || $cantidad <= 0) {
            return self::err('Datos de salida no válidos');
        }

        $cantidad = round($cantidad, 3);

        // La venta del POS descuenta el stock global del producto
        // (productos.stock) y no depende de la fila de la sucursal.
        if ($usarStockGlobal) {
            return $this->salidaStockGlobal(
                $idSucursal,
                $idProducto,
                $cantidad,
                $origen,
                $idReferencia,
                $detalle,
                $idUsuario,
                $permitirNegativo
            );
        }

        try {
            $actual = $this->bloquearFila($idSucursal, $idProducto);

            if (!$actual) {
                return self::err('No hay inventario de ese producto en la sucursal');
            }

            $stockActual = (float) $actual['stock'];

            if (!$permitirNegativo && $stockActual + 0.0001 < $cantidad) {
                return self::err('Stock insuficiente en esta sucursal');
            }

            $costoPromedio = (float) $actual['costo_promedio'];
            $stockNuevo = round($stockActual - $cantidad, 3);
            $valorNuevo = max(0, round((float) $actual['valor_inventario'] - ($cantidad * $costoPromedio), 2));

            if ($stockNuevo <= 0) {
                $stockNuevo = 0.0;
                $valorNuevo = 0.0;
                $costoPromedio = 0.0;
            } elseif ($valorNuevo > 0) {
                $costoPromedio = round($valorNuevo / $stockNuevo, 4);
            }

            $this->conn->prepare(
                "UPDATE productos_sucursal
                 SET stock = :stock,
                     costo_promedio = :costo,
                     valor_inventario = :valor
                 WHERE id_sucursal = :sucursal AND id_producto = :producto"
            )->execute([
                ':stock'    => $stockNuevo,
                ':costo'    => $costoPromedio,
                ':valor'    => $valorNuevo,
                ':sucursal' => $idSucursal,
                ':producto' => $idProducto,
            ]);

            $this->conn->prepare(
                'UPDATE productos SET stock = GREATEST(0, stock - :cantidad) WHERE id_producto = :producto'
            )->execute([
                ':cantidad' => $cantidad,
                ':producto' => $idProducto,
            ]);

            $idMovimiento = $this->insertarMovimiento(
                $idSucursal,
                $idProducto,
                'salida',
                $origen,
                $idReferencia,
                $detalle,
                -$cantidad,
                $costoPromedio,
                null,
                -round($cantidad * $costoPromedio, 2),
                $stockNuevo,
                $costoPromedio,
                $valorNuevo,
                $idUsuario
            );

            return [
                'exito'          => true,
                'mensaje'        => 'Salida registrada',
                'id_movimiento'  => $idMovimiento,
                'saldo_cantidad' => $stockNuevo,
                'costo_promedio' => $costoPromedio,
                'saldo_valor'    => $valorNuevo,
            ];
        } catch (PDOException $e) {
            error_log('Kardex::registrarSalida - ' . $e->getMessage());
            return self::err('No se pudo registrar la salida del kardex');
        }
    }

    /**
     * Ajuste manual de inventario (reajuste de stock desde Productos).
     *
     * Registra un movimiento tipo 'ajuste' / origen 'ajuste' con la
     * diferencia de existencia. Si la sucursal tiene fila en
     * productos_sucursal se usa y actualiza su saldo; si no existe se
     * usa el stock global del producto y su costo.
     *
     * No debe romper la edición del producto: el llamador registra el
     * error en el log y continúa.
     */
    public function registrarAjuste(
        int $idSucursal,
        int $idProducto,
        float $cantidad,
        float $costoUnitario,
        string $detalle,
        int $idUsuario
    ): array {
        if ($idSucursal <= 0 || $idProducto <= 0) {
            return self::err('Datos del ajuste no válidos');
        }

        $cantidad = round($cantidad, 3);
        if (abs($cantidad) < 0.0001) {
            return self::err('El ajuste no tiene diferencia de cantidad');
        }

        $costoUnitario = round(max(0, $costoUnitario), 4);

        try {
            $actual = $this->bloquearFila($idSucursal, $idProducto);

            if ($actual) {
                $stockNuevo = round((float) $actual['stock'] + $cantidad, 3);
                if ($stockNuevo < 0) {
                    $stockNuevo = 0.0;
                }

                $costoPromedio = (float) $actual['costo_promedio'];
                $valorNuevo = max(
                    0,
                    round((float) $actual['valor_inventario'] + ($cantidad * $costoUnitario), 2)
                );

                if ($stockNuevo <= 0) {
                    $stockNuevo = 0.0;
                    $valorNuevo = 0.0;
                    $costoPromedio = 0.0;
                } elseif ($valorNuevo > 0) {
                    $costoPromedio = round($valorNuevo / $stockNuevo, 4);
                }

                $this->conn->prepare(
                    "UPDATE productos_sucursal
                     SET stock = :stock,
                         costo_promedio = :costo,
                         valor_inventario = :valor
                     WHERE id_sucursal = :sucursal AND id_producto = :producto"
                )->execute([
                    ':stock'    => $stockNuevo,
                    ':costo'    => $costoPromedio,
                    ':valor'    => $valorNuevo,
                    ':sucursal' => $idSucursal,
                    ':producto' => $idProducto,
                ]);
            } else {
                // Sin fila en la sucursal: el saldo es el stock global
                // del producto (ya actualizado por el llamador).
                $stmt = $this->conn->prepare(
                    'SELECT stock, costo FROM productos WHERE id_producto = :producto LIMIT 1'
                );
                $stmt->execute([':producto' => $idProducto]);
                $global = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$global) {
                    return self::err('El producto ya no existe');
                }

                $stockNuevo = (float) $global['stock'];

                if ($costoUnitario <= 0) {
                    $costoUnitario = round(max(0, (float) $global['costo']), 4);
                }

                $valorNuevo = max(0, round($stockNuevo * $costoUnitario, 2));
                $costoPromedio = $stockNuevo > 0
                    ? round($valorNuevo / $stockNuevo, 4)
                    : 0.0;
            }

            $idMovimiento = $this->insertarMovimiento(
                $idSucursal,
                $idProducto,
                'ajuste',
                'ajuste',
                null,
                $detalle,
                $cantidad,
                $costoUnitario,
                null,
                round($cantidad * $costoUnitario, 2),
                $stockNuevo,
                $costoPromedio,
                $valorNuevo,
                $idUsuario
            );

            return [
                'exito'          => true,
                'mensaje'        => 'Ajuste registrado',
                'id_movimiento'  => $idMovimiento,
                'saldo_cantidad' => $stockNuevo,
                'costo_promedio' => $costoPromedio,
                'saldo_valor'    => $valorNuevo,
            ];
        } catch (PDOException $e) {
            error_log('Kardex::registrarAjuste - ' . $e->getMessage());
            return self::err('No se pudo registrar el ajuste en el kardex');
        }
    }

    // =================================================
    //  AUXILIARES INTERNOS
    // =================================================

    /**
     * Salida usando productos.stock como referencia (POS).
     *
     * No exige existencia previa en productos_sucursal: la fila se crea
     * con prepararFila y queda alineada con el stock global, de modo
     * que el kardex y una posterior anulación sigan cuadrando.
     */
    private function salidaStockGlobal(
        int $idSucursal,
        int $idProducto,
        float $cantidad,
        string $origen,
        ?int $idReferencia,
        string $detalle,
        int $idUsuario,
        bool $permitirNegativo
    ): array {
        try {
            $this->prepararFila($idSucursal, $idProducto, 0);
            $actual = $this->bloquearFila($idSucursal, $idProducto);

            $stmt = $this->conn->prepare(
                'SELECT stock, costo FROM productos WHERE id_producto = :producto FOR UPDATE'
            );
            $stmt->execute([':producto' => $idProducto]);
            $global = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$global) {
                return self::err('El producto ya no existe');
            }

            $stockActual = (float) $global['stock'];

            if (!$permitirNegativo && $stockActual + 0.0001 < $cantidad) {
                return self::err('Stock insuficiente en esta sucursal');
            }

            $costoPromedio = $actual ? (float) $actual['costo_promedio'] : 0.0;
            if ($costoPromedio <= 0) {
                $costoPromedio = (float) $global['costo'];
            }
            $costoPromedio = round(max(0, $costoPromedio), 4);

            $stockNuevo = round($stockActual - $cantidad, 3);
            if ($stockNuevo < 0 && !$permitirNegativo) {
                $stockNuevo = 0.0;
            }

            $valorNuevo = $stockNuevo > 0
                ? round($stockNuevo * $costoPromedio, 2)
                : 0.0;
            $costoFinal = $stockNuevo > 0 ? $costoPromedio : 0.0;

            $this->conn->prepare(
                "UPDATE productos_sucursal
                 SET stock = :stock,
                     costo_promedio = :costo,
                     valor_inventario = :valor
                 WHERE id_sucursal = :sucursal AND id_producto = :producto"
            )->execute([
                ':stock'    => $stockNuevo,
                ':costo'    => $costoFinal,
                ':valor'    => $valorNuevo,
                ':sucursal' => $idSucursal,
                ':producto' => $idProducto,
            ]);

            // El POS trabaja sobre la existencia global del producto.
            $this->conn->prepare(
                'UPDATE productos SET stock = :stock WHERE id_producto = :producto'
            )->execute([
                ':stock'    => $stockNuevo,
                ':producto' => $idProducto,
            ]);

            $idMovimiento = $this->insertarMovimiento(
                $idSucursal,
                $idProducto,
                'salida',
                $origen,
                $idReferencia,
                $detalle,
                -$cantidad,
                $costoPromedio,
                null,
                -round($cantidad * $costoPromedio, 2),
                $stockNuevo,
                $costoFinal,
                $valorNuevo,
                $idUsuario
            );

            return [
                'exito'          => true,
                'mensaje'        => 'Salida registrada',
                'id_movimiento'  => $idMovimiento,
                'saldo_cantidad' => $stockNuevo,
                'costo_promedio' => $costoFinal,
                'saldo_valor'    => $valorNuevo,
            ];
        } catch (PDOException $e) {
            error_log('Kardex::salidaStockGlobal - ' . $e->getMessage());
            return self::err('No se pudo registrar la salida del kardex');
        }
    }

    private function prepararFila(int $idSucursal, int $idProducto, float $precioVenta): void
    {
        $this->conn->prepare(
            "INSERT INTO productos_sucursal
                (id_producto, id_sucursal, stock, precio_venta, control_stock)
             SELECT p.id_producto, :sucursal, 0,
                    CASE WHEN :precio > 0 THEN :precio2 ELSE p.precio END,
                    1
             FROM productos p
             WHERE p.id_producto = :producto
             ON DUPLICATE KEY UPDATE id_producto_sucursal = id_producto_sucursal"
        )->execute([
            ':sucursal' => $idSucursal,
            ':precio'   => $precioVenta,
            ':precio2'  => $precioVenta,
            ':producto' => $idProducto,
        ]);
    }

    private function bloquearFila(int $idSucursal, int $idProducto): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT stock, costo_promedio, valor_inventario, precio_venta
             FROM productos_sucursal
             WHERE id_sucursal = :sucursal AND id_producto = :producto
             FOR UPDATE'
        );
        $stmt->execute([':sucursal' => $idSucursal, ':producto' => $idProducto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function insertarMovimiento(
        int $idSucursal,
        int $idProducto,
        string $tipo,
        string $origen,
        ?int $idReferencia,
        string $detalle,
        float $cantidad,
        float $costoUnitario,
        ?float $precioVenta,
        float $valorMovimiento,
        float $saldoCantidad,
        float $costoPromedio,
        float $saldoValor,
        int $idUsuario
    ): int {
        $stmt = $this->conn->prepare(
            "INSERT INTO kardex
                (id_sucursal, id_producto, tipo, origen, id_referencia,
                 fecha, detalle, cantidad, costo_unitario, precio_venta,
                 valor_movimiento, saldo_cantidad, costo_promedio, saldo_valor, id_usuario)
             VALUES
                (:sucursal, :producto, :tipo, :origen, :referencia,
                 NOW(), :detalle, :cantidad, :costo, :precio,
                 :valor, :saldo_cantidad, :costo_promedio, :saldo_valor, :usuario)"
        );

        $stmt->execute([
            ':sucursal'      => $idSucursal,
            ':producto'      => $idProducto,
            ':tipo'          => $tipo,
            ':origen'        => $origen,
            ':referencia'    => $idReferencia,
            ':detalle'       => $detalle !== '' ? mb_substr($detalle, 0, 200) : null,
            ':cantidad'      => $cantidad,
            ':costo'         => $costoUnitario,
            ':precio'        => $precioVenta,
            ':valor'         => $valorMovimiento,
            ':saldo_cantidad' => $saldoCantidad,
            ':costo_promedio' => $costoPromedio,
            ':saldo_valor'    => $saldoValor,
            ':usuario'       => $idUsuario > 0 ? $idUsuario : null,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id_movimiento' => 0];
    }

    private function normalizar(array $f): array
    {
        $usuario = trim((string) ($f['usuario_nombre'] ?? ''));

        return [
            'id'                => (int) $f['id_movimiento'],
            'id_sucursal'       => (int) $f['id_sucursal'],
            'id_producto'       => (int) $f['id_producto'],
            'producto_codigo'   => (string) ($f['producto_codigo'] ?? ''),
            'producto'          => (string) ($f['producto_nombre'] ?? ''),
            'sucursal'          => (string) ($f['nombre_sucursal'] ?? ''),
            'ciudad'            => (string) ($f['ciudad'] ?? ''),
            'tipo'              => (string) $f['tipo'],
            'origen'            => (string) $f['origen'],
            'id_referencia'     => $f['id_referencia'] !== null ? (int) $f['id_referencia'] : 0,
            'documento'         => (string) ($f['numero_documento'] ?? ''),
            'detalle'           => (string) ($f['detalle'] ?? ''),
            'cantidad'          => (float) $f['cantidad'],
            'costo_unitario'    => (float) $f['costo_unitario'],
            'precio_venta'      => $f['precio_venta'] !== null ? (float) $f['precio_venta'] : null,
            'valor_movimiento'  => (float) $f['valor_movimiento'],
            'saldo_cantidad'    => (float) $f['saldo_cantidad'],
            'costo_promedio'    => (float) $f['costo_promedio'],
            'saldo_valor'       => (float) $f['saldo_valor'],
            'usuario'           => $usuario !== '' ? $usuario : (string) ($f['username'] ?? ''),
            'fecha_iso'         => (string) $f['fecha'],
            'fecha'             => !empty($f['fecha']) ? date('d/m/Y H:i', strtotime($f['fecha'])) : '—',
            'fecha_dia'         => !empty($f['fecha']) ? date('d/m/Y', strtotime($f['fecha'])) : '—',
        ];
    }
}
