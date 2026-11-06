<?php
/**
 * =====================================================
 * GET  modelos/comprasxfechas/reporte_pdf.php
 * -----------------------------------------------------
 * Genera un PDF vertical carta con el reporte filtrado.
 * =====================================================
 */

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
    require_once __DIR__ . '/CompraReporte.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) {
        http_response_code(401);
        header('Content-Type: text/plain');
        echo 'La sesión ya no es válida';
        exit;
    }
    rm_exigir_api('ver', Permisos::ITEM_REP_COMPRAS_FECHAS);

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
        'id_sucursal' => $idSucursal,
        'id_usuario'  => (int) ($_GET['id_usuario'] ?? 0),
        'desde'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : '',
        'hasta'       => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : '',
        'forma_pago'  => (string) ($_GET['forma_pago'] ?? ''),
        'estado'      => (string) ($_GET['estado'] ?? ''),
    ];

    $modelo  = new CompraReporte($pdo);
    $compras = $modelo->listar($filtros);
    $resumen = $modelo->resumen($filtros);

    // Configuración de la empresa
    $empresa = [];
    try {
        $empresa = $pdo->query(
            "SELECT razon_social, nombre_comercial, nit, direccion, telefono1,
                    email_principal, logo, moneda_principal
               FROM configuracion_empresa
              ORDER BY id_config ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Si la tabla no existe, seguimos sin cabecera enriquecida
    }

    $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dinero = static fn(float $v): string => 'Bs ' . number_format($v, 2, ',', '.');

    // Logo → base64
    $logo = null;
    if (!empty($empresa['logo'])) {
        $rutas = [
            BASE_PATH . '/' . ltrim((string) $empresa['logo'], '/\\'),
            BASE_PATH . '/assets/img/' . basename((string) $empresa['logo']),
            BASE_PATH . '/assets/img/configuracion/' . basename((string) $empresa['logo']),
        ];
        foreach ($rutas as $r) {
            if (is_file($r)) {
                $info = @getimagesize($r);
                if ($info) {
                    $logo = 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($r));
                    break;
                }
            }
        }
    }

    // Cabecera
    $razon   = (string) ($empresa['razon_social']     ?? 'Restaurante');
    $comercial = (string) ($empresa['nombre_comercial'] ?? '') ?: $razon;
    $nit     = (string) ($empresa['nit']               ?? '');
    $dir     = (string) ($empresa['direccion']         ?? '');
    $tel     = (string) ($empresa['telefono1']         ?? '');
    $mail    = (string) ($empresa['email_principal']   ?? '');

    $desdeTxt = $filtros['desde'] !== '' ? date('d/m/Y', strtotime($filtros['desde'])) : '—';
    $hastaTxt = $filtros['hasta'] !== '' ? date('d/m/Y', strtotime($filtros['hasta'])) : '—';
    $filtroForma = in_array($filtros['forma_pago'], ['contado','credito'], true) ? ucfirst($filtros['forma_pago']) : 'Todas';
    $filtroEstado = in_array($filtros['estado'], ['registrada','anulada'], true) ? ucfirst($filtros['estado']) : 'Todos';

    // Filas
    $filas = '';
    $totTotal = 0.0;
    $totSaldo = 0.0;

    foreach ($compras as $c) {
        $totTotal += (float) $c['total'];
        $totSaldo += (float) $c['saldo'];

        $chip = $c['forma_pago'] === 'credito' ? 'credito' : 'contado';
        $estado = $c['estado'] === 'anulada' ? '<span class="badge anulada">Anulada</span>' : '';

        $filas .=
            '<tr>' .
            '<td class="c">#' . (int) $c['id'] . '</td>' .
            '<td>' . $esc($c['fecha_compra']) . '</td>' .
            '<td>' . $esc($c['proveedor']) . '</td>' .
            '<td>' . $esc($c['usuario']) . '</td>' .
            '<td>' . $esc($c['sucursal']) . '</td>' .
            '<td class="r">' . $dinero((float) $c['total']) . '</td>' .
            '<td class="c"><span class="chip ' . $chip . '">' . $esc(ucfirst($c['forma_pago'])) . '</span></td>' .
            '<td class="r">' . ($c['saldo'] > 0 ? $dinero((float) $c['saldo']) : '—') . '</td>' .
            '<td class="c">' . $estado . '</td>' .
            '</tr>';
    }

    if ($filas === '') {
        $filas = '<tr><td colspan="9" class="c">Sin compras en el rango seleccionado.</td></tr>';
    }

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de compras</title>
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
    .filtros td { font-size: 8.5px; color: #555; padding: 3px 8px 3px 0; vertical-align: top; }
    .filtros b { color: #1c1416; }
    .resumen { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
    .resumen td { width: 16.66%; background: #f8f7f7; border: 1px solid #ececef; border-radius: 5px; padding: 6px 9px; vertical-align: top; }
    .resumen .et { display: block; font-size: 7.5px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; color: #a79ea2; }
    .resumen .val { display: block; font-size: 12px; font-weight: bold; color: #1c1416; margin-top: 1px; }
    .resumen .val.rojo { color: #b91c1c; }
    .resumen .val.verde { color: #14803d; }
    .resumen .val.ambar { color: #92400e; }
    table.tabla { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.tabla th { background: #1c1416; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 6px; text-align: left; }
    table.tabla td { padding: 5px 6px; border-bottom: 1px solid #ececef; font-size: 8.5px; vertical-align: middle; }
    table.tabla tfoot td { border-top: 2px solid #1c1416; font-weight: bold; font-size: 9.5px; padding: 7px 6px; }
    .r { text-align: right; }
    .c { text-align: center; }
    .chip { display: inline-block; font-size: 7.5px; padding: 1px 6px; border-radius: 8px; font-weight: bold; text-transform: uppercase; }
    .chip.contado { background: #e6f4ea; color: #14803d; }
    .chip.credito { background: #fef3c7; color: #92400e; }
    .badge.anulada { display: inline-block; background: #fee2e2; color: #b91c1c; font-size: 7.5px; padding: 1px 6px; border-radius: 8px; font-weight: bold; text-transform: uppercase; }
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
            <div class="rotulo">Reporte de compras</div>
            <div class="valor">' . $dinero($resumen['total']) . '</div>
            <div class="dato" style="color:#555;font-size:8.5px;margin-top:2px">' . $resumen['compras'] . ' compras registradas</div>
        </td>
    </tr>
</table>

<table class="filtros">
    <tr>
        <td><b>Desde:</b> ' . $desdeTxt . '</td>
        <td><b>Hasta:</b> ' . $hastaTxt . '</td>
        <td><b>Forma:</b> ' . $esc($filtroForma) . '</td>
        <td><b>Estado:</b> ' . $esc($filtroEstado) . '</td>
        <td><b>Generado:</b> ' . date('d/m/Y H:i') . '</td>
    </tr>
</table>

<table class="resumen">
    <tr>
        <td><span class="et">Compras</span><span class="val">' . $resumen['compras'] . '</span></td>
        <td><span class="et">Contado</span><span class="val verde">' . $dinero($resumen['contado']) . '</span><span class="et">' . $resumen['compras_contado'] . ' compras</span></td>
        <td><span class="et">Crédito</span><span class="val ambar">' . $dinero($resumen['credito']) . '</span><span class="et">' . $resumen['compras_credito'] . ' compras</span></td>
        <td><span class="et">Saldo pendiente</span><span class="val rojo">' . $dinero($resumen['saldo_pendiente']) . '</span></td>
        <td><span class="et">Pagado</span><span class="val">' . $dinero($resumen['abonos'] + $resumen['pagado_posterior']) . '</span></td>
        <td><span class="et">Proveedores</span><span class="val">' . $resumen['proveedores'] . '</span></td>
    </tr>
</table>

<table class="tabla">
    <thead>
        <tr>
            <th class="c" style="width:32px">#</th>
            <th style="width:70px">Fecha</th>
            <th>Proveedor</th>
            <th style="width:90px">Usuario</th>
            <th style="width:80px">Sucursal</th>
            <th class="r" style="width:70px">Total</th>
            <th class="c" style="width:60px">Pago</th>
            <th class="r" style="width:60px">Saldo</th>
            <th class="c" style="width:50px">Estado</th>
        </tr>
    </thead>
    <tbody>' . $filas . '</tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">Totales</td>
            <td class="r">' . $dinero($totTotal) . '</td>
            <td></td>
            <td class="r">' . $dinero($totSaldo) . '</td>
            <td></td>
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

    $nombre = 'reporte-compras-' . date('Ymd-His') . '.pdf';
    $dompdf->stream($nombre, ['Attachment' => 0]);
    exit;
} catch (PDOException $e) {
    error_log('reporte_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('reporte_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'No se pudo generar el reporte';
}