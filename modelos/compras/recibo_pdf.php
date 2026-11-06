<?php
/**
 * =====================================================
 * GET  modelos/compras/recibo_pdf.php
 * -----------------------------------------------------
 * Recibo de compra en PDF (vertical, tamaño carta).
 *
 *   · Logo de la sucursal (o el de la empresa como respaldo)
 *   · Datos de empresa, sucursal y proveedor
 *   · Detalle de productos, totales
 *   · Abono inicial, pagos posteriores y saldo (crédito)
 *
 * Query: id (compra), descargar=1 para forzar descarga.
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

rm_exigir_api_alguno('ver', [Permisos::ITEM_INV_COMPRAS, Permisos::ITEM_INV_NUEVA_COMPRA, Permisos::ITEM_INV_CUENTAS_PAGAR]);

$idCompra = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$descargar = !empty($_GET['descargar']);

if ($idCompra <= 0) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Compra no válida';
    exit;
}

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once __DIR__ . '/ContextoCompras.php';
    require_once __DIR__ . '/Compra.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);

    if (!$usuario) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'La sesión ya no es válida';
        exit;
    }

    $compra = (new Compra($pdo))->obtener($idCompra);

    if (!$compra) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Compra no encontrada';
        exit;
    }

    if (!$usuario['es_superadmin'] && (int) $compra['id_sucursal'] !== (int) $usuario['id_sucursal']) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No puedes ver compras de otra sucursal';
        exit;
    }

    $empresa = $pdo->query(
        "SELECT razon_social, nombre_comercial, nit, direccion, telefono1, email_principal,
                ciudad, pais, moneda_principal, logo
           FROM configuracion_empresa
          ORDER BY id_config ASC
          LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $sucursal = $pdo->prepare(
        "SELECT nombre_sucursal, codigo_sucursal, direccion, telefono, email, ciudad, logo
           FROM sucursales
          WHERE id_sucursal = :id
          LIMIT 1"
    );
    $sucursal->execute([':id' => (int) $compra['id_sucursal']]);
    $datosSucursal = $sucursal->fetch(PDO::FETCH_ASSOC) ?: [];

    $moneda = trim((string) ($empresa['moneda_principal'] ?? 'BOB')) ?: 'BOB';
    $simbolo = $moneda === 'BOB' ? 'Bs' : $moneda;

    $dinero = static function (float $valor) use ($simbolo): string {
        return $simbolo . ' ' . number_format($valor, 2, ',', '.');
    };

    $cantidad = static function (float $valor): string {
        return number_format($valor, fmod($valor, 1.0) !== 0.0 ? 3 : 0, ',', '.');
    };

    // ---------------------------------------------
    // Logo: sucursal -> empresa -> por defecto
    // ---------------------------------------------
    $logo = null;
    $baseSistema = dirname(__DIR__, 2) . '/sistema/images/';

    foreach ([$datosSucursal['logo'] ?? '', $empresa['logo'] ?? '', 'default-logo.png'] as $archivo) {
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

    $formas = [
        'contado'       => 'Contado',
        'credito'       => 'Crédito',
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

    $metodos = [
        'efectivo'      => 'Efectivo',
        'transferencia' => 'Transferencia',
        'qr'            => 'QR',
        'otro'          => 'Otro',
    ];

    $esc = static function ($valor): string {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $razon = (string) ($empresa['razon_social'] ?? '') ?: 'Restaurante Moderno';
    $comercial = (string) ($empresa['nombre_comercial'] ?? '') ?: $razon;

    $lineas = '';

    foreach ($compra['detalle'] as $i => $d) {
        $lineas .=
            '<tr>' .
            '<td class="c">' . ($i + 1) . '</td>' .
            '<td><b>' . $esc($d['producto']) . '</b><span class="sub">' . $esc($d['codigo'] ?? '') . '</span></td>' .
            '<td class="r">' . $cantidad((float) $d['cantidad']) . '</td>' .
            '<td class="r">' . $dinero((float) $d['costo_unitario']) . '</td>' .
            '<td class="r">' . $dinero((float) $d['precio_venta']) . '</td>' .
            '<td class="r b">' . $dinero((float) $d['subtotal']) . '</td>' .
            '</tr>';
    }

    if ($lineas === '') {
        $lineas = '<tr><td colspan="6" class="c">Sin líneas registradas</td></tr>';
    }

    $pagos = $compra['pagos'] ?? [];
    $filasPagos = '';

    foreach ($pagos as $p) {
        $filasPagos .=
            '<tr>' .
            '<td>' . $esc($p['fecha_pago']) . '</td>' .
            '<td>' . $esc($metodos[$p['metodo_pago']] ?? $p['metodo_pago']) . '</td>' .
            '<td>' . $esc(!empty($p['numero_guia']) ? $p['numero_guia'] : '—') . '</td>' .
            '<td>' . $esc($p['referencia'] !== '' ? $p['referencia'] : '—') . '</td>' .
            '<td class="r">' . $dinero((float) $p['monto']) . '</td>' .
            '</tr>';
    }

    $bloqueCredito = '';

    if (!empty($compra['es_credito'])) {
        $bloqueCredito =
            '<div class="credito">' .
            '<div class="credito-titulo">Condición de crédito</div>' .
            '<table class="credito-tabla">' .
            '<tr><td>Total de la compra</td><td class="r b">' . $dinero((float) $compra['total']) . '</td></tr>' .
            '<tr><td>Abono inicial</td><td class="r">− ' . $dinero((float) $compra['abono']) . '</td></tr>' .
            '<tr><td>Pagos posteriores (' . count($pagos) . ')</td><td class="r">− ' . $dinero((float) $compra['pagado']) . '</td></tr>' .
            '<tr class="saldo"><td>Saldo pendiente</td><td class="r">' . $dinero((float) $compra['saldo']) . '</td></tr>' .
            '</table>' .
            ($filasPagos !== ''
                ? '<div class="credito-titulo" style="margin-top:8px">Pagos registrados</div>' .
                  '<table class="pagos"><thead><tr><th>Fecha</th><th>Método</th><th>N° guía</th><th>Referencia</th><th class="r">Monto</th></tr></thead><tbody>' .
                  $filasPagos . '</tbody></table>'
                : '') .
            '</div>';
    }

    $estadoCompra = (string) $compra['estado'] === 'registrada' ? 'Registrada' : 'Anulada';
    $estadoClase = (string) $compra['estado'] === 'registrada' ? 'ok' : 'anulada';

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo de compra #' . (int) $compra['id'] . '</title>
<style>
    @page { margin: 28px 32px 46px 32px; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1c1416; margin: 0; }
    .cabecera { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .cabecera td { vertical-align: top; }
    .logo { width: 145px; }
    .logo img { max-width: 145px; max-height: 62px; }
    .empresa h1 { font-size: 15px; margin: 0 0 2px; color: #1c1416; }
    .empresa .comercial { font-size: 10px; color: #6b6165; margin-bottom: 5px; }
    .empresa .dato { font-size: 8.5px; color: #555; line-height: 1.45; }
    .caja-recibo { border: 1px solid #d9232d; border-radius: 6px; padding: 8px 12px; width: 190px; text-align: center; }
    .caja-recibo .rotulo { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #a79ea2; }
    .caja-recibo .numero { font-size: 17px; font-weight: bold; color: #d9232d; margin: 1px 0; }
    .caja-recibo .estado { display: inline-block; font-size: 7.5px; font-weight: bold; text-transform: uppercase; padding: 2px 7px; border-radius: 8px; }
    .estado.ok { background: #e5f6ea; color: #24703a; }
    .estado.anulada { background: #fdeaea; color: #a32020; }
    .caja-recibo .fecha { font-size: 8.5px; color: #555; margin-top: 4px; }
    .titulo-seccion { font-size: 8px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; color: #a79ea2; margin: 0 0 4px; }
    .datos { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 12px; }
    .datos td { width: 50%; vertical-align: top; background: #f8f7f7; border: 1px solid #ececef; border-radius: 5px; padding: 7px 10px; }
    .datos .nombre { font-size: 10.5px; font-weight: bold; }
    .datos .linea { font-size: 8.5px; color: #555; line-height: 1.4; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.items th { background: #1c1416; color: #fff; font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 7px; text-align: left; }
    table.items td { padding: 6px 7px; border-bottom: 1px solid #ececef; font-size: 9px; vertical-align: top; }
    table.items .sub { display: block; font-size: 7.5px; color: #a79ea2; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .totales { width: 260px; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
    .totales td { padding: 3px 0; font-size: 9.5px; color: #555; }
    .totales tr.total td { border-top: 2px solid #1c1416; padding-top: 7px; font-size: 12px; color: #1c1416; }
    .totales tr.total td.monto { color: #d9232d; font-weight: bold; font-size: 13px; }
    .credito { margin-top: 12px; border: 1px solid #d5e5fb; background: #eef5ff; border-radius: 6px; padding: 8px 12px; }
    .credito-titulo { font-size: 8px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; color: #28527a; margin-bottom: 3px; }
    table.credito-tabla { width: 100%; border-collapse: collapse; }
    table.credito-tabla td { padding: 2px 0; font-size: 9.5px; color: #28527a; }
    table.credito-tabla tr.saldo td { border-top: 1px solid #b9d2f0; padding-top: 5px; font-weight: bold; color: #d9232d; font-size: 11px; }
    table.pagos { width: 100%; border-collapse: collapse; margin-top: 2px; }
    table.pagos th { text-align: left; font-size: 7.5px; text-transform: uppercase; color: #7c8fa5; padding: 3px 4px; border-bottom: 1px solid #cfe0f5; }
    table.pagos td { font-size: 8.5px; padding: 3px 4px; border-bottom: 1px solid #e2ecf9; color: #28527a; }
    .observaciones { margin-top: 12px; padding: 7px 10px; background: #f8f7f7; border-left: 3px solid #d9232d; font-size: 8.5px; color: #555; }
    .firmas { width: 100%; border-collapse: collapse; margin-top: 34px; }
    .firmas td { width: 50%; text-align: center; padding: 0 18px; }
    .firma-linea { border-top: 1px solid #1c1416; padding-top: 4px; font-size: 8.5px; color: #555; }
    .pie { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #a79ea2; border-top: 1px solid #ececef; padding-top: 5px; }
</style>
</head>
<body>

<table class="cabecera">
    <tr>
        <td style="width: 55%">
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
        <td style="width: 45%; text-align: right">
            <div class="caja-recibo">
                <div class="rotulo">Recibo de compra</div>
                <div class="numero">#' . (int) $compra['id'] . '</div>
                <span class="estado ' . $estadoClase . '">' . $esc($estadoCompra) . '</span>
                <div class="fecha">' . $esc($compra['fecha_compra']) . ' · ' . $esc($compra['creado']) . '</div>
            </div>
        </td>
    </tr>
</table>

<table class="datos">
    <tr>
        <td>
            <div class="titulo-seccion">Sucursal</div>
            <div class="nombre">' . $esc($datosSucursal['nombre_sucursal'] ?? $compra['sucursal']) . '</div>
            <div class="linea">
                ' . (!empty($datosSucursal['codigo_sucursal']) ? 'Código: ' . $esc($datosSucursal['codigo_sucursal']) . '<br>' : '') . '
                ' . (!empty($datosSucursal['direccion']) ? $esc($datosSucursal['direccion']) . '<br>' : '') . '
                ' . (!empty($datosSucursal['ciudad']) ? $esc($datosSucursal['ciudad']) : '') . '
                ' . (!empty($datosSucursal['telefono']) ? ' · Tel: ' . $esc($datosSucursal['telefono']) : '') . '
            </div>
        </td>
        <td>
            <div class="titulo-seccion">Proveedor</div>
            <div class="nombre">' . $esc($compra['proveedor']) . '</div>
            <div class="linea">
                ' . (!empty($compra['proveedor_nit']) ? 'NIT: ' . $esc($compra['proveedor_nit']) . '<br>' : '') . '
                ' . (!empty($compra['proveedor_telefono']) ? 'Tel: ' . $esc($compra['proveedor_telefono']) . '<br>' : '') . '
                ' . (!empty($compra['proveedor_contacto']) ? 'Contacto: ' . $esc($compra['proveedor_contacto']) : '') . '
            </div>
        </td>
    </tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-bottom:8px">
    <tr>
        <td style="font-size:8.5px;color:#555"><b>Documento:</b> ' . $esc($documentos[$compra['tipo_documento']] ?? $compra['tipo_documento']) . ($compra['numero_documento'] !== '' ? ' · ' . $esc($compra['numero_documento']) : '') . '</td>
        <td style="font-size:8.5px;color:#555;text-align:center"><b>Forma de pago:</b> ' . $esc($formas[$compra['forma_pago']] ?? $compra['forma_pago']) . '</td>
        <td style="font-size:8.5px;color:#555;text-align:right"><b>Registró:</b> ' . $esc($compra['usuario'] ?: '—') . '</td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th class="c" style="width:26px">#</th>
            <th>Producto</th>
            <th class="r" style="width:52px">Cant.</th>
            <th class="r" style="width:72px">Costo</th>
            <th class="r" style="width:72px">P. venta</th>
            <th class="r" style="width:78px">Subtotal</th>
        </tr>
    </thead>
    <tbody>' . $lineas . '</tbody>
</table>

<table class="totales">
    <tr><td>Subtotal</td><td class="r">' . $dinero((float) $compra['subtotal']) . '</td></tr>
    <tr><td>Descuento</td><td class="r">− ' . $dinero((float) $compra['descuento']) . '</td></tr>
    <tr><td>Impuesto (' . number_format((float) $compra['impuesto_porcentaje'], 2, ',', '.') . ' %)</td><td class="r">' . $dinero((float) $compra['impuesto']) . '</td></tr>
    <tr class="total"><td>Total</td><td class="r monto">' . $dinero((float) $compra['total']) . '</td></tr>
</table>

' . $bloqueCredito . '

' . (!empty($compra['observaciones']) ? '<div class="observaciones"><b>Observaciones:</b> ' . $esc($compra['observaciones']) . '</div>' : '') . '

<table class="firmas">
    <tr>
        <td><div class="firma-linea">Recibido por</div></td>
        <td><div class="firma-linea">Entregado por</div></td>
    </tr>
</table>

<div class="pie">
    ' . $esc($comercial) . ' · Documento generado el ' . date('d/m/Y H:i') . ' · ' . $esc($moneda) . '
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

    $nombre = 'recibo-compra-' . (int) $compra['id'] . '.pdf';

    $dompdf->stream($nombre, ['Attachment' => $descargar ? 1 : 0]);
    exit;
} catch (PDOException $e) {
    error_log('recibo_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('recibo_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el recibo';
}
