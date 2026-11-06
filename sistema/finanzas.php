<?php
/**
 * =====================================================
 * FINANZAS  (sistema/finanzas.php)
 * -----------------------------------------------------
 * 3 pestañas:
 *   · Monedas
 *   · Medios de pago
 *   · Bancos
 *
 * Datos  : ../modelos/finanzas/obtener_finanzas.php
 * API    : ../modelos/finanzas/finanzas_api.php
 * Red    : ../modelos/finanzas/finanzas_api.js
 * UI     : ../modelos/finanzas/finanzas.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Finanzas';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Finanzas (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_CONFIG_FINANZAS);
$rmAcciones = rm_acciones(Permisos::ITEM_CONFIG_FINANZAS);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        /* Pestañas */
        .fin-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            padding: 0 14px;
            overflow-x: auto;
        }

        .fin-tabs button {
            border: none;
            background: transparent;
            padding: 11px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            color: var(--mod-texto-tenue, #a79ea2);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            transition: color .15s, border-color .15s;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .fin-tabs button:hover {
            color: var(--mod-tinta, #1c1416);
        }

        .fin-tabs button.activa {
            color: var(--mod-primaria, #d9232d);
            border-bottom-color: var(--mod-primaria, #d9232d);
        }

        .fin-tabs button i {
            font-size: 13px;
        }

        .fin-panel {
            display: none;
            padding: 12px 14px 14px;
        }

        .fin-panel.activo {
            display: block;
        }

        .fin-barra {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .fin-barra .fb-titulo {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
            margin-right: auto;
        }

        /* Tabla simple (no DataTables) */
        .fin-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .fin-tabla th {
            text-align: left;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--mod-texto-tenue, #a79ea2);
            padding: 8px 10px;
            background: var(--mod-claro, #f8f7f7);
            border-bottom: 1px solid var(--mod-linea, #ececef);
        }

        .fin-tabla td {
            padding: 9px 10px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            vertical-align: middle;
        }

        .fin-tabla tr:hover td {
            background: #fffafa;
        }

        .fin-tabla .fin-vacio {
            padding: 30px 14px;
            text-align: center;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .fin-codigo {
            font-family: monospace;
            font-size: 11.5px;
            background: var(--mod-claro);
            padding: 2px 7px;
            border-radius: 5px;
            color: var(--mod-tinta);
        }

        .fin-nombre {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fin-nombre i {
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            border-radius: 6px;
            background: var(--mod-primaria-suave);
            color: var(--mod-primaria);
            font-size: 13px;
        }

        .fin-acciones {
            text-align: right;
            white-space: nowrap;
        }

        .fin-acciones button {
            border: 1px solid var(--mod-linea-fuerte, #d9d9e0);
            background: #fff;
            color: var(--mod-texto, #6b6165);
            padding: 4px 8px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 11.5px;
            cursor: pointer;
            margin-left: 3px;
        }

        .fin-acciones button:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }

        .fin-acciones button.peligro:hover {
            border-color: #c62828;
            color: #c62828;
        }

        .fin-chip-activo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--mod-verde-fondo, #e6f4ea);
            color: var(--mod-verde, #14803d);
        }

        .fin-chip-inactivo {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--mod-claro);
            color: var(--mod-texto-tenue);
        }

        .fin-chip-base {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
        }

        .fin-tasa {
            font-variant-numeric: tabular-nums;
            font-family: monospace;
            font-size: 12px;
        }
    </style>

    <script defer src="../modelos/finanzas/finanzas_api.js"></script>
    <script defer src="../modelos/finanzas/finanzas.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-coins" aria-hidden="true"></i>
                    Finanzas
                </h3>
            </div>

            <div class="fin-tabs" role="tablist">
                <button type="button" class="activa" data-tab="monedas" role="tab">
                    <i class="fa-solid fa-money-bill-1"></i> Monedas
                </button>
                <button type="button" data-tab="medios" role="tab">
                    <i class="fa-solid fa-credit-card"></i> Medios de pago
                </button>
                <button type="button" data-tab="bancos" role="tab">
                    <i class="fa-solid fa-building-columns"></i> Bancos
                </button>
            </div>

            <div id="finCarga" class="tabla-carga visible" role="status" aria-live="polite">
                <span class="mini-spin" aria-hidden="true"></span>
                <span>Cargando finanzas...</span>
            </div>

            <!-- =============================================
                 PANEL MONEDAS
                 ============================================= -->
            <div class="fin-panel activo" id="panel-monedas" role="tabpanel">
                <div class="fin-barra">
                    <span class="fb-titulo">Monedas</span>
                    <?php if ($rmAcciones['modificar']): ?>
                        <button type="button" class="btn-mod btn-mod-primario" data-nuevo="moneda">
                            <i class="fas fa-plus"></i> Nueva moneda
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="fin-tabla">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Moneda</th>
                                <th>Símbolo</th>
                                <th>Decimales</th>
                                <th>Tasa</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaMonedas"></tbody>
                    </table>
                </div>
            </div>

            <!-- =============================================
                 PANEL MEDIOS DE PAGO
                 ============================================= -->
            <div class="fin-panel" id="panel-medios" role="tabpanel">
                <div class="fin-barra">
                    <span class="fb-titulo">Medios de pago</span>
                    <?php if ($rmAcciones['modificar']): ?>
                        <button type="button" class="btn-mod btn-mod-primario" data-nuevo="medio">
                            <i class="fas fa-plus"></i> Nuevo medio
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="fin-tabla">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Medio</th>
                                <th>Afecta caja</th>
                                <th>Ref.</th>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaMedios"></tbody>
                    </table>
                </div>
            </div>

            <!-- =============================================
                 PANEL BANCOS
                 ============================================= -->
            <div class="fin-panel" id="panel-bancos" role="tabpanel">
                <div class="fin-barra">
                    <span class="fb-titulo">Bancos</span>
                    <?php if ($rmAcciones['modificar']): ?>
                        <button type="button" class="btn-mod btn-mod-primario" data-nuevo="banco">
                            <i class="fas fa-plus"></i> Nuevo banco
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="fin-tabla">
                        <thead>
                            <tr>
                                <th>Banco</th>
                                <th>N° Cuenta</th>
                                <th>Titular</th>
                                <th>Moneda</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaBancos"></tbody>
                    </table>
                </div>
            </div>

        </section>
    </main>

    <!-- =============================================
         MODAL MONEDA
         ============================================= -->
    <div class="modal-overlay" id="modalMoneda" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-money-bill-1"></i></span>
                    <span style="color:#fff" id="mMonTitulo">Nueva moneda</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="moneda">&times;</button>
            </div>
            <form id="formMoneda" novalidate autocomplete="off">
                <div class="modal-cuerpo">
                    <div class="campo" data-campo="codigo">
                        <label for="m_codigo">Código ISO <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-hashtag ico-izq"></i>
                            <input class="input-modern" type="text" id="m_codigo" maxlength="8" placeholder="BOB" style="text-transform:uppercase" required />
                        </div>
                        <span class="ayuda">Ej. BOB, USD, EUR</span>
                    </div>

                    <div class="campo" data-campo="nombre">
                        <label for="m_nombre">Nombre <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-tag ico-izq"></i>
                            <input class="input-modern" type="text" id="m_nombre" maxlength="60" placeholder="Boliviano" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="simbolo">
                        <label for="m_simbolo">Símbolo <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-dollar-sign ico-izq"></i>
                            <input class="input-modern" type="text" id="m_simbolo" maxlength="8" placeholder="Bs" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="decimales">
                        <label for="m_decimales">Decimales</label>
                        <div class="wrap-input">
                            <input class="input-modern" type="number" id="m_decimales" min="0" max="6" value="2" />
                        </div>
                    </div>

                    <div class="campo" data-campo="tasa_cambio">
                        <label for="m_tasa_cambio">Tasa de cambio</label>
                        <div class="wrap-input">
                            <input class="input-modern" type="number" id="m_tasa_cambio" step="0.000001" min="0" value="1.000000" />
                        </div>
                        <span class="ayuda">1 unidad = X moneda base</span>
                    </div>

                    <div class="campo" data-campo="es_base">
                        <label>Moneda base</label>
                        <label class="check-mod" for="m_es_base">
                            <input type="checkbox" id="m_es_base" value="1" />
                            <span>Marcar como moneda base</span>
                        </label>
                        <span class="ayuda">Sólo puede haber una base</span>
                    </div>

                    <div class="campo" data-campo="estado">
                        <label for="m_estado">Estado</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="m_estado">
                                <option value="1">Activa</option>
                                <option value="0">Inactiva</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" id="m_id" />
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn-mod btn-mod-claro" data-cerrar="moneda">Cancelar</button>
                    <button type="submit" class="btn-mod btn-mod-primario" id="mGuardarMoneda">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
         MODAL MEDIO DE PAGO
         ============================================= -->
    <div class="modal-overlay" id="modalMedio" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-credit-card"></i></span>
                    <span style="color:#fff" id="mMedTitulo">Nuevo medio</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="medio">&times;</button>
            </div>
            <form id="formMedio" novalidate autocomplete="off">
                <div class="modal-cuerpo">
                    <div class="campo" data-campo="codigo">
                        <label for="md_codigo">Código <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-hashtag ico-izq"></i>
                            <input class="input-modern" type="text" id="md_codigo" maxlength="30" placeholder="efectivo" style="text-transform:lowercase" required />
                        </div>
                        <span class="ayuda">Se usa en el POS (minúsculas, sin espacios)</span>
                    </div>

                    <div class="campo" data-campo="nombre">
                        <label for="md_nombre">Nombre <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-tag ico-izq"></i>
                            <input class="input-modern" type="text" id="md_nombre" maxlength="60" placeholder="Efectivo" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="icono">
                        <label for="md_icono">Ícono (Font Awesome)</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-icons ico-izq"></i>
                            <input class="input-modern" type="text" id="md_icono" maxlength="60" placeholder="fa-money-bill-wave" />
                        </div>
                        <span class="ayuda">Ej. fa-qrcode, fa-credit-card</span>
                    </div>

                    <div class="campo" data-campo="afecta_caja">
                        <label>Afecta caja</label>
                        <label class="check-mod" for="md_afecta_caja">
                            <input type="checkbox" id="md_afecta_caja" value="1" checked />
                            <span>Suma al arqueo de caja</span>
                        </label>
                    </div>

                    <div class="campo" data-campo="requiere_referencia">
                        <label>Requiere referencia</label>
                        <label class="check-mod" for="md_requiere_ref">
                            <input type="checkbox" id="md_requiere_ref" value="1" />
                            <span>Pedir N° operación / comprobante</span>
                        </label>
                    </div>

                    <div class="campo" data-campo="orden">
                        <label for="md_orden">Orden</label>
                        <div class="wrap-input">
                            <input class="input-modern" type="number" id="md_orden" min="0" value="0" />
                        </div>
                    </div>

                    <div class="campo" data-campo="estado">
                        <label for="md_estado">Estado</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="md_estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" id="md_id" />
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn-mod btn-mod-claro" data-cerrar="medio">Cancelar</button>
                    <button type="submit" class="btn-mod btn-mod-primario" id="mGuardarMedio">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
         MODAL BANCO
         ============================================= -->
    <div class="modal-overlay" id="modalBanco" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-building-columns"></i></span>
                    <span style="color:#fff" id="mBanTitulo">Nuevo banco</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="banco">&times;</button>
            </div>
            <form id="formBanco" novalidate autocomplete="off">
                <div class="modal-cuerpo">
                    <div class="campo ancho-2" data-campo="nombre">
                        <label for="b_nombre">Nombre del banco <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-building-columns ico-izq"></i>
                            <input class="input-modern" type="text" id="b_nombre" maxlength="120" placeholder="Banco Mercantil Santa Cruz" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="codigo">
                        <label for="b_codigo">Código interno</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-hashtag ico-izq"></i>
                            <input class="input-modern" type="text" id="b_codigo" maxlength="20" placeholder="BMSC" />
                        </div>
                    </div>

                    <div class="campo" data-campo="nro_cuenta">
                        <label for="b_nro_cuenta">N° cuenta <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-list-ol ico-izq"></i>
                            <input class="input-modern" type="text" id="b_nro_cuenta" maxlength="60" placeholder="1234567890" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="titular">
                        <label for="b_titular">Titular</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-user ico-izq"></i>
                            <input class="input-modern" type="text" id="b_titular" maxlength="160" placeholder="Razón social" />
                        </div>
                    </div>

                    <div class="campo" data-campo="id_moneda">
                        <label for="b_moneda">Moneda</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="b_moneda">
                                <option value="">— Sin moneda —</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="estado">
                        <label for="b_estado">Estado</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="b_estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo ancho-2" data-campo="observaciones">
                        <label for="b_observaciones">Observaciones</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-pen ico-izq"></i>
                            <textarea class="input-modern" id="b_observaciones" maxlength="255" rows="2"></textarea>
                        </div>
                    </div>

                    <input type="hidden" id="b_id" />
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn-mod btn-mod-claro" data-cerrar="banco">Cancelar</button>
                    <button type="submit" class="btn-mod btn-mod-primario" id="mGuardarBanco">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>