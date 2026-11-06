<?php
/**
 * =====================================================
 * MODELO: CuentaPagar  (modelos/compras/CuentaPagar.php)
 * -----------------------------------------------------
 * Cuentas por pagar de compras al crédito.
 *
 * Reglas:
 *   · saldo = total − abono inicial − pagos registrados.
 *   · Sólo compras 'registrada' y forma_pago 'credito'.
 *   · SuperAdministrador ve todas las sucursales;
 *     el resto, sólo la suya.
 *   · Los abonos se guardan en compras_pagos.
 * =====================================================
 */

require_once __DIR__ . '/Compra.php';

class CuentaPagar
{
    private PDO $conn;

    private const METODOS = ['efectivo', 'transferencia', 'qr', 'otro'];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  CONSULTAS
    // =================================================

    public function pendientes(?int $idSucursal, int $idProveedor = 0, string $buscar = ''): array
    {
        $where = ["c.estado = 'registrada'", "c.forma_pago = 'credito'"];
        $params = [];

        if ($idSucursal !== null && $idSucursal > 0) {
            $where[] = 'c.id_sucursal = :sucursal';
            $params[':sucursal'] = $idSucursal;
        }

        if ($idProveedor > 0) {
            $where[] = 'c.id_proveedor = :proveedor';
            $params[':proveedor'] = $idProveedor;
        }

        $buscar = trim($buscar);

        if ($buscar !== '') {
            $where[] = '(pr.nombre LIKE :buscar OR pr.nit LIKE :buscar2 OR c.numero_documento LIKE :buscar3)';
            $params[':buscar'] = '%' . $buscar . '%';
            $params[':buscar2'] = '%' . $buscar . '%';
            $params[':buscar3'] = '%' . $buscar . '%';
        }

        $sql = "SELECT c.id_compra, c.id_sucursal, c.id_proveedor, c.fecha_compra,
                       c.tipo_documento, c.numero_documento, c.total, c.abono, c.created_at,
                       s.nombre_sucursal, s.ciudad,
                       pr.nombre AS proveedor_nombre, pr.nit AS proveedor_nit,
                       pr.telefono AS proveedor_telefono,
                       COALESCE(SUM(pg.monto), 0) AS pagado,
                       (SELECT COUNT(*) FROM compra_detalle d WHERE d.id_compra = c.id_compra) AS items,
                       GREATEST(0, c.total - c.abono - COALESCE(SUM(pg.monto), 0)) AS saldo
                FROM compras c
                INNER JOIN sucursales s    ON s.id_sucursal = c.id_sucursal
                INNER JOIN proveedores pr  ON pr.id_proveedor = c.id_proveedor
                LEFT  JOIN compras_pagos pg ON pg.id_compra = c.id_compra
                WHERE " . implode(' AND ', $where) . "
                GROUP BY c.id_compra
                HAVING saldo > 0.005
                ORDER BY c.fecha_compra ASC, c.id_compra ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CuentaPagar::pendientes - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    /**
     * Resumen + agrupado por proveedor para la página.
     */
    public function resumen(?int $idSucursal, int $idProveedor = 0, string $buscar = ''): array
    {
        $filas = $this->pendientes($idSucursal, $idProveedor, $buscar);

        $total = 0.0;
        $proveedores = [];
        $sucursales = [];

        foreach ($filas as $f) {
            $total += (float) $f['saldo'];
            $clave = (int) $f['id_proveedor'];

            if (!isset($proveedores[$clave])) {
                $proveedores[$clave] = [
                    'id'        => $clave,
                    'nombre'    => $f['proveedor'],
                    'nit'       => $f['proveedor_nit'],
                    'compras'   => 0,
                    'saldo'     => 0.0,
                    'sucursales'=> [],
                ];
            }

            $proveedores[$clave]['compras']++;
            $proveedores[$clave]['saldo'] += (float) $f['saldo'];

            $suc = (int) $f['id_sucursal'];
            $sucursales[$suc] = $f['sucursal'];
            $proveedores[$clave]['sucursales'][$suc] = $f['sucursal'];
        }

        foreach ($proveedores as &$p) {
            $p['saldo'] = round($p['saldo'], 2);
            $p['sucursales'] = array_values($p['sucursales']);
        }
        unset($p);

        usort($proveedores, static fn(array $a, array $b): int => $b['saldo'] <=> $a['saldo']);

        return [
            'total'        => round($total, 2),
            'compras'      => count($filas),
            'proveedores'  => count($proveedores),
            'sucursales'   => count($sucursales),
            'por_proveedor'=> $proveedores,
        ];
    }

    public function pagos(int $idCompra): array
    {
        return (new Compra($this->conn))->pagos($idCompra);
    }

    // =================================================
    //  ABONOS
    // =================================================

    /**
     * Registra un abono sobre una compra concreta.
     */
    public function registrarPago(array $datos, array $usuario): array
    {
        $idCompra = (int) ($datos['id_compra'] ?? 0);
        $monto = $this->aMonto($datos['monto'] ?? 0);

        if ($idCompra <= 0) {
            return self::err('Selecciona la compra a la que se abonará');
        }

        if ($monto <= 0) {
            return self::err('El monto del abono debe ser mayor que cero');
        }

        $metodo = $this->metodo($datos['metodo_pago'] ?? 'efectivo');
        $numeroGuia = $this->numeroGuia($datos['numero_guia'] ?? '');

        if ($numeroGuia === '') {
            return self::err('Ingresa el N° de guía del recibo emitido por el proveedor');
        }

        $referencia = trim(strip_tags((string) ($datos['referencia'] ?? '')));
        $observaciones = trim(strip_tags((string) ($datos['observaciones'] ?? '')));
        $fecha = $this->aFecha($datos['fecha_pago'] ?? '');

        if ($fecha === null) {
            return self::err('La fecha del pago no es válida');
        }

        if (mb_strlen($numeroGuia) > 100) {
            return self::err('El N° de guía no puede superar los 100 caracteres');
        }

        if (mb_strlen($referencia) > 100) {
            return self::err('La referencia no puede superar los 100 caracteres');
        }

        if (mb_strlen($observaciones) > 255) {
            return self::err('Las observaciones no pueden superar los 255 caracteres');
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "SELECT * FROM compras WHERE id_compra = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $idCompra]);
            $compra = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$compra) {
                throw new RuntimeException('Compra no encontrada');
            }

            if ((string) $compra['estado'] !== 'registrada') {
                throw new RuntimeException('La compra está anulada');
            }

            if ((string) $compra['forma_pago'] !== 'credito') {
                throw new RuntimeException('Sólo las compras al crédito tienen saldo pendiente');
            }

            if (!$this->puedeSucursal($usuario, (int) $compra['id_sucursal'])) {
                throw new RuntimeException('No puedes abonar compras de otra sucursal');
            }

            $pagado = $this->sumaPagos($idCompra);
            $saldo = round((float) $compra['total'] - (float) $compra['abono'] - $pagado, 2);

            if ($saldo <= 0.005) {
                throw new RuntimeException('Esta compra ya está pagada por completo');
            }

            if ($monto > $saldo + 0.005) {
                throw new RuntimeException(
                    'El abono supera el saldo pendiente de '
                    . number_format($saldo, 2, '.', ',')
                );
            }

            $idPago = $this->insertarPago(
                $compra,
                $monto,
                $metodo,
                $numeroGuia,
                $referencia,
                $observaciones,
                $fecha,
                (int) $usuario['id']
            );

            $nuevoSaldo = max(0.0, round($saldo - $monto, 2));

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Abono registrado correctamente',
                'id'      => $idPago,
                'saldo'   => $nuevoSaldo,
                'monto'   => $monto,
                'compras' => [$idCompra],
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

            error_log('CuentaPagar::registrarPago - ' . $e->getMessage());

            return self::err('No se pudo registrar el abono');
        }
    }

    /**
     * Abona un monto global al proveedor de una sucursal.
     * Se distribuye de la compra más antigua a la más nueva.
     */
    public function registrarPagoProveedor(array $datos, array $usuario): array
    {
        $idSucursal = (int) ($datos['id_sucursal'] ?? 0);
        $idProveedor = (int) ($datos['id_proveedor'] ?? 0);
        $monto = $this->aMonto($datos['monto'] ?? 0);

        if ($idSucursal <= 0) {
            return self::err('Selecciona la sucursal');
        }

        if ($idProveedor <= 0) {
            return self::err('Selecciona el proveedor');
        }

        if ($monto <= 0) {
            return self::err('El monto del pago debe ser mayor que cero');
        }

        if (!$this->puedeSucursal($usuario, $idSucursal)) {
            return self::err('No puedes abonar compras de otra sucursal');
        }

        $metodo = $this->metodo($datos['metodo_pago'] ?? 'efectivo');
        $numeroGuia = $this->numeroGuia($datos['numero_guia'] ?? '');

        if ($numeroGuia === '') {
            return self::err('Ingresa el N° de guía del recibo emitido por el proveedor');
        }

        $referencia = trim(strip_tags((string) ($datos['referencia'] ?? '')));
        $observaciones = trim(strip_tags((string) ($datos['observaciones'] ?? '')));
        $fecha = $this->aFecha($datos['fecha_pago'] ?? '');

        if ($fecha === null) {
            return self::err('La fecha del pago no es válida');
        }

        if (mb_strlen($numeroGuia) > 100) {
            return self::err('El N° de guía no puede superar los 100 caracteres');
        }

        if (mb_strlen($referencia) > 100) {
            return self::err('La referencia no puede superar los 100 caracteres');
        }

        if (mb_strlen($observaciones) > 255) {
            return self::err('Las observaciones no pueden superar los 255 caracteres');
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "SELECT c.*,
                        GREATEST(0, c.total - c.abono - COALESCE(SUM(pg.monto), 0)) AS saldo
                   FROM compras c
                   LEFT JOIN compras_pagos pg ON pg.id_compra = c.id_compra
                  WHERE c.id_sucursal = :sucursal
                    AND c.id_proveedor = :proveedor
                    AND c.estado = 'registrada'
                    AND c.forma_pago = 'credito'
                  GROUP BY c.id_compra
                 HAVING saldo > 0.005
                  ORDER BY c.fecha_compra ASC, c.id_compra ASC
                    FOR UPDATE"
            );
            $stmt->execute([
                ':sucursal'  => $idSucursal,
                ':proveedor' => $idProveedor,
            ]);
            $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($compras === []) {
                throw new RuntimeException('El proveedor no tiene compras pendientes en esta sucursal');
            }

            $totalPendiente = 0.0;

            foreach ($compras as $c) {
                $totalPendiente += (float) $c['saldo'];
            }

            $totalPendiente = round($totalPendiente, 2);

            if ($monto > $totalPendiente + 0.005) {
                throw new RuntimeException(
                    'El pago supera el saldo total del proveedor ('
                    . number_format($totalPendiente, 2, '.', ',') . ')'
                );
            }

            $restante = $monto;
            $aplicados = [];
            $idsCompras = [];

            foreach ($compras as $c) {
                if ($restante <= 0.005) {
                    break;
                }

                $saldoCompra = (float) $c['saldo'];
                $aplicar = round(min($saldoCompra, $restante), 2);

                if ($aplicar <= 0) {
                    continue;
                }

                $this->insertarPago(
                    $c,
                    $aplicar,
                    $metodo,
                    $numeroGuia,
                    $referencia,
                    $observaciones,
                    $fecha,
                    (int) $usuario['id']
                );

                $aplicados[] = [
                    'id_compra' => (int) $c['id_compra'],
                    'monto'     => $aplicar,
                    'saldo'     => round($saldoCompra - $aplicar, 2),
                ];

                $idsCompras[] = (int) $c['id_compra'];
                $restante = round($restante - $aplicar, 2);
            }

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Pago aplicado a ' . count($aplicados) . ' compra(s)',
                'id'      => 0,
                'saldo'   => round(max(0, $totalPendiente - $monto), 2),
                'monto'   => $monto,
                'compras' => $idsCompras,
                'detalle' => $aplicados,
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

            error_log('CuentaPagar::registrarPagoProveedor - ' . $e->getMessage());

            return self::err('No se pudo registrar el pago al proveedor');
        }
    }

    public function eliminarPago(int $idPago, array $usuario): array
    {
        if ($idPago <= 0) {
            return self::err('Pago no válido');
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                "SELECT p.*, c.total, c.abono, c.estado
                   FROM compras_pagos p
                   INNER JOIN compras c ON c.id_compra = p.id_compra
                  WHERE p.id_pago = :id
                    FOR UPDATE"
            );
            $stmt->execute([':id' => $idPago]);
            $pago = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pago) {
                throw new RuntimeException('Pago no encontrado');
            }

            if (!$this->puedeSucursal($usuario, (int) $pago['id_sucursal'])) {
                throw new RuntimeException('No puedes eliminar pagos de otra sucursal');
            }

            if ((string) $pago['estado'] !== 'registrada') {
                throw new RuntimeException('La compra está anulada');
            }

            $this->conn->prepare('DELETE FROM compras_pagos WHERE id_pago = :id')
                ->execute([':id' => $idPago]);

            $idCompra = (int) $pago['id_compra'];
            $pagado = $this->sumaPagos($idCompra);
            $saldo = max(0.0, round((float) $pago['total'] - (float) $pago['abono'] - $pagado, 2));

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Pago eliminado; el saldo se restableció',
                'id'      => $idPago,
                'saldo'   => $saldo,
                'compras' => [$idCompra],
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

            error_log('CuentaPagar::eliminarPago - ' . $e->getMessage());

            return self::err('No se pudo eliminar el pago');
        }
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private function insertarPago(
        array $compra,
        float $monto,
        string $metodo,
        string $numeroGuia,
        string $referencia,
        string $observaciones,
        string $fecha,
        int $idUsuario
    ): int {
        $stmt = $this->conn->prepare(
            "INSERT INTO compras_pagos
                (id_compra, id_sucursal, id_proveedor, id_usuario,
                 monto, metodo_pago, numero_guia, referencia, observaciones, fecha_pago)
             VALUES
                (:compra, :sucursal, :proveedor, :usuario,
                 :monto, :metodo, :guia, :referencia, :observaciones, :fecha)"
        );

        $stmt->execute([
            ':compra'        => (int) $compra['id_compra'],
            ':sucursal'      => (int) $compra['id_sucursal'],
            ':proveedor'     => (int) $compra['id_proveedor'],
            ':usuario'       => $idUsuario,
            ':monto'         => round($monto, 2),
            ':metodo'        => $metodo,
            ':guia'          => $numeroGuia,
            ':referencia'    => $referencia !== '' ? $referencia : null,
            ':observaciones' => $observaciones !== '' ? $observaciones : null,
            ':fecha'         => $fecha,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function sumaPagos(int $idCompra): float
    {
        $stmt = $this->conn->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM compras_pagos WHERE id_compra = :id'
        );
        $stmt->execute([':id' => $idCompra]);

        return round((float) $stmt->fetchColumn(), 2);
    }

    private function puedeSucursal(array $usuario, int $idSucursal): bool
    {
        if (Permisos::instancia()->esSuperAdmin((int) ($usuario['id'] ?? 0))) {
            return true;
        }

        return (int) ($usuario['id_sucursal'] ?? 0) === $idSucursal;
    }

    private function metodo($valor): string
    {
        $valor = strtolower(trim((string) $valor));

        return in_array($valor, self::METODOS, true) ? $valor : 'efectivo';
    }

    /**
     * N° de guía del recibo emitido por la empresa proveedora.
     * Se normaliza a mayúsculas y sin etiquetas.
     */
    private function numeroGuia($valor): string
    {
        return mb_strtoupper(trim(strip_tags((string) $valor)));
    }

    private function aMonto($valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace(',', '.', trim($valor));
        }

        if (!is_numeric($valor)) {
            return 0.0;
        }

        return round((float) $valor, 2);
    }

    private function aFecha($valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return date('Y-m-d H:i:s');
        }

        $texto = str_replace('T', ' ', $texto);
        $timestamp = strtotime($texto);

        if ($timestamp === false) {
            return null;
        }

        if ($timestamp > strtotime('today 23:59:59')) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizar(array $f): array
    {
        return [
            'id'             => (int) $f['id_compra'],
            'id_sucursal'    => (int) $f['id_sucursal'],
            'sucursal'       => (string) ($f['nombre_sucursal'] ?? ''),
            'ciudad'         => (string) ($f['ciudad'] ?? ''),
            'id_proveedor'   => (int) $f['id_proveedor'],
            'proveedor'      => (string) ($f['proveedor_nombre'] ?? ''),
            'proveedor_nit'  => (string) ($f['proveedor_nit'] ?? ''),
            'proveedor_telefono' => (string) ($f['proveedor_telefono'] ?? ''),
            'tipo_documento' => (string) ($f['tipo_documento'] ?? ''),
            'numero_documento' => (string) ($f['numero_documento'] ?? ''),
            'fecha_compra'   => !empty($f['fecha_compra']) ? date('d/m/Y', strtotime($f['fecha_compra'])) : '—',
            'fecha_compra_iso' => (string) ($f['fecha_compra'] ?? ''),
            'total'          => (float) $f['total'],
            'abono'          => (float) $f['abono'],
            'pagado'         => (float) $f['pagado'],
            'saldo'          => (float) $f['saldo'],
            'items'          => (int) ($f['items'] ?? 0),
            'creado'         => !empty($f['created_at']) ? date('d/m/Y H:i', strtotime($f['created_at'])) : '—',
        ];
    }

    private static function err(string $mensaje): array
    {
        return [
            'exito'   => false,
            'mensaje' => $mensaje,
            'id'      => 0,
            'saldo'   => 0.0,
            'monto'   => 0.0,
            'compras' => [],
        ];
    }
}
