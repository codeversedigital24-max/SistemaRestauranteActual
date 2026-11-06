<?php
/**
 * =====================================================
 * GET  modelos/cajas/arqueo_pdf.php
 * -----------------------------------------------------
 * Ticket de cierre de caja (80 mm) para reimprimir.
 *
 *   · Empresa / sucursal y caja
 *   · Usuario de apertura y de cierre + fechas
 *   · Monto inicial
 *   · Total de ventas por medio de pago
 *     (efectivo, transferencia, QR, tarjeta, otros)
 *   · Egresos y gastos del turno
 *   · Efectivo esperado, monto contado y diferencia
 *
 * Query: id (arqueo), descargar=1
 * Guardia: privilegio VER · Ítem Arqueo de caja (9)
 * Alcance: SuperAdmin todo; usuario normal su sucursal;
 * cajero sin privilegio de modificar, sólo su caja.
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

// Privilegio: consultar arqueos
rm_exigir_api('ver', Permisos::ITEM_VENTAS_ARQUEO);

$idArqueo = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$descargar = !empty($_GET['descargar']);

$errorTexto = static function (int $codigo, string $mensaje): void {
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    echo $mensaje;
    exit;
};

if ($idArqueo <= 0) {
    $errorTexto(422, 'Arqueo no válido');
}

try {
    $pdo = Conexion::getInstance()->getConnection();
    $idUsuario = (int) $_SESSION['id_usuario'];

    require_once __DIR__ . '/ArqueoCaja.php';

    $modelo = new ArqueoCaja($pdo);
    $arqueo = $modelo->obtener($idArqueo);

    if (!$arqueo) {
        $errorTexto(404, 'Arqueo no encontrado');
    }

    $acceso = $modelo->validarAccesoArqueo($idUsuario, $arqueo);

    if (!$acceso['permitido']) {
        $errorTexto((int) ($acceso['codigo'] ?? 403), (string) $acceso['mensaje']);
    }

    if ($arqueo['estado'] !== 'cerrado') {
        $errorTexto(422, 'El arqueo todavía está abierto: ciérralo para generar el ticket de cierre');
    }

    $resumen = $modelo->resumenCierre($idArqueo);

    if ($resumen === []) {
        $errorTexto(500, 'No se pudo preparar el resumen del arqueo');
    }

    // ---- Datos de la empresa ----
    $empresa = [];

    try {
        $empresa = $pdo->query(
            "SELECT razon_social, nombre_comercial, nit, direccion, telefono1, email_principal,
                    ciudad, moneda_principal
               FROM configuracion_empresa
              ORDER BY id_config ASC
              LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('arqueo_pdf.php empresa: ' . $e->getMessage());
        $empresa = [];
    }

    $moneda = trim((string) ($empresa['moneda_principal'] ?? 'BOB')) ?: 'BOB';
    $simbolo = $moneda === 'BOB' ? 'Bs' : $moneda;

    $dinero = static function (float $valor) use ($simbolo): string {
        return $simbolo . ' ' . number_format($valor, 2, ',', '.');
    };

    $esc = static function ($valor): string {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $razon = trim((string) ($empresa['razon_social'] ?? '')) ?: 'Restaurante Moderno';
    $comercial = trim((string) ($empresa['nombre_comercial'] ?? '')) ?: $razon;

    // ---- Ventas del turno (no incluye compras) ----
    $ventas = $modelo->ventasDeArqueo($idArqueo);
    $filasVentas = '';

    foreach ($ventas as $v) {
        $detallePago = (string) $v['metodo_pago_label'];

        if (!empty($v['pago_detalle'])) {
            $detallePago .= ' · ' . $v['pago_detalle'];
        }

        if (!empty($v['referencia'])) {
            $detallePago .= ' · Ref. ' . $v['referencia'];
        }

        $filasVentas .= '<tr>'
            . '<td class="mov-fecha">' . $esc($v['hora']) . '</td>'
            . '<td class="mov-concepto">'
            . '<b>' . $esc($v['codigo']) . '</b>'
            . ($v['anulada'] ? ' <span class="venta-anulada">ANULADA</span>' : '')
            . '<br><span class="mov-ref">' . $esc($detallePago) . ' · ' . $esc($v['tipo_pedido_label']) . '</span>'
            . '</td>'
            . '<td class="r mov-monto">' . $dinero((float) $v['total']) . '</td>'
            . '</tr>';
    }

    $htmlVentas = '
<div class="sep"></div>
<div class="titulo-seccion">Ventas del turno (' . count($ventas) . ')</div>';

    if ($filasVentas === '') {
        $htmlVentas .= '<div class="nota">Sin ventas registradas en este turno.</div>';
    } else {
        $htmlVentas .= '
<table class="movs">
    ' . $filasVentas . '
</table>';
    }

    // ---- Egresos / gastos ----
    $movimientos = $resumen['movimientos'] ?? [];
    $filasEgresos = '';

    foreach ($movimientos as $m) {
        $filasEgresos .= '<tr>'
            . '<td class="mov-fecha">' . $esc($m['fecha']) . '</td>'
            . '<td class="mov-concepto">'
            . '<span class="mov-tipo">' . $esc($m['tipo_label']) . '</span> ' . $esc($m['concepto'])
            . (!empty($m['referencia']) ? '<br><span class="mov-ref">Ref.: ' . $esc($m['referencia']) . '</span>' : '')
            . '</td>'
            . '<td class="r mov-monto">' . $dinero((float) $m['monto']) . '</td>'
            . '</tr>'
            . '<tr class="mov-detalle">'
            . '<td></td>'
            . '<td colspan="2">' . $esc($m['metodo_pago_label']) . ' · ' . $esc($m['usuario']) . '</td>'
            . '</tr>';
    }

    $htmlEgresos = '';

    if ($filasEgresos !== '') {
        $htmlEgresos = '
<div class="sep"></div>
<div class="titulo-seccion">Egresos y gastos</div>
<table class="movs">
    ' . $filasEgresos . '
</table>';
    }

    $diferencia = $arqueo['diferencia'];
    $etiquetaDiferencia = 'Diferencia';

    if ($diferencia !== null) {
        if (abs((float) $diferencia) < 0.005) {
            $etiquetaDiferencia = 'Diferencia (cuadra)';
        } elseif ((float) $diferencia > 0) {
            $etiquetaDiferencia = 'Diferencia (sobrante)';
        } else {
            $etiquetaDiferencia = 'Diferencia (faltante)';
        }
    }

    $altoMm = max(
        150,
        min(
            2400,
            130
            + (count($ventas) * 5.5)
            + (count($movimientos) * 7)
            + (($arqueo['observacion_apertura'] !== '' ? 8 : 0))
            + (($arqueo['observacion_cierre'] !== '' ? 8 : 0))
        )
    );
    $altoPt = $altoMm * 2.83465;

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ticket de cierre · Arqueo ' . $esc((string) $arqueo['id']) . '</title>
<style>
    @page { margin: 4mm 4.5mm 7mm 4.5mm; }
    * { box-sizing: border-box; }
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 8px;
        line-height: 1.35;
        color: #111;
        margin: 0;
    }
    .r { text-align: right; }
    .empresa { text-align: center; margin-bottom: 5px; }
    .empresa .razon { font-size: 10px; font-weight: bold; letter-spacing: .2px; }
    .empresa .comercial { font-size: 8px; color: #444; margin-top: 1px; }
    .empresa .dato { font-size: 7.2px; color: #555; line-height: 1.4; margin-top: 2px; }
    .sep { border-top: 1px dashed #999; margin: 5px 0; }
    .sep-solida { border-top: 1.5px solid #111; margin: 5px 0 3px; }
    .tipo-doc { text-align: center; font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin: 2px 0 4px; }
    table.meta { width: 100%; border-collapse: collapse; font-size: 7.4px; }
    table.meta td { padding: 1px 0; vertical-align: top; }
    table.meta td.et { color: #555; width: 38%; }
    table.meta td.val { text-align: right; font-weight: bold; }
    .titulo-seccion { font-size: 7px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; color: #555; margin-bottom: 2px; }
    table.totales { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.totales td { padding: 1.5px 0; }
    table.totales tr.sub td { color: #333; }
    table.totales tr.destacado td { border-top: 1px solid #999; padding-top: 3px; font-weight: bold; }
    table.totales tr.total td {
        border-top: 1.5px solid #111;
        padding-top: 4px;
        font-size: 11px;
        font-weight: bold;
    }
    table.totales tr.total td.r { font-size: 12px; }
    table.movs { width: 100%; border-collapse: collapse; font-size: 7.2px; }
    table.movs td { padding: 1.5px 0; vertical-align: top; }
    td.mov-fecha { width: 24%; color: #555; }
    td.mov-monto { width: 24%; font-weight: bold; }
    .mov-tipo { font-size: 6.6px; text-transform: uppercase; color: #7a5500; }
    .mov-ref { color: #666; }
    .venta-anulada { font-size: 6.4px; color: #b00020; font-weight: bold; }
    tr.mov-detalle td { color: #666; padding-bottom: 2px; }
    .nota { font-size: 7.2px; color: #444; margin-top: 4px; }
    .pie { text-align: center; font-size: 6.8px; color: #777; margin-top: 6px; }
    .anulado { margin-top: 6px; padding: 3px; border: 1.5px solid #b00020; color: #b00020; text-align: center; font-size: 9px; font-weight: bold; letter-spacing: 2px; }
</style>
</head>
<body>

<div class="empresa">
    <div class="razon">' . $esc($razon) . '</div>
    <div class="comercial">' . $esc($comercial) . '</div>
    <div class="dato">'
        . (!empty($empresa['nit']) ? 'NIT: ' . $esc($empresa['nit']) . '<br>' : '')
        . $esc($resumen['sucursal'])
        . ($resumen['ciudad'] !== '' ? '<br>' . $esc($resumen['ciudad']) : '')
        . (!empty($empresa['telefono1']) ? ' · Tel. ' . $esc($empresa['telefono1']) : '')
        . (!empty($empresa['email_principal']) ? '<br>' . $esc($empresa['email_principal']) : '')
    . '</div>
</div>

<div class="sep"></div>

<div class="tipo-doc">Ticket de cierre de caja</div>

<table class="meta">
    <tr><td class="et">Arqueo</td><td class="val">#' . $esc((string) $arqueo['id']) . '</td></tr>
    <tr><td class="et">Caja</td><td class="val">' . $esc($resumen['caja_codigo'] . ' · ' . $resumen['caja_nombre']) . '</td></tr>
    <tr><td class="et">Sucursal</td><td class="val">' . $esc($resumen['sucursal']) . '</td></tr>
    <tr><td class="et">Apertura</td><td class="val">' . $esc($resumen['fecha_apertura']) . '</td></tr>
    <tr><td class="et">Abrió</td><td class="val">' . $esc($resumen['usuario_apertura']) . '</td></tr>
    <tr><td class="et">Cierre</td><td class="val">' . $esc((string) ($resumen['fecha_cierre'] ?? '—')) . '</td></tr>
    <tr><td class="et">Cerró</td><td class="val">' . $esc($resumen['usuario_cierre'] !== '' ? $resumen['usuario_cierre'] : '—') . '</td></tr>
</table>

<div class="sep"></div>

<div class="titulo-seccion">Resumen del turno</div>
<table class="totales">
    <tr class="sub"><td>Monto de apertura</td><td class="r">' . $dinero((float) $resumen['monto_apertura']) . '</td></tr>
    <tr class="sub"><td>Total ventas</td><td class="r">' . $dinero((float) $resumen['total_ventas']) . '</td></tr>
    <tr class="sub"><td>Ventas en efectivo</td><td class="r">' . $dinero((float) $resumen['total_efectivo']) . '</td></tr>
    <tr class="sub"><td>Ventas por transferencia</td><td class="r">' . $dinero((float) $resumen['total_transferencia']) . '</td></tr>
    <tr class="sub"><td>Ventas por QR</td><td class="r">' . $dinero((float) $resumen['total_qr']) . '</td></tr>
    <tr class="sub"><td>Ventas con tarjeta</td><td class="r">' . $dinero((float) $resumen['total_tarjeta']) . '</td></tr>
    <tr class="sub"><td>Ventas otros medios</td><td class="r">' . $dinero((float) $resumen['total_otros']) . '</td></tr>
    <tr class="destacado"><td>Egresos / gastos</td><td class="r">− ' . $dinero((float) $resumen['total_egresos']) . '</td></tr>
    <tr><td>(de ellos, en efectivo)</td><td class="r">' . $dinero((float) $resumen['total_egresos_efectivo']) . '</td></tr>
    <tr class="destacado"><td>EFECTIVO ESPERADO</td><td class="r">' . $dinero((float) $resumen['efectivo_esperado']) . '</td></tr>
    <tr><td>Monto contado</td><td class="r">' . ($arqueo['monto_cierre'] !== null ? $dinero((float) $arqueo['monto_cierre']) : '—') . '</td></tr>
    <tr class="total"><td>' . $esc($etiquetaDiferencia) . '</td><td class="r">'
        . ($diferencia !== null ? $dinero((float) $diferencia) : '—')
    . '</td></tr>
</table>'
    . $htmlVentas
    . $htmlEgresos . '

<div class="nota"><b>Nota:</b> este ticket incluye ventas y egresos/gastos de la caja. No incluye compras.</div>

' . ($arqueo['observacion_apertura'] !== '' ? '<div class="nota"><b>Obs. apertura:</b> ' . $esc($arqueo['observacion_apertura']) . '</div>' : '')
    . ($arqueo['observacion_cierre'] !== '' ? '<div class="nota"><b>Obs. cierre:</b> ' . $esc($arqueo['observacion_cierre']) . '</div>' : '') . '

<div class="pie">Ticket de cierre · emitido ' . date('d/m/Y H:i') . '</div>

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
    $dompdf->setPaper([0, 0, 226.77, $altoPt], 'portrait');
    $dompdf->render();

    // Queda la marca del ticket emitido (permite reimprimir).
    $modelo->marcarTicketCierre($idArqueo);

    $nombre = 'arqueo-cierre-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $arqueo['id']) . '.pdf';

    $dompdf->stream($nombre, ['Attachment' => $descargar ? 1 : 0]);
    exit;
} catch (PDOException $e) {
    error_log('arqueo_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('arqueo_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el ticket de cierre';
}
