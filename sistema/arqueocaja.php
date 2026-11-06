<?php
/**
 * =====================================================
 * ARQUEO DE CAJA  (sistema/arqueocaja.php)
 * -----------------------------------------------------
 *  Flujo:
 *   · El usuario apertura la caja que tiene asignada.
 *   · Mientras está abierta se registran ventas y
 *     egresos/gastos del turno.
 *   · Al cerrar se genera el ticket de cierre de 80 mm.
 *   · El SuperAdmin puede reabrir un turno cerrado para
 *     corregirlo y volver a cerrarlo.
 *
 *  Datos  : ../modelos/cajas/obtener_arqueos.php
 *  API    : ../modelos/cajas/arqueo_api.php
 *  Red    : ../modelos/cajas/arqueo_api.js
 *  UI     : ../modelos/cajas/arqueo.js
 *  Modelo : ../modelos/cajas/ArqueoCaja.php
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Arqueo de caja';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Arqueo de caja (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_VENTAS_ARQUEO);
$rmAcciones = rm_acciones(Permisos::ITEM_VENTAS_ARQUEO);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <!-- Token CSRF: sin este meta TODAS las llamadas de arqueo_api.js devuelven 403 -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .content-card {
            border-top: 3px solid var(--mod-primaria, #d9232d);
        }

        /* ---------- Cabecera del módulo ---------- */
        .arq-cabecera {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1 1 100%;
        }

        .arq-cabecera .arq-cabecera-info {
            min-width: 0;
        }

        .arq-cabecera h3 {
            margin: 0;
        }

        .arq-cabecera p {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .arq-cabecera .arq-cabecera-acciones {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        /* ---------- Tarjetas de resumen ---------- */
        .arq-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .arq-mini {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            font-size: 12.5px;
            color: var(--mod-texto, #6b6165);
            line-height: 1.25;
        }

        .arq-mini i {
            color: var(--mod-primaria, #d9232d);
            font-size: 15px;
        }

        .arq-mini span {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .arq-mini b {
            color: var(--mod-tinta, #1c1416);
            font-size: 15px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .arq-mini small {
            font-size: 10px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .arq-mini.gastos i {
            color: #b57a00;
        }

        /* ---------- Mi caja ---------- */
        .arq-vacio {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: var(--mod-radio-sm);
            background: var(--mod-ambar-fondo, #fff4d6);
            border: 1px solid #f1dfa6;
            font-size: 13px;
            color: var(--mod-ambar, #9a6b00);
            line-height: 1.5;
        }

        .arq-vacio > i {
            font-size: 22px;
            margin-top: 1px;
        }

        .arq-vacio strong {
            display: block;
            font-size: 13.5px;
            color: #7a5500;
        }

        .arq-vacio span {
            display: block;
        }

        .arq-mi {
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            overflow: hidden;
            background: #fff;
        }

        .arq-mi-cabecera {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 14px;
            background: var(--mod-primaria-suave);
            border-bottom: 1px solid #f6c9cb;
        }

        .amc-icono {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            border-radius: 11px;
            background: var(--mod-primaria);
            color: #fff;
            font-size: 18px;
        }

        .amc-texto {
            display: flex;
            flex-direction: column;
            line-height: 1.3;
            min-width: 0;
        }

        .amc-titulo {
            font-size: 14.5px;
            font-weight: 800;
            color: var(--mod-tinta);
        }

        .amc-sub {
            font-size: 12px;
            color: var(--mod-texto);
        }

        .amc-estado {
            margin-left: auto;
        }

        .arq-mi-datos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
            gap: 8px;
            padding: 11px 14px;
        }

        .amd-item {
            display: flex;
            flex-direction: column;
            gap: 1px;
            padding: 7px 10px;
            border-radius: 8px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            min-width: 0;
        }

        .amd-et {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .amd-val {
            font-size: 13px;
            font-weight: 700;
            color: var(--mod-tinta, #1c1416);
            font-variant-numeric: tabular-nums;
            overflow-wrap: anywhere;
        }

        .amd-val.gasto {
            color: #b57a00;
        }

        .arq-mi-pie {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 14px;
            border-top: 1px dashed var(--mod-linea);
        }

        .arq-mi-nota {
            font-size: 11.5px;
            color: var(--mod-texto, #6b6165);
            margin-right: auto;
        }

        .arq-mi-nota i {
            color: var(--mod-primaria);
        }

        /* ---------- Filtros ---------- */
        .arq-barra {
            display: flex;
            align-items: flex-end;
            gap: 9px;
            flex-wrap: wrap;
            margin: 0 0 10px;
            padding: 9px 11px;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
        }

        .arq-barra .ab-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .arq-barra label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0;
        }

        .arq-barra select,
        .arq-barra input[type="date"] {
            height: 32px;
            min-width: 145px;
            padding: 0 9px;
            border: 1px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .arq-barra .ab-limpiar {
            height: 32px;
            margin-left: auto;
        }

        /* ---------- Tabla ---------- */
        .arq-acciones {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        #tablaArqueos th:last-child,
        #tablaArqueos td:last-child {
            min-width: 130px;
        }

        .btn-tabla.movs {
            color: #1f6fd0;
            border-color: #cfe3fb;
            background: #e8f2fe;
        }

        .btn-tabla.movs:hover {
            background: #1f6fd0;
            border-color: #1f6fd0;
            color: #fff;
        }

        .btn-tabla.gasto {
            color: #9a6b00;
            border-color: #f0dca0;
            background: #fff4d6;
        }

        .btn-tabla.gasto:hover {
            background: #e0a800;
            border-color: #d19a00;
            color: #3d2b00;
        }

        .btn-tabla.cerrar {
            color: #b41c1b;
            border-color: #f5c4c4;
            background: #ffe8e8;
        }

        .btn-tabla.cerrar:hover {
            background: #e0312f;
            border-color: #b41c1b;
            color: #fff;
        }

        .btn-tabla.reabrir {
            color: #14803d;
            border-color: #bfe6cd;
            background: #e5f7ec;
        }

        .btn-tabla.reabrir:hover {
            background: #14803d;
            border-color: #14803d;
            color: #fff;
        }

        .btn-tabla.ticket {
            color: #5b4b8a;
            border-color: #d7cff0;
            background: #f1eefb;
        }

        .btn-tabla.ticket:hover {
            background: #5b4b8a;
            border-color: #5b4b8a;
            color: #fff;
        }

        .dt-gastos {
            color: #b57a00;
            font-weight: 700;
        }

        .dt-ventas {
            font-weight: 700;
        }

        .arq-estado-espera {
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 11px;
        }

        /* ---------- Modal de movimientos ---------- */
        .arq-modal-movs .modal-caja {
            width: min(980px, 100%);
            max-width: min(980px, 100%);
            max-height: calc(100dvh - 28px);
        }

        .arq-modal-movs .modal-cuerpo {
            display: block;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 14px 16px 10px;
        }

        .arq-movs-cab {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .arq-movs-cab .amv-info {
            min-width: 0;
        }

        .arq-movs-cab .amv-titulo {
            font-size: 16px;
            font-weight: 800;
            color: var(--mod-tinta);
        }

        .arq-movs-cab .amv-sub {
            font-size: 12px;
            color: var(--mod-texto);
            margin-top: 2px;
        }

        .arq-movs-cab .amv-chip {
            margin-left: auto;
        }

        .arq-movs-resumen {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 7px;
            margin-bottom: 10px;
        }

        .arq-movs-resumen .amr-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 8px 9px;
            border-radius: 8px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            min-width: 0;
        }

        .arq-movs-resumen .amr-et {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.35px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .arq-movs-resumen .amr-val {
            font-size: 12.5px;
            font-weight: 800;
            color: var(--mod-tinta);
            font-variant-numeric: tabular-nums;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .arq-movs-resumen .amr-item.gasto .amr-val {
            color: #b57a00;
        }

        .arq-movs-resumen .amr-item.diferencia.ok .amr-val {
            color: var(--mod-verde, #14803d);
        }

        .arq-movs-resumen .amr-item.diferencia.mal .amr-val {
            color: var(--mod-primaria, #d9232d);
        }

        .arq-metodos-pago {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 11px;
        }

        .arq-metodo-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid var(--mod-linea, #ececef);
            background: #fff;
            font-size: 10.5px;
            color: var(--mod-texto);
            font-variant-numeric: tabular-nums;
        }

        .arq-metodo-chip b {
            color: var(--mod-tinta);
            font-weight: 800;
        }

        .arq-metodo-chip i {
            color: var(--mod-primaria);
            font-size: 10px;
        }

        .arq-movs-tabs {
            display: flex;
            align-items: center;
            gap: 5px;
            border-bottom: 1px solid var(--mod-linea);
            margin-bottom: 8px;
        }

        .arq-movs-tabs button {
            border: 0;
            background: transparent;
            padding: 8px 11px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 800;
            color: var(--mod-texto-tenue, #a79ea2);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .arq-movs-tabs button.activa {
            color: var(--mod-primaria);
            border-bottom-color: var(--mod-primaria);
        }

        .arq-movs-tabs button span {
            background: var(--mod-claro);
            color: var(--mod-texto);
            border-radius: 999px;
            padding: 1px 7px;
            font-size: 10px;
        }

        .arq-movs-panel {
            display: none;
        }

        .arq-movs-panel.activo {
            display: block;
        }

        .arq-movs-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .arq-movs-tabla th {
            text-align: left;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.35px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            padding: 5px 6px;
            border-bottom: 1px solid var(--mod-linea);
            white-space: nowrap;
        }

        .arq-movs-tabla td {
            padding: 6px 6px;
            border-bottom: 1px solid var(--mod-claro, #f8f7f7);
            vertical-align: top;
            color: var(--mod-tinta, #1c1416);
        }

        .arq-movs-tabla tr:last-child td {
            border-bottom: 0;
        }

        .arq-movs-tabla .r {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            white-space: nowrap;
        }

        .arq-movs-tabla .c {
            text-align: center;
        }

        .arq-movs-tabla .badge-mod {
            gap: 4px;
            padding: 2px 7px;
            font-size: 10.5px;
        }

        .arq-pago-linea {
            display: block;
            font-size: 10.5px;
            color: var(--mod-texto-tenue, #a79ea2);
            line-height: 1.35;
            margin-top: 1px;
        }

        .arq-venta-codigo {
            font-family: monospace;
            font-size: 11px;
            font-weight: 700;
            color: var(--mod-tinta);
            letter-spacing: 0.2px;
        }

        .arq-venta-anulada {
            display: inline-block;
            margin-left: 4px;
            padding: 0 5px;
            border-radius: 4px;
            background: var(--mod-rojo-fondo, #ffe8e8);
            color: #b41c1b;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.5;
        }

        .arq-movs-vacio {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 12px;
            border-radius: 8px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px dashed var(--mod-linea);
            font-size: 12px;
            color: var(--mod-texto);
        }

        .arq-movs-vacio i {
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .arq-movs-tabla .btn-tabla {
            width: 27px;
            height: 27px;
            font-size: 11px;
        }

        .arq-nota {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea);
            font-size: 11px;
            color: var(--mod-texto);
            line-height: 1.45;
        }

        .arq-nota i {
            color: var(--mod-primaria);
        }

        .arq-modal-movs .modal-pie {
            gap: 7px;
            flex-wrap: wrap;
        }

        .arq-modal-movs .modal-pie .arq-pie-nota {
            margin-right: auto;
            font-size: 11px;
            color: var(--mod-texto-tenue);
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 900px) {
            .arq-movs-resumen {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .arq-cabecera .arq-cabecera-acciones {
                width: 100%;
                margin-left: 0;
                justify-content: flex-start;
            }

            .arq-mi-cabecera {
                flex-wrap: wrap;
            }

            .amc-estado {
                margin-left: 0;
            }

            .arq-mi-pie {
                align-items: stretch;
            }

            .arq-mi-pie .btn-mod {
                flex: 1 1 auto;
                justify-content: center;
            }

            .arq-mi-nota {
                width: 100%;
                margin-right: 0;
            }

            .arq-movs-resumen {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .arq-modal-movs .modal-caja {
                max-height: calc(100dvh - 80px);
            }

            .arq-modal-movs .modal-cuerpo {
                padding: 12px 12px 8px;
            }

            .arq-modal-movs .modal-pie {
                padding: 9px 12px 11px;
            }

            .arq-modal-movs .modal-pie .btn-mod {
                flex: 1 1 auto;
                justify-content: center;
            }
        }

        .dt-subtexto {
            display: block;
            font-size: 11px;
            color: var(--mod-texto-tenue, #a79ea2);
            line-height: 1.35;
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/cajas/arqueo_api.js"></script>
    <script defer src="../modelos/cajas/arqueo.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <!-- ============================================
             MI CAJA: apertura / cierre del turno
             ============================================ -->
        <section class="content-card">
            <div class="card-header">
                <div class="arq-cabecera">
                    <div class="arq-cabecera-info">
                        <h3>
                            <i class="fas fa-cash-register" aria-hidden="true"></i>
                            Mi caja
                        </h3>
                        <p>Turno activo, gastos y cierre de tu caja asignada.</p>
                    </div>
                    <div class="arq-cabecera-acciones">
                        <button type="button" class="btn-mod btn-mod-claro" id="btnRecargarArqueos" title="Actualizar información">
                            <i class="fas fa-rotate"></i> Actualizar
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div id="miCajaPanel">
                    <div class="tabla-carga visible" role="status" aria-live="polite">
                        <span class="mini-spin" aria-hidden="true"></span>
                        <span>Revisando tu caja...</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================
             CAJAS ABIERTAS / CERRADAS + HISTORIAL
             ============================================ -->
        <section class="content-card">

            <div class="card-body">

                <div class="arq-tarjetas">
                    <div class="arq-mini">
                        <i class="fas fa-lock-open"></i>
                        <span><b id="arqAbiertos">0</b><small>Abiertas</small></span>
                    </div>
                    <div class="arq-mini">
                        <i class="fas fa-lock"></i>
                        <span><b id="arqCerrados">0</b><small>Cerradas</small></span>
                    </div>
                    <div class="arq-mini">
                        <i class="fas fa-calendar-day"></i>
                        <span><b id="arqHoy">0</b><small>Hoy</small></span>
                    </div>
                    <div class="arq-mini">
                        <i class="fas fa-arrow-trend-up"></i>
                        <span><b id="arqVentas">Bs 0,00</b><small>Ventas del listado</small></span>
                    </div>
                    <div class="arq-mini gastos">
                        <i class="fas fa-receipt"></i>
                        <span><b id="arqGastos">Bs 0,00</b><small>Gastos del listado</small></span>
                    </div>
                </div>

                <div class="arq-barra">
                    <div class="ab-campo">
                        <label for="filtroEstado">Estado</label>
                        <select id="filtroEstado">
                            <option value="">Todos</option>
                            <option value="abierto">Abiertos</option>
                            <option value="cerrado">Cerrados</option>
                        </select>
                    </div>

                    <div class="ab-campo" id="filtroSucursalWrap" style="display:none;">
                        <label for="filtroSucursal">Sucursal</label>
                        <select id="filtroSucursal">
                            <option value="">Todas</option>
                        </select>
                    </div>

                    <div class="ab-campo">
                        <label for="filtroDesde">Desde</label>
                        <input type="date" id="filtroDesde" />
                    </div>

                    <div class="ab-campo">
                        <label for="filtroHasta">Hasta</label>
                        <input type="date" id="filtroHasta" />
                    </div>

                    <button type="button" class="btn-mod btn-mod-claro ab-limpiar" id="btnLimpiarFiltros">
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                </div>

                <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando arqueos...</span>
                </div>

                <div class="table-responsive">
                    <table id="tablaArqueos" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Caja</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th>Inicial</th>
                                <th>Ventas</th>
                                <th>Gastos</th>
                                <th>Contado</th>
                                <th>Diferencia</th>
                                <th>Estado</th>
                                <th title="Acciones" aria-label="Acciones">...</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </section>

    </main>

    <!-- ============================================
         MODAL: MOVIMIENTOS DE CAJA (ventas + gastos)
         ============================================ -->
    <div class="modal-overlay arq-modal-movs" id="modalMovimientosCaja" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="movsTitulo">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2 id="movsTitulo">
                    <span class="ico-titulo"><i class="fa-solid fa-list-ul"></i></span>
                    <span style="color:#fff;">Movimientos de caja</span>
                </h2>
                <button type="button" class="modal-cerrar" id="movsCerrarX" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-cuerpo">
                <div class="tabla-carga visible" id="movsCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando movimientos...</span>
                </div>

                <div id="movsContenido" style="display:none;">
                    <div class="arq-movs-cab">
                        <div class="amv-info">
                            <div class="amv-titulo" id="movsCajaNombre">Caja</div>
                            <div class="amv-sub" id="movsCajaMeta">Sucursal · Turno</div>
                        </div>
                        <div class="amv-chip" id="movsEstado"></div>
                    </div>

                    <div class="arq-movs-resumen">
                        <div class="amr-item">
                            <span class="amr-et">Monto inicial</span>
                            <span class="amr-val" id="movsInicial">Bs 0,00</span>
                        </div>
                        <div class="amr-item">
                            <span class="amr-et">Ventas</span>
                            <span class="amr-val" id="movsVentas">Bs 0,00</span>
                        </div>
                        <div class="amr-item gasto">
                            <span class="amr-et">Gastos / egresos</span>
                            <span class="amr-val" id="movsGastos">Bs 0,00</span>
                        </div>
                        <div class="amr-item">
                            <span class="amr-et">Esperado en caja</span>
                            <span class="amr-val" id="movsEsperado">Bs 0,00</span>
                        </div>
                        <div class="amr-item">
                            <span class="amr-et">Monto contado</span>
                            <span class="amr-val" id="movsContado">—</span>
                        </div>
                        <div class="amr-item diferencia" id="movsDiferenciaWrap">
                            <span class="amr-et">Diferencia</span>
                            <span class="amr-val" id="movsDiferencia">—</span>
                        </div>
                    </div>

                    <div class="arq-metodos-pago" id="movsMetodosPago"></div>

                    <div class="arq-movs-tabs" id="movsTabs" role="tablist">
                        <button type="button" class="activa" data-tab="ventas" role="tab" aria-selected="true">
                            <i class="fas fa-receipt"></i> Ventas
                            <span id="movsVentasCount">0</span>
                        </button>
                        <button type="button" data-tab="gastos" role="tab" aria-selected="false">
                            <i class="fas fa-money-bill-transfer"></i> Gastos / egresos
                            <span id="movsGastosCount">0</span>
                        </button>
                    </div>

                    <div class="arq-movs-panel activo" id="movsPanelVentas" role="tabpanel">
                        <div class="table-responsive">
                            <table class="arq-movs-tabla">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Ticket</th>
                                        <th>Tipo</th>
                                        <th>Pago</th>
                                        <th style="text-align:right">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="movsVentasBody"></tbody>
                            </table>
                        </div>
                        <div class="arq-movs-vacio" id="movsVentasVacio" style="display:none;">
                            <i class="fas fa-receipt"></i>
                            <span>No hay ventas registradas en este turno.</span>
                        </div>
                    </div>

                    <div class="arq-movs-panel" id="movsPanelGastos" role="tabpanel">
                        <div class="table-responsive">
                            <table class="arq-movs-tabla">
                                <thead>
                                    <tr>
                                        <th>Hora</th>
                                        <th>Tipo</th>
                                        <th>Concepto</th>
                                        <th>Pago</th>
                                        <th style="text-align:right">Monto</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="movsGastosBody"></tbody>
                            </table>
                        </div>
                        <div class="arq-movs-vacio" id="movsGastosVacio" style="display:none;">
                            <i class="fas fa-money-bill-transfer"></i>
                            <span>No hay egresos ni gastos registrados en este turno.</span>
                        </div>
                    </div>

                    <div class="arq-nota">
                        <i class="fas fa-circle-info"></i>
                        Este detalle incluye las ventas y los egresos/gastos de la caja.
                        <strong>No incluye compras</strong> del módulo de inventario.
                    </div>
                </div>
            </div>

            <div class="modal-pie">
                <span class="arq-pie-nota" id="movsNota"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="movsEditar" style="display:none;">
                    <i class="fas fa-pen-to-square"></i> Editar apertura
                </button>
                <button type="button" class="btn-mod btn-mod-claro" id="movsEliminar" style="display:none;">
                    <i class="fas fa-trash"></i> Eliminar arqueo
                </button>
                <button type="button" class="btn-mod btn-mod-primario" id="movsReabrir" style="display:none;">
                    <i class="fas fa-lock-open"></i> Reabrir caja
                </button>
                <button type="button" class="btn-mod btn-mod-claro" id="movsPdf">
                    <i class="fas fa-file-pdf"></i> Imprimir PDF
                </button>
                <button type="button" class="btn-mod btn-mod-claro" id="movsCerrarPie">
                    <i class="fas fa-xmark"></i> Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================
         MODALES: APERTURA / CIERRE / MOVIMIENTO
         ============================================ -->
    <?php include '../modelos/cajas/modalAperturaCaja.php'; ?>
    <?php include '../modelos/cajas/modalCierreCaja.php'; ?>
    <?php include '../modelos/cajas/modalMovimientoCaja.php'; ?>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>
