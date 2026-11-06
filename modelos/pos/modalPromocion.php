<div class="modal-overlay" id="modalPromocion" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="prModalTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="prModalTitulo">
                <span class="ico-titulo" id="prModalIcono"><i class="fa-solid fa-tags"></i></span>
                <span id="prModalTexto" style="color:#fff;">Nueva promoción</span>
            </h2>
            <button type="button" class="modal-cerrar" id="prCerrarX" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formPromocion" novalidate autocomplete="off" data-accion="crear" style="display:flex;flex-direction:column;min-height:0;flex:1 1 auto;overflow:hidden;">
            <div class="modal-cuerpo">

                <div class="campo ancho-2" data-campo="nombre">
                    <label for="pr_nombre"><i class="fa-solid fa-tag txt-ico"></i> Nombre <span class="req">*</span></label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-tag ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_nombre" maxlength="140" placeholder="Ej.: 2x1 en pizzas personales" required />
                    </div>
                    <span class="ayuda">Nombre visible en el POS</span>
                </div>

                <div class="campo ancho-2" data-campo="descripcion">
                    <label for="pr_descripcion"><i class="fa-solid fa-align-left txt-ico"></i> Descripción</label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-align-left ico-izq"></i>
                        <input class="input-modern" type="text" id="pr_descripcion" maxlength="255" placeholder="Detalle opcional de la promoción" />
                    </div>
                    <span class="ayuda">Opcional · máximo 255 caracteres</span>
                </div>

                <div class="campo" data-campo="alcance">
                    <label for="pr_alcance"><i class="fa-solid fa-bullseye txt-ico"></i> Alcance <span class="req">*</span></label>
                    <div class="wrap-input">
                        <select class="select-modern" id="pr_alcance" required>
                            <option value="producto">Un producto</option>
                            <option value="categoria">Una categoría completa</option>
                        </select>
                    </div>
                    <span class="ayuda">La promoción aplica a un producto o a todos los de una categoría</span>
                </div>

                <div class="campo" data-campo="producto" id="prProductoWrap">
                    <label for="pr_producto"><i class="fa-solid fa-burger txt-ico"></i> Producto <span class="req">*</span></label>
                    <div class="wrap-input">
                        <select class="select-modern" id="pr_producto"></select>
                    </div>
                    <span class="ayuda">Producto al que se aplicará el descuento</span>
                </div>

                <div class="campo" data-campo="categoria" id="prCategoriaWrap" style="display:none;">
                    <label for="pr_categoria"><i class="fa-solid fa-layer-group txt-ico"></i> Categoría <span class="req">*</span></label>
                    <div class="wrap-input">
                        <select class="select-modern" id="pr_categoria"></select>
                    </div>
                    <span class="ayuda">Se aplicará a todos los productos de la categoría</span>
                </div>

                <div class="campo" data-campo="tipo_descuento">
                    <label for="pr_tipo"><i class="fa-solid fa-percent txt-ico"></i> Tipo de descuento <span class="req">*</span></label>
                    <div class="wrap-input">
                        <select class="select-modern" id="pr_tipo" required>
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="monto">Monto fijo (Bs)</option>
                        </select>
                    </div>
                    <span class="ayuda">El descuento nunca deja el precio por debajo de cero</span>
                </div>

                <div class="campo" data-campo="valor">
                    <label for="pr_valor"><i class="fa-solid fa-calculator txt-ico"></i> Valor <span class="req">*</span></label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-money-bill-wave ico-izq"></i>
                        <input class="input-modern" type="number" id="pr_valor" min="0.01" step="0.01" placeholder="15" required />
                    </div>
                    <span class="ayuda" id="prValorAyuda">Porcentaje de descuento (0 a 100)</span>
                </div>

                <div class="campo" data-campo="prioridad">
                    <label for="pr_prioridad"><i class="fa-solid fa-arrow-up-wide-short txt-ico"></i> Prioridad</label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-sort ico-izq"></i>
                        <input class="input-modern" type="number" id="pr_prioridad" min="0" step="1" value="0" />
                    </div>
                    <span class="ayuda">Si dos promociones aplican, gana la de mayor prioridad</span>
                </div>

                <div class="campo" data-campo="desde">
                    <label for="pr_desde"><i class="fa-solid fa-calendar-plus txt-ico"></i> Vigente desde</label>
                    <div class="wrap-input">
                        <input class="input-modern" type="datetime-local" id="pr_desde" />
                    </div>
                    <span class="ayuda">Vacío = disponible desde ahora</span>
                </div>

                <div class="campo" data-campo="hasta">
                    <label for="pr_hasta"><i class="fa-solid fa-calendar-xmark txt-ico"></i> Vigente hasta</label>
                    <div class="wrap-input">
                        <input class="input-modern" type="datetime-local" id="pr_hasta" />
                    </div>
                    <span class="ayuda">Vacío = sin fecha de finalización</span>
                </div>

                <div class="campo" data-campo="activo">
                    <label for="pr_activo"><i class="fa-solid fa-toggle-on txt-ico"></i> Estado <span class="req">*</span></label>
                    <div class="wrap-input">
                        <select class="select-modern" id="pr_activo" required>
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                    <span class="ayuda">Las promociones inactivas no se aplican en el POS</span>
                </div>

                <input type="hidden" id="pr_id" value="" />
            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="prPieNota" style="margin-right:auto;font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="prCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="prGuardar">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar</span>
                </button>
            </div>
        </form>

    </div>
</div>
