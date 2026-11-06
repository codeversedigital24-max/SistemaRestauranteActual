<!-- =====================================================
     MODAL: NUEVA / MODIFICAR CAJA
     (se incluye desde sistema/caja.php)
     ===================================================== -->
<style>
    .modal-cuerpo .caja-sep {
        margin: 8px 0 2px;
        padding-top: 8px;
        border-top: 1px dashed var(--mod-linea);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        color: var(--mod-primaria);
    }

    .caja-nota {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 9px 12px;
        margin-top: 2px;
        border-radius: var(--mod-radio-sm);
        background: var(--mod-azul-fondo, #e8f2fe);
        border: 1px solid #cfe3fb;
        font-size: 12px;
        line-height: 1.45;
        color: #1f5fa8;
    }

    .caja-nota i {
        margin-top: 2px;
    }
</style>

<div class="modal-overlay" id="modalCaja" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mCajaTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mCajaTitulo">
                <span class="ico-titulo" id="mCajaIcono"><i class="fa-solid fa-cash-register"></i></span>
                <span id="mCajaTexto" style="color:#fff;">Nueva caja</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mCajaCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formCaja" novalidate autocomplete="off" data-accion="crear" data-id="">

            <div class="modal-cuerpo">

                <div class="caja-sep ancho-2">Ubicación y datos</div>

                <div class="campo" data-campo="id_sucursal">
                    <label for="c_sucursal">
                        <i class="fa-solid fa-store txt-ico"></i> Sucursal <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-location-dot ico-izq"></i>
                        <select class="select-modern" id="c_sucursal" name="id_sucursal" required>
                            <option value="">— Selecciona una sucursal —</option>
                            <?php foreach (($sucursales ?? []) as $suc): ?>
                                <option value="<?php echo (int) $suc['id_sucursal']; ?>">
                                    <?php echo htmlspecialchars($suc['nombre_sucursal']); ?>
                                    <?php echo $suc['ciudad'] ? ' · ' . htmlspecialchars($suc['ciudad']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <span class="ayuda">Una sucursal puede tener varias cajas</span>
                </div>

                <div class="campo" data-campo="codigo">
                    <label for="c_codigo">
                        <i class="fa-solid fa-barcode txt-ico"></i> Código <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-hashtag ico-izq"></i>
                        <input class="input-modern" type="text" id="c_codigo" name="codigo" maxlength="20" placeholder="CAJA-01" autocapitalize="characters" required />
                    </div>
                    <span class="ayuda">2-20 caracteres · único dentro de la sucursal</span>
                </div>

                <div class="campo" data-campo="nombre">
                    <label for="c_nombre">
                        <i class="fa-solid fa-cash-register txt-ico"></i> Nombre de la caja <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-tag ico-izq"></i>
                        <input class="input-modern" type="text" id="c_nombre" name="nombre" maxlength="80" placeholder="Caja 1 · Principal" required />
                    </div>
                    <span class="ayuda">Ej. Caja 1, Caja Delivery, Caja Barra</span>
                </div>

                <div class="campo" data-campo="monto_inicial_sugerido">
                    <label for="c_monto">
                        <i class="fa-solid fa-money-bill-wave txt-ico"></i> Monto inicial sugerido (Bs)
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="c_monto" name="monto_inicial_sugerido" min="0" step="0.01" value="0" />
                    </div>
                    <span class="ayuda">Se propondrá al abrir el arqueo de caja</span>
                </div>

                <div class="campo" data-campo="estado">
                    <label for="c_estado">
                        <i class="fa-solid fa-toggle-on txt-ico"></i> Estado
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-power-off ico-izq"></i>
                        <select class="select-modern" id="c_estado" name="estado">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                    <span class="ayuda">Sólo las activas pueden operar</span>
                </div>

                <div class="campo ancho-2" data-campo="descripcion">
                    <label for="c_descripcion">
                        <i class="fa-solid fa-align-left txt-ico"></i> Descripción
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="c_descripcion" name="descripcion" maxlength="255" rows="2" placeholder="Mostrador principal y pedidos en salón"></textarea>
                    </div>
                    <span class="ayuda">Opcional · hasta 255 caracteres</span>
                </div>

                <div class="caja-nota ancho-2">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Primero se crea la caja. Después, desde la tabla, usa <strong>Asignar</strong> para entregarla a un cajero de la misma sucursal.</span>
                </div>

                <input type="hidden" id="c_id" value="" />

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mCajaNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mCajaCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mCajaGuardar">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar</span>
                </button>
            </div>
        </form>

    </div>
</div>
