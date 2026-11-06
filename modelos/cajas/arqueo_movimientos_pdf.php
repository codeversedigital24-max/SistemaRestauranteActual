<?php
/**
 * =====================================================
 * GET  modelos/cajas/arqueo_movimientos_pdf.php
 * -----------------------------------------------------
 * Reporte de movimientos de caja (tamaño CARTA).
 *
 *   · Datos de la empresa, sucursal, caja y turno
 *   · Resumen del turno y totales por medio de pago
 *   · Ventas cobradas en el arqueo
 *   · Egresos / gastos registrados
 *
 * Sirve tanto para arqueos abiertos como cerrados: es el
 * mismo contenido que se ve en el modal de movimientos.
 *
 * Query: id (arqueo) · descargar=1
 * Guardia: privilegio VER · Ítem Arqueo de caja (9)
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

rm_exigir_api('ver', Permisos::ITEM_VENTAS_ARQUEO);

$idArqueo  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

    $resumen     = $modelo->resumenCierre($idArqueo);
    $ventas      = $modelo->ventasDeArqueo($idArqueo);
    $movimientos = $modelo->movimientosDeArqueo($idArqueo);

    if ($resumen === []) {
        $errorTexto(500, 'No se pudo preparar el resumen del arqueo');
    }

    // ---- Empresa ----
    $empresa = [];
    try {
        $empresa = $pdo->query(
            "SELECT razon_social, nombre_comercial, nit, direccion, telefono1,
                    email_principal, logo, moneda_principal
               FROM configuracion_empresa
              ORDER BY id_config ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('arqueo_movimientos_pdf.php empresa: ' . $e->getMessage());
    }

    $moneda  = trim((string) ($empresa['moneda_principal'] ?? 'BOB')) ?: 'BOB';
    $simbolo = $moneda === 'BOB' ? 'Bs' : $moneda;

    $dinero = static fn(float $v): string => $simbolo . ' ' . number_format($v, 2, ',', '.');
    $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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

    $razon     = trim((string) ($empresa['razon_social'] ?? '')) ?: 'Restaurante Moderno';
    $comercial = trim((string) ($empresa['nombre_comercial'] ?? '')) ?: $razon;
    $nit       = (string) ($empresa['nit'] ?? '');
    $dir       = (string) ($empresa['direccion'] ?? '');
    $tel       = (string) ($empresa['telefono1'] ?? '');
    $mail      = (string) ($empresa['email_principal'] ?? '');

    // ---- Filas de ventas ----
    $filasVentas = '';
    $totalVentas = 0.0;

    foreach ($ventas as $v) {
        $detallePago = (string) $v['metodo_pago_label'];

        if (!empty($v['pago_detalle'])) {
            $detallePago .= ' · ' . $v['pago_detalle'];
        }

        if (!empty($v['referencia'])) {
            $detallePago .= ' · Ref. ' . $v['referencia'];
        }

        if (!$v['anulada']) {
            $totalVentas += (float) $v['total'];
        }

        $filasVentas .= '<tr>'
            . '<td class="c">' . $esc($v['hora']) . '</td>'
            . '<td>' . $esc($v['codigo']) . ($v['anulada'] ? ' <span class="anulada">ANULADA</span>' : '') . '</td>'
            . '<td>' . $esc($v['tipo_pedido_label']) . '</td>'
            . '<td>' . $esc($detallePago) . '</td>'
            . '<td>' . $esc($v['usuario']) . '</td>'
            . '<td class="r">' . $dinero((float) $v['total']) . '</td>'
            . '</tr>';
    }

    if ($filasVentas === '') {
        $filasVentas = '<tr><td colspan="6" class="c">Sin ventas registradas en este turno.</td></tr>';
    }

    // ---- Filas de egresos / gastos ----
    $filasEgresos = '';
    $totalEgresos = 0.0;

    foreach ($movimientos as $m) {
        $totalEgresos += (float) $m['monto'];

        $filasEgresos .= '<tr>'
            . '<td class="c">' . $esc($m['fecha']) . '</td>'
            . '<td>' . $esc($m['tipo_label']) . '</td>'
            . '<td>' . $esc($m['concepto'])
            . (!empty($m['referencia']) ? ' <span class="tenue">Ref. ' . $esc($m['referencia']) . '</span>' : '')
            . (!empty($m['observacion']) ? '<br><span class="tenue">' . $esc($m['observacion']) . '</span>' : '')
            . '</td>'
            . '<td>' . $esc($m['metodo_pago_label']) . '</td>'
            . '<td>' . $esc($m['usuario']) . '</td>'
            . '<td class="r">' . $dinero((float) $m['monto']) . '</td>'
            . '</tr>';
    }

    if ($filasEgresos === '') {
        $filasEgresos = '<tr><td colspan="6" class="c">Sin egresos ni gastos registrados en este turno.</td></tr>';
    }

    // ---- Diferencia ----
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

    $estadoTxt = $resumen['estado'] === 'cerrado' ? 'Cerrado' : 'Abierto';

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Movimientos de caja · Arqueo ' . $esc((string) $arqueo['id']) . '</title>
<style>
    @page { margin: 22px 24px 40px 24px; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1c1416; margin: 0; }
    .r { text-align: right; }
    .c { text-align: center; }
    .tenue { color: #a79ea2; font-size: 7.5px; }

    .cabecera { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .cabecera td { vertical-align: top; }
    .logo { width: 130px; }
    .logo img { max-width: 130px; max-height: 60px; }
    .empresa h1 { font-size: 14px; margin: 0 0 2px; }
    .empresa .dato { font-size: 8.5px; color: #555; line-height: 1.4; }
    .titulo { text-align: right; }
    .titulo .rotulo { font-size: 8.5px; letter-spacing: .8px; text-transform: uppercase; color: #a79ea2; }
    .titulo .valor { font-size: 16px; font-weight: bold; color: #d9232d; margin-top: 2px; }
    .titulo .dato { font-size: 8.5px; color: #555; margin-top: 2px; }

    .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .meta td { font-size: 8.5px; color: #555; padding: 2px 8px 2px 0; vertical-align: top; }
    .meta b { color: #1c1416; }

    .resumen { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 -5px 12px; }
    .resumen td { width: 16.66%; background: #f8f7f7; border: 1px solid #ececef; border-radius: 5px; padding: 6px 8px; vertical-align: top; }
    .resumen .et { display: block; font-size: 7.2px; font-weight: bold; letter-spacing: .4px; text-transform: uppercase; color: #a79ea2; }
    .resumen .val { display: block; font-size: 11.5px; font-weight: bold; color: #1c1416; margin-top: 1px; }
    .resumen .val.verde { color: #14803d; }
    .resumen .val.rojo { color: #b91c1c; }

    .seccion { font-size: 8px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; color: #555; margin: 12px 0 4px; }

    table.tabla { width: 100%; border-collapse: collapse; margin-top: 2px; }
    table.tabla th { background: #1c1416; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 6px; text-align: left; }
    table.tabla td { padding: 5px 6px; border-bottom: 1px solid #ececef; font-size: 8.5px; vertical-align: middle; }
    table.tabla tfoot td { border-top: 2px solid #1c1416; font-weight: bold; font-size: 9.5px; padding: 7px 6px; }
    .anulada { display: inline-block; background: #fee2e2; color: #b91c1c; font-size: 7px; padding: 0 5px; border-radius: 6px; font-weight: bold; }

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
            <div class="rotulo">Movimientos de caja</div>
            <div class="valor">Turno #' . (int) $arqueo['id'] . '</div>
            <div class="dato">' . $esc($resumen['caja_codigo'] . ' · ' . $resumen['caja_nombre']) . ' · ' . $esc($estadoTxt) . '</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><b>Sucursal:</b> ' . $esc($resumen['sucursal']) . ($resumen['ciudad'] !== '' ? ' · ' . $esc($resumen['ciudad']) : '') . '</td>
        <td><b>Apertura:</b> ' . $esc($resumen['fecha_apertura']) . '</td>
        <td><b>Abrió:</b> ' . $esc($resumen['usuario_apertura']) . '</td>
    </tr>
    <tr>
        <td><b>Cierre:</b> ' . $esc((string) ($resumen['fecha_cierre'] ?? '—')) . '</td>
        <td><b>Cerró:</b> ' . $esc($resumen['usuario_cierre'] !== '' ? $resumen['usuario_cierre'] : '—') . '</td>
        <td><b>Generado:</b> ' . date('d/m/Y H:i') . '</td>
    </tr>
</table>

<table class="resumen">
    <tr>
        <td><span class="et">Monto inicial</span><span class="val">' . $dinero((float) $resumen['monto_apertura']) . '</span></td>
        <td><span class="et">Ventas</span><span class="val verde">' . $dinero((float) $resumen['total_ventas']) . '</span></td>
        <td><span class="et">Gastos / egresos</span><span class="val rojo">− ' . $dinero((float) $resumen['total_egresos']) . '</span></td>
        <td><span class="et">Esperado en caja</span><span class="val">' . $dinero((float) $resumen['efectivo_esperado']) . '</span></td>
        <td><span class="et">Monto contado</span><span class="val">' . ($arqueo['monto_cierre'] !== null ? $dinero((float) $arqueo['monto_cierre']) : '—') . '</span></td>
        <td><span class="et">' . $esc($etiquetaDiferencia) . '</span><span class="val">' . ($diferencia !== null ? $dinero((float) $diferencia) : '—') . '</span></td>
    </tr>
</table>

<div class="seccion">Ventas del turno (' . count($ventas) . ')</div>
<table class="tabla">
    <thead>
        <tr>
            <th class="c" style="width:44px">Hora</th>
            <th style="width:86px">Ticket</th>
            <th style="width:70px">Tipo</th>
            <th>Pago</th>
            <th style="width:90px">Cajero</th>
            <th class="r" style="width:70px">Total</th>
        </tr>
    </thead>
    <tbody>' . $filasVentas . '</tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">Total ventas</td>
            <td class="r">' . $dinero($totalVentas) . '</td>
        </tr>
    </tfoot>
</table>

<div class="seccion">Egresos y gastos (' . count($movimientos) . ')</div>
<table class="tabla">
    <thead>
        <tr>
            <th class="c" style="width:86px">Fecha</th>
            <th style="width:54px">Tipo</th>
            <th>Concepto</th>
            <th style="width:80px">Pago</th>
            <th style="width:90px">Registró</th>
            <th class="r" style="width:70px">Monto</th>
        </tr>
    </thead>
    <tbody>' . $filasEgresos . '</tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">Total egresos / gastos</td>
            <td class="r">' . $dinero($totalEgresos) . '</td>
        </tr>
    </tfoot>
</table>

<div class="pie">
    ' . $esc($comercial) . ' · Movimientos de caja generados el ' . date('d/m/Y H:i') . '
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

    $nombre = 'arqueo-movimientos-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $arqueo['id']) . '.pdf';
    $dompdf->stream($nombre, ['Attachment' => $descargar ? 1 : 0]);
    exit;
} catch (PDOException $e) {
    error_log('arqueo_movimientos_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('arqueo_movimientos_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el reporte de movimientos';
}
