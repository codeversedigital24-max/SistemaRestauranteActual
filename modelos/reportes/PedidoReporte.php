<?php
/**
 * =====================================================
 *  REPORTE DE PEDIDOS POR FECHAS  ·  clase PedidoReporte
 * -----------------------------------------------------
 *  Consulta los pedidos (mesas y online: llevar/delivery)
 *  con filtros de sucursal, tipo, estado y rango de fechas.
 *
 *  Se usa desde:
 *    · sistema/pedidosxfechas.php          (pantalla)
 *    · modelos/reportes/obtener_pedidos_reporte.php (JSON)
 *    · modelos/reportes/reporte_pedidos_pdf.php     (PDF)
 * =====================================================
 */

class PedidoReporte
{
    private PDO $conn;

    public function __construct(PDO $conexion)
    {
        $this->conn = $conexion;
    }

    // =================================================
    // SUCURSALES A LAS QUE EL USUARIO TIENE ALCANCE
    // =================================================
    public function sucursalesDisponibles(array $usuario): array
    {
        try {
            if ($usuario['es_superadmin'] ?? false) {
                return $this->conn->query(
                    "SELECT id_sucursal, nombre_sucursal, ciudad
                       FROM sucursales
                      WHERE estado = 1
                      ORDER BY nombre_sucursal"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            $st = $this->conn->prepare(
                "SELECT id_sucursal, nombre_sucursal, ciudad
                   FROM sucursales
                  WHERE id_sucursal = :id AND estado = 1"
            );
            $st->execute([':id' => (int) ($usuario['id_sucursal'] ?? 0)]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('PedidoReporte::sucursalesDisponibles - ' . $e->getMessage());

            return [];
        }
    }

    // =================================================
    // LISTADO DE PEDIDOS
    // =================================================
    public function listar(array $filtros, int $limite = 5000): array
    {
        [$where, $params] = $this->condiciones($filtros);

        $sql = "SELECT pe.id_pedido, pe.codigo, pe.tipo_pedido, pe.estado,
                       pe.delivery_nombre, pe.subtotal, pe.descuento,
                       pe.impuesto, pe.total, pe.fecha_apertura,
                       pe.fecha_cierre, pe.id_venta,
                       m.codigo AS mesa_codigo, m.nombre AS mesa_nombre,
                       s.nombre_sucursal AS sucursal, s.ciudad,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS nombre_usuario,
                       (SELECT COUNT(*) FROM pedido_detalle pd
                         WHERE pd.id_pedido = pe.id_pedido) AS items
                  FROM pedidos pe
                  INNER JOIN sucursales s      ON s.id_sucursal = pe.id_sucursal
                  LEFT  JOIN mesas m           ON m.id_mesa = pe.id_mesa
                  LEFT  JOIN usuarios u        ON u.id_usuario = pe.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario"
            . $where
            . " ORDER BY pe.fecha_apertura DESC, pe.id_pedido DESC
                LIMIT " . max(1, min(10000, $limite));

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('PedidoReporte::listar - ' . $e->getMessage());

            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    // =================================================
    // RESUMEN DEL RANGO
    // =================================================
    public function resumen(array $filtros): array
    {
        [$where, $params] = $this->condiciones($filtros);

        $sql = "SELECT COUNT(*) AS pedidos,
                       SUM(CASE WHEN pe.tipo_pedido = 'mesa' THEN 1 ELSE 0 END) AS mesas,
                       SUM(CASE WHEN pe.tipo_pedido IN ('llevar','delivery') THEN 1 ELSE 0 END) AS online,
                       SUM(CASE WHEN pe.estado = 'cancelado' THEN 1 ELSE 0 END) AS cancelados,
                       SUM(CASE WHEN pe.estado <> 'cancelado' THEN pe.total ELSE 0 END) AS total,
                       AVG(CASE WHEN pe.estado <> 'cancelado' THEN pe.total END) AS promedio
                  FROM pedidos pe" . $where;

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('PedidoReporte::resumen - ' . $e->getMessage());
            $r = [];
        }

        $pedidos = (int) ($r['pedidos'] ?? 0);
        $cancelados = (int) ($r['cancelados'] ?? 0);

        return [
            'pedidos'     => $pedidos,
            'mesas'       => (int) ($r['mesas'] ?? 0),
            'online'      => (int) ($r['online'] ?? 0),
            'cancelados'  => $cancelados,
            'vigentes'    => max(0, $pedidos - $cancelados),
            'total'       => round((float) ($r['total'] ?? 0), 2),
            'promedio'    => round((float) ($r['promedio'] ?? 0), 2),
        ];
    }

    // =================================================
    // PEDIDOS POR DÍA (para la tabla/gráfica)
    // =================================================
    public function porDia(array $filtros, int $limite = 62): array
    {
        [$where, $params] = $this->condiciones($filtros);

        $sql = "SELECT DATE(pe.fecha_apertura) AS fecha,
                       COUNT(*) AS pedidos,
                       SUM(CASE WHEN pe.estado <> 'cancelado' THEN pe.total ELSE 0 END) AS total
                  FROM pedidos pe" . $where . "
                 GROUP BY DATE(pe.fecha_apertura)
                 ORDER BY fecha ASC
                 LIMIT " . max(1, min(400, $limite));

        try {
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('PedidoReporte::porDia - ' . $e->getMessage());

            return [];
        }

        return array_map(static fn(array $f): array => [
            'fecha'   => (string) $f['fecha'],
            'pedidos' => (int) $f['pedidos'],
            'total'   => round((float) $f['total'], 2),
        ], $filas);
    }

    // =================================================
    // AUXILIARES
    // =================================================
    private function condiciones(array $filtros): array
    {
        $where  = [];
        $params = [];

        $idSucursal = (int) ($filtros['id_sucursal'] ?? 0);

        if ($idSucursal > 0) {
            $where[] = 'pe.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        $desde = (string) ($filtros['desde'] ?? '');
        $hasta = (string) ($filtros['hasta'] ?? '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $where[] = 'DATE(pe.fecha_apertura) >= :desde';
            $params[':desde'] = $desde;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $where[] = 'DATE(pe.fecha_apertura) <= :hasta';
            $params[':hasta'] = $hasta;
        }

        $tipo = strtolower(trim((string) ($filtros['tipo'] ?? '')));

        if ($tipo === 'mesa') {
            $where[] = "pe.tipo_pedido = 'mesa'";
        } elseif ($tipo === 'online') {
            $where[] = "pe.tipo_pedido IN ('llevar','delivery')";
        } elseif (in_array($tipo, ['llevar', 'delivery'], true)) {
            $where[] = 'pe.tipo_pedido = :tipo';
            $params[':tipo'] = $tipo;
        }

        $estado = strtolower(trim((string) ($filtros['estado'] ?? '')));

        if (in_array($estado, ['pendiente', 'preparando', 'despachado', 'pendiente_pago', 'pagado', 'cancelado'], true)) {
            $where[] = 'pe.estado = :estado';
            $params[':estado'] = $estado;
        }

        $idUsuario = (int) ($filtros['id_usuario'] ?? 0);

        if ($idUsuario > 0) {
            $where[] = 'pe.id_usuario = :usuario';
            $params[':usuario'] = $idUsuario;
        }

        return [
            $where === [] ? '' : ' WHERE ' . implode(' AND ', $where),
            $params,
        ];
    }

    private function normalizar(array $f): array
    {
        $tipos = [
            'mesa'     => 'Mesa',
            'llevar'   => 'Para llevar',
            'delivery' => 'Delivery',
        ];

        $estados = [
            'pendiente'      => 'Pendiente',
            'preparando'     => 'Preparando',
            'despachado'     => 'Despachado',
            'pendiente_pago' => 'Pendiente de pago',
            'pagado'         => 'Pagado',
            'cancelado'      => 'Cancelado',
        ];

        $tipo = (string) ($f['tipo_pedido'] ?? 'mesa');
        $estado = (string) ($f['estado'] ?? 'pendiente');
        $nombre = trim((string) ($f['nombre_usuario'] ?? ''));

        $mesa = trim((string) ($f['mesa_nombre'] ?? ''));
        $mesaCodigo = trim((string) ($f['mesa_codigo'] ?? ''));
        $delivery = trim((string) ($f['delivery_nombre'] ?? ''));

        if ($tipo === 'mesa') {
            $cliente = $mesa !== '' ? $mesa : ($mesaCodigo !== '' ? $mesaCodigo : 'Mesa');
        } elseif ($tipo === 'delivery') {
            $cliente = $delivery !== '' ? $delivery : 'Delivery';
        } else {
            $cliente = $delivery !== '' ? $delivery : 'Para llevar';
        }

        return [
            'id'             => (int) $f['id_pedido'],
            'codigo'         => (string) ($f['codigo'] ?? ''),
            'fecha'          => !empty($f['fecha_apertura'])
                ? date('d/m/Y H:i', strtotime((string) $f['fecha_apertura']))
                : '—',
            'fecha_iso'      => (string) ($f['fecha_apertura'] ?? ''),
            'fecha_cierre'   => !empty($f['fecha_cierre'])
                ? date('d/m/Y H:i', strtotime((string) $f['fecha_cierre']))
                : null,
            'tipo_pedido'    => $tipo,
            'tipo_label'     => $tipos[$tipo] ?? ucfirst($tipo),
            'grupo_tipo'     => $tipo === 'mesa' ? 'mesa' : 'online',
            'cliente'        => $cliente,
            'mesa'           => $mesa !== '' ? $mesa : $mesaCodigo,
            'delivery_nombre'=> $delivery,
            'estado'         => $estado,
            'estado_label'   => $estados[$estado] ?? ucfirst(str_replace('_', ' ', $estado)),
            'items'          => (int) ($f['items'] ?? 0),
            'subtotal'       => round((float) ($f['subtotal'] ?? 0), 2),
            'descuento'      => round((float) ($f['descuento'] ?? 0), 2),
            'impuesto'       => round((float) ($f['impuesto'] ?? 0), 2),
            'total'          => round((float) ($f['total'] ?? 0), 2),
            'id_venta'       => $f['id_venta'] !== null ? (int) $f['id_venta'] : null,
            'sucursal'       => (string) ($f['sucursal'] ?? ''),
            'ciudad'         => (string) ($f['ciudad'] ?? ''),
            'usuario'        => $nombre !== '' ? $nombre : (string) ($f['username'] ?? ''),
        ];
    }
}
