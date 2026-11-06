<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Productos más vendidos';

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
        .pv-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .pv-card {
            position: relative;
            padding: 14px 16px 14px 60px;
            background: linear-gradient(135deg, #ffffff, #fcfcfd);
            border: 1px solid #ececef;
            border-radius: 12px;
            transition: transform .15s, box-shadow .15s;
        }

        .pv-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        }

        .pv-card .ico {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #d9232d);
            font-size: 15px;
        }

        .pv-card .et {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .pv-card .val {
            display: block;
            font-size: 20px;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
        }

        .pv-card .sub {
            display: block;
            font-size: 11px;
            color: #6b6165;
            margin-top: 2px;
        }

        .pv-card.verde .ico {
            background: #e6f4ea;
            color: #14803d;
        }

        .pv-card.azul .ico {
            background: #dbeafe;
            color: #1e40af;
        }

        .pv-card.ambar .ico {
            background: #fef3c7;
            color: #92400e;
        }

        .pv-card.morado .ico {
            background: #f3e8ff;
            color: #7a2ea3;
        }

        .pv-card.rojo .ico {
            background: #fee2e2;
            color: #b91c1c;
        }

        .pv-filtros {
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

        .pv-filtros .fc {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 130px;
            min-width: 120px;
        }

        .pv-filtros label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .pv-filtros select,
        .pv-filtros input {
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

        .pv-filtros .acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
        }

        .pv-filtros .acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        .pv-graficas {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        @media (max-width: 900px) {
            .pv-graficas {
                grid-template-columns: 1fr;
            }
        }

        .pv-chart {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 10px;
            padding: 12px 14px;
            min-height: 260px;
        }

        .pv-chart h4 {
            margin: 0 0 10px;
            font-size: 12.5px;
            font-weight: 800;
            color: #1c1416;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pv-chart h4 i {
            color: var(--mod-primaria);
        }

        .pv-chart canvas {
            width: 100% !important;
            height: 210px !important;
        }

        .pv-tabla-wrap {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .pv-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .pv-tabla th {
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

        .pv-tabla td {
            padding: 8px 10px;
            border-bottom: 1px solid #ececef;
            vertical-align: middle;
        }

        .pv-tabla tfoot td {
            padding: 10px;
            border-top: 2px solid #1c1416;
            font-weight: 800;
            background: #f8f7f7;
        }

        .pv-tabla tr:hover td {
            background: #fffafa;
        }

        .pv-tabla .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .pv-tabla .verde {
            color: #14803d;
            font-weight: 800;
        }

        .pv-rank {
            display: inline-grid;
            place-items: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #e8e1e2;
            color: #6b6165;
            font-size: 11px;
            font-weight: 800;
        }

        .pv-rank.gold {
            background: #fde68a;
            color: #92400e;
        }

        .pv-rank.silver {
            background: #e5e7eb;
            color: #374151;
        }

        .pv-rank.bronze {
            background: #fed7aa;
            color: #9a3412;
        }

        .pv-prod {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pv-prod .foto {
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

        .pv-prod .foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pv-prod .info {
            min-width: 0;
        }

        .pv-prod .info b {
            display: block;
            font-size: 12px;
            color: #1c1416;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pv-prod .info span {
            display: block;
            font-size: 10px;
            color: #a79ea2;
        }

        .pv-part {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 800;
        }

        .pv-part .bar {
            width: 46px;
            height: 5px;
            border-radius: 3px;
            background: #ececef;
            overflow: hidden;
            position: relative;
        }

        .pv-part .bar>span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, var(--mod-primaria), #f59095);
        }

        .pv-vacio {
            padding: 40px 14px;
            text-align: center;
            color: #a79ea2;
            font-size: 13px;
        }

        .pv-scroll {
            max-height: 62vh;
            overflow: auto;
            border-radius: 8px;
            border: 1px solid #ececef;
        }

        @media (max-width: 640px) {
            .pv-tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .pv-card {
                padding: 12px 12px 12px 52px;
            }

            .pv-card .ico {
                left: 10px;
                width: 30px;
                height: 30px;
                font-size: 13px;
            }

            .pv-card .val {
                font-size: 16px;
            }

            .pv-filtros {
                flex-direction: column;
                align-items: stretch;
            }

            .pv-filtros .fc {
                flex: 1 1 100%;
            }
        }
    </style>

    <script defer src="../modelos/reportes/productos_vendidos_api.js"></script>
    <script defer src="../modelos/reportes/productos_vendidos.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-fire"></i>
                    Productos más vendidos
                </h3>
                <button type="button" class="btn-mod btn-mod-claro" id="pvPdf">
                    <i class="fa-solid fa-file-pdf"></i> <span>Exportar PDF</span>
                </button>
            </div>

            <div class="card-body">

                <!-- Tarjetas resumen -->
                <div class="pv-tarjetas">
                    <div class="pv-card">
                        <span class="ico"><i class="fa-solid fa-box"></i></span>
                        <span class="et">Productos</span>
                        <span class="val" id="pvProductos">0</span>
                        <span class="sub" id="pvVentas">0 ventas</span>
                    </div>
                    <div class="pv-card azul">
                        <span class="ico"><i class="fa-solid fa-cubes"></i></span>
                        <span class="et">Unidades</span>
                        <span class="val" id="pvUnidades">0</span>
                        <span class="sub" id="pvVendedores">0 vendedores</span>
                    </div>
                    <div class="pv-card verde">
                        <span class="ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="et">Venta neta</span>
                        <span class="val" id="pvVenta">Bs 0,00</span>
                        <span class="sub" id="pvDescuento">Desc.: Bs 0,00</span>
                    </div>
                    <div class="pv-card ambar" id="pvCardCosto" style="display:none;">
                        <span class="ico"><i class="fa-solid fa-boxes-stacked"></i></span>
                        <span class="et">Costo</span>
                        <span class="val" id="pvCosto">Bs 0,00</span>
                    </div>
                    <div class="pv-card morado" id="pvCardUtilidad" style="display:none;">
                        <span class="ico"><i class="fa-solid fa-arrow-trend-up"></i></span>
                        <span class="et">Utilidad</span>
                        <span class="val verde" id="pvUtilidad">Bs 0,00</span>
                        <span class="sub" id="pvMargen">Margen: 0 %</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="pv-filtros">
                    <div class="fc" id="pvFiltroSucursalWrap">
                        <label for="pvSucursal">Sucursal</label>
                        <select id="pvSucursal">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="fc">
                        <label for="pvCategoria">Categoría</label>
                        <select id="pvCategoria">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="fc">
                        <label for="pvDesde">Desde</label>
                        <input type="date" id="pvDesde" />
                    </div>
                    <div class="fc">
                        <label for="pvHasta">Hasta</label>
                        <input type="date" id="pvHasta" />
                    </div>
                    <div class="acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="pvFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="pvLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Gráficas -->
                <div class="pv-graficas">
                    <div class="pv-chart">
                        <h4><i class="fa-solid fa-chart-column"></i> Top 10 por cantidad</h4>
                        <canvas id="pvChartTop" width="800" height="210"></canvas>
                    </div>
                    <div class="pv-chart">
                        <h4><i class="fa-solid fa-chart-pie"></i> Ventas por categoría</h4>
                        <canvas id="pvChartCat" width="400" height="210"></canvas>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="pv-tabla-wrap">
                    <div class="tabla-carga" id="pvCarga" role="status" aria-live="polite">
                        <span class="mini-spin"></span> <span>Cargando productos...</span>
                    </div>

                    <div class="pv-scroll" id="pvTablaScroll" style="display:none;">
                        <table class="pv-tabla">
                            <thead>
                                <tr>
                                    <th style="width:44px">#</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th class="num">Cant.</th>
                                    <th class="num">Veces</th>
                                    <th class="num">P. prom.</th>
                                    <th class="num">Venta</th>
                                    <th class="num">Desc.</th>
                                    <th class="num">Particip.</th>
                                    <th class="num pv-col-costo" style="display:none;">Costo</th>
                                    <th class="num pv-col-util" style="display:none;">Utilidad</th>
                                    <th class="num pv-col-marg" style="display:none;">Margen</th>
                                </tr>
                            </thead>
                            <tbody id="pvBody">
                                <tr>
                                    <td colspan="12" class="pv-vacio">Ajusta los filtros y pulsa Filtrar.</td>
                                </tr>
                            </tbody>
                            <tfoot id="pvFoot" style="display:none;">
                                <tr>
                                    <td colspan="3">Totales</td>
                                    <td class="num" id="pvFootCant">0</td>
                                    <td class="num" id="pvFootVeces">0</td>
                                    <td class="num">—</td>
                                    <td class="num" id="pvFootVenta">Bs 0,00</td>
                                    <td class="num" id="pvFootDesc">Bs 0,00</td>
                                    <td class="num">100 %</td>
                                    <td class="num pv-col-costo" id="pvFootCosto" style="display:none;">Bs 0,00</td>
                                    <td class="num pv-col-util" id="pvFootUtil" style="display:none;">Bs 0,00</td>
                                    <td class="num pv-col-marg" id="pvFootMarg" style="display:none;">0 %</td>
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