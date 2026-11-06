<?php
/**
 * =====================================================
 * NUEVA COMPRA  (sistema/nuevacompra.php)
 * -----------------------------------------------------
 * Formulario completo de compra:
 *   · proveedor y documento
 *   · catálogo de productos de la sucursal
 *   · carrito con costo, cantidad y precio de venta
 *   · totales con descuento e impuesto
 *
 * Al guardar: cabecera + detalle + entrada al kardex
 * de ESA sucursal (transaccional).
 *
 * Datos : ../modelos/compras/nuevacompra_datos.php
 * API   : ../modelos/compras/compras_api.php
 * Red   : ../modelos/compras/compras_api.js
 * UI    : ../modelos/compras/nuevacompra.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Nueva compra';

include 'validacionSesion.php';
include 'validacion_permisos.php';

$idCompraEdicion = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$modoEdicion = $idCompraEdicion > 0;

if ($modoEdicion) {
    $tituloPagina = 'Modificar compra';
}

// Acceso: nueva compra (ítem 13) o edición desde el historial (ítem 12)
if (!rm_puede('ver', Permisos::ITEM_INV_NUEVA_COMPRA) && !rm_puede('ver', Permisos::ITEM_INV_COMPRAS)) {
    rm_exigir_permiso('ver', Permisos::ITEM_INV_NUEVA_COMPRA);
}

$rmAcciones = rm_acciones(Permisos::ITEM_INV_NUEVA_COMPRA);

// Un usuario que sólo administra el historial también puede editar compras
$rmAcciones['modificar'] = $rmAcciones['modificar'] || rm_puede('modificar', Permisos::ITEM_INV_COMPRAS);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .nc-cabecera {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 9px 12px;
            padding: 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio, 12px);
        }

        .nc-cabecera .ancho-2 {
            grid-column: span 2;
        }

        .nc-cabecera .campo select,
        .nc-cabecera .campo input,
        .nc-cabecera .campo textarea {
            height: 36px;
            font-size: 12.5px;
        }

        .nc-cabecera .campo textarea {
            height: auto;
            min-height: 36px;
            padding-top: 7px;
        }

        .nc-sucursal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 9px;
            border-radius: 999px;
            background: var(--mod-azul-fondo, #e8f2fe);
            color: var(--mod-azul, #1f6fd0);
            font-size: 10.5px;
            font-weight: 800;
        }

        .nc-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(330px, 1fr);
            gap: 12px;
            margin-top: 12px;
            align-items: start;
        }

        .nc-panel {
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio, 12px);
            background: #fff;
            overflow: hidden;
        }

        .nc-panel-cab {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 9px 12px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            background: #fff;
        }

        .nc-panel-cab h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
        }

        .nc-panel-cab h4 i {
            color: var(--mod-primaria, #d9232d);
            font-size: 12.5px;
        }

        .nc-buscador {
            display: flex;
            gap: 7px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            flex-wrap: wrap;
        }

        .nc-buscador .nc-buscar {
            position: relative;
            flex: 1 1 190px;
        }

        .nc-buscador .nc-buscar i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 12px;
        }

        .nc-buscador input,
        .nc-buscador select {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            font-family: inherit;
            font-size: 12.5px;
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            color: var(--mod-tinta, #1c1416);
        }

        .nc-buscador .nc-buscar input {
            padding-left: 33px;
        }

        .nc-buscador input:focus,
        .nc-buscador select:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
            box-shadow: 0 0 0 3px var(--mod-primaria-suave, #ffe9ea);
        }

        .nc-prod-lista {
            padding: 10px;
            max-height: 58vh;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .nc-prod-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(225px, 1fr));
            gap: 8px;
        }

        .nc-prod {
            display: flex;
            gap: 9px;
            padding: 8px;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 10px;
            background: #fff;
            transition: border-color .15s, box-shadow .15s, transform .12s;
        }

        .nc-prod:hover {
            border-color: #f3c2c5;
            box-shadow: 0 6px 16px -12px rgba(217, 35, 45, .55);
        }

        .nc-prod.nc-en-carrito {
            border-color: #bfe6cd;
            background: #fbfffc;
        }

        .nc-prod .np-img {
            width: 60px;
            height: 60px;
            flex: 0 0 auto;
            border-radius: 8px;
            object-fit: cover;
            background: #f4f2f3;
            display: grid;
            place-items: center;
            color: #c9c2c5;
            font-size: 20px;
            overflow: hidden;
        }

        .nc-prod .np-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nc-prod .np-info {
            min-width: 0;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nc-prod .np-nombre {
            font-size: 12px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nc-prod .np-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .nc-prod .np-precios {
            display: flex;
            flex-wrap: wrap;
            gap: 3px 8px;
            font-size: 10.5px;
            color: var(--mod-texto, #6b6165);
        }

        .nc-prod .np-precios b {
            color: var(--mod-tinta, #1c1416);
            font-variant-numeric: tabular-nums;
        }

        .nc-prod .np-pie {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-top: 2px;
        }

        .nc-prod .np-margen {
            font-size: 10px;
            font-weight: 800;
            color: var(--mod-verde, #14803d);
        }

        .nc-prod .np-margen.bajo {
            color: var(--mod-primaria, #d9232d);
        }

        .np-agregar {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            height: 25px;
            padding: 0 9px;
            border: 0;
            border-radius: 999px;
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #d9232d);
            font-family: inherit;
            font-size: 10.5px;
            font-weight: 800;
            cursor: pointer;
            transition: background .15s, color .15s;
            white-space: nowrap;
        }

        .np-agregar:hover {
            background: var(--mod-primaria, #d9232d);
            color: #fff;
        }

        .np-agregar.agregado {
            background: var(--mod-verde-fondo, #e5f7ec);
            color: var(--mod-verde, #14803d);
        }

        .nc-vacio {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 34px 14px;
            text-align: center;
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 12.5px;
        }

        .nc-vacio i {
            font-size: 26px;
            color: #dedadd;
        }

        /* ------------------ Carrito ------------------ */
        .nc-carrito {
            position: sticky;
            top: 76px;
        }

        .nc-carrito-lista {
            max-height: 36vh;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .nc-cart-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        .nc-cart-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 6px 5px;
            background: var(--mod-claro, #f8f7f7);
            border-bottom: 1px solid var(--mod-linea, #ececef);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            text-align: right;
        }

        .nc-cart-table th:first-child {
            text-align: left;
        }

        .nc-cart-table td {
            padding: 5px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            vertical-align: middle;
            text-align: right;
        }

        .nc-cart-table td:first-child {
            text-align: left;
            max-width: 130px;
        }

        .nc-cart-table .nl-nombre {
            display: block;
            font-size: 11.5px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nc-cart-table .nl-codigo {
            display: block;
            font-size: 9.5px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .nc-cart-table input {
            width: 68px;
            height: 29px;
            padding: 0 5px;
            font-family: inherit;
            font-size: 11.5px;
            text-align: right;
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 7px;
            background: #fff;
            font-variant-numeric: tabular-nums;
        }

        .nc-cart-table input:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
            box-shadow: 0 0 0 2px var(--mod-primaria-suave, #ffe9ea);
        }

        .nc-cart-table input.error {
            border-color: var(--mod-primaria, #d9232d);
            background: #fff6f6;
        }

        .nc-cart-table .nl-sub {
            display: block;
            font-size: 11.5px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .nc-quitar {
            width: 24px;
            height: 24px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #c62828;
            font-size: 11.5px;
            cursor: pointer;
        }

        .nc-quitar:hover {
            background: var(--mod-rojo-fondo, #ffecec);
        }

        .nc-totales {
            padding: 10px 12px;
            border-top: 1px solid var(--mod-linea, #ececef);
            background: #fff;
        }

        .nc-tot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 2px 0;
            font-size: 12px;
            color: var(--mod-texto, #6b6165);
        }

        .nc-tot.total {
            margin-top: 5px;
            padding-top: 8px;
            border-top: 2px solid var(--mod-tinta, #1c1416);
            font-size: 15px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
        }

        .nc-tot.total b {
            color: var(--mod-primaria, #d9232d);
            font-size: 18px;
            font-variant-numeric: tabular-nums;
        }

        .nc-tot .nc-mini-input {
            width: 88px;
            height: 28px;
            padding: 0 7px;
            font-family: inherit;
            font-size: 11.5px;
            text-align: right;
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 7px;
        }

        .nc-tot .nc-mini-input:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
        }

        .nc-acciones {
            padding: 10px 12px 12px;
        }

        .nc-acciones .btn-mod {
            width: 100%;
            justify-content: center;
            height: 40px;
            font-size: 14px;
        }

        @media (max-width: 1100px) {
            .nc-layout {
                grid-template-columns: 1fr;
            }

            .nc-carrito {
                position: static;
            }

            .nc-prod-lista {
                max-height: 45vh;
            }

            .nc-carrito-lista {
                max-height: 34vh;
            }
        }

        @media (max-width: 720px) {
            .nc-cabecera {
                grid-template-columns: 1fr 1fr;
            }

            .nc-cabecera .ancho-2 {
                grid-column: span 2;
            }
        }

        @media (max-width: 520px) {
            .nc-cabecera {
                grid-template-columns: 1fr;
            }

            .nc-cabecera .ancho-2 {
                grid-column: span 1;
            }
        }
    </style>

    <script defer src="../modelos/compras/compras_api.js"></script>
    <script defer src="../modelos/compras/nuevacompra.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-cart-plus" aria-hidden="true"></i>
                    <?php echo $modoEdicion ? 'Modificar compra #' . $idCompraEdicion : 'Registrar nueva compra'; ?>
                    <span class="nc-sucursal-badge" id="ncSucursalBadge" style="display:none">
                        <i class="fa-solid fa-store"></i> <span>—</span>
                    </span>
                </h3>
                <a href="compras.php" class="btn-mod btn-mod-claro">
                    <i class="fas fa-arrow-left"></i> <span>Volver al historial</span>
                </a>
            </div>

            <div class="card-body" style="padding:12px">

                <div class="tabla-carga" id="ncCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando catálogo y proveedores...</span>
                </div>

                <form id="formCompra" novalidate autocomplete="off" data-id="<?php echo $idCompraEdicion; ?>" style="display:none">

                    <!-- Datos del documento -->
                    <div class="nc-cabecera">

                        <div class="campo" data-campo="sucursal">
                            <label for="ncSucursal"><i class="fa-solid fa-store txt-ico"></i> Sucursal <span class="req">*</span></label>
                            <div class="wrap-input">
                                <i class="fa-solid fa-store ico-izq"></i>
                                <select class="select-modern" id="ncSucursal" name="id_sucursal"></select>
                            </div>
                            <span class="ayuda" id="ncSucursalAyuda">El stock se actualiza sólo en esta sucursal</span>
                        </div>

                        <div class="campo" data-campo="fecha">
                            <label for="ncFecha"><i class="fa-solid fa-calendar-days txt-ico"></i> Fecha de compra <span class="req">*</span></label>
                            <div class="wrap-input">
                                <i class="fa-solid fa-calendar ico-izq"></i>
                                <input class="input-modern" type="datetime-local" id="ncFecha" name="fecha_compra" />
                            </div>
                            <span class="ayuda">No puede ser futura</span>
                        </div>

                        <div class="campo" data-campo="proveedor">
                            <label for="ncProveedor"><i class="fa-solid fa-truck-field txt-ico"></i> Proveedor <span class="req">*</span></label>
                            <div style="display:flex;gap:6px">
                                <div class="wrap-input" style="flex:1">
                                    <i class="fa-solid fa-truck ico-izq"></i>
                                    <select class="select-modern" id="ncProveedor" name="id_proveedor"></select>
                                </div>
                                <button type="button" class="btn-mod btn-mod-claro" id="ncNuevoProveedor" title="Nuevo proveedor" style="height:36px;padding:0 11px;flex:0 0 auto">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            <span class="ayuda">Sólo proveedores activos</span>
                        </div>

                        <div class="campo" data-campo="tipo_documento">
                            <label for="ncTipo"><i class="fa-solid fa-file-invoice txt-ico"></i> Tipo de documento</label>
                            <div class="wrap-input">
                                <i class="fa-solid fa-file-lines ico-izq"></i>
                                <select class="select-modern" id="ncTipo" name="tipo_documento">
                                    <option value="factura">Factura</option>
                                    <option value="recibo">Recibo</option>
                                    <option value="nota">Nota de entrega</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                            <span class="ayuda">Documento del proveedor</span>
                        </div>

                        <div class="campo" data-campo="numero_documento">
                            <label for="ncNumero"><i class="fa-solid fa-hashtag txt-ico"></i> Número de documento</label>
                            <div class="wrap-input">
                                <i class="fa-solid fa-hashtag ico-izq"></i>
                                <input class="input-modern" type="text" id="ncNumero" name="numero_documento" maxlength="60" placeholder="FAC-000123" />
                            </div>
                            <span class="ayuda">No se puede repetir con el mismo proveedor</span>
                        </div>

                        <div class="campo" data-campo="forma_pago">
                            <label for="ncPago"><i class="fa-solid fa-wallet txt-ico"></i> Forma de pago</label>
                            <div class="wrap-input">
                                <i class="fa-solid fa-money-bill-wave ico-izq"></i>
                                <select class="select-modern" id="ncPago" name="forma_pago">
                                    <option value="contado">Contado</option>
                                    <option value="credito">Crédito</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="qr">QR</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                            <span class="ayuda">Condición de pago</span>
                        </div>

                        <div class="campo ancho-2" data-campo="observaciones">
                            <label for="ncObs"><i class="fa-solid fa-note-sticky txt-ico"></i> Observaciones</label>
                            <div class="wrap-input">
                                <i class="fa-solid fa-pen ico-izq"></i>
                                <textarea class="input-modern" id="ncObs" name="observaciones" maxlength="255" rows="1" placeholder="Detalle adicional de la compra (opcional)"></textarea>
                            </div>
                            <span class="ayuda">Hasta 255 caracteres</span>
                        </div>

                    </div>

                    <div class="nc-layout">

                        <!-- Catálogo -->
                        <div class="nc-panel">
                            <div class="nc-panel-cab">
                                <h4><i class="fa-solid fa-boxes-stacked"></i> Productos de la sucursal</h4>
                                <span class="badge-mod badge-azul" id="ncProductosCount">0</span>
                            </div>

                            <div class="nc-buscador">
                                <div class="nc-buscar">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="search" id="ncBuscar" placeholder="Buscar por nombre, código o categoría..." autocomplete="off" />
                                </div>
                                <select id="ncCategoria" style="flex:0 1 180px">
                                    <option value="">Todas las categorías</option>
                                </select>
                            </div>

                            <div class="nc-prod-lista" id="ncProductos">
                                <div class="nc-vacio">
                                    <i class="fa-solid fa-box-open"></i>
                                    <span>Cargando productos...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Carrito -->
                        <aside class="nc-panel nc-carrito">
                            <div class="nc-panel-cab">
                                <h4><i class="fa-solid fa-cart-shopping"></i> Carrito de compra</h4>
                                <span class="badge-mod badge-ambar" id="ncCarritoCount">0 ítems</span>
                            </div>

                            <div class="nc-carrito-lista" id="ncCarrito">
                                <div class="nc-vacio">
                                    <i class="fa-solid fa-cart-plus"></i>
                                    <span>Agrega productos desde el catálogo</span>
                                </div>
                            </div>

                            <div class="nc-totales">
                                <div class="nc-tot">
                                    <span>Subtotal</span>
                                    <b id="ncSubtotal">Bs 0,00</b>
                                </div>

                                <div class="nc-tot">
                                    <span><i class="fa-solid fa-tag" style="color:var(--mod-texto-tenue)"></i> Descuento (Bs)</span>
                                    <input class="nc-mini-input" type="number" id="ncDescuento" min="0" step="0.01" value="0" />
                                </div>

                                <div class="nc-tot">
                                    <span><i class="fa-solid fa-percent" style="color:var(--mod-texto-tenue)"></i> Impuesto (%)</span>
                                    <input class="nc-mini-input" type="number" id="ncImpuestoPct" min="0" max="100" step="0.01" value="0" />
                                </div>

                                <div class="nc-tot">
                                    <span>Impuesto</span>
                                    <b id="ncImpuesto">Bs 0,00</b>
                                </div>

                                <div class="nc-tot total">
                                    <span>Total a pagar</span>
                                    <b id="ncTotal">Bs 0,00</b>
                                </div>

                                <div class="nc-tot" id="ncAbonoFila" style="display:none">
                                    <span><i class="fa-solid fa-hand-holding-dollar" style="color:var(--mod-texto-tenue)"></i> Abono inicial (Bs)</span>
                                    <input class="nc-mini-input" type="number" id="ncAbono" min="0" step="0.01" value="0" />
                                </div>

                                <div class="nc-tot" id="ncSaldoFila" style="display:none">
                                    <span>Saldo pendiente</span>
                                    <b id="ncSaldo" style="color:var(--mod-primario)">Bs 0,00</b>
                                </div>
                            </div>

                            <label class="nc-pdf-check" for="ncPdf" style="display:flex;align-items:center;gap:8px;padding:8px 12px 0;font-size:12px;font-weight:600;color:var(--mod-texto);cursor:pointer">
                                <input type="checkbox" id="ncPdf" style="width:16px;height:16px;accent-color:var(--mod-primario)" />
                                Preparar recibo PDF al guardar
                            </label>

                            <div class="nc-acciones">
                            <?php if ($rmAcciones['registrar'] || $rmAcciones['modificar']): ?>
                                <button type="submit" class="btn-mod btn-mod-primario" id="ncGuardar">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    <span><?php echo $modoEdicion ? 'Guardar cambios' : 'Registrar compra'; ?></span>
                                </button>
                            <?php endif; ?>
                            </div>
                        </aside>

                    </div>
                </form>

            </div>
        </section>

    </main>

    <?php include '../modelos/compras/modalProveedor.php'; ?>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>
