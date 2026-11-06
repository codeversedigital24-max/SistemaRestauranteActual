<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Ventas · Utilidades';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Reporte de utilidades (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_REP_UTILIDADES);
$rmAcciones = rm_acciones(Permisos::ITEM_REP_UTILIDADES);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .u-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .u-card {
            position: relative;
            padding: 14px 16px 14px 62px;
            background: linear-gradient(135deg, #ffffff, #fcfcfd);
            border: 1px solid #ececef;
            border-radius: 12px;
            transition: transform .15s, box-shadow .15s;
        }

        .u-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        }

        .u-card .u-ico {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #e74c3c);
            font-size: 16px;
        }

        .u-card .u-et {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .u-card .u-val {
            display: block;
            font-size: 21px;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
        }

        .u-card .u-sub {
            display: block;
            font-size: 11.5px;
            color: #6b6165;
            margin-top: 2px;
        }

        .u-card.verde .u-ico {
            background: #e6f4ea;
            color: #14803d;
        }

        .u-card.ambar .u-ico {
            background: #fef3c7;
            color: #92400e;
        }

        .u-card.azul .u-ico {
            background: #dbeafe;
            color: #1e40af;
        }

        .u-card.morado .u-ico {
            background: #f3e8ff;
            color: #7a2ea3;
        }

        .u-card.rojo .u-ico {
            background: #fee2e2;
            color: #b91c1c;
        }

        .u-card .u-val.verde {
            color: #14803d;
        }

        .u-card .u-val.rojo {
            color: #b91c1c;
        }

        /* Filtros */
        .u-filtros {
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

        .u-filtros .uf-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 130px;
            min-width: 120px;
        }

        .u-filtros label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #a79ea2;
            margin: 0;
        }

        .u-filtros select,
        .u-filtros input {
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

        .u-filtros .uf-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
        }

        .u-filtros .uf-acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        /* Gráficas */
        .u-graficas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        @media (max-width: 820px) {
            .u-graficas {
                grid-template-columns: 1fr;
            }
        }

        .u-chart {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 10px;
            padding: 12px 14px;
            min-height: 240px;
        }

        .u-chart h4 {
            margin: 0 0 10px;
            font-size: 12.5px;
            font-weight: 800;
            color: #1c1416;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .u-chart h4 i {
            color: var(--mod-primaria);
        }

        .u-chart canvas {
            width: 100% !important;
            height: 190px !important;
        }

        /* Tabs de la tabla (Por producto / Por venta) */
        .u-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid #ececef;
            margin-bottom: 10px;
        }

        .u-tabs button {
            border: none;
            background: transparent;
            padding: 8px 14px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: #a79ea2;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .u-tabs button.activa {
            color: var(--mod-primaria);
            border-bottom-color: var(--mod-primaria);
        }

        /* Tabla */
        .u-tabla-wrap {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .u-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .u-tabla th {
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

        .u-tabla td {
            padding: 8px 10px;
            border-bottom: 1px solid #ececef;
            vertical-align: middle;
        }

        .u-tabla tfoot td {
            padding: 10px;
            border-top: 2px solid #1c1416;
            font-weight: 800;
            background: #f8f7f7;
        }

        .u-tabla tr:hover td {
            background: #fffafa;
        }

        .u-tabla .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .u-tabla .verde {
            color: #14803d;
            font-weight: 800;
        }

        .u-tabla .rojo {
            color: #b91c1c;
            font-weight: 800;
        }

        .u-tabla .cod {
            font-family: monospace;
            font-size: 11.5px;
            background: #f8f7f7;
            padding: 2px 7px;
            border-radius: 4px;
        }

        .u-tabla .margen {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
        }

        .u-tabla .margen.alto {
            background: #e6f4ea;
            color: #14803d;
        }

        .u-tabla .margen.medio {
            background: #fef3c7;
            color: #92400e;
        }

        .u-tabla .margen.bajo {
            background: #fee2e2;
            color: #b91c1c;
        }

        .u-acciones button {
            border: 1px solid #d9d9e0;
            background: #fff;
            color: #6b6165;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 11.5px;
            cursor: pointer;
        }

        .u-acciones button:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }

        .u-tabla-scroll {
            max-height: 60vh;
            overflow: auto;
            border-radius: 8px;
            border: 1px solid #ececef;
        }

        .u-vacio {
            padding: 40px 14px;
            text-align: center;
            color: #a79ea2;
            font-size: 13px;
        }

        @media (max-width: 620px) {
            .u-tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .u-card {
                padding: 12px 12px 12px 52px;
            }

            .u-card .u-ico {
                left: 10px;
                width: 30px;
                height: 30px;
                font-size: 14px;
            }

            .u-card .u-val {
                font-size: 16px;
            }

            .u-filtros {
                flex-direction: column;
            }

            .u-filtros .uf-campo {
                flex: 1 1 100%;
            }
        }
    </style>

    <script defer src="../modelos/reportes/utilidades_api.js"></script>
    <script defer src="../modelos/reportes/utilidades.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-hand-holding-dollar" aria-hidden="true"></i>
                    Ventas · Utilidades
                </h3>
                <button type="button" class="btn-mod btn-mod-claro" id="btnPdf">
                    <i class="fa-solid fa-file-pdf"></i> <span>Exportar PDF</span>
                </button>
            </div>

            <div class="card-body">

                <!-- Tarjetas -->
                <div class="u-tarjetas">
                    <div class="u-card">
                        <span class="u-ico"><i class="fa-solid fa-receipt"></i></span>
                        <span class="u-et">Ventas</span>
                        <span class="u-val" id="uVentas">0</span>
                        <span class="u-sub" id="uVendedores">0 vendedores</span>
                    </div>
                    <div class="u-card azul">
                        <span class="u-ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="u-et">Venta neta</span>
                        <span class="u-val" id="uVentaNeta">Bs 0,00</span>
                        <span class="u-sub" id="uCajas">0 cajas</span>
                    </div>
                    <div class="u-card ambar">
                        <span class="u-ico"><i class="fa-solid fa-boxes-stacked"></i></span>
                        <span class="u-et">Costo total</span>
                        <span class="u-val" id="uCosto">Bs 0,00</span>
                    </div>
                    <div class="u-card verde">
                        <span class="u-ico"><i class="fa-solid fa-arrow-trend-up"></i></span>
                        <span class="u-et">Utilidad bruta</span>
                        <span class="u-val verde" id="uUtilidad">Bs 0,00</span>
                    </div>
                    <div class="u-card morado">
                        <span class="u-ico"><i class="fa-solid fa-percent"></i></span>
                        <span class="u-et">Margen</span>
                        <span class="u-val" id="uMargen">0 %</span>
                        <span class="u-sub" id="uSucursales">0 sucursales</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="u-filtros">
                    <div class="uf-campo" id="ufSucursalWrap">
                        <label for="ufSucursal">Sucursal</label>
                        <select id="ufSucursal">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="uf-campo">
                        <label for="ufCaja">Caja</label>
                        <select id="ufCaja">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="uf-campo">
                        <label for="ufUsuario">Vendedor</label>
                        <select id="ufUsuario">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="uf-campo">
                        <label for="ufDesde">Desde</label>
                        <input type="date" id="ufDesde" />
                    </div>
                    <div class="uf-campo">
                        <label for="ufHasta">Hasta</label>
                        <input type="date" id="ufHasta" />
                    </div>
                    <div class="uf-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="ufFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="ufLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Nota -->
                <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#6b6165;background:#f8f7f7;border:1px solid #ececef;border-radius:8px;padding:7px 10px;margin-bottom:10px;">
                    <i class="fa-solid fa-circle-info" style="color:#d9232d"></i>
                    <span>El reporte no incluye ventas anuladas. La venta neta, el costo y la utilidad se calculan sin IVA.</span>
                </div>

                <!-- Gráficas -->
                <div class="u-graficas">
                    <div class="u-chart">
                        <h4><i class="fa-solid fa-chart-column"></i> Utilidad por sucursal</h4>
                        <canvas id="chartSucursal" width="400" height="190"></canvas>
                    </div>
                    <div class="u-chart">
                        <h4><i class="fa-solid fa-ranking-star"></i> Top vendedores por utilidad</h4>
                        <canvas id="chartVend" width="400" height="190"></canvas>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="u-tabla-wrap">
                    <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                        <span class="mini-spin"></span> <span>Cargando utilidades...</span>
                    </div>

                    <div class="u-tabs" role="tablist">
                        <button type="button" class="activa" data-tab="productos">
                            <i class="fas fa-box"></i> Por producto
                        </button>
                        <button type="button" data-tab="ventas">
                            <i class="fas fa-receipt"></i> Por venta
                        </button>
                    </div>

                    <!-- Tabla por producto -->
                    <div id="tabProductos">
                        <div class="u-tabla-scroll">
                            <table class="u-tabla">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th class="num">Cant.</th>
                                        <th class="num">Venta</th>
                                        <th class="num">Costo</th>
                                        <th class="num">Utilidad</th>
                                        <th class="num">Margen</th>
                                    </tr>
                                </thead>
                                <tbody id="uProductosBody">
                                    <tr>
                                        <td colspan="7" class="u-vacio">Ajusta los filtros y pulsa Filtrar.</td>
                                    </tr>
                                </tbody>
                                <tfoot id="uProductosFoot" style="display:none;">
                                    <tr>
                                        <td colspan="3">Totales</td>
                                        <td class="num" id="pVenta">Bs 0,00</td>
                                        <td class="num" id="pCosto">Bs 0,00</td>
                                        <td class="num verde" id="pUtilidad">Bs 0,00</td>
                                        <td class="num" id="pMargen">0 %</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla por venta -->
                    <div id="tabVentas" style="display:none;">
                        <div class="u-tabla-scroll">
                            <table class="u-tabla">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Fecha</th>
                                        <th>Código</th>
                                        <th>Vendedor</th>
                                        <th>Caja</th>
                                        <th class="num">Venta</th>
                                        <th class="num">Costo</th>
                                        <th class="num">Utilidad</th>
                                        <th class="num">Margen</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="uVentasBody">
                                    <tr>
                                        <td colspan="10" class="u-vacio">Ajusta los filtros y pulsa Filtrar.</td>
                                    </tr>
                                </tbody>
                                <tfoot id="uVentasFoot" style="display:none;">
                                    <tr>
                                        <td colspan="5">Totales</td>
                                        <td class="num" id="vVenta">Bs 0,00</td>
                                        <td class="num" id="vCosto">Bs 0,00</td>
                                        <td class="num verde" id="vUtilidad">Bs 0,00</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>