<!-- =====================================================
     MODAL: NUEVA / MODIFICAR SUCURSAL
     (se incluye desde sistema/sucursales.php)
     ===================================================== -->
<div class="modal-overlay" id="modalSucursal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mSucTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mSucTitulo">
                <span class="ico-titulo" id="mSucIcono"><i class="fa-solid fa-store"></i></span>
                <span id="mSucTexto" style="color:#fff;">Nueva sucursal</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mSucCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formSucursal" novalidate autocomplete="off" data-accion="crear" data-id="">

            <div class="modal-cuerpo">

                <div class="campo" data-campo="codigo">
                    <label for="s_codigo">
                        <i class="fa-solid fa-barcode txt-ico"></i> Código <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-hashtag ico-izq"></i>
                        <input class="input-modern" type="text" id="s_codigo" name="codigo" maxlength="20" placeholder="SUC-001" autocapitalize="characters" required />
                    </div>
                    <span class="ayuda">3-20 caracteres · único (letras, números, - o _)</span>
                </div>

                <div class="campo" data-campo="nombre">
                    <label for="s_nombre">
                        <i class="fa-solid fa-store txt-ico"></i> Nombre <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-shop ico-izq"></i>
                        <input class="input-modern" type="text" id="s_nombre" name="nombre" maxlength="100" placeholder="Sucursal Central" required />
                    </div>
                    <span class="ayuda">Mínimo 3 caracteres · único</span>
                </div>

                <div class="campo" data-campo="ciudad">
                    <label for="s_ciudad">
                        <i class="fa-solid fa-city txt-ico"></i> Ciudad
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-location-dot ico-izq"></i>
                        <input class="input-modern" type="text" id="s_ciudad" name="ciudad" maxlength="50" placeholder="Santa Cruz" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="telefono">
                    <label for="s_telefono">
                        <i class="fa-solid fa-phone txt-ico"></i> Teléfono
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-mobile-screen-button ico-izq"></i>
                        <input class="input-modern" type="text" id="s_telefono" name="telefono" maxlength="20" inputmode="tel" placeholder="591-2-1234567" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="email">
                    <label for="s_email">
                        <i class="fa-solid fa-envelope txt-ico"></i> Correo
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-at ico-izq"></i>
                        <input class="input-modern" type="email" id="s_email" name="email" maxlength="100" placeholder="sucursal@restaurante.com" autocapitalize="none" />
                    </div>
                    <span class="ayuda">Opcional · formato válido</span>
                </div>

                <div class="campo" data-campo="estado">
                    <label for="s_estado">
                        <i class="fa-solid fa-toggle-on txt-ico"></i> Estado
                    </label>
                    <div class="wrap-input">
                        <select class="select-modern" id="s_estado" name="estado">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                    <span class="ayuda">Sólo aparecen en el sistema las activas</span>
                </div>

                <div class="campo ancho-2" data-campo="direccion">
                    <label for="s_direccion">
                        <i class="fa-solid fa-map-pin txt-ico"></i> Dirección
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-location-crosshairs ico-izq"></i>
                        <textarea class="input-modern" id="s_direccion" name="direccion" maxlength="255" rows="2" placeholder="Av. Principal #123, Zona Central"></textarea>
                    </div>
                    <span class="ayuda">Opcional · hasta 255 caracteres</span>
                </div>

                <input type="hidden" id="s_id" value="" />

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mSucNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mSucCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mSucGuardar">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar</span>
                </button>
            </div>
        </form>

    </div>
</div>