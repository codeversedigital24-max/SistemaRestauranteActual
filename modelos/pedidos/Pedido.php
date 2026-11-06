<?php
require_once __DIR__ . '/../compras/Kardex.php';
require_once __DIR__ . '/../cajas/ArqueoCaja.php';
require_once __DIR__ . '/../pos/Promocion.php';
require_once __DIR__ . '/../pos/PrecioPos.php';
require_once __DIR__ . '/../pos/Venta.php';

class Pedido
{
    private PDO $conn;

    /** Caché por request de las columnas de la migración sql_pedidos_stock.sql. */
    private static ?array $columnasCache = null;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =====================================================
    // LECTURA
    // =====================================================

    /** Mesas + pedido abierto (si existe) para la pestaña Salón. */
    public function mesasConPedido(int $idSucursal): array
    {
        $sql = "SELECT m.*,
                       p.nombre AS piso_nombre, p.icono AS piso_icono,
                       pe.id_pedido AS pedido_id,
                       pe.codigo AS pedido_codigo,
                       pe.estado AS pedido_estado,
                       pe.total AS pedido_total,
                       pe.fecha_apertura AS pedido_fecha,
                       pe.id_usuario AS pedido_usuario,
                       pe.tipo_pedido AS pedido_tipo,
                       (SELECT COUNT(*) FROM pedido_detalle d WHERE d.id_pedido = pe.id_pedido) AS pedido_items
                  FROM mesas m
                  LEFT JOIN pisos p ON p.id_piso = m.id_piso
                  LEFT JOIN pedidos pe
       ON pe.id_mesa = m.id_mesa
      AND pe.estado IN ('pendiente','preparando','despachado','pendiente_pago')
                 WHERE m.id_sucursal = :s
                   AND m.estado = 1
                 ORDER BY p.orden ASC, m.orden ASC, m.codigo ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute([':s' => $idSucursal]);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Pedido::mesasConPedido - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($m) {
            $imagen = (string) ($m['imagen'] ?? '');
            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

            return [
                'id_mesa'         => (int) $m['id_mesa'],
                'codigo'          => (string) $m['codigo'],
                'nombre'          => (string) $m['nombre'],
                'capacidad'       => (int) $m['capacidad'],
                'forma'           => (string) $m['forma'],
                'icono'           => (string) ($m['icono'] ?: 'fa-chair'),
                'imagen_url'      => $imagen !== ''
                    ? ($base !== '' ? $base . '/' . ltrim($imagen, '/') : '/' . ltrim($imagen, '/'))
                    : '',
                'piso'            => (string) ($m['piso_nombre'] ?? ''),
                'piso_icono'      => (string) ($m['piso_icono'] ?? 'fa-layer-group'),
                'estado_mesa'     => (string) $m['estado_mesa'],
                'pedido_id'       => $m['pedido_id'] ? (int) $m['pedido_id'] : 0,
                'pedido_codigo'   => (string) ($m['pedido_codigo'] ?? ''),
                'pedido_estado'   => (string) ($m['pedido_estado'] ?? ''),
                'pedido_total'    => (float) ($m['pedido_total'] ?? 0),
                'pedido_fecha'    => $m['pedido_fecha']
                    ? date('H:i', strtotime($m['pedido_fecha']))
                    : '',
                'pedido_items'    => (int) ($m['pedido_items'] ?? 0),
                'pedido_tipo'     => (string) ($m['pedido_tipo'] ?? ''),
            ];
        }, $filas);
    }

    /** Cola de cocina: pedidos pendientes/preparando. */
    public function colaCocina(int $idSucursal): array
    {
        $sql = "SELECT pe.*,
                       m.codigo AS mesa_codigo, m.nombre AS mesa_nombre,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                       u.username
                  FROM pedidos pe
                  LEFT JOIN mesas m ON m.id_mesa = pe.id_mesa
                  INNER JOIN usuarios u ON u.id_usuario = pe.id_usuario
                  LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                 WHERE pe.id_sucursal = :s
                   AND pe.estado IN ('pendiente','preparando')
                 ORDER BY pe.fecha_apertura ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute([':s' => $idSucursal]);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Pedido::colaCocina - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($p) {
            $nombre = trim((string) $p['usuario_nombre']);
            $p['usuario_nombre'] = $nombre !== '' ? $nombre : (string) $p['username'];
            $p['detalle'] = $this->detalle((int) $p['id_pedido']);
            $p['formateado'] = $this->normalizar($p);
            return $p['formateado'];
        }, $filas);
    }

    /** Mesas pendientes de pago. */
    public function mesasPorCobrar(int $idSucursal): array
    {
        $sql = "SELECT pe.*,
                       m.codigo AS mesa_codigo, m.nombre AS mesa_nombre
                  FROM pedidos pe
                  LEFT JOIN mesas m ON m.id_mesa = pe.id_mesa
                 WHERE pe.id_sucursal = :s
                   AND pe.estado = 'pendiente_pago'
                 ORDER BY pe.fecha_apertura ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute([':s' => $idSucursal]);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Pedido::mesasPorCobrar - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($p) {
            $p['detalle'] = $this->detalle((int) $p['id_pedido']);
            return $this->normalizar($p);
        }, $filas);
    }

    /** Pedidos online (llevar / delivery) activos de la sucursal. */
    public function listarOnline(int $idSucursal): array
    {
        $sql = "SELECT pe.*,
                       m.codigo AS mesa_codigo, m.nombre AS mesa_nombre,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                       u.username
                  FROM pedidos pe
                  LEFT JOIN mesas m ON m.id_mesa = pe.id_mesa
                  INNER JOIN usuarios u ON u.id_usuario = pe.id_usuario
                  LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
                 WHERE pe.id_sucursal = :s
                   AND pe.tipo_pedido IN ('llevar','delivery')
                   AND pe.estado IN ('pendiente','preparando','despachado','pendiente_pago')
                 ORDER BY pe.fecha_apertura ASC";

        try {
            $st = $this->conn->prepare($sql);
            $st->execute([':s' => $idSucursal]);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Pedido::listarOnline - ' . $e->getMessage());
            return [];
        }

        $lista = [];

        foreach ($filas as $p) {
            $n = trim((string) ($p['usuario_nombre'] ?? ''));
            $p['usuario_nombre'] = $n !== '' ? $n : (string) ($p['username'] ?? '');
            $f = $this->normalizar($p);
            $f['tipo_label'] = $f['tipo_pedido'] === 'delivery' ? 'Delivery' : 'Para llevar';
            $f['cliente'] = trim((string) ($f['delivery_nombre'] ?? '')) !== ''
                ? (string) $f['delivery_nombre']
                : $f['usuario'];
            $f['detalle'] = $this->detalle((int) $p['id_pedido']);
            $lista[] = $f;
        }

        return $lista;
    }

    public function obtener(int $id): ?array
    {
        if ($id <= 0) return null;

        $st = $this->conn->prepare(
            "SELECT pe.*, m.codigo AS mesa_codigo, m.nombre AS mesa_nombre,
                    u.username,
                    TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre
               FROM pedidos pe
               LEFT JOIN mesas m ON m.id_mesa = pe.id_mesa
               INNER JOIN usuarios u ON u.id_usuario = pe.id_usuario
               LEFT JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
              WHERE pe.id_pedido = :id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);

        if (!$fila) return null;

        $fila['detalle'] = $this->detalle($id);
        return $this->normalizar($fila);
    }

    public function detalle(int $idPedido): array
    {
        $st = $this->conn->prepare(
            "SELECT d.*, p.nombre, p.codigo,
                    (SELECT pi.archivo FROM productos_imagenes pi
                      WHERE pi.id_producto = p.id_producto AND pi.principal = 1 LIMIT 1) AS imagen
               FROM pedido_detalle d
               INNER JOIN productos p ON p.id_producto = d.id_producto
              WHERE d.id_pedido = :id
              ORDER BY d.id_detalle ASC"
        );
        $st->execute([':id' => $idPedido]);
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($d) {
            $img = (string) ($d['imagen'] ?? '');
            $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
            return [
                'id_detalle'     => (int) $d['id_detalle'],
                'id_producto'    => (int) $d['id_producto'],
                'codigo'         => (string) $d['codigo'],
                'producto'       => (string) $d['nombre'],
                'cantidad'       => (float) $d['cantidad'],
                'precio_unitario' => (float) $d['precio_unitario'],
                'descuento'      => (float) $d['descuento'],
                'impuesto'       => (float) $d['impuesto'],
                'subtotal'       => (float) $d['subtotal'],
                'total'          => (float) $d['total'],
                'promocion'      => (string) ($d['promocion'] ?? ''),
                'observaciones'  => (string) ($d['observaciones'] ?? ''),
                'control_stock'  => isset($d['control_stock']) && $d['control_stock'] !== null
                    ? (int) $d['control_stock']
                    : null,
                'despachado'     => (int) $d['despachado'],
                'enviado_cocina' => (int) $d['enviado_cocina'],
                'imagen_url'     => $img !== ''
                    ? ($base !== '' ? $base . '/' . ltrim($img, '/') : '/' . ltrim($img, '/'))
                    : '',
            ];
        }, $filas);
    }

    // =====================================================
    // ESCRITURA
    // =====================================================

    /** Abre un pedido para una mesa o tipo (llevar/delivery). */
    public function abrir(array $datos, int $idUsuario, int $idSucursal): array
    {
        $tipo = strtolower(trim((string) ($datos['tipo_pedido'] ?? 'mesa')));
        if (!in_array($tipo, ['mesa', 'llevar', 'delivery'], true)) {
            return self::err('Tipo de pedido inválido');
        }

        $idMesa = (int) ($datos['id_mesa'] ?? 0);
        $delivery = trim(strip_tags((string) ($datos['delivery_nombre'] ?? '')));

        if ($tipo === 'mesa') {
            if ($idMesa <= 0) return self::err('Selecciona la mesa');
            $st = $this->conn->prepare(
                "SELECT estado_mesa FROM mesas WHERE id_mesa = :id AND id_sucursal = :s LIMIT 1"
            );
            $st->execute([':id' => $idMesa, ':s' => $idSucursal]);
            $estado = $st->fetchColumn();
            if ($estado === false) return self::err('Mesa no encontrada');
            if ($estado !== 'libre') return self::err('Esa mesa no está libre');
        }

        if ($tipo === 'delivery' && $delivery === '') {
            return self::err('Ingresa el nombre del delivery');
        }

        try {
            $this->conn->beginTransaction();

            // Bloquear la mesa para que dos usuarios no abran dos pedidos
            // sobre la misma mesa al mismo tiempo.
            if ($tipo === 'mesa' && $idMesa > 0) {
                $lock = $this->conn->prepare(
                    "SELECT estado_mesa FROM mesas
                      WHERE id_mesa = :id AND id_sucursal = :s LIMIT 1 FOR UPDATE"
                );
                $lock->execute([':id' => $idMesa, ':s' => $idSucursal]);
                $estadoMesa = $lock->fetchColumn();

                if ($estadoMesa === false) {
                    $this->conn->rollBack();
                    return self::err('Mesa no encontrada');
                }

                if ($estadoMesa !== 'libre') {
                    $this->conn->rollBack();
                    return self::err('Esa mesa no está libre');
                }

                $chk = $this->conn->prepare(
                    "SELECT 1 FROM pedidos
                      WHERE id_mesa = :m
                        AND estado IN ('pendiente','preparando','despachado','pendiente_pago')
                      LIMIT 1"
                );
                $chk->execute([':m' => $idMesa]);

                if ($chk->fetchColumn()) {
                    $this->conn->rollBack();
                    return self::err('Esa mesa ya tiene un pedido abierto. Ciérralo o cancélalo antes.');
                }
            }

            $codigo = $this->codigoPedido();

            $this->conn->prepare(
                "INSERT INTO pedidos
                    (id_sucursal, id_mesa, id_usuario, codigo, tipo_pedido,
                     delivery_nombre, estado, observaciones)
                 VALUES
                    (:s, :m, :u, :c, :t, :d, 'pendiente', :o)"
            )->execute([
                ':s' => $idSucursal,
                ':m' => $idMesa > 0 ? $idMesa : null,
                ':u' => $idUsuario,
                ':c' => $codigo,
                ':t' => $tipo,
                ':d' => $tipo === 'delivery' ? $delivery : null,
                ':o' => trim(strip_tags((string) ($datos['observaciones'] ?? ''))) ?: null,
            ]);

            $idPedido = (int) $this->conn->lastInsertId();

            // La mesa queda ocupada en la misma transacción.
            if ($tipo === 'mesa' && $idMesa > 0) {
                $this->conn->prepare(
                    "UPDATE mesas SET estado_mesa = 'ocupada' WHERE id_mesa = :id"
                )->execute([':id' => $idMesa]);
            }

            $this->conn->commit();
            return ['exito' => true, 'mensaje' => 'Pedido abierto', 'id' => $idPedido];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Pedido::abrir - ' . $e->getMessage());
            return self::err('No se pudo abrir el pedido');
        }
    }

    /** Agrega líneas a un pedido existente y recalcula totales. */
    public function agregarItems(int $idPedido, array $items, int $idUsuario, int $idSucursal): array
    {
        $pedido = $this->obtener($idPedido);
        if (!$pedido) return self::err('Pedido no encontrado');
        if (!in_array($pedido['estado'], ['pendiente', 'preparando', 'despachado'], true)) {
            return self::err('Ese pedido ya no admite más productos');
        }

        if (!is_array($items) || $items === []) return self::err('Sin productos');

        $promociones = (new Promocion($this->conn))->vigentes();
        // Migración sql_pedidos_stock.sql: si aún no se aplicó, el pedido
        // conserva el comportamiento anterior (stock se descuenta al cobrar).
        $conSnapshot    = $this->columnaExiste('pedido_detalle', 'control_stock');
        $conDescontado  = $this->columnaExiste('pedidos', 'stock_descontado');
        $descontarStock = $conSnapshot && $conDescontado;
        $lineas = [];

        foreach ($items as $it) {
            $idProd = (int) ($it['id_producto'] ?? 0);
            $cant   = round((float) ($it['cantidad'] ?? 0), 3);
            if ($idProd <= 0 || $cant <= 0) return self::err('Línea inválida');

            $prod = $this->productoVendible($idProd);
            if (!$prod) return self::err('Producto no disponible');

            $controlaStock = (int) $prod['control_stock'] === 1;
            if ($controlaStock) {
                // El pedido descuenta el stock GLOBAL del producto
                // (productos.stock), igual que el POS.
                $stock = (float) ($prod['stock'] ?? 0);
                if ($stock + 0.0001 < $cant) {
                    return self::err('Stock insuficiente de "' . $prod['nombre'] . '" (disponible: ' . $stock . ')');
                }
            }

            $base = $this->precioSucursal($idSucursal, $idProd, (float) $prod['precio']);
            $prod['oferta_vigente'] = $this->ofertaVigente($prod) ? 1 : 0;
            $calc = PrecioPos::calcular($base, $prod, $promociones);

            $precioU = (float) $calc['precio_unitario'];
            $baseL   = round($precioU * $cant, 2);
            $ivaL    = round($baseL * ((float) $calc['impuesto_porcentaje'] / 100), 2);
            $totalL  = round($baseL + $ivaL, 2);

            $lineas[] = [
                'id_producto'    => $idProd,
                'cantidad'       => $cant,
                'precio_unitario' => $precioU,
                'precio_base'    => (float) $calc['precio_base'],
                'descuento'      => (float) $calc['descuento_unitario'] * $cant,
                'impuesto'       => $ivaL,
                'subtotal'       => $baseL,
                'total'          => $totalL,
                'promocion'      => $calc['promocion'] ?: null,
                'control_stock'  => $controlaStock ? 1 : 0,
                'observaciones'  => trim(strip_tags((string) ($it['observaciones'] ?? ''))) ?: null,
            ];
        }

        try {
            $this->conn->beginTransaction();

            $colsDetalle = "id_pedido, id_producto, cantidad, precio_unitario, precio_base,
                     descuento, impuesto, subtotal, total, promocion, observaciones";
            $valsDetalle = ":pe, :p, :c, :pu, :pb, :d, :i, :s, :t, :pr, :o";

            if ($conSnapshot) {
                $colsDetalle .= ", control_stock";
                $valsDetalle .= ", :cs";
            }

            $ins = $this->conn->prepare(
                "INSERT INTO pedido_detalle ($colsDetalle) VALUES ($valsDetalle)"
            );

            foreach ($lineas as $l) {
                $params = [
                    ':pe' => $idPedido,
                    ':p'  => $l['id_producto'],
                    ':c'  => $l['cantidad'],
                    ':pu' => $l['precio_unitario'],
                    ':pb' => $l['precio_base'],
                    ':d'  => $l['descuento'],
                    ':i'  => $l['impuesto'],
                    ':s'  => $l['subtotal'],
                    ':t'  => $l['total'],
                    ':pr' => $l['promocion'],
                    ':o'  => $l['observaciones'],
                ];

                if ($conSnapshot) {
                    $params[':cs'] = $l['control_stock'];
                }

                $ins->execute($params);
            }

            // Igual que el POS: el stock se descuenta al TOMAR el pedido
            // (modo global sobre productos.stock) y queda marcado para
            // devolverlo si se cancela antes de cobrarse.
            if ($descontarStock) {
                $kardex = new Kardex($this->conn);

                foreach ($lineas as $l) {
                    if ((int) $l['control_stock'] !== 1) {
                        continue;
                    }

                    $mov = $kardex->registrarSalida(
                        $idSucursal,
                        (int) $l['id_producto'],
                        (float) $l['cantidad'],
                        'venta',
                        $idPedido,
                        'Pedido ' . $pedido['codigo'],
                        $idUsuario,
                        false,   // permitirNegativo
                        true     // usarStockGlobal: descuenta productos.stock
                    );

                    if (!$mov['exito']) {
                        throw new RuntimeException($mov['mensaje']);
                    }
                }

                $this->conn->prepare(
                    "UPDATE pedidos SET stock_descontado = 1 WHERE id_pedido = :id"
                )->execute([':id' => $idPedido]);
            }

            $this->recalcular($idPedido);

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Productos agregados',
                'pedido'  => $this->obtener($idPedido),
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Pedido::agregarItems - ' . $e->getMessage());
            return self::err('No se pudieron agregar los productos');
        }
    }

   public function despachar(int $idPedido, int $idUsuario): array
{
    $pedido = $this->obtener($idPedido);
    if (!$pedido) return self::err('Pedido no encontrado');
    if (!in_array($pedido['estado'], ['pendiente', 'preparando'], true)) {
        return self::err('Ese pedido ya fue despachado');
    }

    try {
        $this->conn->beginTransaction();

        $this->conn->prepare(
            "UPDATE pedidos
                SET estado = 'despachado', fecha_despacho = NOW()
              WHERE id_pedido = :id"
        )->execute([':id' => $idPedido]);

        $this->conn->prepare(
            "UPDATE pedido_detalle SET despachado = 1 WHERE id_pedido = :id"
        )->execute([':id' => $idPedido]);

        if (!empty($pedido['id_mesa'])) {
            $this->conn->prepare(
                "UPDATE mesas SET estado_mesa = 'despachado' WHERE id_mesa = :id"
            )->execute([':id' => (int) $pedido['id_mesa']]);
        }

        $this->conn->commit();
        return ['exito' => true, 'mensaje' => 'Pedido despachado'];
    } catch (PDOException $e) {
        if ($this->conn->inTransaction()) $this->conn->rollBack();
        error_log('Pedido::despachar - ' . $e->getMessage());
        return self::err('No se pudo despachar');
    }
}

    /** Envía la mesa a caja: estado = pendiente_pago. */
    public function enviarACaja(int $idPedido): array
    {
        $pedido = $this->obtener($idPedido);
        if (!$pedido) return self::err('Pedido no encontrado');

        if (!in_array($pedido['estado'], ['pendiente', 'preparando', 'despachado'], true)) {
            return self::err('Ese pedido no puede enviarse a caja');
        }

        try {
            $this->conn->beginTransaction();

            $this->conn->prepare(
                "UPDATE pedidos SET estado = 'pendiente_pago' WHERE id_pedido = :id"
            )->execute([':id' => $idPedido]);

            if ($pedido['id_mesa'] > 0) {
                $this->conn->prepare(
                    "UPDATE mesas SET estado_mesa = 'pendiente_pago' WHERE id_mesa = :id"
                )->execute([':id' => $pedido['id_mesa']]);
            }

            $this->conn->commit();

            return ['exito' => true, 'mensaje' => 'Enviado a caja'];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Pedido::enviarACaja - ' . $e->getMessage());
            return self::err('No se pudo enviar a caja');
        }
    }

    /**
     * Cobra el pedido: crea la venta con el POS, la asocia al pedido,
     * marca como pagado y libera la mesa.
     */
    public function cobrar(int $idPedido, array $pago, int $idUsuario, int $idSucursal, int $idArqueo): array
    {
        $pedido = $this->obtener($idPedido);
        if (!$pedido) return self::err('Pedido no encontrado');
        // Se puede cobrar un pedido despachado o ya enviado a caja.
        if (!in_array($pedido['estado'], ['despachado', 'pendiente_pago'], true)) {
            return self::err('El pedido aún no está listo para cobrar');
        }

        // Agrupar por producto: un pedido puede tener el mismo producto en
        // varias tandas y Venta::registrar rechaza productos repetidos.
        $items = [];
        foreach ($pedido['detalle'] as $d) {
            $idProducto = (int) ($d['id_producto'] ?? 0);
            if ($idProducto <= 0) {
                continue;
            }
            if (!isset($items[$idProducto])) {
                $items[$idProducto] = ['id_producto' => $idProducto, 'cantidad' => 0.0];
            }
            $items[$idProducto]['cantidad'] += (float) ($d['cantidad'] ?? 0);
        }
        $items = array_values(array_map(static function (array $it): array {
            $it['cantidad'] = round((float) $it['cantidad'], 3);
            return $it;
        }, $items));

        if ($items === []) {
            return self::err('El pedido no tiene productos para cobrar');
        }

        $ventaDatos = [
            'id_sucursal' => $idSucursal,
            'items'       => $items,
            'metodo_pago' => $pago['metodo_pago'] ?? 'efectivo',
            'recibido'    => $pago['recibido'] ?? 0,
            'pagos'       => $pago['pagos'] ?? [],
            'referencia'  => $pago['referencia_pago'] ?? '',
            'tipo_pedido' => $pedido['tipo_pedido'] ?? 'mesa',
            'delivery_nombre' => $pedido['delivery_nombre'] ?? '',
            'observaciones'   => $pedido['observaciones'] ?? '',
        ];

        $ventaModel = new Venta($this->conn);
        // Si el stock ya se descontó al tomar el pedido, la venta se crea
        // SIN volver a mover inventario (el POS conserva su comportamiento).
        $moverStock = (int) ($pedido['stock_descontado'] ?? 0) !== 1;
        $r = $ventaModel->registrar($ventaDatos, $idUsuario, $idSucursal, $idArqueo, $moverStock);

        if (!$r['exito']) {
            return self::err($r['mensaje']);
        }

        $idVenta = (int) $r['id'];

        try {
            $this->conn->prepare(
                "UPDATE pedidos
                    SET estado = 'pagado', id_venta = :v, fecha_cierre = NOW()
                  WHERE id_pedido = :id"
            )->execute([':v' => $idVenta, ':id' => $idPedido]);

            if (!empty($pedido['id_mesa'])) {
                $this->conn->prepare(
                    "UPDATE mesas SET estado_mesa = 'libre' WHERE id_mesa = :id"
                )->execute([':id' => (int) $pedido['id_mesa']]);
            }

            return [
                'exito'    => true,
                'mensaje'  => 'Pedido cobrado',
                'id_venta' => $idVenta,
                'venta'    => $r['venta'] ?? null,
            ];
        } catch (PDOException $e) {
            error_log('Pedido::cobrar - ' . $e->getMessage());
            return self::err('Venta registrada, pero no se pudo cerrar el pedido');
        }
    }

    /** Cancela pedido (sin cobrar) y devuelve el stock descontado al tomarlo. */
    public function cancelar(int $idPedido, int $idUsuario = 0): array
    {
        $pedido = $this->obtener($idPedido);
        if (!$pedido) return self::err('Pedido no encontrado');

        if (in_array($pedido['estado'], ['pagado', 'cancelado'], true)) {
            return self::err('Ese pedido ya está cerrado');
        }

        $conSnapshot   = $this->columnaExiste('pedido_detalle', 'control_stock');
        $conDescontado = $this->columnaExiste('pedidos', 'stock_descontado');

        try {
            $this->conn->beginTransaction();

            // Bloquear el pedido para no devolver el stock dos veces.
            $colsPedido = 'id_sucursal, codigo, id_mesa, estado'
                . ($conDescontado ? ', stock_descontado' : '');
            $lock = $this->conn->prepare(
                "SELECT $colsPedido FROM pedidos WHERE id_pedido = :id FOR UPDATE"
            );
            $lock->execute([':id' => $idPedido]);
            $fila = $lock->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                $this->conn->rollBack();
                return self::err('Pedido no encontrado');
            }

            if (in_array((string) $fila['estado'], ['pagado', 'cancelado'], true)) {
                $this->conn->rollBack();
                return self::err('Ese pedido ya está cerrado');
            }

            $stockDevuelto = false;

            if ($conDescontado && $conSnapshot && (int) ($fila['stock_descontado'] ?? 0) === 1) {
                $stDet = $this->conn->prepare(
                    "SELECT id_producto, cantidad, control_stock
                       FROM pedido_detalle
                      WHERE id_pedido = :id"
                );
                $stDet->execute([':id' => $idPedido]);
                $lineas = $stDet->fetchAll(PDO::FETCH_ASSOC);

                $kardex = new Kardex($this->conn);
                // Costo con el que salió cada producto al tomar el pedido.
                $costoStmt = $this->conn->prepare(
                    "SELECT COALESCE(
                                (SELECT k.costo_unitario
                                   FROM kardex k
                                  WHERE k.id_sucursal = :s
                                    AND k.id_producto = :p1
                                    AND k.origen = 'venta'
                                    AND k.id_referencia = :r
                                  ORDER BY k.id_movimiento ASC
                                  LIMIT 1),
                                (SELECT p.costo FROM productos p WHERE p.id_producto = :p2),
                                0
                            ) AS costo"
                );

                foreach ($lineas as $linea) {
                    // Sólo se devuelve el stock de las líneas que sí lo
                    // controlaban (snapshot guardado al tomar el pedido).
                    if ((int) ($linea['control_stock'] ?? 0) !== 1) {
                        continue;
                    }

                    $idProducto = (int) $linea['id_producto'];
                    $cantidad   = (float) $linea['cantidad'];

                    if ($idProducto <= 0 || $cantidad <= 0) {
                        continue;
                    }

                    $costoStmt->execute([
                        ':s'  => (int) $fila['id_sucursal'],
                        ':p1' => $idProducto,
                        ':p2' => $idProducto,
                        ':r'  => $idPedido,
                    ]);
                    $costo = max(0.0, (float) $costoStmt->fetchColumn());

                    $mov = $kardex->registrarEntrada(
                        (int) $fila['id_sucursal'],
                        $idProducto,
                        $cantidad,
                        $costo,
                        0.0,
                        'ajuste',
                        $idPedido,
                        'Cancelación pedido ' . $fila['codigo'],
                        $idUsuario
                    );

                    if (!$mov['exito']) {
                        throw new RuntimeException($mov['mensaje']);
                    }

                    $stockDevuelto = true;
                }

                $this->conn->prepare(
                    "UPDATE pedidos SET stock_descontado = 0 WHERE id_pedido = :id"
                )->execute([':id' => $idPedido]);
            }

            $this->conn->prepare(
                "UPDATE pedidos SET estado = 'cancelado', fecha_cierre = NOW() WHERE id_pedido = :id"
            )->execute([':id' => $idPedido]);

            if (!empty($fila['id_mesa'])) {
                $this->conn->prepare(
                    "UPDATE mesas SET estado_mesa = 'libre' WHERE id_mesa = :id"
                )->execute([':id' => (int) $fila['id_mesa']]);
            }

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => $stockDevuelto
                    ? 'Pedido cancelado: se devolvió el stock al inventario'
                    : 'Pedido cancelado',
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            error_log('Pedido::cancelar - ' . $e->getMessage());
            return self::err('No se pudo cancelar');
        }
    }

    // =====================================================
    // AUXILIARES
    // =====================================================

    private function recalcular(int $idPedido): void
    {
        $st = $this->conn->prepare(
            "SELECT COALESCE(SUM(subtotal),0) AS sub,
                    COALESCE(SUM(descuento),0) AS descu,
                    COALESCE(SUM(impuesto),0) AS imp,
                    COALESCE(SUM(total),0) AS tot
               FROM pedido_detalle WHERE id_pedido = :id"
        );
        $st->execute([':id' => $idPedido]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->conn->prepare(
            "UPDATE pedidos SET subtotal = :s, descuento = :d, impuesto = :i, total = :t WHERE id_pedido = :id"
        )->execute([
            ':s' => (float) ($r['sub'] ?? 0),
            ':d' => (float) ($r['descu'] ?? 0),
            ':i' => (float) ($r['imp'] ?? 0),
            ':t' => (float) ($r['tot'] ?? 0),
            ':id' => $idPedido,
        ]);
    }

    private function normalizar(array $p): array
    {
        $nombre = trim((string) ($p['usuario_nombre'] ?? ''));
        return [
            'id_pedido'     => (int) $p['id_pedido'],
            'codigo'        => (string) $p['codigo'],
            'id_sucursal'   => (int) $p['id_sucursal'],
            'id_mesa'       => $p['id_mesa'] ? (int) $p['id_mesa'] : 0,
            'mesa'          => (string) ($p['mesa_nombre'] ?? ''),
            'mesa_codigo'   => (string) ($p['mesa_codigo'] ?? ''),
            'usuario'       => $nombre !== '' ? $nombre : (string) ($p['username'] ?? ''),
            'tipo_pedido'   => (string) $p['tipo_pedido'],
            'delivery_nombre' => (string) ($p['delivery_nombre'] ?? ''),
            'estado'        => (string) $p['estado'],
            'subtotal'      => (float) $p['subtotal'],
            'descuento'     => (float) $p['descuento'],
            'impuesto'      => (float) $p['impuesto'],
            'total'         => (float) $p['total'],
            'observaciones' => (string) ($p['observaciones'] ?? ''),
            'stock_descontado' => (int) ($p['stock_descontado'] ?? 0),
            'fecha_apertura' => date('H:i', strtotime($p['fecha_apertura'])),
            'fecha_iso'     => (string) $p['fecha_apertura'],
            'detalle'       => $p['detalle'] ?? [],
        ];
    }

    private function productoVendible(int $id): ?array
    {
        $st = $this->conn->prepare(
            "SELECT id_producto, id_categoria, nombre, precio,
                    descuento_porcentaje, precio_oferta, oferta_activa,
                    oferta_desde, oferta_hasta, impuesto_porcentaje, control_stock, stock
               FROM productos
              WHERE id_producto = :id AND estado = 1 AND disponible = 1 LIMIT 1"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function ofertaVigente(array $p): bool
    {
        if ((int) ($p['oferta_activa'] ?? 0) !== 1) return false;
        $ahora = time();
        if (!empty($p['oferta_desde']) && strtotime((string) $p['oferta_desde']) > $ahora) return false;
        if (!empty($p['oferta_hasta']) && strtotime((string) $p['oferta_hasta']) < $ahora) return false;
        return true;
    }

    /** ¿La tabla tiene la columna? (convive con la migración pendiente). */
    private function columnaExiste(string $tabla, string $columna): bool
    {
        if (self::$columnasCache === null) {
            self::$columnasCache = [];

            try {
                $st = $this->conn->prepare(
                    "SELECT TABLE_NAME, COLUMN_NAME
                       FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME IN ('pedidos', 'pedido_detalle')"
                );
                $st->execute();

                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                    $t = strtolower((string) $fila['TABLE_NAME']);
                    self::$columnasCache[$t][] = strtolower((string) $fila['COLUMN_NAME']);
                }
            } catch (PDOException $e) {
                error_log('Pedido::columnaExiste - ' . $e->getMessage());
            }
        }

        return in_array(strtolower($columna), self::$columnasCache[strtolower($tabla)] ?? [], true);
    }

    private function precioSucursal(int $idS, int $idP, float $fallback): float
    {
        $st = $this->conn->prepare(
            "SELECT COALESCE(NULLIF(precio_venta,0), :f)
               FROM productos_sucursal
              WHERE id_sucursal = :s AND id_producto = :p LIMIT 1"
        );
        $st->execute([':s' => $idS, ':p' => $idP, ':f' => $fallback]);
        $v = $st->fetchColumn();
        return $v === false ? $fallback : (float) $v;
    }

    private function codigoPedido(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $codigo = 'P-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $st = $this->conn->prepare("SELECT 1 FROM pedidos WHERE codigo = :c LIMIT 1");
            $st->execute([':c' => $codigo]);
            if (!$st->fetchColumn()) return $codigo;
        }
        return 'P-' . time();
    }

    private static function err(string $m): array
    {
        return ['exito' => false, 'mensaje' => $m, 'id' => 0];
    }
}
