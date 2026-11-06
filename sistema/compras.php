<?php
/**
 * =====================================================
 * COMPRAS  (sistema/compras.php)
 * -----------------------------------------------------
 * Historial de compras por sucursal + gestión de
 * proveedores. Cada compra alimenta el kardex de SU
 * sucursal de forma independiente.
 *
 * Datos  : ../modelos/compras/obtener_compras.php
 * API    : ../modelos/compras/compras_api.php
 * Red    : ../modelos/compras/compras_api.js
 * UI     : ../modelos/compras/compras.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Compras';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Compras (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_INV_COMPRAS);
$rmAcciones = rm_acciones(Permisos::ITEM_INV_COMPRAS);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .comp-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 8px;
            padding: 10px 12px 4px;
        }

        .comp-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
        }

        .comp-mini .cm-ico {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #d9232d);
            font-size: 14px;
        }

        .comp-mini .cm-txt {
            min-width: 0;
            line-height: 1.15;
        }

        .comp-mini .cm-valor {
            display: block;
            font-size: 16px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .comp-mini .cm-et {
            display: block;
            margin-top: 2px;
            font-size: 10.5px;
            font-weight: 600;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .comp-filtros {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 8px;
            padding: 8px 12px 10px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
        }

        .cf-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 130px;
            flex: 1 1 150px;
        }

        .cf-campo label {
            font-size: 10.5px;
            font-weight: 800;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .cf-campo select,
        .cf-campo input {
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

        .cf-campo select:focus,
        .cf-campo input:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
            box-shadow: 0 0 0 3px var(--mod-primaria-suave, #ffe9ea);
        }

        .cf-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
            padding-bottom: 1px;
        }

        .cf-acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        .comp-doc {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .comp-doc b {
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .comp-sub {
            display: block;
            font-size: 10.5px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        #tablaProveedores {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        #tablaProveedores th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 8px 10px;
            background: var(--mod-claro, #f8f7f7);
            border-bottom: 1px solid var(--mod-linea, #ececef);
            text-align: left;
            font-size: 10.5px;
            font-weight: 800;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        #tablaProveedores td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            vertical-align: middle;
        }

        #tablaProveedores tr:hover td {
            background: #fffafa;
        }

        .prov-lista-vacia {
            padding: 26px 10px;
            text-align: center;
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 13px;
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/compras/compras_api.js"></script>
    <script defer src="../modelos/compras/compras.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">

            <div class="card-header">
                <h3>
                    <i class="fas fa-cart-shopping" aria-hidden="true"></i>
                    Compras por sucursal
                </h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php if (rm_puede('ver', Permisos::ITEM_INV_CUENTAS_PAGAR)): ?>
                        <a href="cuentas_pagar.php" class="btn-mod btn-mod-claro">
                            <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                            <span>Cuentas por pagar</span>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn-mod btn-mod-claro" id="btnProveedores">
                        <i class="fas fa-truck-field" aria-hidden="true"></i>
                        <span>Proveedores</span>
                    </button>
                    <?php if (rm_puede('ver', Permisos::ITEM_INV_NUEVA_COMPRA)): ?>
                        <a href="nuevacompra.php" class="btn-mod btn-mod-primario">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <span>Nueva compra</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body" style="padding:0">

                <div class="comp-tarjetas" id="compTarjetas">
                    <div class="comp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-receipt"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cmCompras">—</b><span class="cm-et">Compras</span></span>
                    </div>
                    <div class="comp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cmTotal">—</b><span class="cm-et">Invertido</span></span>
                    </div>
                    <div class="comp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cmPorPagar">—</b><span class="cm-et">Por pagar</span></span>
                    </div>
                    <div class="comp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-calendar-days"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cmMes">—</b><span class="cm-et">Este mes</span></span>
                    </div>
                    <div class="comp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-boxes-stacked"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cmUnidades">—</b><span class="cm-et">Unidades</span></span>
                    </div>
                    <div class="comp-mini">
                        <span class="cm-ico"><i class="fa-solid fa-truck"></i></span>
                        <span class="cm-txt"><b class="cm-valor" id="cmProveedores">—</b><span class="cm-et">Proveedores</span></span>
                    </div>
                </div>

                <div class="comp-filtros">
                    <div class="cf-campo" id="cfSucursalCaja">
                        <label for="fSucursal">Sucursal</label>
                        <select id="fSucursal">
                            <option value="">Todas las sucursales</option>
                        </select>
                    </div>

                    <div class="cf-campo">
                        <label for="fProveedor">Proveedor</label>
                        <select id="fProveedor">
                            <option value="">Todos los proveedores</option>
                        </select>
                    </div>

                    <div class="cf-campo">
                        <label for="fDesde">Desde</label>
                        <input type="date" id="fDesde" />
                    </div>

                    <div class="cf-campo">
                        <label for="fHasta">Hasta</label>
                        <input type="date" id="fHasta" />
                    </div>

                    <div class="cf-campo">
                        <label for="fEstado">Estado</label>
                        <select id="fEstado">
                            <option value="">Todas</option>
                            <option value="registrada">Registradas</option>
                            <option value="anulada">Anuladas</option>
                        </select>
                    </div>

                    <div class="cf-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="btnFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="btnLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <div style="padding:0 12px 12px">
                    <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                        <span class="mini-spin" aria-hidden="true"></span>
                        <span>Cargando compras...</span>
                    </div>

                    <div class="table-responsive">
                        <table id="tablaCompras" class="display nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Documento</th>
                                    <th>Proveedor</th>
                                    <th>Sucursal</th>
                                    <th>Ítems</th>
                                    <th>Total</th>
                                    <th>Pago</th>
                                    <th>Estado</th>
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
         MODAL: NUEVO / MODIFICAR PROVEEDOR
         ============================================ -->
    <?php include '../modelos/compras/modalProveedor.php'; ?>

    <!-- ============================================
         MODAL: LISTA / GESTIÓN DE PROVEEDORES
         ============================================ -->
    <div class="modal-overlay" id="modalProveedores" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mProvListaTitulo">
        <div class="modal-caja" style="width:min(980px,100%)">
            <div class="modal-cabecera">
                <h2 id="mProvListaTitulo">
                    <span class="ico-titulo"><i class="fa-solid fa-truck-field"></i></span>
                    <span style="color:#fff;">Proveedores</span>
                </h2>
                <button type="button" class="modal-cerrar" id="mProvListaCerrar" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-cuerpo" style="display:block;padding:14px 16px 8px;overflow:auto">
                <div id="provListaCarga" class="tabla-carga">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando proveedores...</span>
                </div>
                <div class="table-responsive" style="max-height:56vh;overflow:auto">
                    <table id="tablaProveedores">
                        <thead>
                            <tr>
                                <th>Proveedor</th>
                                <th>NIT</th>
                                <th>Contacto</th>
                                <th>Teléfono</th>
                                <th>Compras</th>
                                <th>Estado</th>
                                <th style="text-align:right">...</th>
                            </tr>
                        </thead>
                        <tbody id="provListaBody">
                            <tr><td colspan="7" class="prov-lista-vacia">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-pie">
                <span class="txt-tenue" style="margin-right:auto;font-size:12px">Los proveedores inactivos conservan su historial.</span>
                <button type="button" class="btn-mod btn-mod-claro" id="mProvListaCerrar2">
                    <i class="fa-solid fa-xmark"></i> Cerrar
                </button>
                <button type="button" class="btn-mod btn-mod-primario" id="mProvListaNuevo">
                    <i class="fa-solid fa-plus"></i> Nuevo proveedor
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>
