<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Productos en Stock';

include 'validacionSesion.php';
include 'validacion_permisos.php';

if (!rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS)) {
    $_SESSION['mensaje_error'] = 'No tienes permisos para ver este reporte';
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .ps-tabs {
            display: flex;
            gap: 4px;
            overflow-x: auto;
            padding: 0 4px 10px;
            scrollbar-width: none;
        }

        .ps-tabs::-webkit-scrollbar {
            display: none;
        }

        .ps-tabs button {
            flex: 0 0 auto;
            height: 38px;
            padding: 0 16px;
            border-radius: 999px;
            border: 1.5px solid #ececef;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: #6b6165;
            cursor: pointer;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: .14s ease;
        }

        .ps-tabs button:hover {
            border-color: #f0b6b9;
            color: #d9232d;
        }

        .ps-tabs button.activa {
            background: var(--mod-primaria);
            border-color: var(--mod-primaria);
            color: #fff;
        }

        .ps-tabs button .cnt {
            font-size: 10.5px;
            font-weight: 800;
            background: rgba(0, 0, 0, .15);
            padding: 1px 7px;
            border-radius: 999px;
        }

        .ps-tabs button:not(.activa) .cnt {
            background: #f2eeee;
            color: #6b6165;
        }

        .ps-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .ps-card {
            position: relative;
            padding: 14px 16px 14px 60px;
            background: linear-gradient(135deg, #ffffff, #fcfcfd);
            border: 1px solid #ececef;
            border-radius: 12px;
            transition: transform .15s, box-shadow .15s;
            cursor: pointer;
        }

        .ps-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        }

        .ps-card.activa {
            box-shadow: 0 0 0 2px var(--mod-primaria);
        }

        .ps-card .ico {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: var(--mod-primaria-suave);
            color: var(--mod-primaria);
            font-size: 15px;
        }

        .ps-card .et {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .ps-card .val {
            display: block;
            font-size: 20px;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
        }

        .ps-card .sub {
            display: block;
            font-size: 11px;
            color: #6b6165;
            margin-top: 2px;
        }

        .ps-card.rojo .ico {
            background: #fee2e2;
            color: #b91c1c;
        }

        .ps-card.ambar .ico {
            background: #fef3c7;
            color: #92400e;
        }

        .ps-card.verde .ico {
            background: #e6f4ea;
            color: #14803d;
        }

        .ps-card.gris .ico {
            background: #f3f4f6;
            color: #6b7280;
        }

        .ps-card.azul .ico {
            background: #dbeafe;
            color: #1e40af;
        }

        .ps-filtros {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .ps-filtros .fc {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 130px;
            min-width: 120px;
        }

        .ps-filtros label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .ps-filtros select,
        .ps-filtros input {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            border: 1.5px solid #d9d9e0;
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: #1c1416;
        }

        .ps-filtros .acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
        }

        .ps-filtros .acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        .ps-tabla-wrap {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .ps-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .ps-tabla th {
            text-align: left;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #a79ea2;
            padding: 8px 10px;
            background: #f8f7f7;
            border-bottom: 1px solid #ececef;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .ps-tabla td {
            padding: 8px 10px;
            border-bottom: 1px solid #ececef;
            vertical-align: middle;
        }

        .ps-tabla tfoot td {
            padding: 10px;
            border-top: 2px solid #1c1416;
            font-weight: 800;
            background: #f8f7f7;
        }

        .ps-tabla tr:hover td {
            background: #fffafa;
        }

        .ps-tabla .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .ps-prod {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ps-prod .foto {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: #f4f0f0;
            display: grid;
            place-items: center;
            color: #c7cdd8;
            font-size: 14px;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .ps-prod .foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ps-prod .info {
            min-width: 0;
        }

        .ps-prod .info b {
            display: block;
            font-size: 12px;
            color: #1c1416;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ps-prod .info span {
            display: block;
            font-size: 10px;
            color: #a79ea2;
        }

        .ps-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .ps-chip.sin {
            background: #fee2e2;
            color: #b91c1c;
        }

        .ps-chip.bajo {
            background: #fef3c7;
            color: #92400e;
        }

        .ps-chip.optimo {
            background: #e6f4ea;
            color: #14803d;
        }

        .ps-chip.sin_control {
            background: #f3f4f6;
            color: #6b7280;
        }

        .ps-stock-val {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .ps-stock-val.sin {
            color: #b91c1c;
        }

        .ps-stock-val.bajo {
            color: #92400e;
        }

        .ps-stock-val.optimo {
            color: #14803d;
        }

        .ps-vacio {
            padding: 40px 14px;
            text-align: center;
            color: #a79ea2;
            font-size: 13px;
        }

        .ps-scroll {
            max-height: 62vh;
            overflow: auto;
            border-radius: 8px;
            border: 1px solid #ececef;
        }

        @media (max-width: 640px) {
            .ps-tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .ps-card {
                padding: 12px 12px 12px 52px;
            }

            .ps-card .ico {
                left: 10px;
                width: 30px;
                height: 30px;
                font-size: 13px;
            }

            .ps-card .val {
                font-size: 16px;
            }

            .ps-filtros {
                flex-direction: column;
                align-items: stretch;
            }

            .ps-filtros .fc {
                flex: 1 1 100%;
            }
        }
    </style>

    <script defer src="../modelos/reportes/productos_stock_api.js"></script>
    <script defer src="../modelos/reportes/productos_stock.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
                    Productos en stock
                </h3>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="btn-mod btn-mod-claro" id="psPdf">
                        <i class="fa-solid fa-file-pdf"></i> <span>PDF del filtro</span>
                    </button>
                    <button type="button" class="btn-mod btn-mod-primario" id="psPdfTodo">
                        <i class="fa-solid fa-file-pdf"></i> <span>PDF completo</span>
                    </button>
                </div>
            </div>

            <div class="card-body">

                <!-- Tarjetas resumen (sirven de filtro rápido) -->
                <div class="ps-tarjetas">
                    <div class="ps-card azul" data-estado="">
                        <span class="ico"><i class="fa-solid fa-cubes"></i></span>
                        <span class="et">Total</span>
                        <span class="val" id="psTotal">0</span>
                        <span class="sub">productos activos</span>
                    </div>
                    <div class="ps-card rojo" data-estado="sin">
                        <span class="ico"><i class="fa-solid fa-circle-xmark"></i></span>
                        <span class="et">Sin stock</span>
                        <span class="val" id="psSin">0</span>
                        <span class="sub">agotados</span>
                    </div>
                    <div class="ps-card ambar" data-estado="bajo">
                        <span class="ico"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        <span class="et">Bajo stock</span>
                        <span class="val" id="psBajo">0</span>
                        <span class="sub">en alerta</span>
                    </div>
                    <div class="ps-card verde" data-estado="optimo">
                        <span class="ico"><i class="fa-solid fa-circle-check"></i></span>
                        <span class="et">Stock óptimo</span>
                        <span class="val" id="psOptimo">0</span>
                        <span class="sub">sobre el mínimo</span>
                    </div>
                    <div class="ps-card gris" data-estado="sin_control">
                        <span class="ico"><i class="fa-solid fa-infinity"></i></span>
                        <span class="et">Sin control</span>
                        <span class="val" id="psSinControl">0</span>
                        <span class="sub">no descuenta stock</span>
                    </div>
                    <div class="ps-card verde" style="cursor:default;">
                        <span class="ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="et">Valor inventario</span>
                        <span class="val" id="psValor">Bs 0,00</span>
                        <span class="sub">stock × costo</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="ps-filtros">
                    <div class="fc" id="psFiltroSucursalWrap">
                        <label for="psSucursal">Sucursal</label>
                        <select id="psSucursal">
                            <option value="">—</option>
                        </select>
                    </div>
                    <div class="fc">
                        <label for="psCategoria">Categoría</label>
                        <select id="psCategoria">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="fc">
                        <label for="psBuscar">Buscar</label>
                        <input type="search" id="psBuscar" placeholder="Nombre o código…" />
                    </div>
                    <div class="acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="psFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="psLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Nota: el stock es global (tabla productos) -->
                <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#6b6165;background:#f8f7f7;border:1px solid #ececef;border-radius:8px;padding:7px 10px;margin-bottom:8px;">
                    <i class="fa-solid fa-circle-info" style="color:#d9232d"></i>
                    <span>El stock mostrado es el <b>global del producto</b> (tabla productos), igual que en el POS. La sucursal se usa para precio, costo y stock mínimo.</span>
                </div>

                <!-- Tabla -->
                <div class="ps-tabla-wrap">
                    <div class="tabla-carga" id="psCarga" role="status" aria-live="polite">
                        <span class="mini-spin"></span> <span>Cargando productos...</span>
                    </div>

                    <div class="ps-scroll" id="psTablaScroll" style="display:none;">
                        <table class="ps-tabla">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th class="num" title="Stock global del producto (tabla productos)">Stock global</th>
                                    <th class="num">Mín.</th>
                                    <th class="num">Falta</th>
                                    <th class="num">Precio</th>
                                    <th class="num">Valor</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="psBody">
                                <tr>
                                    <td colspan="8" class="ps-vacio">Ajusta los filtros y pulsa Filtrar.</td>
                                </tr>
                            </tbody>
                            <tfoot id="psFoot" style="display:none;">
                                <tr>
                                    <td colspan="2">Totales</td>
                                    <td class="num" id="psFootStock">0</td>
                                    <td class="num">—</td>
                                    <td class="num" id="psFootFalta">0</td>
                                    <td class="num">—</td>
                                    <td class="num" id="psFootValor">Bs 0,00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>