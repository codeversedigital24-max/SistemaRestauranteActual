<?php
/**
 * =====================================================
 * GET  modelos/compras/comprobante_abono_pdf.php
 * -----------------------------------------------------
 * Comprobante de un abono (pago posterior) de una compra
 * al crédito.
 *
 *   · Empresa / sucursal / proveedor
 *   · N° de guía del recibo emitido por el proveedor
 *   · Monto abonado, saldo anterior y saldo actual
 *
 * Query: id_pago (obligatorio), descargar=1
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

rm_iniciar_sesion();

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No autorizado';
    exit;
}

rm_exigir_api('ver', Permisos::ITEM_INV_CUENTAS_PAGAR);

$idPago = isset($_GET['id_pago']) ? (int) $_GET['id_pago'] : 0;
$descargar = !empty($_GET['descargar']);

if ($idPago <= 0) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Abono no válido';
    exit;
}

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/ContextoCompras.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'La sesión ya no es válida';
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT p.*,
                c.total, c.abono, c.fecha_compra, c.tipo_documento, c.numero_documento,
                pr.nombre AS proveedor_nombre, pr.nit AS proveedor_nit,
                pr.telefono AS proveedor_telefono,
                s.nombre_sucursal, s.codigo_sucursal, s.direccion AS sucursal_direccion,
                s.ciudad AS sucursal_ciudad, s.telefono AS sucursal_telefono, s.logo AS sucursal_logo,
                u.username,
                TRIM(CONCAT(COALESCE(ud.nombres, ''), ' ', COALESCE(ud.apellidos, ''))) AS usuario_nombre
           FROM compras_pagos p
           INNER JOIN compras c      ON c.id_compra = p.id_compra
           INNER JOIN proveedores pr ON pr.id_proveedor = p.id_proveedor
           INNER JOIN sucursales s   ON s.id_sucursal = p.id_sucursal
           INNER JOIN usuarios u     ON u.id_usuario = p.id_usuario
           LEFT  JOIN usuarios_datos ud ON ud.id_usuario = u.id_usuario
          WHERE p.id_pago = :id
          LIMIT 1"
    );
    $stmt->execute([':id' => $idPago]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pago) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Abono no encontrado';
        exit;
    }

    if (!$usuario['es_superadmin'] && (int) $pago['id_sucursal'] !== (int) $usuario['id_sucursal']) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No puedes ver abonos de otra sucursal';
        exit;
    }

    // Abonos registrados antes de este (por orden de registro).
    $anterior = $pdo->prepare(
        "SELECT COALESCE(SUM(monto), 0)
           FROM compras_pagos
          WHERE id_compra = :compra AND id_pago < :pago"
    );
    $anterior->execute([
        ':compra' => (int) $pago['id_compra'],
        ':pago'   => $idPago,
    ]);
    $pagadoAntes = round((float) $anterior->fetchColumn(), 2);

    $total = (float) $pago['total'];
    $abonoInicial = (float) $pago['abono'];
    $monto = (float) $pago['monto'];
    $saldoAnterior = max(0.0, round($total - $abonoInicial - $pagadoAntes, 2));
    $saldoActual = max(0.0, round($saldoAnterior - $monto, 2));

    $empresa = $pdo->query(
        "SELECT razon_social, nombre_comercial, nit, direccion, telefono1, email_principal,
                ciudad, pais, moneda_principal, logo
           FROM configuracion_empresa
          ORDER BY id_config ASC
          LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $moneda = trim((string) ($empresa['moneda_principal'] ?? 'BOB')) ?: 'BOB';
    $simbolo = $moneda === 'BOB' ? 'Bs' : $moneda;

    $dinero = static function (float $valor) use ($simbolo): string {
        return $simbolo . ' ' . number_format($valor, 2, ',', '.');
    };

    $esc = static function ($valor): string {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $metodos = [
        'efectivo'      => 'Efectivo',
        'transferencia' => 'Transferencia',
        'qr'            => 'QR',
        'otro'          => 'Otro',
    ];

    $documentos = [
        'factura' => 'Factura',
        'recibo'  => 'Recibo',
        'nota'    => 'Nota de entrega',
        'otro'    => 'Otro',
    ];

    // Logo: sucursal -> empresa -> por defecto
    $logo = null;
    $baseSistema = dirname(__DIR__, 2) . '/sistema/images/';

    foreach ([$pago['sucursal_logo'] ?? '', $empresa['logo'] ?? '', 'default-logo.png'] as $archivo) {
        $archivo = trim((string) $archivo);

        if ($archivo === '') {
            continue;
        }

        $rutas = [
            $baseSistema . basename($archivo),
            dirname(__DIR__, 2) . '/assets/img/' . basename($archivo),
            dirname(__DIR__, 2) . '/' . ltrim($archivo, '/\\'),
        ];

        foreach ($rutas as $ruta) {
            if (is_file($ruta)) {
                $info = @getimagesize($ruta);

                if ($info !== false) {
                    $logo = 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($ruta));
                    break 2;
                }
            }
        }
    }

    $razon = (string) ($empresa['razon_social'] ?? '') ?: 'Restaurante Moderno';
    $comercial = (string) ($empresa['nombre_comercial'] ?? '') ?: $razon;
    $registro = trim((string) ($pago['usuario_nombre'] ?? '')) ?: (string) $pago['username'];

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comprobante de abono #' . (int) $pago['id_pago'] . '</title>
<style>
    @page { margin: 32px 38px 46px 38px; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1c1416; margin: 0; }
    .cabecera { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .cabecera td { vertical-align: top; }
    .logo { width: 145px; }
    .logo img { max-width: 145px; max-height: 62px; }
    .empresa h1 { font-size: 15px; margin: 0 0 2px; }
    .empresa .comercial { font-size: 10px; color: #6b6165; margin-bottom: 5px; }
    .empresa .dato { font-size: 8.5px; color: #555; line-height: 1.45; }
    .caja { border: 1px solid #1f6fd0; border-radius: 6px; padding: 9px 12px; width: 200px; text-align: center; }
    .caja .rotulo { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #7c8fa5; }
    .caja .numero { font-size: 17px; font-weight: bold; color: #1f6fd0; margin: 1px 0; }
    .caja .fecha { font-size: 8.5px; color: #555; margin-top: 3px; }
    .titulo-seccion { font-size: 8px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; color: #a79ea2; margin: 0 0 4px; }
    .datos { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 14px; }
    .datos td { width: 50%; vertical-align: top; background: #f8f7f7; border: 1px solid #ececef; border-radius: 5px; padding: 8px 10px; }
    .datos .nombre { font-size: 10.5px; font-weight: bold; }
    .datos .linea { font-size: 8.5px; color: #555; line-height: 1.45; }
    table.detalle { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.detalle th { background: #1c1416; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 8px; text-align: left; }
    table.detalle td { padding: 8px; border-bottom: 1px solid #ececef; font-size: 9.5px; vertical-align: top; }
    .guia { display: inline-block; padding: 3px 8px; border: 1px dashed #1f6fd0; border-radius: 4px; background: #eef5ff; color: #1f6fd0; font-weight: bold; letter-spacing: .4px; }
    .bloque { margin-top: 14px; border: 1px solid #d5e5fb; background: #eef5ff; border-radius: 6px; padding: 10px 14px; }
    .bloque .et { font-size: 8px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; color: #28527a; margin-bottom: 4px; }
    table.totales { width: 100%; border-collapse: collapse; }
    table.totales td { padding: 3px 0; font-size: 9.5px; color: #28527a; }
    table.totales tr.monto td { border-top: 2px solid #1f6fd0; padding-top: 7px; font-size: 12px; font-weight: bold; color: #1c1416; }
    table.totales tr.monto td.valor { color: #1f6fd0; font-size: 15px; }
    table.totales tr.saldo td { border-top: 1px solid #b9d2f0; padding-top: 6px; font-weight: bold; color: #d9232d; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .observaciones { margin-top: 12px; padding: 8px 10px; background: #f8f7f7; border-left: 3px solid #1f6fd0; font-size: 8.5px; color: #555; }
    .firmas { width: 100%; border-collapse: collapse; margin-top: 46px; }
    .firmas td { width: 50%; text-align: center; padding: 0 22px; }
    .firma-linea { border-top: 1px solid #1c1416; padding-top: 5px; font-size: 8.5px; color: #555; }
    .pie { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #a79ea2; border-top: 1px solid #ececef; padding-top: 5px; }
</style>
</head>
<body>

<table class="cabecera">
    <tr>
        <td style="width: 58%">
            <table>
                <tr>
                    ' . ($logo !== null ? '<td class="logo"><img src="' . $logo . '" alt="Logo"></td>' : '') . '
                    <td class="empresa">
                        <h1>' . $esc($razon) . '</h1>
                        <div class="comercial">' . $esc($comercial) . '</div>
                        <div class="dato">
                            ' . (!empty($empresa['nit']) ? 'NIT: ' . $esc($empresa['nit']) . '<br>' : '') . '
                            ' . (!empty($empresa['direccion']) ? $esc($empresa['direccion']) . '<br>' : '') . '
                            ' . (!empty($empresa['telefono1']) ? 'Tel: ' . $esc($empresa['telefono1']) : '') . '
                            ' . (!empty($empresa['email_principal']) ? ' · ' . $esc($empresa['email_principal']) : '') . '
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 42%; text-align: right">
            <div class="caja">
                <div class="rotulo">Comprobante de abono</div>
                <div class="numero">#' . (int) $pago['id_pago'] . '</div>
                <div class="fecha">Compra #' . (int) $pago['id_compra'] . ' · ' . $esc(date('d/m/Y H:i', strtotime($pago['fecha_pago']))) . '</div>
            </div>
        </td>
    </tr>
</table>

<table class="datos">
    <tr>
        <td>
            <div class="titulo-seccion">Proveedor</div>
            <div class="nombre">' . $esc($pago['proveedor_nombre']) . '</div>
            <div class="linea">
                ' . (!empty($pago['proveedor_nit']) ? 'NIT: ' . $esc($pago['proveedor_nit']) . '<br>' : '') . '
                ' . (!empty($pago['proveedor_telefono']) ? 'Tel: ' . $esc($pago['proveedor_telefono']) : '') . '
            </div>
        </td>
        <td>
            <div class="titulo-seccion">Sucursal</div>
            <div class="nombre">' . $esc($pago['nombre_sucursal']) . '</div>
            <div class="linea">
                ' . (!empty($pago['codigo_sucursal']) ? 'Código: ' . $esc($pago['codigo_sucursal']) . '<br>' : '') . '
                ' . (!empty($pago['sucursal_direccion']) ? $esc($pago['sucursal_direccion']) . '<br>' : '') . '
                ' . (!empty($pago['sucursal_ciudad']) ? $esc($pago['sucursal_ciudad']) : '') . '
                ' . (!empty($pago['sucursal_telefono']) ? ' · Tel: ' . $esc($pago['sucursal_telefono']) : '') . '
            </div>
        </td>
    </tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-bottom:12px">
    <tr>
        <td style="font-size:8.5px;color:#555"><b>Compra:</b> #' . (int) $pago['id_compra'] . ' · ' . $esc(date('d/m/Y', strtotime($pago['fecha_compra']))) . '</td>
        <td style="font-size:8.5px;color:#555;text-align:center"><b>Documento:</b> ' . $esc($documentos[$pago['tipo_documento']] ?? $pago['tipo_documento']) . (!empty($pago['numero_documento']) ? ' · ' . $esc($pago['numero_documento']) : '') . '</td>
        <td style="font-size:8.5px;color:#555;text-align:right"><b>Registró:</b> ' . $esc($registro) . '</td>
    </tr>
</table>

<table class="detalle">
    <thead>
        <tr>
            <th style="width:120px">Fecha del abono</th>
            <th style="width:110px">Método</th>
            <th>N° guía del recibo del proveedor</th>
            <th style="width:110px">Referencia</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>' . $esc(date('d/m/Y H:i', strtotime($pago['fecha_pago']))) . '</td>
            <td>' . $esc($metodos[$pago['metodo_pago']] ?? $pago['metodo_pago']) . '</td>
            <td><span class="guia">' . $esc($pago['numero_guia'] !== '' && $pago['numero_guia'] !== null ? $pago['numero_guia'] : 'SIN GUÍA') . '</span></td>
            <td>' . $esc(!empty($pago['referencia']) ? $pago['referencia'] : '—') . '</td>
        </tr>
    </tbody>
</table>

<div class="bloque">
    <div class="et">Resumen de la cuenta</div>
    <table class="totales">
        <tr><td>Total de la compra</td><td class="r">' . $dinero($total) . '</td></tr>
        <tr><td>Abono inicial</td><td class="r">− ' . $dinero($abonoInicial) . '</td></tr>
        <tr><td>Pagos anteriores</td><td class="r">− ' . $dinero($pagadoAntes) . '</td></tr>
        <tr><td class="b">Saldo antes de este abono</td><td class="r b">' . $dinero($saldoAnterior) . '</td></tr>
        <tr class="monto"><td>Monto abonado</td><td class="r valor">' . $dinero($monto) . '</td></tr>
        <tr class="saldo"><td>Saldo pendiente actual</td><td class="r">' . $dinero($saldoActual) . '</td></tr>
    </table>
</div>

' . (!empty($pago['observaciones']) ? '<div class="observaciones"><b>Observaciones:</b> ' . $esc($pago['observaciones']) . '</div>' : '') . '

<table class="firmas">
    <tr>
        <td><div class="firma-linea">Recibido por (proveedor)</div></td>
        <td><div class="firma-linea">Entregado por</div></td>
    </tr>
</table>

<div class="pie">
    ' . $esc($comercial) . ' · Comprobante generado el ' . date('d/m/Y H:i') . ' · ' . $esc($moneda) . '
</div>

</body>
</html>';

    require_once dirname(__DIR__, 2) . '/vendor/dompdf/autoload.inc.php';

    $opciones = new \Dompdf\Options();
    $opciones->set('isRemoteEnabled', false);
    $opciones->set('isHtml5ParserEnabled', true);
    $opciones->set('defaultFont', 'DejaVu Sans');
    $opciones->set('chroot', dirname(__DIR__, 2));

    $dompdf = new \Dompdf\Dompdf($opciones);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    $nombre = 'comprobante-abono-' . (int) $pago['id_pago'] . '.pdf';

    $dompdf->stream($nombre, ['Attachment' => $descargar ? 1 : 0]);
    exit;
} catch (PDOException $e) {
    error_log('comprobante_abono_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('comprobante_abono_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el comprobante';
}
