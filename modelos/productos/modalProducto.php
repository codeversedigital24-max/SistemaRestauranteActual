<!-- =====================================================
     MODAL: NUEVO / MODIFICAR PRODUCTO
     (se incluye desde sistema/productos.php)
     ===================================================== -->
<style>
    
    .modal-cuerpo .prod-sep {
        margin: 8px 0 2px;
        padding-top: 8px;
        border-top: 1px dashed var(--mod-linea);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        color: var(--mod-primaria);
    }

    .modal-cuerpo .check-mod {
        display: flex;
        align-items: center;
        gap: 8px;
        height: 36px;
        font-size: 13px;
        font-weight: 600;
        color: var(--mod-texto);
        cursor: pointer;
        user-select: none;
    }

    .modal-cuerpo .check-mod input[type="checkbox"] {
        width: 17px !important;
        height: 17px !important;
        min-width: 17px;
        padding: 0 !important;
        margin: 0;
        accent-color: var(--mod-primaria);
        cursor: pointer;
        flex: 0 0 auto;
    }

    .prod-preview {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        padding: 10px 14px;
        background: var(--mod-primaria-suave);
        border: 1px solid #f6c9cb;
        border-radius: var(--mod-radio-sm);
        margin-top: 4px;
    }

    .prod-preview .pp-et {
        font-size: 12px;
        font-weight: 700;
        color: var(--mod-texto);
    }

    .prod-preview .pp-valor {
        font-size: 18px;
        font-weight: 800;
        color: var(--mod-primaria);
        font-variant-numeric: tabular-nums;
    }

    .prod-preview .pp-ahorro {
        font-size: 11.5px;
        font-weight: 700;
        color: var(--mod-verde);
    }

    .galeria-wrap {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
        gap: 10px;
        margin-top: 6px;
    }

    .galeria-grid {
        display: contents;
    }

    .galeria-item {
        position: relative;
        aspect-ratio: 1 / 1;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid var(--mod-linea);
        background: #f4f4f6;
        transition: border-color .15s;
    }

    .galeria-item.principal {
        border-color: var(--mod-primaria);
        box-shadow: 0 0 0 2px rgba(217, 35, 45, 0.15);
    }

    .galeria-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .galeria-item .g-estrella,
    .galeria-item .g-borrar {
        position: absolute;
        top: 4px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: none;
        background: rgba(0, 0, 0, .6);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        cursor: pointer;
        padding: 0;
    }

    .galeria-item .g-estrella {
        left: 4px;
    }

    .galeria-item .g-borrar {
        right: 4px;
        background: rgba(217, 35, 45, .85);
    }

    .galeria-item .g-estrella.activa {
        background: #f0b400;
        color: #1c1416;
    }

    .galeria-item .g-estrella:hover {
        background: #f0b400;
    }

    .galeria-item.g-nueva::after {
        content: "Nueva";
        position: absolute;
        bottom: 4px;
        left: 4px;
        font-size: 9px;
        background: var(--mod-verde, #14803d);
        color: #fff;
        padding: 1px 6px;
        border-radius: 4px;
        font-weight: 700;
    }

    .galeria-add {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        aspect-ratio: 1 / 1;
        border: 2px dashed var(--mod-linea);
        border-radius: 10px;
        color: var(--mod-texto-tenue);
        font-size: 11.5px;
        gap: 4px;
        cursor: pointer;
        transition: border-color .15s, color .15s;
    }

    .galeria-add:hover {
        border-color: var(--mod-primaria);
        color: var(--mod-primaria);
    }

    .galeria-add i {
        font-size: 20px;
    }

    .galeria-add span {
        font-weight: 600;
    }
</style>

<div class="modal-overlay" id="modalProducto" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mProdTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mProdTitulo">
                <span class="ico-titulo" id="mProdIcono"><i class="fa-solid fa-burger"></i></span>
                <span id="mProdTexto" style="color:#fff;">Nuevo producto</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mProdCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formProducto" novalidate autocomplete="off" data-accion="crear" data-id="">

            <div class="modal-cuerpo">

                <div class="prod-sep ancho-2">Datos generales</div>

                <div class="campo" data-campo="codigo">
                    <label for="p_codigo">
                        <i class="fa-solid fa-barcode txt-ico"></i> Código
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-hashtag ico-izq"></i>
                        <input class="input-modern" type="text" id="p_codigo" name="codigo" maxlength="20" placeholder="HAMB-001" autocapitalize="characters" />
                    </div>
                    <span class="ayuda">Opcional · si lo dejas vacío se genera solo</span>
                </div>

                <div class="campo" data-campo="nombre">
                    <label for="p_nombre">
                        <i class="fa-solid fa-burger txt-ico"></i> Nombre <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-utensils ico-izq"></i>
                        <input class="input-modern" type="text" id="p_nombre" name="nombre" maxlength="120" placeholder="Hamburguesa Doble Queso" required />
                    </div>
                    <span class="ayuda">Mínimo 3 caracteres</span>
                </div>

                <div class="campo" data-campo="id_categoria">
                    <label for="p_categoria">
                        <i class="fa-solid fa-layer-group txt-ico"></i> Categoría <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <select class="select-modern" id="p_categoria" name="id_categoria" required>
                            <option value="">— Selecciona una categoría —</option>
                            <?php foreach (($categorias ?? []) as $cat): ?>
                                <option value="<?php echo (int) $cat['id_categoria']; ?>">
                                    <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <span class="ayuda">Define dónde aparece en la carta</span>
                </div>

                <div class="campo ancho-2" data-campo="galeria">
                    <label>
                        <i class="fa-solid fa-images txt-ico"></i> Galería de imágenes
                        <span class="txt-tenue" style="font-weight:500;font-size:11.5px;margin-left:4px">
                            (hasta 10 · JPG/PNG/WEBP · máx. 5 MB c/u)
                        </span>
                    </label>

                    <div class="galeria-wrap">
                        <div class="galeria-grid" id="galeriaGrid"></div>

                        <label class="galeria-add" for="p_archivos" id="galeriaAdd">
                            <i class="fa-solid fa-plus"></i>
                            <span>Añadir imágenes</span>
                            <input type="file" id="p_archivos" accept="image/jpeg,image/png,image/webp" multiple hidden />
                        </label>
                    </div>

                    <span class="ayuda" id="galeriaAyuda">La primera imagen (o la marcada con la estrella) será la principal</span>

                    <!-- Campo legado: lo mantenemos oculto para no romper el modelo -->
                    <input type="hidden" id="p_imagen" name="imagen" value="" />
                </div>

                <div class="campo ancho-2" data-campo="descripcion">
                    <label for="p_descripcion">
                        <i class="fa-solid fa-align-left txt-ico"></i> Descripción
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="p_descripcion" name="descripcion" maxlength="500" rows="2" placeholder="Carne 150g, lechuga, tomate, cebolla y salsa de la casa."></textarea>
                    </div>
                    <span class="ayuda">Opcional · hasta 500 caracteres</span>
                </div>

                <div class="prod-sep ancho-2">Precio y oferta</div>

                <div class="campo" data-campo="precio">
                    <label for="p_precio">
                        <i class="fa-solid fa-money-bill-wave txt-ico"></i> Precio normal (Bs) <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="p_precio" name="precio" min="0.01" step="0.01" placeholder="25.00" required />
                    </div>
                    <span class="ayuda">Precio de venta sin descuento</span>
                </div>

                <div class="campo" data-campo="descuento">
                    <label for="p_descuento">
                        <i class="fa-solid fa-percent txt-ico"></i> Descuento (%)
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-tag ico-izq"></i>
                        <input class="input-modern" type="number" id="p_descuento" name="descuento_porcentaje" min="0" max="100" step="0.01" value="0" />
                    </div>
                    <span class="ayuda">0 a 100 · se aplica si la oferta está activa</span>
                </div>

                <div class="campo" data-campo="precio_oferta">
                    <label for="p_precio_oferta">
                        <i class="fa-solid fa-fire txt-ico"></i> Precio de oferta (Bs)
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="p_precio_oferta" name="precio_oferta" min="0" step="0.01" placeholder="Opcional" />
                    </div>
                    <span class="ayuda">Debe ser menor al precio normal</span>
                </div>

                <div class="campo" data-campo="oferta_activa">
                    <label>
                        <i class="fa-solid fa-bullhorn txt-ico"></i> Oferta
                    </label>
                    <label class="check-mod" for="p_oferta_activa">
                        <input type="checkbox" id="p_oferta_activa" name="oferta_activa" value="1" />
                        <span>Activar oferta</span>
                    </label>
                    <span class="ayuda">Se muestra con precio tachado</span>
                </div>

                <div class="campo" data-campo="oferta_desde">
                    <label for="p_oferta_desde">
                        <i class="fa-solid fa-calendar-plus txt-ico"></i> Oferta desde
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-calendar ico-izq"></i>
                        <input class="input-modern" type="datetime-local" id="p_oferta_desde" name="oferta_desde" />
                    </div>
                    <span class="ayuda">Opcional · vacío = desde ahora</span>
                </div>

                <div class="campo" data-campo="oferta_hasta">
                    <label for="p_oferta_hasta">
                        <i class="fa-solid fa-calendar-xmark txt-ico"></i> Oferta hasta
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-calendar ico-izq"></i>
                        <input class="input-modern" type="datetime-local" id="p_oferta_hasta" name="oferta_hasta" />
                    </div>
                    <span class="ayuda">Opcional · vacío = sin fecha de fin</span>
                </div>

                <div class="prod-preview ancho-2" id="prodPreview" aria-live="polite">
                    <span class="pp-et"><i class="fa-solid fa-cash-register"></i> Precio final al cliente</span>
                    <span class="pp-valor" id="prodPreviewFinal">Bs 0,00</span>
                    <span class="pp-ahorro" id="prodPreviewAhorro"></span>
                </div>

                <div class="prod-sep ancho-2">Operación y visibilidad</div>

                <div class="campo" data-campo="costo">
                    <label for="p_costo">
                        <i class="fa-solid fa-coins txt-ico"></i> Costo (Bs)
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="p_costo" name="costo" min="0" step="0.01" value="0" />
                    </div>
                    <span class="ayuda">Opcional · para calcular margen</span>
                </div>

                <div class="campo" data-campo="impuesto">
                    <label for="p_impuesto">
                        <i class="fa-solid fa-receipt txt-ico"></i> Impuesto (%)
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-percent ico-izq"></i>
                        <input class="input-modern" type="number" id="p_impuesto" name="impuesto_porcentaje" min="0" max="100" step="0.01" value="13" />
                    </div>
                    <span class="ayuda">Ej. IVA 13 %</span>
                </div>

                <div class="campo" data-campo="tiempo_preparacion">
                    <label for="p_tiempo_preparacion">
                        <i class="fa-solid fa-clock txt-ico"></i> Tiempo de preparación
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-hourglass-half ico-izq"></i>
                        <input class="input-modern" type="number" id="p_tiempo_preparacion" name="tiempo_preparacion" min="0" step="1" value="0" />
                    </div>
                    <span class="ayuda">Minutos estimados</span>
                </div>

                <div class="campo" data-campo="control_stock">
                    <label>
                        <i class="fa-solid fa-boxes-stacked txt-ico"></i> Inventario
                    </label>
                    <label class="check-mod" for="p_control_stock">
                        <input type="checkbox" id="p_control_stock" name="control_stock" value="1" />
                        <span>Controlar stock</span>
                    </label>
                    <span class="ayuda">Desactivado = stock ilimitado</span>
                </div>

                <div class="campo" data-campo="stock">
                    <label for="p_stock">
                        <i class="fa-solid fa-cubes txt-ico"></i> Stock disponible
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-cube ico-izq"></i>
                        <input class="input-modern" type="number" id="p_stock" name="stock" min="0" step="1" value="0" />
                    </div>
                    <span class="ayuda">Unidades listas para vender</span>
                </div>

                <div class="campo" data-campo="stock_minimo">
                    <label for="p_stock_minimo">
                        <i class="fa-solid fa-triangle-exclamation txt-ico"></i> Stock mínimo
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bell ico-izq"></i>
                        <input class="input-modern" type="number" id="p_stock_minimo" name="stock_minimo" min="0" step="1" value="0" />
                    </div>
                    <span class="ayuda">Alerta cuando baja de este valor</span>
                </div>

                <div class="campo" data-campo="disponible">
                    <label for="p_disponible">
                        <i class="fa-solid fa-circle-check txt-ico"></i> Disponible para venta
                    </label>
                    <div class="wrap-input">
                        <select class="select-modern" id="p_disponible" name="disponible">
                            <option value="1">Sí, disponible</option>
                            <option value="0">No disponible</option>
                        </select>
                    </div>
                    <span class="ayuda">Se puede pausar sin dar de baja</span>
                </div>

                <div class="campo" data-campo="destacado">
                    <label>
                        <i class="fa-solid fa-star txt-ico"></i> Destacado
                    </label>
                    <label class="check-mod" for="p_destacado">
                        <input type="checkbox" id="p_destacado" name="destacado" value="1" />
                        <span>Mostrar en destacados</span>
                    </label>
                    <span class="ayuda">Para la carta o vitrina principal</span>
                </div>

                <div class="campo" data-campo="estado">
                    <label for="p_estado">
                        <i class="fa-solid fa-toggle-on txt-ico"></i> Estado
                    </label>
                    <div class="wrap-input">
                        <select class="select-modern" id="p_estado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <span class="ayuda">Sólo los activos aparecen en el sistema</span>
                </div>

                <input type="hidden" id="p_id" value="" />

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mProdNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mProdCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mProdGuardar">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar</span>
                </button>
            </div>
        </form>

    </div>
</div>