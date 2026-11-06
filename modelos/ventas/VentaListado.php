<?php
/**
 * =====================================================
 * MODELO: VentaListado  (modelos/ventas/VentaListado.php)
 * -----------------------------------------------------
 * Consultas de lectura para el historial de ventas.
 * No modifica nada: sólo arma listados, resumen y
 * catálogos (sucursales, vendedores, detalle).
 * =====================================================
 */

class VentaListado
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    public function listar(array $f): array
    {
        $where = [];
        $params = [];

        // Alcance por sucursal: null/ausente = todas (SuperAdministrador);
        // 0 = usuario sin sucursal: no se devuelve nada.
        $idSucursalFiltro = array_key_exists('id_sucursal', $f) ? $f['id_sucursal'] : null;

        if ($idSucursalFiltro !== null) {
            if ((int) $idSucursalFiltro <= 0) {
                return [];
            }

            $where[] = 'v.id_sucursal = :sucursal';
            $params[':sucursal'] = (int) $idSucursalFiltro;
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
        if (in_array($f['metodo_pago'] ?? '', ['efectivo','qr','tarjeta','mixto','otro'], true)) {
            $where[] = 'v.metodo_pago = :metodo';
            $params[':metodo'] = $f['metodo_pago'];
        }
        if (in_array($f['estado'] ?? '', ['registrada','anulada'], true)) {
            $where[] = 'v.estado = :estado';
            $params[':estado'] = $f['estado'];
        }

        $buscar = trim((string) ($f['buscar'] ?? ''));
        if ($buscar !== '') {
            if (mb_strlen($buscar) > 80) $buscar = mb_substr($buscar, 0, 80);
            $where[] = '(v.codigo LIKE :buscar OR u.username LIKE :buscar2)';
            $params[':buscar']  = '%' . $buscar . '%';
            $params[':buscar2'] = '%' . $buscar . '%';
        }

        $sql = "SELECT v.id_venta, v.codigo, v.id_sucursal, v.id_arqueo, v.id_usuario,
                       v.fecha_venta, v.subtotal, v.descuento, v.impuesto, v.total,
                       v.metodo_pago, v.recibido, v.cambio, v.estado,
                       s.nombre_sucursal, s.ciudad,
                       u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS usuario_nombre,
                       (SELECT COUNT(*) FROM venta_detalle d WHERE d.id_venta = v.id_venta) AS items
                  FROM ventas v
                  INNER JOIN sucursales s ON s.id_sucursal = v.id_sucursal
                  INNER JOIN usuarios u   ON u.id_usuario  = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";

        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY v.fecha_venta DESC, v.id_venta DESC LIMIT 500';

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaListado::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(function (array $r) {
            $nombre = trim((string) $r['usuario_nombre']);
            return [
                'id'          => (int) $r['id_venta'],
                'codigo'      => (string) $r['codigo'],
                'id_sucursal' => (int) $r['id_sucursal'],
                'id_arqueo'   => (int) $r['id_arqueo'],
                'id_usuario'  => (int) $r['id_usuario'],
                'sucursal'    => (string) $r['nombre_sucursal'],
                'ciudad'      => (string) ($r['ciudad'] ?? ''),
                'usuario'     => $nombre !== '' ? $nombre : (string) $r['username'],
                'username'    => (string) $r['username'],
                'fecha_venta' => date('d/m/Y H:i', strtotime($r['fecha_venta'])),
                'fecha_iso'   => (string) $r['fecha_venta'],
                'subtotal'    => (float) $r['subtotal'],
                'descuento'   => (float) $r['descuento'],
                'impuesto'    => (float) $r['impuesto'],
                'total'       => (float) $r['total'],
                'metodo_pago' => (string) $r['metodo_pago'],
                'recibido'    => (float) $r['recibido'],
                'cambio'      => (float) $r['cambio'],
                'estado'      => (string) $r['estado'],
                'items'       => (int) $r['items'],
            ];
        }, $filas);
    }

    public function detalle(int $idVenta): array
    {
        if ($idVenta <= 0) return [];

        $sql = "SELECT d.*, p.codigo, p.nombre
                  FROM venta_detalle d
                  INNER JOIN productos p ON p.id_producto = d.id_producto
                 WHERE d.id_venta = :id
                 ORDER BY d.id_detalle ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $idVenta]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaListado::detalle - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $d) => [
            'id_producto'     => (int) $d['id_producto'],
            'codigo'          => (string) $d['codigo'],
            'producto'        => (string) $d['nombre'],
            'cantidad'        => (float) $d['cantidad'],
            'precio_unitario' => (float) $d['precio_unitario'],
            'subtotal'        => (float) $d['subtotal'],
        ], $filas);
    }

    public function vendedores(?int $idSucursal): array
    {
        // Usuario sin sucursal: no hay vendedores que mostrar.
        if ($idSucursal !== null && $idSucursal <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT u.id_usuario, u.username,
                       TRIM(CONCAT(COALESCE(ud.nombres,''),' ',COALESCE(ud.apellidos,''))) AS nombre
                  FROM ventas v
                  INNER JOIN usuarios u ON u.id_usuario = v.id_usuario
                  LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario";
        $params = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $sql .= " WHERE v.id_sucursal = :sucursal";
            $params[':sucursal'] = $idSucursal;
        }
        $sql .= " ORDER BY nombre ASC, u.username ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('VentaListado::vendedores - ' . $e->getMessage());
            return [];
        }

        return array_map(function ($r) {
            $n = trim((string) $r['nombre']);
            return [
                'id'       => (int) $r['id_usuario'],
                'username' => (string) $r['username'],
                'nombre'   => $n !== '' ? $n : (string) $r['username'],
            ];
        }, $filas);
    }

    public function resumen(array $ventas): array
    {
        $r = [
            'ventas'   => count($ventas),
            'total'    => 0.0,
            'efectivo' => 0.0,
            'qr'       => 0.0,
            'tarjeta'  => 0.0,
            'mixto'    => 0.0,
            'otros'    => 0.0,
            'anuladas' => 0,
        ];

        foreach ($ventas as $v) {
            if ($v['estado'] !== 'registrada') { $r['anuladas']++; continue; }
            $r['total'] += $v['total'];
            switch ($v['metodo_pago']) {
                case 'efectivo': $r['efectivo'] += $v['total']; break;
                case 'qr':       $r['qr']       += $v['total']; break;
                case 'tarjeta':  $r['tarjeta']  += $v['total']; break;
                case 'mixto':    $r['mixto']    += $v['total']; break;
                default:         $r['otros']    += $v['total']; break;
            }
        }

        return $r;
    }
}