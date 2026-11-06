<?php

/**
 * =====================================================
 * MESAS  (sistema/mesas.php)
 * -----------------------------------------------------
 * Configuración de pisos y mesas por sucursal.
 * Vista de tarjetas (grid) con foto/ícono y filtros.
 * Sólo SuperAdmin y Administrador.
 *
 * Datos  : ../modelos/mesas/obtener_mesas.php
 * API    : ../modelos/mesas/mesas_api.php
 * Red    : ../modelos/mesas/mesas_api.js
 * UI     : ../modelos/mesas/mesas.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Mesas';


include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Configuración de mesas (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_CONFIG_MESAS);
$rmAcciones = rm_acciones(Permisos::ITEM_CONFIG_MESAS);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        /* Filtros */
        .mes-filtros {
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

        .mes-filtros .mf-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 140px;
            min-width: 120px;
        }

        .mes-filtros label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0;
        }

        .mes-filtros select {
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

        .mes-filtros .mf-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
        }

        /* Chips pisos rápidos */
        .mes-pisos {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .mes-pisos button {
            border: 1px solid var(--mod-linea-fuerte);
            background: #fff;
            color: var(--mod-texto);
            padding: 7px 14px;
            border-radius: 999px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background .15s, color .15s, border-color .15s;
        }

        .mes-pisos button.activa {
            background: var(--mod-primaria);
            border-color: var(--mod-primaria);
            color: #fff;
        }

        .mes-pisos button .badge-piso {
            background: rgba(255, 255, 255, .25);
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 10.5px;
        }

        .mes-pisos button:not(.activa) .badge-piso {
            background: var(--mod-claro);
            color: var(--mod-texto-tenue);
        }

        /* Grid de mesas */
        .mes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 12px;
        }

        .mes-card {
            position: relative;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .12s, box-shadow .15s, border-color .15s;
            cursor: default;
        }

        .mes-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, .08);
        }

        .mes-card.inactiva {
            opacity: .55;
        }

        .mes-card.ocupada {
            border-color: #fde68a;
        }

        .mes-card.reservada {
            border-color: #bfdbfe;
        }

        .mes-card.inactiva-mesa {
            border-color: #e5e7eb;
            opacity: .6;
        }

        .mes-card .mc-foto {
            aspect-ratio: 4 / 3;
            background: linear-gradient(135deg, #f8f7f7, #f1eff0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c7cdd8;
            font-size: 44px;
            overflow: hidden;
            position: relative;
        }

        .mes-card .mc-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mes-card .mc-forma {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, .9);
            color: var(--mod-tinta);
            border-radius: 8px;
            padding: 3px 8px;
            font-size: 10.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .mes-card .mc-piso {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(28, 20, 22, .7);
            color: #fff;
            border-radius: 8px;
            padding: 3px 8px;
            font-size: 10.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }

        .mes-card .mc-body {
            padding: 10px 12px 12px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
        }

        .mes-card .mc-codigo {
            font-family: monospace;
            font-size: 10.5px;
            color: var(--mod-texto-tenue);
            letter-spacing: .5px;
        }

        .mes-card .mc-nombre {
            font-size: 14px;
            font-weight: 800;
            color: var(--mod-tinta);
            line-height: 1.2;
        }

        .mes-card .mc-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 11.5px;
            color: var(--mod-texto);
        }

        .mes-card .mc-meta i {
            color: var(--mod-primaria);
            font-size: 11px;
            margin-right: 4px;
        }

        .mes-card .mc-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .mc-chip.libre {
            background: #e6f4ea;
            color: #14803d;
        }

        .mc-chip.ocupada {
            background: #fef3c7;
            color: #92400e;
        }

        .mc-chip.reservada {
            background: #dbeafe;
            color: #1e40af;
        }

        .mc-chip.inactiva {
            background: #f3f4f6;
            color: #6b7280;
        }

        .mes-card .mc-acciones {
            display: flex;
            gap: 4px;
            margin-top: auto;
            padding-top: 6px;
            border-top: 1px dashed var(--mod-linea);
        }

        .mes-card .mc-acciones button {
            flex: 1 1 auto;
            height: 30px;
            border: 1px solid var(--mod-linea-fuerte);
            background: #fff;
            color: var(--mod-texto);
            border-radius: 7px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .mes-card .mc-acciones button:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }

        .mes-card .mc-acciones button.peligro:hover {
            border-color: #b91c1c;
            color: #b91c1c;
        }

        /* Añadir mesa como tarjeta fantasma */
        .mes-add {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 260px;
            border: 2px dashed var(--mod-linea-fuerte);
            border-radius: 14px;
            color: var(--mod-texto-tenue);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            background: #fff;
            transition: border-color .15s, color .15s;
        }

        .mes-add:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }

        .mes-add i {
            font-size: 28px;
        }

        /* Bloques de pisos (sub-cards) */
        .mes-pisos-card {
            background: #fff;
            border: 1px solid var(--mod-linea);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 12px;
        }

        .mes-pisos-card h4 {
            margin: 0 0 8px;
            font-size: 12.5px;
            font-weight: 800;
            color: var(--mod-tinta);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .mes-pisos-card h4 i {
            color: var(--mod-primaria);
        }

        .mes-piso-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            background: var(--mod-claro);
            border: 1px solid var(--mod-linea);
            border-radius: 8px;
            font-size: 12px;
            margin: 0 4px 4px 0;
        }

        .mes-piso-chip b {
            color: var(--mod-tinta);
        }

        .mes-piso-chip .btn-x {
            border: none;
            background: transparent;
            color: var(--mod-texto-tenue);
            cursor: pointer;
            padding: 0 2px;
            font-size: 12px;
        }

        .mes-piso-chip .btn-x:hover {
            color: #b91c1c;
        }

        .mes-vacio {
            grid-column: 1 / -1;
            padding: 40px 14px;
            text-align: center;
            color: var(--mod-texto-tenue);
            font-size: 13px;
        }

        /* Imagen mesa preview */
        .mes-img-prev {
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #f4f4f6;
            border: 2px dashed var(--mod-linea-fuerte);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #c7cdd8;
            font-size: 26px;
            margin-bottom: 6px;
        }

        .mes-img-prev img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mes-img-acciones {
            display: flex;
            gap: 6px;
            margin-bottom: 6px;
        }

        .mes-img-acciones button {
            flex: 1 1 auto;
            height: 30px;
            border-radius: 7px;
            border: 1px solid var(--mod-linea-fuerte);
            background: #fff;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            color: var(--mod-texto);
            cursor: pointer;
        }

        .mes-img-acciones button:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }
    </style>

    <script defer src="../modelos/mesas/mesas_api.js"></script>
    <script defer src="../modelos/mesas/mesas.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-chair" aria-hidden="true"></i>
                    Configuración de mesas
                </h3>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn-mod btn-mod-claro" id="btnPisos">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Pisos</span>
                    </button>
                    <?php if ($rmAcciones['modificar']): ?>
                        <button type="button" class="btn-mod btn-mod-primario" id="btnNuevaMesa">
                            <i class="fa-solid fa-plus"></i>
                            <span>Nueva mesa</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body" style="padding:0">

                <div id="mesCarga" class="tabla-carga visible" role="status" aria-live="polite">
                    <span class="mini-spin"></span>
                    <span>Cargando mesas...</span>
                </div>

                <div id="mesContenido" style="display:none;padding:12px">

                    <!-- Pisos disponibles (chips) -->
                    <div class="mes-pisos-card">
                        <h4><i class="fa-solid fa-layer-group"></i> Pisos configurados</h4>
                        <div id="mesPisosChips">Cargando pisos…</div>
                    </div>

                    <!-- Filtros -->
                    <div class="mes-filtros">
                        <div class="mf-campo" id="mfSucursalWrap">
                            <label for="mfSucursal">Sucursal</label>
                            <select id="mfSucursal">
                                <option value="">Todas</option>
                            </select>
                        </div>
                        <div class="mf-campo">
                            <label for="mfPiso">Piso</label>
                            <select id="mfPiso">
                                <option value="">Todos los pisos</option>
                            </select>
                        </div>
                        <div class="mf-campo">
                            <label for="mfEstado">Estado</label>
                            <select id="mfEstado">
                                <option value="">Todas</option>
                                <option value="libre">Libres</option>
                                <option value="ocupada">Ocupadas</option>
                                <option value="reservada">Reservadas</option>
                                <option value="inactiva">Inactivas</option>
                            </select>
                        </div>
                        <div class="mf-acciones">
                            <button type="button" class="btn-mod btn-mod-claro" id="mfLimpiar">
                                <i class="fa-solid fa-rotate-left"></i> Limpiar
                            </button>
                        </div>
                    </div>

                    <!-- Grid de mesas -->
                    <div class="mes-grid" id="mesGrid"></div>

                </div>
            </div>
        </section>
    </main>

    <!-- =============================================
         MODAL PISO
         ============================================= -->
    <div class="modal-overlay" id="modalPiso" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-layer-group"></i></span>
                    <span style="color:#fff" id="pisoTitulo">Nuevo piso</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="piso">&times;</button>
            </div>
            <form id="formPiso" novalidate autocomplete="off">
                <div class="modal-cuerpo">
                    <div class="campo" data-campo="id_sucursal">
                        <label for="piso_sucursal">Sucursal <span class="req">*</span></label>
                        <div class="wrap-input">
                            <select class="select-modern" id="piso_sucursal" required>
                                <option value="">— Selecciona —</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="nombre">
                        <label for="piso_nombre">Nombre <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-tag ico-izq"></i>
                            <input class="input-modern" type="text" id="piso_nombre" maxlength="60" placeholder="Planta baja" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="icono">
                        <label for="piso_icono">Ícono</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-icons ico-izq"></i>
                            <input class="input-modern" type="text" id="piso_icono" maxlength="60" placeholder="fa-house" />
                        </div>
                        <span class="ayuda">Ej. fa-stairs, fa-umbrella-beach</span>
                    </div>

                    <div class="campo" data-campo="orden">
                        <label for="piso_orden">Orden</label>
                        <div class="wrap-input">
                            <input class="input-modern" type="number" id="piso_orden" min="0" value="0" />
                        </div>
                    </div>

                    <div class="campo" data-campo="estado">
                        <label for="piso_estado">Estado</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="piso_estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" id="piso_id" />
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn-mod btn-mod-claro" data-cerrar="piso">Cancelar</button>
                    <button type="submit" class="btn-mod btn-mod-primario" id="pisoGuardar">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =============================================
         MODAL MESA
         ============================================= -->
    <div class="modal-overlay" id="modalMesa" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-chair"></i></span>
                    <span style="color:#fff" id="mesaTitulo">Nueva mesa</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="mesa">&times;</button>
            </div>
            <form id="formMesa" novalidate autocomplete="off">
                <div class="modal-cuerpo">
                    <div class="campo" data-campo="id_sucursal">
                        <label for="mesa_sucursal">Sucursal <span class="req">*</span></label>
                        <div class="wrap-input">
                            <select class="select-modern" id="mesa_sucursal" required>
                                <option value="">— Selecciona —</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="id_piso">
                        <label for="mesa_piso">Piso</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="mesa_piso">
                                <option value="">— Sin piso —</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="codigo">
                        <label for="mesa_codigo">Código <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-hashtag ico-izq"></i>
                            <input class="input-modern" type="text" id="mesa_codigo" maxlength="20" placeholder="M1" style="text-transform:uppercase" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="nombre">
                        <label for="mesa_nombre">Nombre <span class="req">*</span></label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-tag ico-izq"></i>
                            <input class="input-modern" type="text" id="mesa_nombre" maxlength="80" placeholder="Mesa 1" required />
                        </div>
                    </div>

                    <div class="campo" data-campo="capacidad">
                        <label for="mesa_capacidad">Capacidad</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-user-group ico-izq"></i>
                            <input class="input-modern" type="number" id="mesa_capacidad" min="1" max="50" value="4" />
                        </div>
                        <span class="ayuda">Personas por mesa</span>
                    </div>

                    <div class="campo" data-campo="forma">
                        <label for="mesa_forma">Forma</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="mesa_forma">
                                <option value="redonda">Redonda</option>
                                <option value="cuadrada">Cuadrada</option>
                                <option value="rectangular">Rectangular</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="icono">
                        <label for="mesa_icono">Ícono</label>
                        <div class="wrap-input">
                            <i class="fa-solid fa-icons ico-izq"></i>
                            <input class="input-modern" type="text" id="mesa_icono" maxlength="60" placeholder="fa-chair" />
                        </div>
                    </div>

                    <div class="campo" data-campo="estado_mesa">
                        <label for="mesa_estado_mesa">Disponibilidad</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="mesa_estado_mesa">
                                <option value="libre">Libre</option>
                                <option value="ocupada">Ocupada</option>
                                <option value="reservada">Reservada</option>
                                <option value="inactiva">Inactiva</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo" data-campo="orden">
                        <label for="mesa_orden">Orden</label>
                        <div class="wrap-input">
                            <input class="input-modern" type="number" id="mesa_orden" min="0" value="0" />
                        </div>
                    </div>

                    <div class="campo" data-campo="estado">
                        <label for="mesa_estado">Activa</label>
                        <div class="wrap-input">
                            <select class="select-modern" id="mesa_estado">
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>

                    <div class="campo ancho-2" data-campo="imagen">
                        <label>Imagen (opcional)</label>
                        <div class="mes-img-prev" id="mesaImgPrev"><i class="fa-solid fa-image"></i></div>
                        <div class="mes-img-acciones">
                            <button type="button" id="mesaImgElegir"><i class="fa-solid fa-upload"></i> Subir</button>
                            <button type="button" id="mesaImgQuitar"><i class="fa-solid fa-xmark"></i> Quitar</button>
                        </div>
                        <input type="file" id="mesaImgFile" accept="image/jpeg,image/png,image/webp" hidden />
                        <input type="hidden" id="mesa_imagen" value="" />
                    </div>

                    <input type="hidden" id="mesa_id" />
                </div>
                <div class="modal-pie">
                    <button type="button" class="btn-mod btn-mod-claro" data-cerrar="mesa">Cancelar</button>
                    <button type="submit" class="btn-mod btn-mod-primario" id="mesaGuardar">
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