<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Pedidos por fechas';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Ventas por fechas (mismo
// criterio que los demás reportes de ventas/productos).
rm_exigir_permiso('ver', Permisos::ITEM_REP_VENTAS_FECHAS);
$rmAcciones = rm_acciones(Permisos::ITEM_REP_VENTAS_FECHAS);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>
        window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;
    </script>
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
            border: 1px solid #ececef;
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
            color: #a79ea2;
        }

        .rep-card .rc-val {
            display: block;
            font-size: 20px;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
        }

        .rep-card .rc-sub {
            display: block;
            font-size: 11.5px;
            color: #6b6165;
            margin-top: 2px;
        }

        .rep-card.rc-mesa .rc-ico {
            background: #e6f4ea;
            color: #14803d;
        }

        .rep-card.rc-online .rc-ico {
            background: #dbeafe;
            color: #1e40af;
        }

        .rep-card.rc-cancelado .rc-ico {
            background: #fee2e2;
            color: #b91c1c;
        }

        .rep-card.rc-total .rc-ico {
            background: #fef3c7;
            color: #92400e;
        }

        .rep-card.rc-promedio .rc-ico {
            background: #f3e8ff;
            color: #7a2ea3;
        }

        /* Filtros */
        .rep-filtros {
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
            color: #a79ea2;
            margin: 0;
        }

        .rep-filtros select,
        .rep-filtros input {
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

        /* Tabla */
        .rep-tabla-wrap {
            background: #fff;
            border: 1px solid #ececef;
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
            color: #a79ea2;
            padding: 8px 10px;
            background: #f8f7f7;
            border-bottom: 1px solid #ececef;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .rep-tabla td {
            padding: 8px 10px;
            border-bottom: 1px solid #ececef;
            vertical-align: middle;
        }

        .rep-tabla tfoot td {
            padding: 10px;
            border-top: 2px solid #1c1416;
            font-weight: 800;
            background: #f8f7f7;
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
            background: #f8f7f7;
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
            white-space: nowrap;
        }

        .rep-chip.mesa {
            background: #e6f4ea;
            color: #14803d;
        }

        .rep-chip.online {
            background: #dbeafe;
            color: #1e40af;
        }

        .rep-chip.pendiente,
        .rep-chip.preparando {
            background: #fef3c7;
            color: #92400e;
        }

        .rep-chip.despachado,
        .rep-chip.pendiente_pago {
            background: #dbeafe;
            color: #1e40af;
        }

        .rep-chip.pagado {
            background: #e6f4ea;
            color: #14803d;
        }

        .rep-chip.cancelado {
            background: #fee2e2;
            color: #b91c1c;
        }

        .rep-vacio {
            padding: 40px 14px;
            text-align: center;
            color: #a79ea2;
            font-size: 13px;
        }

        .rep-tabla-scroll {
            max-height: 60vh;
            overflow: auto;
            border-radius: 8px;
            border: 1px solid #ececef;
        }

        @media (max-width: 620px) {
            .rep-tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .rep-card {
                padding: 12px 12px 12px 52px;
            }

            .rep-card .rc-ico {
                left: 10px;
                width: 30px;
                height: 30px;
                font-size: 13px;
            }

            .rep-card .rc-val {
                font-size: 16px;
            }

            .rep-filtros {
                flex-direction: column;
            }

            .rep-filtros .rf-campo {
                flex: 1 1 100%;
            }
        }
    </style>

    <script defer src="../modelos/reportes/pedidos_reporte_api.js"></script>
    <script defer src="../modelos/reportes/pedidos_reporte.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-calendar-days" aria-hidden="true"></i>
                    Pedidos por fechas
                </h3>
                <button type="button" class="btn-mod btn-mod-claro" id="btnPdf">
                    <i class="fa-solid fa-file-pdf"></i> <span>Exportar PDF</span>
                </button>
            </div>

            <div class="card-body">

                <!-- Tarjetas resumen -->
                <div class="rep-tarjetas">
                    <div class="rep-card">
                        <span class="rc-ico"><i class="fa-solid fa-clipboard-list"></i></span>
                        <span class="rc-et">Pedidos</span>
                        <span class="rc-val" id="rcPedidos">0</span>
                        <span class="rc-sub" id="rcVigentes">0 vigentes</span>
                    </div>
                    <div class="rep-card rc-mesa">
                        <span class="rc-ico"><i class="fa-solid fa-chair"></i></span>
                        <span class="rc-et">Mesas</span>
                        <span class="rc-val" id="rcMesas">0</span>
                    </div>
                    <div class="rep-card rc-online">
                        <span class="rc-ico"><i class="fa-solid fa-truck"></i></span>
                        <span class="rc-et">Online</span>
                        <span class="rc-val" id="rcOnline">0</span>
                    </div>
                    <div class="rep-card rc-cancelado">
                        <span class="rc-ico"><i class="fa-solid fa-circle-xmark"></i></span>
                        <span class="rc-et">Cancelados</span>
                        <span class="rc-val" id="rcCancelados">0</span>
                    </div>
                    <div class="rep-card rc-total">
                        <span class="rc-ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="rc-et">Total pedidos</span>
                        <span class="rc-val" id="rcTotal">Bs 0,00</span>
                    </div>
                    <div class="rep-card rc-promedio">
                        <span class="rc-ico"><i class="fa-solid fa-chart-line"></i></span>
                        <span class="rc-et">Promedio</span>
                        <span class="rc-val" id="rcPromedio">Bs 0,00</span>
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
                        <label for="rfTipo">Tipo</label>
                        <select id="rfTipo">
                            <option value="">Todos</option>
                            <option value="mesa">Mesas</option>
                            <option value="online">Online (llevar y delivery)</option>
                            <option value="llevar">Solo para llevar</option>
                            <option value="delivery">Solo delivery</option>
                        </select>
                    </div>
                    <div class="rf-campo">
                        <label for="rfEstado">Estado</label>
                        <select id="rfEstado">
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="preparando">Preparando</option>
                            <option value="despachado">Despachado</option>
                            <option value="pendiente_pago">Pendiente de pago</option>
                            <option value="pagado">Pagado</option>
                            <option value="cancelado">Cancelado</option>
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
                    <div class="rf-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="rfFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="rfLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="rep-tabla-wrap">
                    <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                        <span class="mini-spin"></span> <span>Cargando pedidos...</span>
                    </div>

                    <div class="rep-tabla-scroll" id="repTablaScroll" style="display:none;">
                        <table class="rep-tabla">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Código</th>
                                    <th>Tipo</th>
                                    <th>Cliente / Mesa</th>
                                    <th>Sucursal</th>
                                    <th class="num">Ítems</th>
                                    <th class="num">Total</th>
                                    <th>Estado</th>
                                    <th>Registrado por</th>
                                </tr>
                            </thead>
                            <tbody id="repTablaBody">
                                <tr>
                                    <td colspan="10" class="rep-vacio">Ajusta los filtros y pulsa Filtrar.</td>
                                </tr>
                            </tbody>
                            <tfoot id="repTablaFoot" style="display:none;">
                                <tr>
                                    <td colspan="7">Totales</td>
                                    <td class="num" id="footTotal">Bs 0,00</td>
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
