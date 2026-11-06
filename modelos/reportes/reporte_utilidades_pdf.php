<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
rm_iniciar_sesion();

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'No autorizado';
    exit;
}

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/UtilidadReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) {
        http_response_code(401);
        header('Content-Type: text/plain');
        echo 'La sesión ya no es válida';
        exit;
    }
    rm_exigir_api('ver', Permisos::ITEM_REP_UTILIDADES);

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    if (!$usuario['es_superadmin'] && $idSucursal <= 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Tu usuario no tiene sucursal asignada';
        exit;
    }

    $filtros = [
        'id_sucursal'  => $idSucursal,
        'id_usuario'   => (int) ($_GET['id_usuario'] ?? 0),
        'id_arqueo'    => (int) ($_GET['id_arqueo'] ?? 0),
        'id_categoria' => (int) ($_GET['id_categoria'] ?? 0),
        'desde'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : '',
        'hasta'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : '',
    ];

    $modelo  = new UtilidadReporte($pdo);
    $productos = $modelo->detallePorProducto($filtros);
    $ventas    = $modelo->detallePorVenta($filtros);
    $resumen   = $modelo->resumen($filtros);

    // Etiquetas de los filtros aplicados
    $sucursalTxt = 'Todas';

    if ($idSucursal !== null && $idSucursal > 0) {
        foreach ($modelo->sucursalesDisponibles($usuario) as $suc) {
            if ((int) $suc['id_sucursal'] === (int) $idSucursal) {
                $sucursalTxt = (string) $suc['nombre_sucursal'];
                break;
            }
        }
    }

    $cajaTxt = 'Todas';

    if (!empty($filtros['id_arqueo'])) {
        foreach ($modelo->cajasDisponibles($idSucursal) as $caja) {
            if ((int) $caja['id'] === (int) $filtros['id_arqueo']) {
                $cajaTxt = $caja['codigo'] . ' · ' . $caja['nombre'] . ' · ' . $caja['fecha'];
                break;
            }
        }
    }

    $vendedorTxt = 'Todos';

    if (!empty($filtros['id_usuario'])) {
        foreach ($modelo->usuariosDisponibles($idSucursal) as $vend) {
            if ((int) $vend['id'] === (int) $filtros['id_usuario']) {
                $vendedorTxt = $vend['nombre'] . ' (' . $vend['username'] . ')';
                break;
            }
        }
    }

    // Configuración empresa
    $empresa = [];
    try {
        $empresa = $pdo->query(
            "SELECT razon_social, nombre_comercial, nit, direccion, telefono1,
                    email_principal, logo, moneda_principal
               FROM configuracion_empresa
              ORDER BY id_config ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {}

    $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dinero = static fn(float $v): string => 'Bs ' . number_format($v, 2, ',', '.');
    $pct = static fn(float $v): string => number_format($v, 2, ',', '.') . ' %';

    // Logo
    $logo = null;
    if (!empty($empresa['logo'])) {
        foreach ([
            BASE_PATH . '/' . ltrim((string) $empresa['logo'], '/\\'),
            BASE_PATH . '/assets/img/' . basename((string) $empresa['logo']),
            BASE_PATH . '/assets/img/configuracion/' . basename((string) $empresa['logo']),
        ] as $r) {
            if (is_file($r)) {
                $info = @getimagesize($r);
                if ($info) {
                    $logo = 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($r));
                    break;
                }
            }
        }
    }

    $razon     = (string) ($empresa['razon_social'] ?? 'Restaurante');
    $comercial = (string) ($empresa['nombre_comercial'] ?? '') ?: $razon;
    $nit       = (string) ($empresa['nit'] ?? '');
    $dir       = (string) ($empresa['direccion'] ?? '');
    $tel       = (string) ($empresa['telefono1'] ?? '');
    $mail      = (string) ($empresa['email_principal'] ?? '');

    $desdeTxt = $filtros['desde'] !== '' ? date('d/m/Y', strtotime($filtros['desde'])) : '—';
    $hastaTxt = $filtros['hasta'] !== '' ? date('d/m/Y', strtotime($filtros['hasta'])) : '—';

    // Filas por producto
    $filasP = '';
    $totCant = 0; $totVenta = 0; $totCosto = 0; $totUtilidad = 0;

    foreach ($productos as $p) {
        $totCant     += (float) $p['cantidad'];
        $totVenta    += (float) $p['venta'];
        $totCosto    += (float) $p['costo'];
        $totUtilidad += (float) $p['utilidad'];

        $filasP .=
            '<tr>' .
            '<td><b>' . $esc($p['producto']) . '</b><br><span class="sub">' . $esc($p['codigo']) . '</span></td>' .
            '<td>' . $esc($p['categoria']) . '</td>' .
            '<td class="r">' . number_format((float) $p['cantidad'], 0, ',', '.') . '</td>' .
            '<td class="r">' . $dinero((float) $p['venta']) . '</td>' .
            '<td class="r">' . $dinero((float) $p['costo']) . '</td>' .
            '<td class="r b">' . $dinero((float) $p['utilidad']) . '</td>' .
            '<td class="c">' . $pct((float) $p['margen']) . '</td>' .
            '</tr>';
    }

    if ($filasP === '') {
        $filasP = '<tr><td colspan="7" class="c">Sin productos vendidos en el rango.</td></tr>';
    }

    $margenGlobal = $totVenta > 0 ? round(($totUtilidad / $totVenta) * 100, 2) : 0;

    // Filas por venta
    $filasV = '';
    $totVSub = 0; $totVCosto = 0; $totVUtilidad = 0;

    foreach ($ventas as $v) {
        $totVSub      += (float) $v['subtotal'];
        $totVCosto    += (float) $v['costo'];
        $totVUtilidad += (float) $v['utilidad'];

        $filasV .=
            '<tr>' .
            '<td class="c">#' . (int) $v['id'] . '</td>' .
            '<td>' . $esc($v['fecha']) . '</td>' .
            '<td>' . $esc($v['codigo']) . '</td>' .
            '<td>' . $esc($v['usuario']) . '</td>' .
            '<td>' . $esc($v['caja_codigo'] !== '' ? $v['caja_codigo'] . ' · ' . $v['caja_nombre'] : '—') . '</td>' .
            '<td class="r">' . $dinero((float) $v['subtotal']) . '</td>' .
            '<td class="r">' . $dinero((float) $v['costo']) . '</td>' .
            '<td class="r b">' . $dinero((float) $v['utilidad']) . '</td>' .
            '<td class="c">' . $pct((float) $v['margen']) . '</td>' .
            '</tr>';
    }

    if ($filasV === '') {
        $filasV = '<tr><td colspan="9" class="c">Sin ventas registradas en el rango.</td></tr>';
    }

    $margenVentas = $totVSub > 0 ? round(($totVUtilidad / $totVSub) * 100, 2) : 0;

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de utilidades</title>
<style>
    @page { margin: 22px 24px 40px 24px; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1c1416; margin: 0; }

    .cabecera { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .cabecera td { vertical-align: top; }
    .logo { width: 130px; }
    .logo img { max-width: 130px; max-height: 60px; }
    .empresa h1 { font-size: 14px; margin: 0 0 2px; }
    .empresa .dato { font-size: 8.5px; color: #555; line-height: 1.4; }
    .titulo { text-align: right; }
    .titulo .rotulo { font-size: 8.5px; letter-spacing: .8px; text-transform: uppercase; color: #a79ea2; }
    .titulo .valor { font-size: 16px; font-weight: bold; color: #14803d; margin-top: 2px; }

    .filtros { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .filtros td { font-size: 8.5px; color: #555; padding: 3px 8px 3px 0; vertical-align: top; }
    .filtros b { color: #1c1416; }

    .nota { font-size: 8px; color: #6b6165; margin: -2px 0 10px; }
    .nota b { color: #1c1416; }

    .seccion { font-size: 8px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; color: #555; margin: 14px 0 4px; }

    .resumen { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
    .resumen td { width: 16.66%; background: #f8f7f7; border: 1px solid #ececef; border-radius: 5px; padding: 6px 9px; vertical-align: top; }
    .resumen .et { display: block; font-size: 7.5px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; color: #a79ea2; }
    .resumen .val { display: block; font-size: 12px; font-weight: bold; color: #1c1416; margin-top: 1px; }
    .resumen .val.verde { color: #14803d; }
    .resumen .val.ambar { color: #92400e; }

    table.tabla { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.tabla th { background: #1c1416; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 6px; text-align: left; }
    table.tabla td { padding: 5px 6px; border-bottom: 1px solid #ececef; font-size: 8.5px; vertical-align: middle; }
    table.tabla tfoot td { border-top: 2px solid #1c1416; font-weight: bold; font-size: 9.5px; padding: 7px 6px; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .sub { font-size: 7.5px; color: #a79ea2; }
    .verde { color: #14803d; }

    .pie { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7.5px; color: #a79ea2; text-align: center; border-top: 1px solid #ececef; padding-top: 5px; }
</style>
</head>
<body>

<table class="cabecera">
    <tr>
        <td style="width:60%">
            <table>
                <tr>
                    ' . ($logo ? '<td class="logo"><img src="' . $logo . '" alt="Logo"></td>' : '') . '
                    <td class="empresa">
                        <h1>' . $esc($razon) . '</h1>
                        <div class="dato">
                            ' . ($nit ? 'NIT: ' . $esc($nit) . '<br>' : '') . '
                            ' . ($dir ? $esc($dir) . '<br>' : '') . '
                            ' . ($tel ? 'Tel: ' . $esc($tel) : '') . ($mail ? ' · ' . $esc($mail) : '') . '
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="titulo" style="width:40%">
            <div class="rotulo">Utilidad bruta</div>
            <div class="valor">' . $dinero($resumen['utilidad']) . '</div>
            <div class="dato" style="color:#555;font-size:8.5px;margin-top:2px">Margen global: ' . $pct($resumen['margen']) . '</div>
        </td>
    </tr>
</table>

<table class="filtros">
    <tr>
        <td><b>Sucursal:</b> ' . $esc($sucursalTxt) . '</td>
        <td><b>Caja:</b> ' . $esc($cajaTxt) . '</td>
        <td><b>Vendedor:</b> ' . $esc($vendedorTxt) . '</td>
    </tr>
    <tr>
        <td><b>Desde:</b> ' . $desdeTxt . '</td>
        <td><b>Hasta:</b> ' . $hastaTxt . '</td>
        <td><b>Generado:</b> ' . date('d/m/Y H:i') . '</td>
    </tr>
</table>

<div class="nota"><b>Nota:</b> no se incluyen ventas anuladas. La venta neta, el costo y la utilidad se calculan sin IVA.</div>

<table class="resumen">
    <tr>
        <td><span class="et">Ventas</span><span class="val">' . $resumen['ventas'] . '</span></td>
        <td><span class="et">Venta neta</span><span class="val">' . $dinero($resumen['venta_neta']) . '</span></td>
        <td><span class="et">Costo</span><span class="val ambar">' . $dinero($resumen['costo']) . '</span></td>
        <td><span class="et">Utilidad</span><span class="val verde">' . $dinero($resumen['utilidad']) . '</span></td>
        <td><span class="et">Margen</span><span class="val">' . $pct($resumen['margen']) . '</span></td>
        <td><span class="et">Vendedores</span><span class="val">' . $resumen['vendedores'] . '</span></td>
    </tr>
</table>

<table class="tabla">
    <thead>
        <tr>
            <th>Producto</th>
            <th style="width:100px">Categoría</th>
            <th class="r" style="width:50px">Cant.</th>
            <th class="r" style="width:70px">Venta</th>
            <th class="r" style="width:70px">Costo</th>
            <th class="r" style="width:70px">Utilidad</th>
            <th class="c" style="width:50px">Margen</th>
        </tr>
    </thead>
    <tbody>' . $filasP . '</tbody>
    <tfoot>
        <tr>
            <td colspan="2" class="r">Totales</td>
            <td class="r">' . number_format($totCant, 0, ',', '.') . '</td>
            <td class="r">' . $dinero($resumen['venta_neta']) . '</td>
            <td class="r">' . $dinero($resumen['costo']) . '</td>
            <td class="r verde">' . $dinero($resumen['utilidad']) . '</td>
            <td class="c">' . $pct($resumen['margen']) . '</td>
        </tr>
    </tfoot>
</table>

<div class="seccion">Detalle por venta</div>
<table class="tabla">
    <thead>
        <tr>
            <th class="c" style="width:32px">#</th>
            <th style="width:80px">Fecha</th>
            <th style="width:88px">Código</th>
            <th>Vendedor</th>
            <th style="width:90px">Caja</th>
            <th class="r" style="width:70px">Subtotal</th>
            <th class="r" style="width:70px">Costo</th>
            <th class="r" style="width:70px">Utilidad</th>
            <th class="c" style="width:50px">Margen</th>
        </tr>
    </thead>
    <tbody>' . $filasV . '</tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">Totales</td>
            <td class="r">' . $dinero($totVSub) . '</td>
            <td class="r">' . $dinero($totVCosto) . '</td>
            <td class="r verde">' . $dinero($totVUtilidad) . '</td>
            <td class="c">' . $pct($margenVentas) . '</td>
        </tr>
    </tfoot>
</table>

<div class="pie">
    ' . $esc($comercial) . ' · Reporte generado el ' . date('d/m/Y H:i') . '
</div>

</body>
</html>';

    require_once dirname(__DIR__, 2) . '/vendor/dompdf/autoload.inc.php';

    $opciones = new \Dompdf\Options();
    $opciones->set('isRemoteEnabled', false);
    $opciones->set('isHtml5ParserEnabled', true);
    $opciones->set('defaultFont', 'DejaVu Sans');
    $opciones->set('chroot', BASE_PATH);

    $dompdf = new \Dompdf\Dompdf($opciones);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    $nombre = 'reporte-utilidades-' . date('Ymd-His') . '.pdf';
    $dompdf->stream($nombre, ['Attachment' => 0]);
    exit;
} catch (PDOException $e) {
    error_log('reporte_utilidades_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('reporte_utilidades_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'No se pudo generar el reporte';
}