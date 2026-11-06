<?php

/**
 * =====================================================
 * VENTAS  (sistema/ventas.php)
 * -----------------------------------------------------
 * Historial de ventas por sucursal y vendedor.
 *
 * Datos  : ../modelos/ventas/obtener_ventas.php
 * API    : ../modelos/ventas/ventas_api.php
 * Red    : ../modelos/ventas/ventas_api.js
 * UI     : ../modelos/ventas/ventas.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Ventas';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Historial de ventas (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_VENTAS_HISTORIAL);
$rmAcciones = rm_acciones(Permisos::ITEM_VENTAS_HISTORIAL);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .ven-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .ven-mini {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            font-size: 12.5px;
            color: var(--mod-texto, #6b6165);
        }

        .ven-mini i {
            color: var(--mod-primaria, #d9232d);
            font-size: 15px;
        }

        .ven-mini b {
            color: var(--mod-tinta, #1c1416);
            font-size: 15px;
            font-variant-numeric: tabular-nums;
        }

        .ven-barra {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding: 9px 11px;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            margin-bottom: 10px;
        }

        .ven-barra .vb-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 140px;
            min-width: 120px;
        }

        .ven-barra label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0;
        }

        .ven-barra select,
        .ven-barra input {
            height: 32px;
            width: 100%;
            padding: 0 9px;
            border: 1px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .ven-barra .vb-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
            padding-bottom: 1px;
        }

        .ven-barra .vb-acciones .btn-mod {
            height: 32px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        .ven-doc {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .ven-doc b {
            font-family: monospace;
            font-size: 11.5px;
            color: var(--mod-tinta, #1c1416);
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/ventas/ventas_api.js"></script>
    <script defer src="../modelos/ventas/ventas.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">

            <div class="card-header">
                <h3>
                    <i class="fas fa-receipt" aria-hidden="true"></i>
                    Historial de ventas
                </h3>
                <a href="pos.php" class="btn-mod btn-mod-primario">
                    <i class="fas fa-cash-register" aria-hidden="true"></i>
                    <span>Ir al POS</span>
                </a>
            </div>

            <div class="card-body">

                <!-- Tarjetas de resumen -->
                <div class="ven-tarjetas">
                    <div class="ven-mini">
                        <i class="fas fa-receipt"></i>
                        <span><b id="rvVentas">0</b> ventas</span>
                    </div>
                    <div class="ven-mini">
                        <i class="fas fa-sack-dollar"></i>
                        <span><b id="rvTotal">Bs 0,00</b> total</span>
                    </div>
                    <div class="ven-mini">
                        <i class="fas fa-money-bill-wave"></i>
                        <span><b id="rvEfectivo">Bs 0,00</b> efectivo</span>
                    </div>
                    <div class="ven-mini">
                        <i class="fas fa-credit-card"></i>
                        <span><b id="rvTarjeta">Bs 0,00</b> tarjeta</span>
                    </div>
                    <div class="ven-mini">
                        <i class="fas fa-qrcode"></i>
                        <span><b id="rvQr">Bs 0,00</b> QR</span>
                    </div>
                    <div class="ven-mini">
                        <i class="fas fa-ban"></i>
                        <span><b id="rvAnuladas">0</b> anuladas</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="ven-barra">
                    <div class="vb-campo" id="fSucursalWrap">
                        <label for="fSucursal">Sucursal</label>
                        <select id="fSucursal">
                            <option value="">Todas</option>
                        </select>
                    </div>

                    <div class="vb-campo">
                        <label for="fVendedor">Vendedor</label>
                        <select id="fVendedor">
                            <option value="">Todos</option>
                        </select>
                    </div>

                    <div class="vb-campo">
                        <label for="fMetodo">Método</label>
                        <select id="fMetodo">
                            <option value="">Todos</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="qr">QR</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="mixto">Mixto</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div class="vb-campo">
                        <label for="fEstado">Estado</label>
                        <select id="fEstado">
                            <option value="">Todos</option>
                            <option value="registrada">Registradas</option>
                            <option value="anulada">Anuladas</option>
                        </select>
                    </div>

                    <div class="vb-campo">
                        <label for="fDesde">Desde</label>
                        <input type="date" id="fDesde" />
                    </div>

                    <div class="vb-campo">
                        <label for="fHasta">Hasta</label>
                        <input type="date" id="fHasta" />
                    </div>

                    <div class="vb-campo">
                        <label for="fBuscar">Buscar</label>
                        <input type="search" id="fBuscar" placeholder="Código o usuario…" />
                    </div>

                    <div class="vb-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="btnFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="btnLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Carga -->
                <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando ventas...</span>
                </div>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table id="tablaVentas" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Fecha</th>
                                <th>Vendedor</th>
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
        </section>

    </main>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>