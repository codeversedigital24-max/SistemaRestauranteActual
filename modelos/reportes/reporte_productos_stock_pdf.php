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
    require_once __DIR__ . '/ProductoStockReporte.php';

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
        ? (isset($_GET['id_sucursal']) && $_GET['id_sucursal'] !== '' ? (int) $_GET['id_sucursal'] : 0)
        : (int) $usuario['id_sucursal'];

    if ($idSucursal <= 0 && $usuario['es_superadmin']) {
        $primera = $pdo->query("SELECT id_sucursal FROM sucursales WHERE estado = 1 ORDER BY id_sucursal LIMIT 1")->fetchColumn();
        $idSucursal = (int) ($primera ?: 0);
    }
    if ($idSucursal <= 0) {
        http_response_code(422);
        header('Content-Type: text/plain');
        echo 'Selecciona una sucursal';
        exit;
    }

    $estado      = (string) ($_GET['estado'] ?? '');
    $idCategoria = (int) ($_GET['id_categoria'] ?? 0);
    $buscar      = (string) ($_GET['buscar'] ?? '');

    if (!in_array($estado, ['', 'sin', 'bajo', 'optimo', 'sin_control'], true)) $estado = '';

    $modelo   = new ProductoStockReporte($pdo);
    $productos = $modelo->listar($idSucursal, $estado, $idCategoria, $buscar);
    $resumen   = $modelo->resumen($idSucursal);

    // Datos de sucursal
    $st = $pdo->prepare("SELECT nombre_sucursal, ciudad, direccion, telefono FROM sucursales WHERE id_sucursal = :id");
    $st->execute([':id' => $idSucursal]);
    $sucursalInfo = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    // Empresa
    $empresa = [];
    try {
        $empresa = $pdo->query(
            "SELECT razon_social, nombre_comercial, nit, direccion, telefono1,
                    email_principal, logo
               FROM configuracion_empresa
              ORDER BY id_config ASC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {}

    $esc    = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dinero = static fn(float $v): string => 'Bs ' . number_format($v, 2, ',', '.');
    $cant   = static function (float $v): string {
        $texto = number_format($v, 3, ',', '.');
        $texto = rtrim(rtrim($texto, '0'), ',');
        return $texto === '' ? '0' : $texto;
    };

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

    $estadoTxt = [
        ''           => 'Todos',
        'sin'        => 'Sin stock',
        'bajo'       => 'Bajo stock',
        'optimo'     => 'Stock óptimo',
        'sin_control'=> 'Sin control de stock',
    ][$estado] ?? 'Todos';

    $sucTxt = ($sucursalInfo['nombre_sucursal'] ?? 'Sucursal') .
              (!empty($sucursalInfo['ciudad']) ? ' · ' . $sucursalInfo['ciudad'] : '');

    $filas = '';
    $totStock = 0; $totFalta = 0; $totValor = 0;
    $i = 1;

    foreach ($productos as $p) {
        $totStock += (float) $p['stock'];
        $totFalta += (float) $p['deficit'];
        $totValor += (float) $p['valor_stock'];

        $estadoCell = '';
        switch ($p['estado_stock']) {
            case 'sin':         $estadoCell = '<span class="chip rojo">Sin stock</span>'; break;
            case 'bajo':        $estadoCell = '<span class="chip ambar">Bajo</span>'; break;
            case 'optimo':      $estadoCell = '<span class="chip verde">Óptimo</span>'; break;
            case 'sin_control': $estadoCell = '<span class="chip gris">Sin control</span>'; break;
        }

        $filas .=
            '<tr>' .
            '<td class="c">' . $i++ . '</td>' .
            '<td><b>' . $esc($p['nombre']) . '</b><br><span class="sub">' . $esc($p['codigo']) . '</span></td>' .
            '<td>' . $esc($p['categoria']) . '</td>' .
            '<td class="r b">' . $cant((float) $p['stock']) . '</td>' .
            '<td class="r">' . ($p['controla'] ? $cant((float) $p['stock_minimo']) : '—') . '</td>' .
            '<td class="r rojo">' . ($p['deficit'] > 0 ? $cant((float) $p['deficit']) : '—') . '</td>' .
            '<td class="r">' . $dinero((float) $p['precio']) . '</td>' .
            '<td class="r b">' . $dinero((float) $p['valor_stock']) . '</td>' .
            '<td class="c">' . $estadoCell . '</td>' .
            '</tr>';
    }

    if ($filas === '') {
        $filas = '<tr><td colspan="9" class="c">Sin productos en este filtro</td></tr>';
    }

    $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Productos en stock</title>
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
    .rojo { color: #b91c1c; }

    .chip { display: inline-block; font-size: 7px; padding: 1px 6px; border-radius: 8px; font-weight: bold; text-transform: uppercase; }
    .chip.rojo  { background: #fee2e2; color: #b91c1c; }
    .chip.ambar { background: #fef3c7; color: #92400e; }
    .chip.verde { background: #e6f4ea; color: #14803d; }
    .chip.gris  { background: #f3f4f6; color: #6b7280; }

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
                            ' . $esc($sucTxt) . '
                            ' . (!empty($sucursalInfo['telefono']) ? ' · Tel: ' . $esc($sucursalInfo['telefono']) : '') . '
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="titulo" style="width:40%">
            <div class="rotulo">Productos en stock</div>
            <div class="valor">' . $esc($estadoTxt) . '</div>
            <div class="dato" style="color:#555;font-size:8.5px;margin-top:2px">' . count($productos) . ' productos</div>
        </td>
    </tr>
</table>

<table class="filtros">
    <tr>
        <td><b>Sucursal:</b> ' . $esc($sucTxt) . '</td>
        <td><b>Filtro:</b> ' . $esc($estadoTxt) . '</td>
        <td><b>Generado:</b> ' . date('d/m/Y H:i') . '</td>
    </tr>
</table>

<table class="resumen">
    <tr>
        <td><span class="et">Total</span><span class="val">' . $resumen['total'] . '</span></td>
        <td><span class="et">Sin stock</span><span class="val rojo">' . $resumen['sin'] . '</span></td>
        <td><span class="et">Bajo</span><span class="val ambar">' . $resumen['bajo'] . '</span></td>
        <td><span class="et">Óptimo</span><span class="val verde">' . $resumen['optimo'] . '</span></td>
        <td><span class="et">Sin control</span><span class="val">' . $resumen['sin_control'] . '</span></td>
        <td><span class="et">Valor inventario</span><span class="val verde">' . $dinero((float) $resumen['valor']) . '</span></td>
    </tr>
</table>

<table class="tabla">
    <thead>
        <tr>
            <th class="c" style="width:26px">#</th>
            <th>Producto</th>
            <th style="width:90px">Categoría</th>
            <th class="r" style="width:58px">Stock global</th>
            <th class="r" style="width:50px">Mín.</th>
            <th class="r" style="width:50px">Falta</th>
            <th class="r" style="width:60px">Precio</th>
            <th class="r" style="width:70px">Valor</th>
            <th class="c" style="width:60px">Estado</th>
        </tr>
    </thead>
    <tbody>' . $filas . '</tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="r">Totales</td>
            <td class="r">' . $cant($totStock) . '</td>
            <td class="r">—</td>
            <td class="r rojo">' . $cant($totFalta) . '</td>
            <td class="r">—</td>
            <td class="r">' . $dinero($totValor) . '</td>
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

    $nombre = 'productos-stock-' . date('Ymd-His') . '.pdf';
    $dompdf->stream($nombre, ['Attachment' => 0]);
    exit;
} catch (PDOException $e) {
    error_log('reporte_productos_stock_pdf.php PDO: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain');
    echo 'Base de datos no disponible';
} catch (Throwable $e) {
    error_log('reporte_productos_stock_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'No se pudo generar el reporte';
}