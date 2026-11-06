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
    require_once __DIR__ . '/ProductoVendidoReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) {
        http_response_code(401);
        header('Content-Type: text/plain');
        echo 'La sesión ya no es válida';
        exit;
    }
    if (!ContextoCompras::puedeInventario($pdo, $usuario)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'No tienes permisos';
        exit;
    }

    $idSucursal = $usuario['es_superadmin']
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : null)
        : (int) $usuario['id_sucursal'];

    $filtros = [
        'id_sucursal'  => $idSucursal,
        'id_categoria' => (int) ($_GET['id_categoria'] ?? 0),
        'id_usuario'   => (int) ($_GET['id_usuario'] ?? 0),
        'desde'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : '',
        'hasta'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : '',
    ];

    $verUtilidad = $usuario['es_superadmin'] || in_array((int) $usuario['nivel_permiso'], [1, 2], true);
    $modelo = new ProductoVendidoReporte($pdo, $verUtilidad);
    $ranking = $modelo->ranking($filtros);
    $resumen = $modelo->resumen($filtros);

    $empresa = [];
    try {
        $empresa = $pdo->query(
            "SELECT razon_social, nombre_comercial, nit, direccion, telefono1,
                    email_principal, logo, moneda_principal
               FROM configuracion_empresa
              ORDER BY id_config ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }

    $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dinero = static fn(float $v): string => 'Bs ' . number_format($v, 2, ',', '.');
    $pct = static fn(float $v): string => number_format($v, 2, ',', '.') . ' %';
    $cant = static fn(float $v): string => number_format($v, 0, ',', '.');

    $logo = null;
    if (!empty($empresa['logo'])) {
        foreach (
            [
                BASE_PATH . '/' . ltrim((string) $empresa['logo'], '/\\'),
                BASE_PATH . '/assets/img/' . basename((string) $empresa['logo']),
                BASE_PATH . '/assets/img/configuracion/' . basename((string) $empresa['logo']),
            ] as $r
        ) {
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

    $filas = '';
    $totCant = 0;
    $totVenta = 0;
    $totDesc = 0;
    $totCosto = 0;
    $totUtil = 0;
    $pos = 1;

    foreach ($ranking as $p) {
        $totCant  += (float) $p['cantidad'];
        $totVenta += (float) $p['venta'];
        $totDesc  += (float) $p['descuento'];
        if ($verUtilidad) {
            $totCosto += (float) ($p['costo'] ?? 0);
            $totUtil  += (float) ($p['utilidad'] ?? 0);
        }

        $cols = '';
        if ($verUtilidad) {
            $cols .=
                '<td class="r">' . $dinero((float) ($p['costo'] ?? 0)) . '</td>' .
                '<td class="r b">' . $dinero((float) ($p['utilidad'] ?? 0)) . '</td>' .
                '<td class="c">' . $pct((float) ($p['margen'] ?? 0)) . '</td>';
        }

        $filas .=
            '<tr>' .
            '<td class="c">' . $pos++ . '</td>' .
            '<td><b>' . $esc($p['nombre']) . '</b><br><span class="sub">' . $esc($p['codigo']) . '</span></td>' .
            '<td>' . $esc($p['categoria']) . '</td>' .
            '<td class="r">' . $cant((float) $p['cantidad']) . '</td>' .
            '<td class="r">' . $dinero((float) $p['venta']) . '</td>' .
            '<td class="r">' . ($p['descuento'] > 0 ? '− ' . $dinero((float) $p['descuento']) : '—') . '</td>' .
            '<td class="r">' . $pct((float) $p['participacion']) . '</td>' .
            $cols .
            '</tr>';
    }

    if ($filas === '') {
        $filas = '<tr><td colspan="' . ($verUtilidad ? 10 : 7) . '" class="c">Sin productos vendidos en el rango.</td></tr>';
    }

    $colsTot = '';
    if ($verUtilidad) {
        $colsTot .=
            '<td class="r">' . $dinero($totCosto) . '</td>' .
            '<td class="r b">' . $dinero($totUtil) . '</td>' .
            '<td class="c">' . $pct($totVenta > 0 ? ($totUtil / $totVenta) * 100 : 0) . '</td>';
    }

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Productos más vendidos</title>
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
    .titulo .valor { font-size: 16px; font-weight: bold; color: #d9232d; margin-top: 2px; }

    .filtros { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .filtros td { font-size: 8.5px; color: #555; padding: 3px 8px 3px 0; }
    .filtros b { color: #1c1416; }

       .resumen { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
    .resumen td { width: 16.66%; background: #f8f7f7; border: 1px solid #ececef; border-radius: 5px; padding: 6px 9px; vertical-align: top; }
    .resumen .et { display: block; font-size: 7.5px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; color: #a79ea2; }
    .resumen .val { display: block; font-size: 12px; font-weight: bold; color: #1c1416; margin-top: 1px; }
    .resumen .val.verde { color: #14803d; }
    .resumen .val.ambar { color: #92400e; }
    .resumen .val.rojo  { color: #b91c1c; }

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
            <div class="rotulo">Productos más vendidos</div>
            <div class="valor">' . $cant($resumen['unidades']) . ' unidades</div>
            <div class="dato" style="color:#555;font-size:8.5px;margin-top:2px">' . $resumen['productos'] . ' productos · ' . $resumen['ventas'] . ' ventas</div>
        </td>
    </tr>
</table>

<table class="filtros">
    <tr>
        <td><b>Desde:</b> ' . $desdeTxt . '</td>
        <td><b>Hasta:</b> ' . $hastaTxt . '</td>
        <td><b>Generado:</b> ' . date('d/m/Y H:i') . '</td>
    </tr>
</table>

<table class="resumen">
    <tr>
        <td><span class="et">Productos</span><span class="val">' . $resumen['productos'] . '</span></td>
        <td><span class="et">Unidades</span><span class="val">' . $cant($resumen['unidades']) . '</span></td>
        <td><span class="et">Venta neta</span><span class="val verde">' . $dinero($resumen['venta']) . '</span></td>
        <td><span class="et">Descuentos</span><span class="val rojo">' . $dinero($resumen['descuento']) . '</span></td>
        ' . ($verUtilidad
        ? '<td><span class="et">Costo</span><span class="val ambar">' . $dinero((float) $resumen['costo']) . '</span></td>' .
        '<td><span class="et">Utilidad</span><span class="val verde">' . $dinero((float) $resumen['utilidad']) . '</span></td>'
        : '<td><span class="et">Vendedores</span><span class="val">' . $resumen['vendedores'] . '</span></td>' .
        '<td><span class="et">Ventas</span><span class="val">' . $resumen['ventas'] . '</span></td>') . '
    </tr>
</table>

<table class="tabla">
    <thead>
        <tr>
            <th class="c" style="width:26px">#</th>
            <th>Producto</th>
            <th style="width:90px">Categoría</th>
            <th class="r" style="width:50px">Cant.</th>
            <th class="r" style="width:70px">Venta</th>
            <th class="r" style="width:60px">Desc.</th>
            <th class="r" style="width:50px">Partic.</th>
            ' . ($verUtilidad
        ? '<th class="r" style="width:60px">Costo</th>' .
        '<th class="r" style="width:60px">Utilidad</th>' .
        '<th class="c" style="width:50px">Margen</th>'
        : '') . '
        </tr>
    </thead>
    <tbody>' . $filas . '</tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="r">Totales</td>
            <td class="r">' . $cant($totCant) . '</td>
            <td class="r">' . $dinero($totVenta) . '</td>
            <td class="r">' . $dinero($totDesc) . '</td>
            <td class="r">100 %</td>
            ' . $colsTot . '
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

    $nombre = 'productos-vendidos-' . date('Ymd-His') . '.pdf';
    $dompdf->stream($nombre, ['Attachment' => 0]);
    exit;
} catch (PDOException $e) {
    error_log('reporte_productos_vendidos_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('reporte_productos_vendidos_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'No se pudo generar el reporte';
}
