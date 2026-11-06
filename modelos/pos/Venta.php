<?php

/**
 * =====================================================
 * MODELO: Venta  (modelos/pos/Venta.php)
 * -----------------------------------------------------
 * Registra una venta en `ventas` + `venta_detalle`.
 *
 * Reglas de negocio:
 *   · El precio se calcula SIEMPRE en el servidor:
 *     precio de sucursal → oferta del producto →
 *     promoción vigente (producto/categoría) → IVA.
 *   · Kardex: sólo se mueve el stock de productos con
 *     `control_stock = 1`; se valida y descuenta el stock
 *     global de `productos.stock`. Los demás no descuentan.
 *   · Tipo de pedido: mesa, para llevar o delivery
 *     (con nombre del repartidor).
 *   · Pago mixto: se guarda el desglose exacto por medio.
 *   · Al anular se devuelve el stock de las líneas que
 *     sí controlaban stock y se revierte el arqueo.
 * =====================================================
 */

require_once __DIR__ . '/../compras/Kardex.php';
require_once __DIR__ . '/../cajas/ArqueoCaja.php';
require_once __DIR__ . '/Promocion.php';
require_once __DIR__ . '/PrecioPos.php';

class Venta
{
    private PDO $conn;

    /** null = aún no se consultó si la columna de idempotencia existe. */
    private ?bool $columnaIdempotencia = null;

    private const METODOS = ['efectivo', 'qr', 'tarjeta', 'mixto', 'otro'];
    private const TIPOS_PEDIDO = ['mesa', 'llevar', 'delivery'];
    private const TIPOS_PAGO = ['efectivo', 'qr', 'tarjeta'];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    /** Obtener una venta completa (para recibo/detalle). */
    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT v.*,
                    s.nombre_sucursal, s.ciudad, s.direccion AS sucursal_direccion,
                    s.telefono AS sucursal_telefono, s.logo AS sucursal_logo,
                    s.codigo_sucursal,
                    u.username,
                    TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre
               FROM ventas v
               INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
               INNER JOIN usuarios u   ON u.id_usuario  = v.id_usuario
               LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
              WHERE v.id_venta = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT d.*, p.codigo, p.nombre
               FROM venta_detalle d
               INNER JOIN productos p ON p.id_producto = d.id_producto
              WHERE d.id_venta = :id
              ORDER BY d.id_detalle ASC"
        );
        $stmt->execute([':id' => $id]);
        $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $usuario = trim((string) $fila['usuario_nombre']);

        $tipos = [
            'mesa'     => 'Para mesa',
            'llevar'   => 'Para llevar',
            'delivery' => 'Delivery',
        ];

        $metodos = [
            'efectivo' => 'Efectivo',
            'qr'       => 'QR',
            'tarjeta'  => 'Tarjeta',
            'mixto'    => 'Mixto',
            'otro'     => 'Otro',
        ];

        $pagos = [];
        $mixtoEf = (float) ($fila['mixto_efectivo'] ?? 0);
        $mixtoQr = (float) ($fila['mixto_qr'] ?? 0);
        $mixtoTj = (float) ($fila['mixto_tarjeta'] ?? 0);

        if ($mixtoEf + $mixtoQr + $mixtoTj > 0.004) {
            if ($mixtoEf > 0.004) $pagos[] = ['metodo' => 'efectivo', 'etiqueta' => 'Efectivo', 'monto' => $mixtoEf];
            if ($mixtoQr > 0.004) $pagos[] = ['metodo' => 'qr', 'etiqueta' => 'QR', 'monto' => $mixtoQr];
            if ($mixtoTj > 0.004) $pagos[] = ['metodo' => 'tarjeta', 'etiqueta' => 'Tarjeta', 'monto' => $mixtoTj];
        } else {
            $pagos[] = [
                'metodo'   => $fila['metodo_pago'],
                'etiqueta' => $metodos[$fila['metodo_pago']] ?? ucfirst((string) $fila['metodo_pago']),
                'monto'    => (float) $fila['total'],
            ];
        }

        $subtotal = (float) $fila['subtotal'];
        $descuento = (float) $fila['descuento'];

        return [
            'id'                  => (int) $fila['id_venta'],
            'codigo'              => $fila['codigo'],
            'id_sucursal'         => (int) $fila['id_sucursal'],
            'id_arqueo'           => (int) $fila['id_arqueo'],
            'sucursal'            => $fila['nombre_sucursal'],
            'sucursal_direccion'  => (string) ($fila['sucursal_direccion'] ?? ''),
            'sucursal_telefono'   => (string) ($fila['sucursal_telefono'] ?? ''),
            'sucursal_logo'       => (string) ($fila['sucursal_logo'] ?? ''),
            'sucursal_codigo'     => (string) ($fila['codigo_sucursal'] ?? ''),
            'ciudad'              => (string) ($fila['ciudad'] ?? ''),
            'fecha_venta'         => date('d/m/Y H:i', strtotime($fila['fecha_venta'])),
            'fecha_iso'           => (string) $fila['fecha_venta'],
            'usuario'             => $usuario !== '' ? $usuario : $fila['username'],
            'username'            => (string) $fila['username'],
            'subtotal'            => $subtotal,
            'subtotal_bruto'      => round($subtotal + $descuento, 2),
            'descuento'           => $descuento,
            'descuento_productos' => (float) ($fila['descuento_productos'] ?? 0),
            'impuesto_porcentaje' => (float) $fila['impuesto_porcentaje'],
            'impuesto'            => (float) $fila['impuesto'],
            'total'               => (float) $fila['total'],
            'metodo_pago'         => $fila['metodo_pago'],
            'metodo_pago_label'   => $metodos[$fila['metodo_pago']] ?? ucfirst((string) $fila['metodo_pago']),
            'tipo_pedido'         => (string) ($fila['tipo_pedido'] ?? 'mesa'),
            'tipo_pedido_label'   => $tipos[$fila['tipo_pedido'] ?? 'mesa'] ?? 'Para mesa',
            'delivery_nombre'     => (string) ($fila['delivery_nombre'] ?? ''),
            'recibido'            => (float) $fila['recibido'],
            'cambio'              => (float) $fila['cambio'],
            'mixto_efectivo'      => $mixtoEf,
            'mixto_qr'            => $mixtoQr,
            'mixto_tarjeta'       => $mixtoTj,
            'referencia_pago'     => (string) ($fila['referencia_pago'] ?? ''),
            'pagos'               => $pagos,
            'estado'              => $fila['estado'],
            'observaciones'       => (string) ($fila['observaciones'] ?? ''),
            'detalle'             => array_map(function (array $d): array {
                $cantidad = (float) $d['cantidad'];
                $precio   = (float) $d['precio_unitario'];
                $base     = (float) ($d['precio_base'] ?? $precio);
                $desc     = (float) ($d['descuento'] ?? 0);
                $iva      = (float) ($d['impuesto'] ?? 0);
                $total    = (float) ($d['total'] ?? $d['subtotal']);

                return [
                    'id_producto'         => (int) $d['id_producto'],
                    'codigo'              => $d['codigo'],
                    'producto'            => $d['nombre'],
                    'cantidad'            => $cantidad,
                    'precio_base'         => $base,
                    'precio_unitario'     => $precio,
                    'descuento_porcentaje' => (float) ($d['descuento_porcentaje'] ?? 0),
                    'descuento'           => $desc,
                    'promocion'           => (string) ($d['promocion'] ?? ''),
                    'impuesto_porcentaje' => (float) ($d['impuesto_porcentaje'] ?? 0),
                    'impuesto'            => $iva,
                    'subtotal'            => (float) $d['subtotal'],
                    'total'               => $total,
                ];
            }, $detalle),
        ];
    }

    // =================================================
    //  REGISTRO
    // =================================================

    /**
     * Registra una venta.
     *
     * @param array $datos {items:[{id_producto,cantidad}], metodo_pago,
     *                      pagos:[{metodo,monto}], recibido, referencia_pago,
     *                      tipo_pedido, delivery_nombre, observaciones, id_sucursal}
     * @param bool $moverStock true = descuenta kardex/productos.stock (POS).
     *                         false = la venta ya descontó el stock (pedido).
     */
    public function registrar(array $datos, int $idUsuario, int $idSucursal, int $idArqueo, bool $moverStock = true): array
    {
        if ($idUsuario <= 0 || $idSucursal <= 0 || $idArqueo <= 0) {
            return self::err('Datos de la venta incompletos');
        }

        $items = $datos['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return self::err('Agrega al menos un producto');
        }

        // ---- Idempotencia (evita doble registro por doble clic/reintento) ----
        $idempotencia = trim(strip_tags((string) ($datos['idempotencia'] ?? '')));

        if (mb_strlen($idempotencia) > 64) {
            $idempotencia = mb_substr($idempotencia, 0, 64);
        }

        $tieneIdempotencia = $this->tieneIdempotencia();

        if ($tieneIdempotencia && $idempotencia !== '') {
            $existente = $this->ventaPorIdempotencia($idempotencia);

            if ($existente !== null) {
                return [
                    'exito'   => true,
                    'mensaje' => 'Venta ya registrada',
                    'id'      => (int) $existente,
                    'venta'   => $this->obtener((int) $existente),
                ];
            }
        }

        // ---- Tipo de pedido ----
        $tipoPedido = strtolower(trim((string) ($datos['tipo_pedido'] ?? 'mesa')));
        if (!in_array($tipoPedido, self::TIPOS_PEDIDO, true)) {
            return self::err('Selecciona el tipo de pedido');
        }

        $deliveryNombre = trim(strip_tags((string) ($datos['delivery_nombre'] ?? '')));
        if ($tipoPedido === 'delivery' && $deliveryNombre === '') {
            return self::err('Ingresa el nombre del delivery');
        }
        if (mb_strlen($deliveryNombre) > 120) {
            return self::err('El nombre del delivery no puede superar los 120 caracteres');
        }
        if ($tipoPedido !== 'delivery') {
            $deliveryNombre = '';
        }

        // ---- Promociones vigentes ----
        $promociones = (new Promocion($this->conn))->vigentes();

        $lineas              = [];
        $vistos              = [];
        $subtotal            = 0.0;
        $descuentoProductos  = 0.0;
        $impuesto            = 0.0;
        $total               = 0.0;
        $totalUtilidadVenta  = 0.0;   // ← NUEVO

        foreach ($items as $item) {
            $idProd = (int) ($item['id_producto'] ?? 0);
            $cant   = round((float) ($item['cantidad'] ?? 0), 3);

            if ($idProd <= 0) return self::err('Línea sin producto');
            if (isset($vistos[$idProd])) return self::err('Producto repetido en el carrito');
            $vistos[$idProd] = true;

            if ($cant <= 0) return self::err('La cantidad debe ser mayor que cero');

            $prod  = $this->productoVendible($idProd);
            $costo = $this->costocompra($idProd);   // solo productos.costo

            if (!$prod) return self::err('Un producto ya no está disponible');

            $controla = (int) $prod['control_stock'] === 1;

            if ($controla && $moverStock) {
                // El POS valida contra el stock global del producto
                // (productos.stock), no contra la fila de la sucursal.
                // Si la venta viene de un pedido, el stock ya se descontó
                // al tomar el pedido y no corresponde volver a validarlo.
                $stock = (float) $prod['stock'];
                if ($stock + 0.0001 < $cant) {
                    return self::err('Stock insuficiente de "' . $prod['nombre'] . '" (disponible: ' . $stock . ')');
                }
            }

            $base = $this->precioSucursal($idSucursal, $idProd, (float) $prod['precio']);
            if ($base <= 0) {
                return self::err('El producto "' . $prod['nombre'] . '" no tiene precio de venta');
            }

            $prod['oferta_vigente'] = $this->ofertaVigente($prod) ? 1 : 0;

            $calc = PrecioPos::calcular($base, $prod, $promociones);

            $precioUnit = (float) $calc['precio_unitario'];
            $baseLinea  = round($precioUnit * $cant, 2);
            $descLinea  = round((float) $calc['descuento_unitario'] * $cant, 2);
            $ivaLinea   = round($baseLinea * ((float) $calc['impuesto_porcentaje'] / 100), 2);
            $totalLinea = round($baseLinea + $ivaLinea, 2);

            // Costo unitario + costo total de la línea
            $utilidadUnitaria = round((float) $costo, 4);
            $totalUtilidad    = round($costo * $cant, 2);

            $lineas[] = [
                'id_producto'          => $idProd,
                'cantidad'             => $cant,
                'precio_base'          => (float) $calc['precio_base'],
                'precio_unitario'      => $precioUnit,
                'descuento_porcentaje' => (float) $calc['descuento_porcentaje'],
                'descuento'            => $descLinea,
                'promocion'            => trim((string) $calc['promocion']),
                'control_stock'        => $controla ? 1 : 0,
                'impuesto_porcentaje'  => (float) $calc['impuesto_porcentaje'],
                'impuesto'             => $ivaLinea,
                'subtotal'             => $baseLinea,
                'total'                => $totalLinea,
                'utilidad_bruta'       => $utilidadUnitaria,   // ← costo unitario
                'total_utilidad'       => $totalUtilidad,      // ← costo × cantidad
                'nombre'               => $prod['nombre'],
            ];

            $subtotal           += $baseLinea;
            $descuentoProductos += $descLinea;
            $impuesto           += $ivaLinea;
            $total              += $totalLinea;
            $totalUtilidadVenta += $totalUtilidad;   // ← acumula para ventas.total_utilidad
        }

        $subtotal           = round($subtotal, 2);
        $descuentoProductos = round($descuentoProductos, 2);
        $impuesto           = round($impuesto, 2);
        $total              = round($total, 2);
        $totalUtilidadVenta = round($totalUtilidadVenta, 2);

        if ($total <= 0) {
            return self::err('El total de la venta debe ser mayor que cero');
        }

        $pctEfectivo = $subtotal > 0 ? round(($impuesto / $subtotal) * 100, 2) : 0.0;

        // ---- Forma de pago ----
        $pago = $this->resolverPago($datos, $total);
        if (!$pago['ok']) {
            return self::err($pago['mensaje']);
        }

        $referencia = trim(strip_tags((string) ($datos['referencia_pago'] ?? $datos['referencia'] ?? '')));
        if (mb_strlen($referencia) > 100) {
            $referencia = mb_substr($referencia, 0, 100);
        }

        $observaciones = trim(strip_tags((string) ($datos['observaciones'] ?? '')));
        if (mb_strlen($observaciones) > 255) {
            $observaciones = mb_substr($observaciones, 0, 255);
        }

        try {
            $this->conn->beginTransaction();

            $codigo = $this->codigoVenta();

            $columnasVenta = 'id_sucursal, id_arqueo, id_usuario, codigo,
                 subtotal, descuento, descuento_productos,
                 impuesto_porcentaje, impuesto, total,
                 total_utilidad,
                 metodo_pago, tipo_pedido, delivery_nombre,
                 recibido, mixto_efectivo, mixto_qr, mixto_tarjeta,
                 referencia_pago, cambio, estado, observaciones';

            $valoresVenta = ':sucursal, :arqueo, :usuario, :codigo,
                 :subtotal, :descuento, :descuento_productos,
                 :pct, :impuesto, :total,
                 :total_utilidad,
                 :metodo, :tipo_pedido, :delivery,
                 :recibido, :mixto_efectivo, :mixto_qr, :mixto_tarjeta,
                 :referencia, :cambio, \'registrada\', :obs';

            $paramsVenta = [
                ':sucursal'            => $idSucursal,
                ':arqueo'              => $idArqueo,
                ':usuario'             => $idUsuario,
                ':codigo'              => $codigo,
                ':subtotal'            => $subtotal,
                ':descuento'           => $descuentoProductos,
                ':descuento_productos' => $descuentoProductos,
                ':pct'                 => $pctEfectivo,
                ':impuesto'            => $impuesto,
                ':total'               => $total,
                ':total_utilidad'      => $totalUtilidadVenta,
                ':metodo'              => $pago['metodo'],
                ':tipo_pedido'         => $tipoPedido,
                ':delivery'            => $tipoPedido === 'delivery' ? $deliveryNombre : null,
                ':recibido'            => $pago['recibido'],
                ':mixto_efectivo'      => $pago['mixto_efectivo'],
                ':mixto_qr'            => $pago['mixto_qr'],
                ':mixto_tarjeta'       => $pago['mixto_tarjeta'],
                ':referencia'          => $referencia !== '' ? $referencia : null,
                ':cambio'              => $pago['cambio'],
                ':obs'                 => $observaciones !== '' ? $observaciones : null,
            ];

            if ($tieneIdempotencia && $idempotencia !== '') {
                $columnasVenta .= ', clave_idempotencia';
                $valoresVenta  .= ', :idempotencia';
                $paramsVenta[':idempotencia'] = $idempotencia;
            }

            $this->conn->prepare(
                "INSERT INTO ventas ({$columnasVenta}) VALUES ({$valoresVenta})"
            )->execute($paramsVenta);

            $idVenta = (int) $this->conn->lastInsertId();

            $detalle = $this->conn->prepare(
                "INSERT INTO venta_detalle
                (id_venta, id_producto, cantidad, precio_unitario, precio_base,
                 utilidad_bruta, total_utilidad,
                 descuento_porcentaje, descuento, promocion, control_stock,
                 impuesto_porcentaje, impuesto, total, subtotal)
             VALUES
                (:v, :p, :c, :pu, :pb,
                 :utilidad, :total_utilidad,
                 :dp, :d, :promo, :control,
                 :ip, :i, :t, :s)"
            );

            $kardex = new Kardex($this->conn);

            foreach ($lineas as $l) {
                $detalle->execute([
                    ':v'              => $idVenta,
                    ':p'              => $l['id_producto'],
                    ':c'              => $l['cantidad'],
                    ':pu'             => $l['precio_unitario'],
                    ':pb'             => $l['precio_base'],
                    ':utilidad'       => $l['utilidad_bruta'],   // costo unitario
                    ':total_utilidad' => $l['total_utilidad'],   // costo × cantidad
                    ':dp'             => $l['descuento_porcentaje'],
                    ':d'              => $l['descuento'],
                    ':promo'          => $l['promocion'] !== '' ? $l['promocion'] : null,
                    ':control'        => $l['control_stock'],
                    ':ip'             => $l['impuesto_porcentaje'],
                    ':i'              => $l['impuesto'],
                    ':t'              => $l['total'],
                    ':s'              => $l['subtotal'],
                ]);

                // Sólo se mueve el kardex si esta venta debe mover stock
                // (la venta desde un pedido ya lo descontó al tomarse) y la
                // línea controla stock.
                if (!$moverStock || $l['control_stock'] !== 1) {
                    continue;
                }

                $mov = $kardex->registrarSalida(
                    $idSucursal,
                    $l['id_producto'],
                    $l['cantidad'],
                    'venta',
                    $idVenta,
                    'Venta ' . $codigo,
                    $idUsuario,
                    false,   // permitirNegativo
                    true     // usarStockGlobal: descuenta productos.stock
                );

                if (!$mov['exito']) {
                    throw new RuntimeException($mov['mensaje']);
                }
            }

            // Recalcular el arqueo desde las ventas reales (sin duplicar
            // acumulados ni dejar cifras viejas si algo se anuló antes).
            $arqueo = new ArqueoCaja($this->conn);
            $r = $arqueo->recalcularVentas($idArqueo);

            if (!$r['exito']) {
                throw new RuntimeException($r['mensaje']);
            }

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Venta registrada',
                'id'      => $idVenta,
                'venta'   => $this->obtener($idVenta),
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();

            // Si dos peticiones llegaron a la vez con la misma clave,
            // devolvemos la venta que sí se guardó.
            if ($e->getCode() === '23000' && $tieneIdempotencia && $idempotencia !== '') {
                $existente = $this->ventaPorIdempotencia($idempotencia);

                if ($existente !== null) {
                    return [
                        'exito'   => true,
                        'mensaje' => 'Venta ya registrada',
                        'id'      => (int) $existente,
                        'venta'   => $this->obtener((int) $existente),
                    ];
                }
            }

            error_log('Venta::registrar - ' . $e->getMessage());
            return self::err('No se pudo registrar la venta');
        }
    }

    // =================================================
    //  ANULACIÓN
    // =================================================

    /**
     * Anula una venta: devuelve stock sólo de las líneas
     * que controlaban stock y revierte el arqueo.
     *
     * @param array $usuario ContextoCompras::usuario()
     * @return array{exito:bool,mensaje:string}
     */
    public function anular(int $idVenta, array $usuario): array
    {
        if ($idVenta <= 0 || empty($usuario['id'])) {
            return ['exito' => false, 'mensaje' => 'Venta no válida'];
        }

        try {
            $this->conn->beginTransaction();

            $lock = $this->conn->prepare("SELECT * FROM ventas WHERE id_venta = :id FOR UPDATE");
            $lock->execute([':id' => $idVenta]);
            $fila = $lock->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                if ($this->conn->inTransaction()) $this->conn->rollBack();
                return ['exito' => false, 'mensaje' => 'Venta no encontrada'];
            }

            if ((string) $fila['estado'] !== 'registrada') {
                if ($this->conn->inTransaction()) $this->conn->rollBack();
                return ['exito' => false, 'mensaje' => 'Esta venta ya está anulada'];
            }

            if (
                !Permisos::instancia()->esSuperAdmin((int) $usuario['id'])
                && (int) $fila['id_sucursal'] !== (int) ($usuario['id_sucursal'] ?? 0)
            ) {
                if ($this->conn->inTransaction()) $this->conn->rollBack();
                return ['exito' => false, 'mensaje' => 'No puedes anular ventas de otra sucursal'];
            }

            $detalle = $this->conn->prepare(
                "SELECT d.*, p.control_stock AS control_actual
                   FROM venta_detalle d
                   INNER JOIN productos p ON p.id_producto = d.id_producto
                  WHERE d.id_venta = :id"
            );
            $detalle->execute([':id' => $idVenta]);
            $lineas = $detalle->fetchAll(PDO::FETCH_ASSOC);

            $kardex = new Kardex($this->conn);
            $stockDevuelto = 0;

            // Costo con el que salió originalmente cada producto: así la
            // anulación no altera el precio de venta ni el costo actual.
            $costoStmt = $this->conn->prepare(
                "SELECT COALESCE(
                            (SELECT k.costo_unitario
                               FROM kardex k
                              WHERE k.id_sucursal = :s
                                AND k.id_producto = :p1
                                AND k.origen = 'venta'
                                AND k.id_referencia = :v
                              ORDER BY k.id_movimiento ASC
                              LIMIT 1),
                            (SELECT p.costo FROM productos p WHERE p.id_producto = :p2),
                            0
                        ) AS costo"
            );

            foreach ($lineas as $linea) {
                $snapshot = $linea['control_stock'];
                $controla = $snapshot === null
                    ? ((int) $linea['control_actual'] === 1)
                    : ((int) $snapshot === 1);

                if (!$controla) {
                    continue;
                }

                $costoStmt->execute([
                    ':s'  => (int) $fila['id_sucursal'],
                    ':p1' => (int) $linea['id_producto'],
                    ':p2' => (int) $linea['id_producto'],
                    ':v'  => $idVenta,
                ]);
                $costo = max(0.0, (float) $costoStmt->fetchColumn());

                $mov = $kardex->registrarEntrada(
                    (int) $fila['id_sucursal'],
                    (int) $linea['id_producto'],
                    (float) $linea['cantidad'],
                    $costo,
                    0.0,
                    'ajuste',
                    $idVenta,
                    'Anulación venta ' . $fila['codigo'],
                    (int) $usuario['id']
                );

                if (!$mov['exito']) {
                    throw new RuntimeException($mov['mensaje']);
                }

                $stockDevuelto++;
            }

            $this->conn->prepare(
                "UPDATE ventas SET estado = 'anulada' WHERE id_venta = :id AND estado = 'registrada'"
            )->execute([':id' => $idVenta]);

            // Recalcular los totales del arqueo desde las ventas reales.
            // Así la venta anulada deja de sumar en efectivo, QR, tarjeta y
            // efectivo esperado, incluso si el arqueo ya estaba cerrado.
            if ((int) $fila['id_arqueo'] > 0) {
                $arqueo = new ArqueoCaja($this->conn);
                $r = $arqueo->recalcularVentas((int) $fila['id_arqueo']);

                if (!$r['exito']) {
                    throw new RuntimeException($r['mensaje']);
                }
            }

            $this->conn->commit();

            $mensaje = $stockDevuelto > 0
                ? 'Venta anulada: se devolvió el stock al inventario'
                : 'Venta anulada: los productos no controlan stock, no hubo cambios en inventario';

            return ['exito' => true, 'mensaje' => $mensaje];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return ['exito' => false, 'mensaje' => $e->getMessage()];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Venta::anular - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo anular la venta'];
        }
    }

    // =================================================
    //  AUXILIARES DE PAGO
    // =================================================

    /** ¿La tabla ventas tiene la columna de idempotencia? */
    private function tieneIdempotencia(): bool
    {
        if ($this->columnaIdempotencia !== null) {
            return $this->columnaIdempotencia;
        }

        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM ventas LIKE 'clave_idempotencia'");
            $this->columnaIdempotencia = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Venta::tieneIdempotencia - ' . $e->getMessage());
            $this->columnaIdempotencia = false;
        }

        return $this->columnaIdempotencia;
    }

    /** Id de la venta que ya usó esa clave de idempotencia. */
    private function ventaPorIdempotencia(string $clave): ?int
    {
        if ($clave === '' || !$this->tieneIdempotencia()) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT id_venta FROM ventas WHERE clave_idempotencia = :clave LIMIT 1"
            );
            $stmt->execute([':clave' => $clave]);
            $id = $stmt->fetchColumn();

            return $id !== false ? (int) $id : null;
        } catch (PDOException $e) {
            error_log('Venta::ventaPorIdempotencia - ' . $e->getMessage());
            return null;
        }
    }

    /** Normaliza y valida la forma de pago (simple o mixta). */
    private function resolverPago(array $datos, float $total): array
    {
        $metodo = strtolower(trim((string) ($datos['metodo_pago'] ?? 'efectivo')));

        if (!in_array($metodo, self::METODOS, true)) {
            $metodo = 'efectivo';
        }

        $efectivo = 0.0;
        $qr = 0.0;
        $tarjeta = 0.0;
        $otros = 0.0;
        $recibido = $total;
        $cambio = 0.0;

        if ($metodo === 'mixto') {
            $pagos = [];

            if (!empty($datos['pagos']) && is_array($datos['pagos'])) {
                foreach ($datos['pagos'] as $p) {
                    $m = strtolower(trim((string) ($p['metodo'] ?? '')));
                    $monto = round((float) ($p['monto'] ?? 0), 2);

                    if (!in_array($m, self::TIPOS_PAGO, true)) {
                        return ['ok' => false, 'mensaje' => 'Medio de pago mixto no válido'];
                    }

                    if ($monto <= 0) {
                        continue;
                    }

                    $pagos[$m] = ($pagos[$m] ?? 0) + $monto;
                }
            } else {
                // Compatibilidad con el formato anterior.
                $mEf = round((float) ($datos['mixto_efectivo'] ?? 0), 2);
                $mOt = round((float) ($datos['mixto_otro_monto'] ?? 0), 2);
                $mMedio = strtolower((string) ($datos['mixto_otro_medio'] ?? 'qr'));
                if (!in_array($mMedio, self::TIPOS_PAGO, true)) $mMedio = 'qr';

                if ($mEf > 0) $pagos['efectivo'] = $mEf;
                if ($mOt > 0) $pagos[$mMedio] = ($pagos[$mMedio] ?? 0) + $mOt;
            }

            if (count($pagos) < 1) {
                return ['ok' => false, 'mensaje' => 'Indica cómo se divide el pago mixto'];
            }

            $suma = round(array_sum($pagos), 2);

            if (abs($suma - $total) > 0.011) {
                return [
                    'ok' => false,
                    'mensaje' => 'La suma del pago mixto (' . number_format($suma, 2, '.', '') . ') debe ser igual al total (' . number_format($total, 2, '.', '') . ')',
                ];
            }

            $efectivo = round((float) ($pagos['efectivo'] ?? 0), 2);
            $qr = round((float) ($pagos['qr'] ?? 0), 2);
            $tarjeta = round((float) ($pagos['tarjeta'] ?? 0), 2);

            $metodoFinal = count($pagos) === 1 ? array_key_first($pagos) : 'mixto';
            $recibido = $efectivo > 0 ? $efectivo : $total;

            return [
                'ok'             => true,
                'mensaje'        => '',
                'metodo'         => $metodoFinal,
                'recibido'       => round($recibido, 2),
                'cambio'         => 0.0,
                'mixto_efectivo' => $efectivo,
                'mixto_qr'       => $qr,
                'mixto_tarjeta'  => $tarjeta,
                'bolsa'          => [
                    'efectivo' => $efectivo,
                    'qr'       => $qr,
                    'tarjeta'  => $tarjeta,
                    'otros'    => 0.0,
                ],
            ];
        }

        if ($metodo === 'efectivo') {
            $recibido = round((float) ($datos['recibido'] ?? $total), 2);

            if ($recibido + 0.005 < $total) {
                return ['ok' => false, 'mensaje' => 'El efectivo recibido es menor al total'];
            }

            $cambio = round(max(0, $recibido - $total), 2);
            $efectivo = $total;
        } elseif ($metodo === 'qr') {
            $qr = $total;
        } elseif ($metodo === 'tarjeta') {
            $tarjeta = $total;
        } else {
            $otros = $total;
        }

        return [
            'ok'             => true,
            'mensaje'        => '',
            'metodo'         => $metodo,
            'recibido'       => $recibido,
            'cambio'         => $cambio,
            'mixto_efectivo' => 0.0,
            'mixto_qr'       => 0.0,
            'mixto_tarjeta'  => 0.0,
            'bolsa'          => [
                'efectivo' => $efectivo,
                'qr'       => $qr,
                'tarjeta'  => $tarjeta,
                'otros'    => $otros,
            ],
        ];
    }

    /** Desglose por medio con el que se pagó una venta. */
    private function bolsaDeVenta(array $fila): array
    {
        $ef = (float) ($fila['mixto_efectivo'] ?? 0);
        $qr = (float) ($fila['mixto_qr'] ?? 0);
        $tj = (float) ($fila['mixto_tarjeta'] ?? 0);

        if ($ef + $qr + $tj > 0.004) {
            return ['efectivo' => $ef, 'qr' => $qr, 'tarjeta' => $tj, 'otros' => 0.0];
        }

        $total = (float) $fila['total'];

        return match ((string) $fila['metodo_pago']) {
            'efectivo' => ['efectivo' => $total, 'qr' => 0.0, 'tarjeta' => 0.0, 'otros' => 0.0],
            'qr'       => ['efectivo' => 0.0, 'qr' => $total, 'tarjeta' => 0.0, 'otros' => 0.0],
            'tarjeta'  => ['efectivo' => 0.0, 'qr' => 0.0, 'tarjeta' => $total, 'otros' => 0.0],
            default    => ['efectivo' => 0.0, 'qr' => 0.0, 'tarjeta' => 0.0, 'otros' => $total],
        };
    }

    // =================================================
    //  AUXILIARES DE PRODUCTO
    // =================================================

    private function productoVendible(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_producto, id_categoria, nombre, precio, costo,
                descuento_porcentaje, precio_oferta, oferta_activa,
                oferta_desde, oferta_hasta, impuesto_porcentaje,
                control_stock, stock
           FROM productos
          WHERE id_producto = :id AND estado = 1 AND disponible = 1
          LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    private function costocompra(int $idProducto): float
    {
        if ($idProducto <= 0) return 0.0;

        $stmt = $this->conn->prepare(
            "SELECT costo FROM productos
          WHERE id_producto = :id AND estado = 1 AND disponible = 1
          LIMIT 1"
        );
        $stmt->execute([':id' => $idProducto]);
        $v = $stmt->fetchColumn();

        return $v !== false ? round((float) $v, 4) : 0.0;
    }
    private function ofertaVigente(array $prod): bool
    {
        if ((int) ($prod['oferta_activa'] ?? 0) !== 1) {
            return false;
        }

        $ahora = time();

        if (!empty($prod['oferta_desde']) && strtotime((string) $prod['oferta_desde']) > $ahora) {
            return false;
        }

        if (!empty($prod['oferta_hasta']) && strtotime((string) $prod['oferta_hasta']) < $ahora) {
            return false;
        }

        return true;
    }

    private function precioSucursal(int $idS, int $idP, float $fallback): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(NULLIF(precio_venta,0), :f)
               FROM productos_sucursal
              WHERE id_sucursal = :s AND id_producto = :p LIMIT 1"
        );
        $stmt->execute([':s' => $idS, ':p' => $idP, ':f' => $fallback]);
        $v = $stmt->fetchColumn();

        return $v === false ? $fallback : (float) $v;
    }

    private function codigoVenta(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $codigo = 'V-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $this->conn->prepare("SELECT 1 FROM ventas WHERE codigo = :c LIMIT 1");
            $stmt->execute([':c' => $codigo]);
            if (!$stmt->fetchColumn()) return $codigo;
        }

        return 'V-' . time();
    }

    private static function err(string $m): array
    {
        return ['exito' => false, 'mensaje' => $m, 'id' => 0, 'venta' => null];
    }
}
