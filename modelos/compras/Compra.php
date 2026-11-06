<?php
/**
 * =====================================================
 * MODELO: Compra  (modelos/compras/Compra.php)
 * -----------------------------------------------------
 * Registro de compras con detalle y actualización del
 * kardex por sucursal.
 *
 * Reglas de negocio:
 *   · Una compra pertenece a UNA sucursal.
 *   · Un usuario no SuperAdmin sólo compra para su
 *     sucursal (el API fuerza el id).
 *   · Cada línea define costo de compra y precio de
 *     venta para esa sucursal.
 *   · El stock y el costo promedio se actualizan en
 *     productos_sucursal; el histórico queda en kardex.
 *   · Toda la operación es transaccional.
 * =====================================================
 */

require_once __DIR__ . '/Kardex.php';

class Compra
{
    private PDO $conn;

    private const TIPOS_DOCUMENTO = ['factura', 'recibo', 'nota', 'otro'];
    private const FORMAS_PAGO = ['contado', 'credito', 'transferencia', 'qr', 'otro'];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    public function listar(
        ?int $idSucursal,
        int $idProveedor = 0,
        string $desde = '',
        string $hasta = '',
        string $estado = '',
        int $limite = 400
    ): array {
        $where = [];
        $params = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $where[] = 'c.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        if ($idProveedor > 0) {
            $where[] = 'c.id_proveedor = :proveedor';
            $params[':proveedor'] = $idProveedor;
        }

        if ($desde !== '') {
            $where[] = 'c.fecha_compra >= :desde';
            $params[':desde'] = $desde . ' 00:00:00';
        }

        if ($hasta !== '') {
            $where[] = 'c.fecha_compra <= :hasta';
            $params[':hasta'] = $hasta . ' 23:59:59';
        }

        if ($estado === 'registrada' || $estado === 'anulada') {
            $where[] = 'c.estado = :estado';
            $params[':estado'] = $estado;
        }

        $sql = "SELECT c.*,
                       s.nombre_sucursal,
                       s.ciudad,
                       pr.nombre AS proveedor_nombre,
                       pr.nit AS proveedor_nit,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre,
                       COUNT(d.id_detalle) AS items,
                       COALESCE(SUM(d.cantidad), 0) AS unidades,
                       COALESCE((SELECT SUM(pg.monto)
                                   FROM compras_pagos pg
                                  WHERE pg.id_compra = c.id_compra), 0) AS pagado
                FROM compras c
                INNER JOIN sucursales s  ON s.id_sucursal = c.id_sucursal
                INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                INNER JOIN usuarios u     ON u.id_usuario = c.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                LEFT  JOIN compra_detalle d ON d.id_compra = c.id_compra";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY c.id_compra';
        $sql .= ' ORDER BY c.fecha_compra DESC, c.id_compra DESC';
        $sql .= ' LIMIT ' . max(1, min(1000, $limite));

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Compra::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "SELECT c.*,
                       s.nombre_sucursal,
                       s.ciudad,
                       pr.nombre AS proveedor_nombre,
                       pr.nit AS proveedor_nit,
                       pr.telefono AS proveedor_telefono,
                       pr.contacto AS proveedor_contacto,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre,
                       COALESCE((SELECT SUM(pg.monto)
                                   FROM compras_pagos pg
                                  WHERE pg.id_compra = c.id_compra), 0) AS pagado
                FROM compras c
                INNER JOIN sucursales s  ON s.id_sucursal = c.id_sucursal
                INNER JOIN proveedores pr ON pr.id_proveedor = c.id_proveedor
                INNER JOIN usuarios u     ON u.id_usuario = c.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                WHERE c.id_compra = :id
                LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Compra::obtener - ' . $e->getMessage());
            return null;
        }

        if (!$fila) {
            return null;
        }

        $compra = $this->normalizar($fila);
        $compra['detalle'] = $this->detalle($id);
        $compra['items'] = count($compra['detalle']);
        $compra['unidades'] = array_sum(array_column($compra['detalle'], 'cantidad'));
        $compra['pagos'] = $this->pagos($id);

        return $compra;
    }

    public function detalle(int $idCompra): array
    {
        $sql = "SELECT d.*,
                       p.codigo AS producto_codigo,
                       p.nombre AS producto_nombre,
                       p.imagen AS producto_imagen,
                       cat.nombre_categoria,
                       (SELECT pi.archivo
                          FROM productos_imagenes pi
                         WHERE pi.id_producto = p.id_producto AND pi.principal = 1
                         LIMIT 1) AS imagen_principal
                FROM compra_detalle d
                INNER JOIN productos p ON p.id_producto = d.id_producto
                INNER JOIN categorias_productos cat ON cat.id_categoria = p.id_categoria
                WHERE d.id_compra = :id
                ORDER BY d.id_detalle ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idCompra]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Compra::detalle - ' . $e->getMessage());
            return [];
        }

        return array_map(function (array $f): array {
            $archivo = (string) ($f['imagen_principal'] ?? '');
            $legacy = (string) ($f['producto_imagen'] ?? '');
            $ruta = $archivo !== '' ? $archivo : $legacy;

            return [
                'id'               => (int) $f['id_detalle'],
                'id_producto'      => (int) $f['id_producto'],
                'codigo'           => (string) $f['producto_codigo'],
                'producto'         => (string) $f['producto_nombre'],
                'categoria'        => (string) $f['nombre_categoria'],
                'imagen'           => $ruta,
                'imagen_url'       => self::urlImagen($ruta),
                'cantidad'         => (float) $f['cantidad'],
                'costo_unitario'   => (float) $f['costo_unitario'],
                'precio_venta'     => (float) $f['precio_venta_unitario'],
                'subtotal'         => (float) $f['subtotal'],
            ];
        }, $filas);
    }

    /** Tarjetas de resumen para la página de compras. */
    public function resumen(?int $idSucursal): array
    {
        $r = [
            'compras'       => 0,
            'total'         => 0.0,
            'mes'           => 0.0,
            'compras_mes'   => 0,
            'unidades'      => 0.0,
            'proveedores'   => 0,
            'por_pagar'     => 0.0,
            'creditos_pendientes' => 0,
        ];

        $filtro = '';
        $params = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $filtro = ' AND c.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT
                    COUNT(*) AS compras,
                    COALESCE(SUM(c.total), 0) AS total,
                    COALESCE(SUM(CASE WHEN c.fecha_compra >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                                      THEN c.total ELSE 0 END), 0) AS mes,
                    COALESCE(SUM(CASE WHEN c.fecha_compra >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                                      THEN 1 ELSE 0 END), 0) AS compras_mes,
                    COUNT(DISTINCT c.id_proveedor) AS proveedores,
                    COALESCE(SUM(CASE WHEN c.forma_pago = 'credito'
                                      THEN GREATEST(0, c.total - c.abono - COALESCE(
                                           (SELECT SUM(pg.monto)
                                              FROM compras_pagos pg
                                             WHERE pg.id_compra = c.id_compra), 0))
                                      ELSE 0 END), 0) AS por_pagar,
                    COALESCE(SUM(CASE WHEN c.forma_pago = 'credito'
                                       AND c.total - c.abono - COALESCE(
                                           (SELECT SUM(pg.monto)
                                              FROM compras_pagos pg
                                             WHERE pg.id_compra = c.id_compra), 0) > 0.005
                                      THEN 1 ELSE 0 END), 0) AS creditos_pendientes
                 FROM compras c
                 WHERE c.estado = 'registrada'" . $filtro
            );
            $stmt->execute($params);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fila) {
                $r['compras'] = (int) $fila['compras'];
                $r['total'] = (float) $fila['total'];
                $r['mes'] = (float) $fila['mes'];
                $r['compras_mes'] = (int) $fila['compras_mes'];
                $r['proveedores'] = (int) $fila['proveedores'];
                $r['por_pagar'] = (float) ($fila['por_pagar'] ?? 0);
                $r['creditos_pendientes'] = (int) ($fila['creditos_pendientes'] ?? 0);
            }

            $stmt = $this->conn->prepare(
                "SELECT COALESCE(SUM(d.cantidad), 0) AS unidades
                 FROM compra_detalle d
                 INNER JOIN compras c ON c.id_compra = d.id_compra
                 WHERE c.estado = 'registrada'" . $filtro
            );
            $stmt->execute($params);
            $r['unidades'] = (float) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Compra::resumen - ' . $e->getMessage());
        }

        return $r;
    }

    /**
     * Catálogo de productos con existencia y precio de la
     * sucursal seleccionada (para el formulario de compra).
     */
    public function productosParaCompra(int $idSucursal): array
    {
        if ($idSucursal <= 0) {
            return [];
        }

        $sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion,
                       p.imagen, p.precio, p.costo, p.control_stock,
                       p.stock_minimo, p.stock AS stock_global,
                       cat.id_categoria, cat.nombre_categoria, cat.icono AS categoria_icono,
                       COALESCE(ps.stock, 0) AS stock_sucursal,
                       COALESCE(ps.precio_venta, p.precio) AS precio_sucursal,
                       COALESCE(ps.costo_promedio, p.costo) AS costo_sucursal,
                       COALESCE(ps.stock_minimo, p.stock_minimo) AS stock_minimo_sucursal,
                       (SELECT pi.archivo
                          FROM productos_imagenes pi
                         WHERE pi.id_producto = p.id_producto AND pi.principal = 1
                         LIMIT 1) AS imagen_principal
                FROM productos p
                INNER JOIN categorias_productos cat ON cat.id_categoria = p.id_categoria
                LEFT  JOIN productos_sucursal ps
                       ON ps.id_producto = p.id_producto AND ps.id_sucursal = :sucursal
                WHERE p.estado = 1
                ORDER BY cat.orden ASC, p.nombre ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':sucursal' => $idSucursal]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Compra::productosParaCompra - ' . $e->getMessage());
            return [];
        }

        return array_map(function (array $f): array {
            $archivo = (string) ($f['imagen_principal'] ?? '');
            $legacy = (string) ($f['imagen'] ?? '');
            $ruta = $archivo !== '' ? $archivo : $legacy;

            return [
                'id'              => (int) $f['id_producto'],
                'codigo'          => (string) $f['codigo'],
                'nombre'          => (string) $f['nombre'],
                'descripcion'     => (string) ($f['descripcion'] ?? ''),
                'id_categoria'    => (int) $f['id_categoria'],
                'categoria'       => (string) $f['nombre_categoria'],
                'categoria_icono' => (string) ($f['categoria_icono'] ?? 'fa-utensils'),
                'imagen'          => $ruta,
                'imagen_url'      => self::urlImagen($ruta),
                'precio'          => (float) $f['precio'],
                'costo'           => (float) $f['costo'],
                'stock'           => (float) $f['stock_global'],
                'stock_global'    => (float) $f['stock_global'],
                'stock_sucursal'  => (float) $f['stock_sucursal'],
                'precio_sucursal' => (float) $f['precio_sucursal'],
                'costo_sucursal'  => (float) $f['costo_sucursal'],
                'stock_minimo'    => (float) $f['stock_minimo_sucursal'],
                'control_stock'   => (int) ($f['control_stock'] ?? 1),
            ];
        }, $filas);
    }

    // =================================================
    //  REGISTRO
    // =================================================

    /**
     * Registra una compra completa.
     *
     * @param int|null $idSucursalForzada Sucursal del usuario (no SuperAdmin).
     * @return array{exito:bool, mensaje:string, id:int, compra:?array}
     */
    public function registrar(array $datos, int $idUsuario, ?int $idSucursalForzada = null): array
    {
        if ($idUsuario <= 0) {
            return self::err('Sesión no válida');
        }

        $idSucursal = $idSucursalForzada !== null
            ? (int) $idSucursalForzada
            : (int) ($datos['id_sucursal'] ?? 0);

        if ($idSucursal <= 0) {
            return self::err('Selecciona la sucursal de la compra');
        }

        if (!$this->sucursalActiva($idSucursal)) {
            return self::err('La sucursal seleccionada no existe o está inactiva');
        }

        $preparada = $this->prepararCompra($datos, $idSucursal);

        if (!$preparada['exito']) {
            return self::err($preparada['mensaje']);
        }

        $d = $preparada['datos'];

        $idProveedor        = $d['id_proveedor'];
        $tipoDocumento      = $d['tipo_documento'];
        $formaPago          = $d['forma_pago'];
        $numeroDocumento    = $d['numero_documento'];
        $fechaCompra        = $d['fecha_compra'];
        $observaciones      = $d['observaciones'];
        $subtotal           = $d['subtotal'];
        $descuento          = $d['descuento'];
        $impuestoPorcentaje = $d['impuesto_porcentaje'];
        $impuesto           = $d['impuesto'];
        $total              = $d['total'];
        $abono              = $d['abono'];
        $lineas             = $d['lineas'];

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "INSERT INTO compras
                    (id_sucursal, id_proveedor, id_usuario,
                     tipo_documento, numero_documento, fecha_compra, forma_pago,
                     estado, subtotal, descuento, impuesto_porcentaje, impuesto, total, abono,
                     observaciones)
                 VALUES
                    (:sucursal, :proveedor, :usuario,
                     :tipo, :numero, :fecha, :forma,
                     'registrada', :subtotal, :descuento, :impuesto_pct, :impuesto, :total, :abono,
                     :observaciones)"
            );

            $stmt->execute([
                ':sucursal'      => $idSucursal,
                ':proveedor'     => $idProveedor,
                ':usuario'       => $idUsuario,
                ':tipo'          => $tipoDocumento,
                ':numero'        => $numeroDocumento !== '' ? $numeroDocumento : null,
                ':fecha'         => $fechaCompra,
                ':forma'         => $formaPago,
                ':subtotal'      => $subtotal,
                ':descuento'     => $descuento,
                ':impuesto_pct'  => $impuestoPorcentaje,
                ':impuesto'      => $impuesto,
                ':total'         => $total,
                ':abono'         => $abono,
                ':observaciones' => $observaciones !== '' ? $observaciones : null,
            ]);

            $idCompra = (int) $this->conn->lastInsertId();

            $detalle = $this->conn->prepare(
                "INSERT INTO compra_detalle
                    (id_compra, id_producto, cantidad, costo_unitario, precio_venta_unitario, subtotal)
                 VALUES
                    (:compra, :producto, :cantidad, :costo, :precio, :subtotal)"
            );

            $kardex = new Kardex($this->conn);
            $proveedor = $this->proveedorNombre($idProveedor);

            foreach ($lineas as $linea) {
                $detalle->execute([
                    ':compra'   => $idCompra,
                    ':producto' => $linea['id_producto'],
                    ':cantidad' => $linea['cantidad'],
                    ':costo'    => $linea['costo_unitario'],
                    ':precio'   => $linea['precio_venta'],
                    ':subtotal' => $linea['subtotal'],
                ]);

                $movimiento = $kardex->registrarEntrada(
                    $idSucursal,
                    $linea['id_producto'],
                    $linea['cantidad'],
                    $linea['costo_unitario'],
                    $linea['precio_venta'],
                    'compra',
                    $idCompra,
                    'Compra #' . $idCompra . ' · ' . $proveedor,
                    $idUsuario
                );

                if (!$movimiento['exito']) {
                    throw new RuntimeException($movimiento['mensaje']);
                }
            }

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Compra registrada: el stock y el kardex de la sucursal se actualizaron',
                'id'      => $idCompra,
                'compra'  => $this->obtener($idCompra),
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Compra::registrar - ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                return self::err('No se pudo registrar la compra: revisa el documento y los productos');
            }

            return self::err('No se pudo registrar la compra');
        }
    }

    // =================================================
    //  EDICIÓN / ANULACIÓN
    // =================================================

    /**
     * Modifica una compra registrada.
     *
     * El inventario se ajusta por DIFERENCIA de cantidades:
     * si la nueva línea pide más, entra la diferencia; si pide
     * menos, sale la diferencia. Así no se "reingresa" stock
     * que ya pudo haberse vendido.
     */
    public function actualizar(int $id, array $datos, int $idUsuario): array
    {
        if ($id <= 0 || $idUsuario <= 0) {
            return self::err('Compra no válida');
        }

        $actual = $this->obtener($id);

        if (!$actual) {
            return self::err('Compra no encontrada');
        }

        if ($actual['estado'] !== 'registrada') {
            return self::err('Solo se pueden modificar compras registradas');
        }

        $idSucursal = (int) $actual['id_sucursal'];

        $preparada = $this->prepararCompra($datos, $idSucursal, $id);

        if (!$preparada['exito']) {
            return self::err($preparada['mensaje']);
        }

        $d = $preparada['datos'];
        $pagado = $this->totalPagado($id);

        if (round($d['abono'] + $pagado, 2) > round($d['total'] + 0.005, 2)) {
            return self::err(
                'El total no puede ser menor que el abono inicial más los pagos registrados ('
                . number_format($pagado, 2, '.', ',') . ')'
            );
        }

        try {
            $this->conn->beginTransaction();

            $bloqueo = $this->conn->prepare(
                "SELECT estado FROM compras WHERE id_compra = :id FOR UPDATE"
            );
            $bloqueo->execute([':id' => $id]);

            if ((string) $bloqueo->fetchColumn() !== 'registrada') {
                throw new RuntimeException('La compra ya no está disponible para editar');
            }

            $anteriores = $this->detalle($id);
            $mapaAnterior = [];

            foreach ($anteriores as $linea) {
                $mapaAnterior[(int) $linea['id_producto']] = $linea;
            }

            $mapaNuevo = [];

            foreach ($d['lineas'] as $linea) {
                $mapaNuevo[(int) $linea['id_producto']] = $linea;
            }

            $productos = array_values(array_unique(array_merge(
                array_keys($mapaAnterior),
                array_keys($mapaNuevo)
            )));

            $kardex = new Kardex($this->conn);

            foreach ($productos as $idProducto) {
                $cantidadAnterior = isset($mapaAnterior[$idProducto])
                    ? (float) $mapaAnterior[$idProducto]['cantidad']
                    : 0.0;

                $nueva = $mapaNuevo[$idProducto] ?? null;
                $cantidadNueva = $nueva ? (float) $nueva['cantidad'] : 0.0;
                $diferencia = round($cantidadNueva - $cantidadAnterior, 3);

                if (abs($diferencia) < 0.0005) {
                    if ($nueva) {
                        $this->actualizarPrecioSucursal(
                            $idSucursal,
                            $idProducto,
                            (float) $nueva['precio_venta']
                        );
                    }

                    continue;
                }

                if ($diferencia > 0) {
                    $movimiento = $kardex->registrarEntrada(
                        $idSucursal,
                        $idProducto,
                        $diferencia,
                        (float) $nueva['costo_unitario'],
                        (float) $nueva['precio_venta'],
                        'ajuste',
                        $id,
                        'Edición compra #' . $id . ' · +' . number_format($diferencia, 3, '.', ''),
                        $idUsuario
                    );
                } else {
                    $movimiento = $kardex->registrarSalida(
                        $idSucursal,
                        $idProducto,
                        abs($diferencia),
                        'ajuste',
                        $id,
                        'Edición compra #' . $id . ' · −' . number_format(abs($diferencia), 3, '.', ''),
                        $idUsuario,
                        true
                    );
                }

                if (!$movimiento['exito']) {
                    throw new RuntimeException($movimiento['mensaje']);
                }

                if ($nueva) {
                    $this->actualizarPrecioSucursal(
                        $idSucursal,
                        $idProducto,
                        (float) $nueva['precio_venta']
                    );
                }
            }

            // Reemplaza el detalle por las líneas nuevas.
            $this->conn->prepare('DELETE FROM compra_detalle WHERE id_compra = :id')
                ->execute([':id' => $id]);

            $detalle = $this->conn->prepare(
                "INSERT INTO compra_detalle
                    (id_compra, id_producto, cantidad, costo_unitario, precio_venta_unitario, subtotal)
                 VALUES
                    (:compra, :producto, :cantidad, :costo, :precio, :subtotal)"
            );

            foreach ($d['lineas'] as $linea) {
                $detalle->execute([
                    ':compra'   => $id,
                    ':producto' => $linea['id_producto'],
                    ':cantidad' => $linea['cantidad'],
                    ':costo'    => $linea['costo_unitario'],
                    ':precio'   => $linea['precio_venta'],
                    ':subtotal' => $linea['subtotal'],
                ]);
            }

            $this->conn->prepare(
                "UPDATE compras
                    SET id_proveedor = :proveedor,
                        tipo_documento = :tipo,
                        numero_documento = :numero,
                        fecha_compra = :fecha,
                        forma_pago = :forma,
                        subtotal = :subtotal,
                        descuento = :descuento,
                        impuesto_porcentaje = :impuesto_pct,
                        impuesto = :impuesto,
                        total = :total,
                        abono = :abono,
                        observaciones = :observaciones
                  WHERE id_compra = :id"
            )->execute([
                ':proveedor'     => $d['id_proveedor'],
                ':tipo'          => $d['tipo_documento'],
                ':numero'        => $d['numero_documento'] !== '' ? $d['numero_documento'] : null,
                ':fecha'         => $d['fecha_compra'],
                ':forma'         => $d['forma_pago'],
                ':subtotal'      => $d['subtotal'],
                ':descuento'     => $d['descuento'],
                ':impuesto_pct'  => $d['impuesto_porcentaje'],
                ':impuesto'      => $d['impuesto'],
                ':total'         => $d['total'],
                ':abono'         => $d['abono'],
                ':observaciones' => $d['observaciones'] !== '' ? $d['observaciones'] : null,
                ':id'            => $id,
            ]);

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Compra actualizada y stock ajustado correctamente',
                'id'      => $id,
                'compra'  => $this->obtener($id),
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Compra::actualizar - ' . $e->getMessage());

            return self::err('No se pudo actualizar la compra');
        }
    }

    /**
     * Anula una compra (baja lógica) y devuelve el stock.
     *
     * · Se rechaza si tiene pagos registrados.
     * · Cada línea genera una salida en el kardex (origen ajuste).
     * · El stock de la sucursal y el global bajan, sin quedar negativos.
     */
    public function anular(int $id, int $idUsuario): array
    {
        if ($id <= 0 || $idUsuario <= 0) {
            return self::err('Compra no válida');
        }

        $compra = $this->obtener($id);

        if (!$compra) {
            return self::err('Compra no encontrada');
        }

        if ($compra['estado'] !== 'registrada') {
            return self::err('Esta compra ya está anulada');
        }

        if ($this->totalPagado($id) > 0.005) {
            return self::err('No se puede eliminar: la compra tiene pagos registrados. Elimina primero los abonos.');
        }

        try {
            $this->conn->beginTransaction();

            $bloqueo = $this->conn->prepare(
                "SELECT estado FROM compras WHERE id_compra = :id FOR UPDATE"
            );
            $bloqueo->execute([':id' => $id]);

            if ((string) $bloqueo->fetchColumn() !== 'registrada') {
                throw new RuntimeException('Esta compra ya está anulada');
            }

            $kardex = new Kardex($this->conn);

            foreach ($compra['detalle'] as $linea) {
                $movimiento = $kardex->registrarSalida(
                    (int) $compra['id_sucursal'],
                    (int) $linea['id_producto'],
                    (float) $linea['cantidad'],
                    'ajuste',
                    $id,
                    'Anulación compra #' . $id . ' · ' . $compra['proveedor'],
                    $idUsuario,
                    true
                );

                if (!$movimiento['exito']) {
                    throw new RuntimeException($movimiento['mensaje']);
                }
            }

            $this->conn->prepare(
                "UPDATE compras SET estado = 'anulada' WHERE id_compra = :id AND estado = 'registrada'"
            )->execute([':id' => $id]);

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Compra anulada: el stock y el kardex se actualizaron',
                'id'      => $id,
                'compra'  => $this->obtener($id),
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Compra::anular - ' . $e->getMessage());

            return self::err('No se pudo anular la compra');
        }
    }

    // =================================================
    //  PAGOS
    // =================================================

    public function pagos(int $idCompra): array
    {
        if ($idCompra <= 0) {
            return [];
        }

        $sql = "SELECT p.*,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre
                FROM compras_pagos p
                INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
                LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                WHERE p.id_compra = :id
                ORDER BY p.fecha_pago ASC, p.id_pago ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idCompra]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Compra::pagos - ' . $e->getMessage());
            return [];
        }

        return array_map(function (array $f): array {
            $usuario = trim((string) ($f['usuario_nombre'] ?? ''));

            return [
                'id'             => (int) $f['id_pago'],
                'id_compra'      => (int) $f['id_compra'],
                'id_sucursal'    => (int) $f['id_sucursal'],
                'id_proveedor'   => (int) $f['id_proveedor'],
                'monto'          => (float) $f['monto'],
                'metodo_pago'    => (string) $f['metodo_pago'],
                'numero_guia'    => (string) ($f['numero_guia'] ?? ''),
                'referencia'     => (string) ($f['referencia'] ?? ''),
                'observaciones'  => (string) ($f['observaciones'] ?? ''),
                'fecha_pago'     => !empty($f['fecha_pago']) ? date('d/m/Y H:i', strtotime($f['fecha_pago'])) : '—',
                'fecha_pago_iso' => (string) ($f['fecha_pago'] ?? ''),
                'usuario'        => $usuario !== '' ? $usuario : (string) ($f['username'] ?? ''),
            ];
        }, $filas);
    }

    public function totalPagado(int $idCompra): float
    {
        if ($idCompra <= 0) {
            return 0.0;
        }

        $stmt = $this->conn->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM compras_pagos WHERE id_compra = :id'
        );
        $stmt->execute([':id' => $idCompra]);

        return round((float) $stmt->fetchColumn(), 2);
    }

    public function saldo(int $idCompra): float
    {
        $compra = $this->obtener($idCompra);

        return $compra ? (float) $compra['saldo'] : 0.0;
    }

    // =================================================
    //  VALIDACIÓN COMPARTIDA
    // =================================================

    /**
     * Valida cabecera y líneas. Devuelve los datos listos
     * para insertar/actualizar.
     *
     * @return array{exito:bool, mensaje:string, datos:array}
     */
    private function prepararCompra(array $datos, int $idSucursal, int $idCompraExcluir = 0): array
    {
        if ($idSucursal <= 0) {
            return self::errDatos('Selecciona la sucursal de la compra');
        }

        if (!$this->sucursalActiva($idSucursal)) {
            return self::errDatos('La sucursal seleccionada no existe o está inactiva');
        }

        $idProveedor = (int) ($datos['id_proveedor'] ?? 0);
        if ($idProveedor <= 0) {
            return self::errDatos('Selecciona el proveedor de la compra');
        }

        if (!$this->proveedorActivo($idProveedor)) {
            return self::errDatos('El proveedor seleccionado no existe o está inactivo');
        }

        $tipoDocumento = (string) ($datos['tipo_documento'] ?? 'factura');
        if (!in_array($tipoDocumento, self::TIPOS_DOCUMENTO, true)) {
            $tipoDocumento = 'factura';
        }

        $formaPago = (string) ($datos['forma_pago'] ?? 'contado');
        if (!in_array($formaPago, self::FORMAS_PAGO, true)) {
            $formaPago = 'contado';
        }

        $numeroDocumento = mb_strtoupper(trim(strip_tags((string) ($datos['numero_documento'] ?? ''))));
        if (mb_strlen($numeroDocumento) > 60) {
            return self::errDatos('El número de documento no puede superar los 60 caracteres');
        }

        $fechaCompra = $this->aFechaCompra($datos['fecha_compra'] ?? '');
        if ($fechaCompra === null) {
            return self::errDatos('La fecha de compra no es válida');
        }

        if (strtotime($fechaCompra) > strtotime('today 23:59:59')) {
            return self::errDatos('La fecha de compra no puede ser futura');
        }

        $observaciones = trim(strip_tags((string) ($datos['observaciones'] ?? '')));
        if (mb_strlen($observaciones) > 255) {
            return self::errDatos('Las observaciones no pueden superar los 255 caracteres');
        }

        if ($numeroDocumento !== '' && $this->documentoDuplicado($idSucursal, $idProveedor, $numeroDocumento, $idCompraExcluir)) {
            return self::errDatos('Ese proveedor ya tiene una compra con el documento ' . $numeroDocumento);
        }

        $items = $datos['items'] ?? null;

        if (!is_array($items) || $items === []) {
            return self::errDatos('Agrega al menos un producto a la compra');
        }

        if (count($items) > 200) {
            return self::errDatos('Una compra no puede tener más de 200 líneas');
        }

        $lineas = [];
        $productosVistos = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $idProducto = (int) ($item['id_producto'] ?? 0);
            $cantidad = round($this->aDecimal($item['cantidad'] ?? 0), 3);
            $costo = round($this->aDecimal($item['costo_unitario'] ?? 0), 4);
            $precioVenta = round($this->aDecimal($item['precio_venta'] ?? 0), 2);

            if ($idProducto <= 0) {
                return self::errDatos('Hay una línea sin producto');
            }

            if (isset($productosVistos[$idProducto])) {
                return self::errDatos('El mismo producto está repetido en la compra');
            }

            $productosVistos[$idProducto] = true;

            $producto = $this->productoActivo($idProducto);
            if (!$producto) {
                return self::errDatos('Uno de los productos ya no existe o está inactivo');
            }

            if ($cantidad <= 0) {
                return self::errDatos('La cantidad de "' . $producto['nombre'] . '" debe ser mayor que cero');
            }

            if ($cantidad > 999999) {
                return self::errDatos('La cantidad de "' . $producto['nombre'] . '" es demasiado grande');
            }

            if ($costo < 0) {
                return self::errDatos('El costo de "' . $producto['nombre'] . '" no puede ser negativo');
            }

            if ($precioVenta <= 0) {
                return self::errDatos('Define un precio de venta mayor que cero para "' . $producto['nombre'] . '"');
            }

            $subtotalLinea = round($cantidad * $costo, 2);
            $subtotal += $subtotalLinea;

            $lineas[] = [
                'id_producto'    => $idProducto,
                'producto'       => $producto['nombre'],
                'cantidad'       => $cantidad,
                'costo_unitario' => $costo,
                'precio_venta'   => $precioVenta,
                'subtotal'       => $subtotalLinea,
            ];
        }

        $subtotal = round($subtotal, 2);

        $descuento = round($this->aDecimal($datos['descuento'] ?? 0), 2);
        if ($descuento < 0 || $descuento > $subtotal) {
            return self::errDatos('El descuento debe estar entre 0 y el subtotal');
        }

        $impuestoPorcentaje = round($this->aDecimal($datos['impuesto_porcentaje'] ?? 0), 2);
        if ($impuestoPorcentaje < 0 || $impuestoPorcentaje > 100) {
            return self::errDatos('El impuesto debe estar entre 0 y 100 %');
        }

        $base = round($subtotal - $descuento, 2);
        $impuesto = round($base * ($impuestoPorcentaje / 100), 2);
        $total = round($base + $impuesto, 2);

        $abono = round($this->aDecimal($datos['abono'] ?? 0), 2);

        if ($formaPago === 'credito') {
            if ($abono < 0) {
                return self::errDatos('El abono inicial no puede ser negativo');
            }

            if ($abono > $total + 0.005) {
                return self::errDatos('El abono inicial no puede superar el total de la compra');
            }

            $abono = min($abono, $total);
        } else {
            // Contado, transferencia, QR u otro: la compra queda pagada.
            $abono = $total;
        }

        return [
            'exito'   => true,
            'mensaje' => 'ok',
            'datos'   => [
                'id_proveedor'        => $idProveedor,
                'tipo_documento'      => $tipoDocumento,
                'forma_pago'          => $formaPago,
                'numero_documento'    => $numeroDocumento,
                'fecha_compra'        => $fechaCompra,
                'observaciones'       => $observaciones,
                'subtotal'            => $subtotal,
                'descuento'           => $descuento,
                'impuesto_porcentaje' => $impuestoPorcentaje,
                'impuesto'            => $impuesto,
                'total'               => $total,
                'abono'               => $abono,
                'lineas'              => $lineas,
            ],
        ];
    }

    private function actualizarPrecioSucursal(int $idSucursal, int $idProducto, float $precio): void
    {
        if ($precio <= 0) {
            return;
        }

        $this->conn->prepare(
            "UPDATE productos_sucursal
                SET precio_venta = :precio
              WHERE id_sucursal = :sucursal AND id_producto = :producto"
        )->execute([
            ':precio'   => round($precio, 2),
            ':sucursal' => $idSucursal,
            ':producto' => $idProducto,
        ]);
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private static function errDatos(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'datos' => []];
    }

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0, 'compra' => null];
    }

    private function aDecimal($valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace(',', '.', trim($valor));
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    private function aFechaCompra($valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return date('Y-m-d H:i:s');
        }

        $texto = str_replace('T', ' ', $texto);
        $timestamp = strtotime($texto);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function sucursalActiva(int $idSucursal): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM sucursales WHERE id_sucursal = :id AND estado = 1 LIMIT 1');
        $stmt->execute([':id' => $idSucursal]);

        return (bool) $stmt->fetchColumn();
    }

    private function proveedorActivo(int $idProveedor): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM proveedores WHERE id_proveedor = :id AND estado = 1 LIMIT 1');
        $stmt->execute([':id' => $idProveedor]);

        return (bool) $stmt->fetchColumn();
    }

    private function proveedorNombre(int $idProveedor): string
    {
        $stmt = $this->conn->prepare('SELECT nombre FROM proveedores WHERE id_proveedor = :id LIMIT 1');
        $stmt->execute([':id' => $idProveedor]);

        return (string) ($stmt->fetchColumn() ?: 'Proveedor');
    }

    private function productoActivo(int $idProducto): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id_producto, codigo, nombre, precio FROM productos
             WHERE id_producto = :id AND estado = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $idProducto]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    private function documentoDuplicado(int $idSucursal, int $idProveedor, string $numero, int $excluirId = 0): bool
    {
        $cond = '';

        if ($excluirId > 0) {
            $cond = ' AND id_compra != :excluir';
        }

        $stmt = $this->conn->prepare(
            "SELECT 1 FROM compras
             WHERE id_sucursal = :sucursal
               AND id_proveedor = :proveedor
               AND numero_documento = :numero
               AND estado = 'registrada'" . $cond . "
             LIMIT 1"
        );

        $params = [
            ':sucursal'  => $idSucursal,
            ':proveedor' => $idProveedor,
            ':numero'    => $numero,
        ];

        if ($excluirId > 0) {
            $params[':excluir'] = $excluirId;
        }

        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private static function urlImagen(string $ruta): string
    {
        if ($ruta === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }

        $ruta = ltrim(str_replace('\\', '/', $ruta), '/');

        return defined('BASE_URL') ? BASE_URL . '/' . $ruta : $ruta;
    }

    private function normalizar(array $f): array
    {
        $usuario = trim((string) ($f['usuario_nombre'] ?? ''));
        $total = (float) $f['total'];
        $esCredito = (string) $f['forma_pago'] === 'credito';
        $abono = $esCredito ? (float) ($f['abono'] ?? 0) : $total;
        $pagado = (float) ($f['pagado'] ?? 0);
        $saldo = $esCredito ? max(0.0, round($total - $abono - $pagado, 2)) : 0.0;

        return [
            'id'             => (int) $f['id_compra'],
            'id_sucursal'    => (int) $f['id_sucursal'],
            'sucursal'       => (string) ($f['nombre_sucursal'] ?? ''),
            'ciudad'         => (string) ($f['ciudad'] ?? ''),
            'id_proveedor'   => (int) $f['id_proveedor'],
            'proveedor'      => (string) ($f['proveedor_nombre'] ?? ''),
            'proveedor_nit'  => (string) ($f['proveedor_nit'] ?? ''),
            'proveedor_telefono' => (string) ($f['proveedor_telefono'] ?? ''),
            'proveedor_contacto' => (string) ($f['proveedor_contacto'] ?? ''),
            'tipo_documento' => (string) $f['tipo_documento'],
            'numero_documento' => (string) ($f['numero_documento'] ?? ''),
            'fecha_compra'   => !empty($f['fecha_compra']) ? date('d/m/Y', strtotime($f['fecha_compra'])) : '—',
            'fecha_compra_iso' => (string) ($f['fecha_compra'] ?? ''),
            'forma_pago'     => (string) $f['forma_pago'],
            'estado'         => (string) $f['estado'],
            'subtotal'       => (float) $f['subtotal'],
            'descuento'      => (float) $f['descuento'],
            'impuesto_porcentaje' => (float) $f['impuesto_porcentaje'],
            'impuesto'       => (float) $f['impuesto'],
            'total'          => $total,
            'abono'          => round($abono, 2),
            'pagado'         => round($pagado, 2),
            'saldo'          => $saldo,
            'es_credito'     => $esCredito,
            'observaciones'  => (string) ($f['observaciones'] ?? ''),
            'usuario'        => $usuario !== '' ? $usuario : (string) ($f['username'] ?? ''),
            'items'          => (int) ($f['items'] ?? 0),
            'unidades'       => (float) ($f['unidades'] ?? 0),
            'creado'         => !empty($f['created_at']) ? date('d/m/Y H:i', strtotime($f['created_at'])) : '—',
        ];
    }
}
