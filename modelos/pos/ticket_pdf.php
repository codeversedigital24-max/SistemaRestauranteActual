<?php
/**
 * =====================================================
 * GET  modelos/pos/ticket_pdf.php
 * -----------------------------------------------------
 * Comprobante de venta en formato ticket de 80 mm.
 *
 *   · Logo y datos de la empresa / sucursal
 *   · N° de venta, fecha, cajero y tipo de pedido
 *   · Detalle con descuentos, promociones e IVA
 *   · Desglose de pago (efectivo, QR, tarjeta o mixto)
 *
 * Query: id (venta), descargar=1
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

// Privilegio: ticket visible desde POS (6), pedidos (7) o historial de ventas (10)
rm_exigir_api_alguno('ver', [
    Permisos::ITEM_VENTAS_POS,
    Permisos::ITEM_VENTAS_PEDIDOS,
    Permisos::ITEM_VENTAS_HISTORIAL,
]);

$idVenta = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$descargar = !empty($_GET['descargar']);

if ($idVenta <= 0) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Venta no válida';
    exit;
}

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/Venta.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'La sesión ya no es válida';
        exit;
    }

    $venta = (new Venta($pdo))->obtener($idVenta);

    if (!$venta) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Venta no encontrada';
        exit;
    }

    if (!rm_es_superadmin() && (int) $venta['id_sucursal'] !== (int) $usuario['id_sucursal']) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No puedes ver ventas de otra sucursal';
        exit;
    }

    $empresa = $pdo->query(
        "SELECT razon_social, nombre_comercial, nit, direccion, telefono1, email_principal,
                ciudad, moneda_principal, logo
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

    // ---- Logo: sucursal → empresa → por defecto ----
    $logo = null;
    $baseProyecto = dirname(__DIR__, 2);

    $candidatos = [
        $venta['sucursal_logo'] ?? '',
        $empresa['logo'] ?? '',
        'default-logo.png',
    ];

    foreach ($candidatos as $archivo) {
        $archivo = trim((string) $archivo);

        if ($archivo === '') {
            continue;
        }

        $rutas = [
            $baseProyecto . '/' . ltrim($archivo, '/\\'),
            $baseProyecto . '/sistema/images/' . basename($archivo),
            $baseProyecto . '/assets/img/' . basename($archivo),
        ];

        foreach ($rutas as $ruta) {
            if (!is_file($ruta)) {
                continue;
            }

            $info = @getimagesize($ruta);

            if ($info !== false) {
                $logo = 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($ruta));
                break 2;
            }
        }
    }

    $razon = trim((string) ($empresa['razon_social'] ?? '')) ?: 'Restaurante Moderno';
    $comercial = trim((string) ($empresa['nombre_comercial'] ?? '')) ?: $razon;
    $ciudad = trim((string) ($venta['ciudad'] ?? '')) ?: trim((string) ($empresa['ciudad'] ?? ''));

    // ---- Filas del detalle ----
    $filas = '';

    foreach ($venta['detalle'] as $d) {
        $descuento = (float) $d['descuento'];
        $ivaPct = (float) $d['impuesto_porcentaje'];

        $filas .= '<tr class="item">'
            . '<td colspan="2" class="item-nombre">' . $esc($d['producto']) . '</td>'
            . '</tr>'
            . '<tr class="item-linea">'
            . '<td class="item-detalle">'
            . number_format((float) $d['cantidad'], 0, ',', '.') . ' x ' . $dinero((float) $d['precio_unitario'])
            . ($ivaPct > 0 ? ' · IVA ' . rtrim(rtrim(number_format($ivaPct, 2, ',', '.'), '0'), ',') . '%' : '')
            . ($descuento > 0.004
                ? '<br><span class="desc">Desc. ' . $dinero($descuento)
                  . (!empty($d['promocion']) ? ' · ' . $esc($d['promocion']) : '') . '</span>'
                : '')
            . '</td>'
            . '<td class="item-total">' . $dinero((float) $d['total']) . '</td>'
            . '</tr>';
    }

    // ---- Formas de pago ----
    $pagosHtml = '';

    foreach ($venta['pagos'] as $p) {
        $pagosHtml .= '<tr><td>' . $esc($p['etiqueta']) . '</td><td class="r">' . $dinero((float) $p['monto']) . '</td></tr>';
    }

    if ($venta['metodo_pago'] === 'efectivo' && (float) $venta['cambio'] > 0.004) {
        $pagosHtml .= '<tr><td>Recibido</td><td class="r">' . $dinero((float) $venta['recibido']) . '</td></tr>';
        $pagosHtml .= '<tr class="cambio"><td>Cambio</td><td class="r">' . $dinero((float) $venta['cambio']) . '</td></tr>';
    }

    if ($venta['referencia_pago'] !== '') {
        $pagosHtml .= '<tr><td>Referencia</td><td class="r">' . $esc($venta['referencia_pago']) . '</td></tr>';
    }

    $pedido = $esc($venta['tipo_pedido_label']);

    if ($venta['tipo_pedido'] === 'delivery' && $venta['delivery_nombre'] !== '') {
        $pedido .= ' · ' . $esc($venta['delivery_nombre']);
    }

    $anulada = $venta['estado'] === 'anulada';

    $altoMm = max(150, min(480, 128 + (count($venta['detalle']) * 9) + (count($venta['pagos']) * 4)));
    $altoPt = $altoMm * 2.83465;

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ticket ' . $esc($venta['codigo']) . '</title>
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
    .centro { text-align: center; }
    .r { text-align: right; }
    .logo { text-align: center; margin-bottom: 4px; }
    .logo img { max-width: 52mm; max-height: 16mm; }
    .empresa { text-align: center; margin-bottom: 5px; }
    .empresa .razon { font-size: 10px; font-weight: bold; letter-spacing: .2px; }
    .empresa .comercial { font-size: 8px; color: #444; margin-top: 1px; }
    .empresa .dato { font-size: 7.2px; color: #555; line-height: 1.4; margin-top: 2px; }
    .sep { border-top: 1px dashed #999; margin: 5px 0; }
    .sep-solida { border-top: 1.5px solid #111; margin: 5px 0 3px; }
    .meta { width: 100%; border-collapse: collapse; font-size: 7.4px; }
    .meta td { padding: 1px 0; vertical-align: top; }
    .meta td.et { color: #555; width: 34%; }
    .meta td.val { text-align: right; font-weight: bold; }
    .titulo-items { font-size: 7px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; color: #555; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items td { padding: 0; vertical-align: top; }
    tr.item td.item-nombre { padding-top: 4px; font-size: 8.2px; font-weight: bold; }
    tr.item-linea td { padding-bottom: 3px; }
    td.item-detalle { font-size: 7.2px; color: #444; }
    td.item-total { font-size: 8.2px; font-weight: bold; text-align: right; white-space: nowrap; }
    .desc { color: #1a6b3a; }
    table.totales { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.totales td { padding: 1.5px 0; }
    table.totales tr.total td {
        border-top: 1.5px solid #111;
        padding-top: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    table.totales tr.total td.r { font-size: 13px; }
    table.pagos { width: 100%; border-collapse: collapse; font-size: 7.6px; }
    table.pagos td { padding: 1.2px 0; color: #333; }
    table.pagos tr.cambio td { font-weight: bold; color: #1a6b3a; }
    .nota { font-size: 7.2px; color: #444; margin-top: 4px; }
    .gracias { text-align: center; font-size: 8px; font-weight: bold; margin-top: 8px; letter-spacing: .3px; }
    .pie { text-align: center; font-size: 6.8px; color: #777; margin-top: 4px; }
    .anulada {
        margin-top: 6px;
        padding: 4px;
        border: 1.5px solid #b00020;
        color: #b00020;
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 2px;
    }
</style>
</head>
<body>

<div class="logo">' . ($logo !== null ? '<img src="' . $logo . '" alt="Logo">' : '') . '</div>

<div class="empresa">
    <div class="razon">' . $esc($razon) . '</div>
    <div class="comercial">' . $esc($comercial) . '</div>
    <div class="dato">'
        . (!empty($empresa['nit']) ? 'NIT: ' . $esc($empresa['nit']) . '<br>' : '')
        . $esc($venta['sucursal'])
        . (!empty($venta['sucursal_direccion']) ? '<br>' . $esc($venta['sucursal_direccion']) : '')
        . ($ciudad !== '' ? '<br>' . $esc($ciudad) : '')
        . (!empty($venta['sucursal_telefono']) ? ' · Tel. ' . $esc($venta['sucursal_telefono']) : '')
        . (!empty($empresa['email_principal']) ? '<br>' . $esc($empresa['email_principal']) : '')
    . '</div>
</div>

<div class="sep"></div>

<table class="meta">
    <tr><td class="et">Ticket</td><td class="val">' . $esc($venta['codigo']) . '</td></tr>
    <tr><td class="et">Fecha</td><td class="val">' . $esc($venta['fecha_venta']) . '</td></tr>
    <tr><td class="et">Cajero</td><td class="val">' . $esc($venta['usuario']) . '</td></tr>
    <tr><td class="et">Pedido</td><td class="val">' . $pedido . '</td></tr>
</table>

<div class="sep"></div>

<div class="titulo-items">Detalle</div>
<table class="items">
    ' . $filas . '
</table>

<div class="sep-solida"></div>

<table class="totales">
    <tr><td>Subtotal</td><td class="r">' . $dinero((float) $venta['subtotal_bruto']) . '</td></tr>'
    . ((float) $venta['descuento'] > 0.004
        ? '<tr><td>Descuentos</td><td class="r">− ' . $dinero((float) $venta['descuento']) . '</td></tr>'
        : '')
    . ((float) $venta['impuesto'] > 0.004
        ? '<tr><td>IVA</td><td class="r">' . $dinero((float) $venta['impuesto']) . '</td></tr>'
        : '')
    . '<tr class="total"><td>TOTAL</td><td class="r">' . $dinero((float) $venta['total']) . '</td></tr>
</table>

<div class="sep"></div>

<table class="pagos">
    ' . $pagosHtml . '
</table>

' . ($venta['observaciones'] !== '' ? '<div class="nota"><b>Obs.:</b> ' . $esc($venta['observaciones']) . '</div>' : '') . '

' . ($anulada ? '<div class="anulada">VENTA ANULADA</div>' : '') . '

<div class="gracias">¡Gracias por su preferencia!</div>
<div class="pie">' . $esc($comercial) . ' · ' . date('d/m/Y H:i') . '</div>

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

    $nombre = 'ticket-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $venta['codigo']) . '.pdf';

    $dompdf->stream($nombre, ['Attachment' => $descargar ? 1 : 0]);
    exit;
} catch (PDOException $e) {
    error_log('ticket_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('ticket_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el ticket';
}
