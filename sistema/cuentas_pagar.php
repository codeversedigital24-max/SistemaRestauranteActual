<?php
/**
 * =====================================================
 * CUENTAS POR PAGAR  (sistema/cuentas_pagar.php)
 * -----------------------------------------------------
 * Compras al crédito con saldo pendiente.
 *   · lista de compras por sucursal / proveedor
 *   · abonos con N° de guía del recibo del proveedor
 *   · comprobante PDF del abono
 *
 * Datos  : ../modelos/compras/obtener_cuentas_pagar.php
 * API    : ../modelos/compras/cuentas_pagar_api.php
 * Red    : ../modelos/compras/compras_api.js
 * UI     : ../modelos/compras/cuentas_pagar.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Cuentas por pagar';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Cuentas por pagar (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_INV_CUENTAS_PAGAR);
$rmAcciones = rm_acciones(Permisos::ITEM_INV_CUENTAS_PAGAR);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .cp-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 8px;
            padding: 10px 12px 4px;
        }

        .cp-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
        }

        .cp-mini .cm-ico {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: var(--mod-azul-fondo, #e8f2fe);
            color: var(--mod-azul, #1f6fd0);
            font-size: 14px;
        }

        .cp-mini .cm-txt {
            min-width: 0;
            line-height: 1.15;
        }

        .cp-mini .cm-valor {
            display: block;
            font-size: 16px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .cp-mini .cm-et {
            display: block;
            margin-top: 2px;
            font-size: 10.5px;
            font-weight: 600;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .cp-filtros {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 8px;
            padding: 8px 12px 10px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
        }

        .cp-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 130px;
            flex: 1 1 150px;
        }

        .cp-campo label {
            font-size: 10.5px;
            font-weight: 800;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .cp-campo select,
        .cp-campo input {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
        }

        .cp-campo select:focus,
        .cp-campo input:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
            box-shadow: 0 0 0 3px var(--mod-primaria-suave, #ffe9ea);
        }

        .cp-acciones-filtro {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
            padding-bottom: 1px;
        }

        .cp-acciones-filtro .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        .cp-doc {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .cp-doc b {
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .cp-sub {
            display: block;
            font-size: 10.5px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .cp-saldo {
            font-variant-numeric: tabular-nums;
            font-weight: 800;
            color: #c62828;
        }

        .cp-saldo-cero {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: var(--mod-verde, #24703a);
        }

        .cp-resumen-abono {
            grid-column: span 2;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 7px;
            padding: 9px 10px;
            margin-bottom: 4px;
            background: #eef5ff;
            border: 1px solid #d5e5fb;
            border-radius: 9px;
        }

        .cp-resumen-abono .dato {
            line-height: 1.2;
        }

        .cp-resumen-abono .dato span {
            display: block;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
            color: #7c8fa5;
        }

        .cp-resumen-abono .dato b {
            display: block;
            margin-top: 1px;
            font-size: 12.5px;
            color: #28527a;
        }

        .cp-pagos-lista {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .cp-pagos-lista th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 8px 9px;
            background: var(--mod-claro, #f8f7f7);
            border-bottom: 1px solid var(--mod-linea, #ececef);
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .cp-pagos-lista td {
            padding: 8px 9px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            vertical-align: middle;
        }

        .cp-pagos-lista tr:hover td {
            background: #fffafa;
        }

        .cp-guia {
            display: inline-block;
            padding: 2px 7px;
            border: 1px dashed #1f6fd0;
            border-radius: 5px;
            background: #eef5ff;
            color: #1f6fd0;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .3px;
            white-space: nowrap;
        }

        .cp-vacio {
            padding: 26px 10px;
            text-align: center;
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 13px;
        }

        @media (max-width: 640px) {
            .cp-resumen-abono {
                grid-column: span 1;
            }
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/compras/compras_api.js"></script>
    <script defer src="../modelos/compras/cuentas_pagar.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">

            <div class="card-header">
                <h3>
                    <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                    Cuentas por pagar
                </h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="compras.php" class="btn-mod btn-mod-claro">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <span>Historial de compras</span>
                    </a>
                </div>
            </div>

            <div class="card-body" style="padding:0">

                <div class="cp-tarjetas" id="cpTarjetas">
                    <div class="cp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cpTotal">—</b><span class="cm-et">Total por pagar</span></span>
                    </div>
                    <div class="cp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-file-invoice"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cpCompras">—</b><span class="cm-et">Compras pendientes</span></span>
                    </div>
                    <div class="cp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-truck-field"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cpProveedores">—</b><span class="cm-et">Proveedores</span></span>
                    </div>
                    <div class="cp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-store"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cpSucursales">—</b><span class="cm-et">Sucursales</span></span>
                    </div>
                </div>

                <div class="cp-filtros">
                    <div class="cp-campo" id="cpSucursalCaja">
                        <label for="cpSucursal">Sucursal</label>
                        <select id="cpSucursal">
                            <option value="">Todas las sucursales</option>
                        </select>
                    </div>

                    <div class="cp-campo">
                        <label for="cpProveedorFiltro">Proveedor</label>
                        <select id="cpProveedorFiltro">
                            <option value="">Todos los proveedores</option>
                        </select>
                    </div>

                    <div class="cp-campo">
                        <label for="cpBuscar">Buscar</label>
                        <input type="search" id="cpBuscar" placeholder="Proveedor, NIT o documento" maxlength="80" />
                    </div>

                    <div class="cp-acciones-filtro">
                        <button type="button" class="btn-mod btn-mod-primario" id="cpFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="cpLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <div style="padding:0 12px 12px">
                    <div class="tabla-carga" id="cpCarga" role="status" aria-live="polite">
                        <span class="mini-spin" aria-hidden="true"></span>
                        <span>Cargando cuentas por pagar...</span>
                    </div>

                    <div class="table-responsive">
                        <table id="tablaCuentas" class="display nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th>Proveedor</th>
                                    <th>Sucursal</th>
                                    <th>Total</th>
                                    <th>Abono inicial</th>
                                    <th>Pagado</th>
                                    <th>Saldo</th>
                                    <th title="Acciones" aria-label="Acciones">...</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>

    </main>

    <!-- ============================================
         MODAL: REGISTRAR ABONO
         ============================================ -->
    <div class="modal-overlay" id="modalAbono" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mAbonoTitulo">
        <div class="modal-caja" style="width:min(700px,100%)">

            <div class="modal-cabecera">
                <h2 id="mAbonoTitulo">
                    <span class="ico-titulo"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                    <span style="color:#fff;">Registrar abono</span>
                </h2>
                <button type="button" class="modal-cerrar" id="mAbonoCerrar" aria-label="Cerrar">&times;</button>
            </div>

            <form id="formAbono" novalidate autocomplete="off">
                <div class="modal-cuerpo">

                    <div class="cp-resumen-abono" id="abonoResumen"></div>

                    <div class="campo" data-campo="monto">
                        <label for="abonoMonto">
                            <i class="fa-solid fa-money-bill-wave txt-ico"></i> Monto del abono <span class="req">*</span>
                        </label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-coins ico-izq"></i>
                            <input class="input-modern" type="number" id="abonoMonto" min="0.01" step="0.01" placeholder="0.00" required />
                        </div>
                        <span class="ayuda" id="abonoMontoAyuda">No puede superar el saldo pendiente</span>
                    </div>

                    <div class="campo" data-campo="metodo_pago">
                        <label for="abonoMetodo">
                            <i class="fa-solid fa-wallet txt-ico"></i> Método de pago
                        </label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-credit-card ico-izq"></i>
                            <select class="select-modern" id="abonoMetodo">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="qr">QR</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="numero_guia">
                        <label for="abonoGuia">
                            <i class="fa-solid fa-receipt txt-ico"></i> N° de guía del recibo <span class="req">*</span>
                        </label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-hashtag ico-izq"></i>
                            <input class="input-modern" type="text" id="abonoGuia" maxlength="100" placeholder="Guía del recibo del proveedor" required />
                        </div>
                        <span class="ayuda">Número del recibo que emite la empresa proveedora</span>
                    </div>

                    <div class="campo" data-campo="referencia">
                        <label for="abonoReferencia">
                            <i class="fa-solid fa-link txt-ico"></i> Referencia
                        </label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-barcode ico-izq"></i>
                            <input class="input-modern" type="text" id="abonoReferencia" maxlength="100" placeholder="N° de transferencia, cheque, etc." />
                        </div>
                        <span class="ayuda">Opcional · hasta 100 caracteres</span>
                    </div>

                    <div class="campo" data-campo="fecha_pago">
                        <label for="abonoFecha">
                            <i class="fa-solid fa-calendar-day txt-ico"></i> Fecha del abono
                        </label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-clock ico-izq"></i>
                            <input class="input-modern" type="datetime-local" id="abonoFecha" />
                        </div>
                        <span class="ayuda">No puede ser futura</span>
                    </div>

                    <div class="campo ancho-2" data-campo="observaciones">
                        <label for="abonoObservaciones">
                            <i class="fa-solid fa-note-sticky txt-ico"></i> Observaciones
                        </label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-pen ico-izq"></i>
                            <textarea class="input-modern" id="abonoObservaciones" maxlength="255" rows="2" placeholder="Detalle del abono"></textarea>
                        </div>
                        <span class="ayuda">Opcional · hasta 255 caracteres</span>
                    </div>

                    <input type="hidden" id="abonoIdCompra" value="" />
                </div>

                <div class="modal-pie">
                    <span class="txt-tenue" id="abonoNota" style="margin-right:auto;font-size:12px"></span>
                    <button type="button" class="btn-mod btn-mod-claro" id="mAbonoCancelar">
                        <i class="fa-solid fa-xmark"></i> Cancelar
                    </button>
                    <?php if ($rmAcciones['registrar']): ?>
                        <button type="submit" class="btn-mod btn-mod-primario" id="mAbonoGuardar">
                            <i class="fa-solid fa-floppy-disk"></i> <span>Registrar abono</span>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
         MODAL: ABONOS DE LA COMPRA
         ============================================ -->
    <div class="modal-overlay" id="modalPagos" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mPagosTitulo">
        <div class="modal-caja" style="width:min(900px,100%)">
            <div class="modal-cabecera">
                <h2 id="mPagosTitulo">
                    <span class="ico-titulo"><i class="fa-solid fa-list-check"></i></span>
                    <span style="color:#fff;">Abonos de la compra</span>
                </h2>
                <button type="button" class="modal-cerrar" id="mPagosCerrar" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-cuerpo" style="display:block;padding:14px 16px 8px;overflow:auto">
                <div id="pagosResumen" style="margin-bottom:10px"></div>

                <div id="pagosCarga" class="tabla-carga">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando abonos...</span>
                </div>

                <div class="table-responsive" style="max-height:54vh;overflow:auto">
                    <table class="cp-pagos-lista">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Método</th>
                                <th>N° guía</th>
                                <th>Referencia</th>
                                <th>Registró</th>
                                <th style="text-align:right">Monto</th>
                                <th style="text-align:right">...</th>
                            </tr>
                        </thead>
                        <tbody id="pagosBody">
                            <tr><td colspan="8" class="cp-vacio">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-pie">
                <span class="txt-tenue" style="margin-right:auto;font-size:12px">Cada abono genera su comprobante con el N° de guía del proveedor.</span>
                <button type="button" class="btn-mod btn-mod-claro" id="mPagosCerrar2">
                    <i class="fa-solid fa-xmark"></i> Cerrar
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>
