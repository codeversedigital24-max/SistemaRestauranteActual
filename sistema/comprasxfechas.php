<?php

/**
 * =====================================================
 * COMPRAS POR FECHAS  (sistema/comprasxfechas.php)
 * -----------------------------------------------------
 * Reporte con filtros: fechas, sucursal, usuario,
 * forma de pago, estado.
 * Tarjetas, gráficas, tabla y exportación a PDF.
 *
 * Datos  : ../modelos/comprasxfechas/obtener_compras_reporte.php
 * PDF    : ../modelos/comprasxfechas/reporte_pdf.php
 * Red    : ../modelos/comprasxfechas/compras_reporte_api.js
 * UI     : ../modelos/comprasxfechas/compras_reporte.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Compras por fechas';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Compras por fechas (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_REP_COMPRAS_FECHAS);
$rmAcciones = rm_acciones(Permisos::ITEM_REP_COMPRAS_FECHAS);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .rep-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .rep-card {
            position: relative;
            padding: 14px 16px 14px 60px;
            background: linear-gradient(135deg, #ffffff, #fcfcfd);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 12px;
            overflow: hidden;
            transition: transform .15s, box-shadow .15s;
        }

        .rep-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        }

        .rep-card .rc-ico {
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

        .rep-card .rc-et {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .rep-card .rc-val {
            display: block;
            font-size: 20px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
        }

        .rep-card .rc-sub {
            display: block;
            font-size: 11.5px;
            color: var(--mod-texto, #6b6165);
            margin-top: 2px;
        }

        /* Colores por tarjeta */
        .rep-card.rc-contado .rc-ico {
            background: #e6f4ea;
            color: #14803d;
        }

        .rep-card.rc-credito .rc-ico {
            background: #fef3c7;
            color: #92400e;
        }

        .rep-card.rc-saldo .rc-ico {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* Barra de filtros */
        .rep-filtros {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .rep-filtros .rf-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 130px;
            min-width: 120px;
        }

        .rep-filtros label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0;
        }

        .rep-filtros select,
        .rep-filtros input {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .rep-filtros .rf-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
        }

        .rep-filtros .rf-acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        /* Gráficas en 2 columnas */
        .rep-graficas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        @media (max-width: 820px) {
            .rep-graficas {
                grid-template-columns: 1fr;
            }
        }

        .rep-chart {
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 10px;
            padding: 12px 14px;
            min-height: 220px;
        }

        .rep-chart h4 {
            margin: 0 0 10px;
            font-size: 12.5px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rep-chart h4 i {
            color: var(--mod-primaria, #d9232d);
        }

        .rep-chart canvas {
            width: 100% !important;
            height: 180px !important;
        }

        /* Tabla */
        .rep-tabla-wrap {
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 10px;
            padding: 12px 14px;
        }

        .rep-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .rep-tabla th {
            text-align: left;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--mod-texto-tenue, #a79ea2);
            padding: 8px 10px;
            background: var(--mod-claro, #f8f7f7);
            border-bottom: 1px solid var(--mod-linea, #ececef);
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .rep-tabla td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            vertical-align: middle;
        }

        .rep-tabla tfoot td {
            padding: 10px;
            border-top: 2px solid var(--mod-tinta);
            font-weight: 800;
            background: var(--mod-claro);
        }

        .rep-tabla tr:hover td {
            background: #fffafa;
        }

        .rep-tabla .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .rep-tabla .cod {
            font-family: monospace;
            font-size: 11.5px;
            background: var(--mod-claro);
            padding: 2px 7px;
            border-radius: 4px;
        }

        .rep-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .rep-chip.contado {
            background: #e6f4ea;
            color: #14803d;
        }

        .rep-chip.credito {
            background: #fef3c7;
            color: #92400e;
        }

        .rep-chip.registrada {
            background: #e6f4ea;
            color: #14803d;
        }

        .rep-chip.anulada {
            background: #fee2e2;
            color: #b91c1c;
        }

        .rep-acciones button {
            border: 1px solid var(--mod-linea-fuerte);
            background: #fff;
            color: var(--mod-texto, #6b6165);
            padding: 4px 8px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 11.5px;
            cursor: pointer;
        }

        .rep-acciones button:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }

        .rep-vacio {
            padding: 40px 14px;
            text-align: center;
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 13px;
        }

        .rep-tabla-scroll {
            max-height: 60vh;
            overflow: auto;
            border-radius: 8px;
            border: 1px solid var(--mod-linea);
        }
    </style>

    <script defer src="../modelos/comprasxfechas/compras_reporte_api.js"></script>
    <script defer src="../modelos/comprasxfechas/compras_reporte.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-calendar-range" aria-hidden="true"></i>
                    Compras por fechas
                </h3>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn-mod btn-mod-claro" id="btnPdf">
                        <i class="fa-solid fa-file-pdf"></i>
                        <span>Exportar PDF</span>
                    </button>
                </div>
            </div>

            <div class="card-body">

                <!-- Tarjetas resumen -->
                <div class="rep-tarjetas">
                    <div class="rep-card">
                        <span class="rc-ico"><i class="fa-solid fa-receipt"></i></span>
                        <span class="rc-et">Compras</span>
                        <span class="rc-val" id="rcCompras">0</span>
                    </div>
                    <div class="rep-card">
                        <span class="rc-ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="rc-et">Total invertido</span>
                        <span class="rc-val" id="rcTotal">Bs 0,00</span>
                    </div>
                    <div class="rep-card rc-contado">
                        <span class="rc-ico"><i class="fa-solid fa-money-bill-wave"></i></span>
                        <span class="rc-et">Al contado</span>
                        <span class="rc-val" id="rcContado">Bs 0,00</span>
                        <span class="rc-sub" id="rcContadoN">0 compras</span>
                    </div>
                    <div class="rep-card rc-credito">
                        <span class="rc-ico"><i class="fa-solid fa-credit-card"></i></span>
                        <span class="rc-et">Al crédito</span>
                        <span class="rc-val" id="rcCredito">Bs 0,00</span>
                        <span class="rc-sub" id="rcCreditoN">0 compras</span>
                    </div>
                    <div class="rep-card rc-saldo">
                        <span class="rc-ico"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                        <span class="rc-et">Saldo pendiente</span>
                        <span class="rc-val" id="rcSaldo">Bs 0,00</span>
                        <span class="rc-sub" id="rcPagado">Pagado: Bs 0,00</span>
                    </div>
                    <div class="rep-card">
                        <span class="rc-ico"><i class="fa-solid fa-truck"></i></span>
                        <span class="rc-et">Proveedores</span>
                        <span class="rc-val" id="rcProv">0</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="rep-filtros">
                    <div class="rf-campo" id="rfSucursalWrap">
                        <label for="rfSucursal">Sucursal</label>
                        <select id="rfSucursal">
                            <option value="">Todas</option>
                        </select>
                    </div>

                    <div class="rf-campo">
                        <label for="rfUsuario">Usuario</label>
                        <select id="rfUsuario">
                            <option value="">Todos</option>
                        </select>
                    </div>

                    <div class="rf-campo">
                        <label for="rfDesde">Desde</label>
                        <input type="date" id="rfDesde" />
                    </div>

                    <div class="rf-campo">
                        <label for="rfHasta">Hasta</label>
                        <input type="date" id="rfHasta" />
                    </div>

                    <div class="rf-campo">
                        <label for="rfForma">Forma de pago</label>
                        <select id="rfForma">
                            <option value="">Todas</option>
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select>
                    </div>

                    <div class="rf-campo">
                        <label for="rfEstado">Estado</label>
                        <select id="rfEstado">
                            <option value="">Todos</option>
                            <option value="registrada">Registradas</option>
                            <option value="anulada">Anuladas</option>
                        </select>
                    </div>

                    <div class="rf-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="rfFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="rfLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Gráficas -->
                <div class="rep-graficas">
                    <div class="rep-chart">
                        <h4><i class="fa-solid fa-chart-pie"></i> Contado vs crédito</h4>
                        <canvas id="chartFormaPago" width="400" height="180"></canvas>
                    </div>
                    <div class="rep-chart">
                        <h4><i class="fa-solid fa-chart-line"></i> Compras por día</h4>
                        <canvas id="chartPorDia" width="400" height="180"></canvas>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="rep-tabla-wrap">
                    <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                        <span class="mini-spin"></span> <span>Cargando compras...</span>
                    </div>

                    <div class="rep-tabla-scroll" id="repTablaScroll" style="display:none;">
                        <table class="rep-tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th>Proveedor</th>
                                    <th>Usuario</th>
                                    <th>Sucursal</th>
                                    <th class="num">Total</th>
                                    <th>Pago</th>
                                    <th class="num">Saldo</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="repTablaBody">
                                <tr>
                                    <td colspan="11" class="rep-vacio">Ajusta los filtros y pulsa Filtrar.</td>
                                </tr>
                            </tbody>
                            <tfoot id="repTablaFoot" style="display:none;">
                                <tr>
                                    <td colspan="6">Totales</td>
                                    <td class="num" id="footTotal">Bs 0,00</td>
                                    <td></td>
                                    <td class="num" id="footSaldo">Bs 0,00</td>
                                    <td colspan="2"></td>
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