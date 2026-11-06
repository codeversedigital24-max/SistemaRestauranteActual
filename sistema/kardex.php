<?php
/**
 * =====================================================
 * KARDEX  (sistema/kardex.php)
 * -----------------------------------------------------
 * Movimientos valorados de inventario POR SUCURSAL.
 * Cada entrada (compra) y salida queda con su saldo
 * histórico: cantidad, costo promedio y valor.
 *
 * Datos : ../modelos/compras/obtener_kardex.php
 * Red   : ../modelos/compras/compras_api.js
 * UI    : ../modelos/compras/kardex.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Kardex';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Kardex (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_INV_KARDEX);
$rmAcciones = rm_acciones(Permisos::ITEM_INV_KARDEX);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .kx-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            padding: 10px 12px 4px;
        }

        .kx-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
        }

        .kx-mini .kx-ico {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 9px;
            font-size: 14px;
            background: var(--mod-azul-fondo, #e8f2fe);
            color: var(--mod-azul, #1f6fd0);
        }

        .kx-mini.verde .kx-ico {
            background: var(--mod-verde-fondo, #e5f7ec);
            color: var(--mod-verde, #14803d);
        }

        .kx-mini.ambar .kx-ico {
            background: var(--mod-ambar-fondo, #fff4d6);
            color: var(--mod-ambar, #9a6b00);
        }

        .kx-mini.rojo .kx-ico {
            background: var(--mod-rojo-fondo, #ffecec);
            color: #c62828;
        }

        .kx-mini .kx-txt {
            min-width: 0;
            line-height: 1.15;
        }

        .kx-mini .kx-valor {
            display: block;
            font-size: 15.5px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .kx-mini .kx-et {
            display: block;
            margin-top: 2px;
            font-size: 10px;
            font-weight: 600;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .kx-filtros {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 8px;
            padding: 8px 12px 10px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
        }

        .kx-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 125px;
            flex: 1 1 145px;
        }

        .kx-campo label {
            font-size: 10.5px;
            font-weight: 800;
            color: var(--mod-texto-tenue, #a79ea2);
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .kx-campo select,
        .kx-campo input {
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

        .kx-campo select:focus,
        .kx-campo input:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
            box-shadow: 0 0 0 3px var(--mod-primaria-suave, #ffe9ea);
        }

        .kx-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
            padding-bottom: 1px;
        }

        .kx-acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        .kx-tipo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .kx-tipo.entrada {
            background: var(--mod-verde-fondo, #e5f7ec);
            color: var(--mod-verde, #14803d);
        }

        .kx-tipo.salida {
            background: var(--mod-rojo-fondo, #ffecec);
            color: #c62828;
        }

        .kx-tipo.ajuste {
            background: var(--mod-ambar-fondo, #fff4d6);
            color: var(--mod-ambar, #9a6b00);
        }

        .kx-prod {
            display: block;
            max-width: 210px;
        }

        .kx-prod b {
            display: block;
            font-size: 12px;
            color: var(--mod-tinta, #1c1416);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .kx-prod span {
            display: block;
            font-size: 10px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .kx-num {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-size: 12px;
        }

        .kx-cant.positivo {
            color: var(--mod-verde, #14803d);
            font-weight: 800;
        }

        .kx-cant.negativo {
            color: #c62828;
            font-weight: 800;
        }

        #tablaKardex td {
            font-size: 12px;
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/compras/compras_api.js"></script>
    <script defer src="../modelos/compras/kardex.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">

            <div class="card-header">
                <h3>
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    Kardex valorado por sucursal
                </h3>
                <?php if (rm_puede('ver', Permisos::ITEM_INV_NUEVA_COMPRA)): ?>
                    <a href="nuevacompra.php" class="btn-mod btn-mod-primario">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>Nueva compra</span>
                    </a>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding:0">

                <div class="kx-tarjetas">
                    <div class="kx-mini">
                        <span class="kx-ico"><i class="fa-solid fa-boxes-stacked"></i></span>
                        <span class="kx-txt"><b class="kx-valor" id="kxProductos">—</b><span class="kx-et">Productos</span></span>
                    </div>
                    <div class="kx-mini verde">
                        <span class="kx-ico"><i class="fa-solid fa-cubes"></i></span>
                        <span class="kx-txt"><b class="kx-valor" id="kxStock">—</b><span class="kx-et">Stock total</span></span>
                    </div>
                    <div class="kx-mini verde">
                        <span class="kx-ico"><i class="fa-solid fa-sack-dollar"></i></span>
                        <span class="kx-txt"><b class="kx-valor" id="kxValor">—</b><span class="kx-et">Valor inventario</span></span>
                    </div>
                    <div class="kx-mini ambar">
                        <span class="kx-ico"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        <span class="kx-txt"><b class="kx-valor" id="kxBajo">—</b><span class="kx-et">Stock bajo</span></span>
                    </div>
                    <div class="kx-mini">
                        <span class="kx-ico"><i class="fa-solid fa-arrow-down-long"></i></span>
                        <span class="kx-txt"><b class="kx-valor" id="kxEntradas">—</b><span class="kx-et">Entradas del mes</span></span>
                    </div>
                    <div class="kx-mini rojo">
                        <span class="kx-ico"><i class="fa-solid fa-arrow-up-long"></i></span>
                        <span class="kx-txt"><b class="kx-valor" id="kxSalidas">—</b><span class="kx-et">Salidas del mes</span></span>
                    </div>
                </div>

                <div class="kx-filtros">
                    <div class="kx-campo" id="kxSucursalCaja">
                        <label for="kxFiltroSucursal">Sucursal</label>
                        <select id="kxFiltroSucursal">
                            <option value="">Todas las sucursales</option>
                        </select>
                    </div>

                    <div class="kx-campo">
                        <label for="kxFiltroProducto">Producto</label>
                        <select id="kxFiltroProducto">
                            <option value="">Todos los productos</option>
                        </select>
                    </div>

                    <div class="kx-campo">
                        <label for="kxFiltroTipo">Movimiento</label>
                        <select id="kxFiltroTipo">
                            <option value="">Todos</option>
                            <option value="entrada">Entradas</option>
                            <option value="salida">Salidas</option>
                            <option value="ajuste">Ajustes</option>
                        </select>
                    </div>

                    <div class="kx-campo">
                        <label for="kxFiltroDesde">Desde</label>
                        <input type="date" id="kxFiltroDesde" />
                    </div>

                    <div class="kx-campo">
                        <label for="kxFiltroHasta">Hasta</label>
                        <input type="date" id="kxFiltroHasta" />
                    </div>

                    <div class="kx-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="kxFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="kxLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <div style="padding:0 12px 12px">
                    <div class="tabla-carga" id="kxCarga" role="status" aria-live="polite">
                        <span class="mini-spin" aria-hidden="true"></span>
                        <span>Cargando movimientos...</span>
                    </div>

                    <div class="table-responsive">
                        <table id="tablaKardex" class="display nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Sucursal</th>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Detalle</th>
                                    <th>Cantidad</th>
                                    <th>Costo unit.</th>
                                    <th>P. venta</th>
                                    <th>Valor</th>
                                    <th>Saldo cant.</th>
                                    <th>Costo prom.</th>
                                    <th>Saldo valor</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
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
