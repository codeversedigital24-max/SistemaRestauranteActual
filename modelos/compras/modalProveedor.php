<!-- =====================================================
     MODAL: NUEVO / MODIFICAR PROVEEDOR
     (se incluye desde sistema/compras.php y nuevacompra.php)
     ===================================================== -->
<div class="modal-overlay" id="modalProveedor" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mProvTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mProvTitulo">
                <span class="ico-titulo" id="mProvIcono"><i class="fa-solid fa-truck-field"></i></span>
                <span id="mProvTexto" style="color:#fff;">Nuevo proveedor</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mProvCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formProveedor" novalidate autocomplete="off" data-accion="crear" data-id="">

            <div class="modal-cuerpo">

                <div class="campo" data-campo="nombre">
                    <label for="pr_nombre">
                        <i class="fa-solid fa-truck txt-ico"></i> Nombre comercial <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-tag ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_nombre" name="nombre" maxlength="150" placeholder="Distribuidora Central" required />
                    </div>
                    <span class="ayuda">Mínimo 3 caracteres</span>
                </div>

                <div class="campo" data-campo="razon_social">
                    <label for="pr_razon">
                        <i class="fa-solid fa-building txt-ico"></i> Razón social
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-file-signature ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_razon" name="razon_social" maxlength="150" placeholder="Distribuidora Central S.R.L." />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="nit">
                    <label for="pr_nit">
                        <i class="fa-solid fa-id-card txt-ico"></i> NIT / Documento
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-hashtag ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_nit" name="nit" maxlength="30" placeholder="1020304050" />
                    </div>
                    <span class="ayuda">Opcional · no se puede repetir</span>
                </div>

                <div class="campo" data-campo="contacto">
                    <label for="pr_contacto">
                        <i class="fa-solid fa-user-tie txt-ico"></i> Persona de contacto
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-user ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_contacto" name="contacto" maxlength="100" placeholder="María López" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="telefono">
                    <label for="pr_telefono">
                        <i class="fa-solid fa-phone txt-ico"></i> Teléfono
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-phone ico-izq"></i>
                        <input class="input-modern" type="tel" id="pr_telefono" name="telefono" maxlength="30" placeholder="+591 71234567" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="email">
                    <label for="pr_email">
                        <i class="fa-solid fa-envelope txt-ico"></i> Correo
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-at ico-izq"></i>
                        <input class="input-modern" type="email" id="pr_email" name="email" maxlength="120" placeholder="ventas@proveedor.bo" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="ciudad">
                    <label for="pr_ciudad">
                        <i class="fa-solid fa-city txt-ico"></i> Ciudad
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-location-dot ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_ciudad" name="ciudad" maxlength="80" placeholder="Santa Cruz" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo" data-campo="estado">
                    <label for="pr_estado">
                        <i class="fa-solid fa-toggle-on txt-ico"></i> Estado
                    </label>
                    <div class="wrap-input">
                        <select class="select-modern" id="pr_estado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <span class="ayuda">Sólo los activos se pueden comprar</span>
                </div>

                <div class="campo ancho-2" data-campo="direccion">
                    <label for="pr_direccion">
                        <i class="fa-solid fa-map-location-dot txt-ico"></i> Dirección
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-location-dot ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_direccion" name="direccion" maxlength="255" placeholder="Av. Cañoto #123" />
                    </div>
                    <span class="ayuda">Opcional</span>
                </div>

                <div class="campo ancho-2" data-campo="notas">
                    <label for="pr_notas">
                        <i class="fa-solid fa-note-sticky txt-ico"></i> Notas
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="pr_notas" name="notas" maxlength="255" rows="2" placeholder="Días de entrega, condiciones, etc."></textarea>
                    </div>
                    <span class="ayuda">Opcional · hasta 255 caracteres</span>
                </div>

                <input type="hidden" id="pr_id" value="" />

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mProvNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mProvCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mProvGuardar">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar</span>
                </button>
            </div>
        </form>

    </div>
</div>
