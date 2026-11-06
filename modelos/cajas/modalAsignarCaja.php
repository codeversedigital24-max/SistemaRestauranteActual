<!-- =====================================================
     MODAL: ASIGNAR CAJA A UN PERSONAL
     (se incluye desde sistema/caja.php)
     ===================================================== -->
<style>
    .caja-resumen {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 14px;
        margin-bottom: 4px;
        border-radius: var(--mod-radio-sm);
        background: var(--mod-primaria-suave);
        border: 1px solid #f6c9cb;
    }

    .caja-resumen .cr-icono {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        border-radius: 10px;
        background: var(--mod-primaria);
        color: #fff;
        font-size: 17px;
    }

    .caja-resumen .cr-texto {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
        min-width: 0;
    }

    .caja-resumen .cr-titulo {
        font-size: 14px;
        font-weight: 800;
        color: var(--mod-tinta);
    }

    .caja-resumen .cr-sub {
        font-size: 12px;
        color: var(--mod-texto);
    }

    .caja-resumen .cr-actual {
        margin-left: auto;
        text-align: right;
        font-size: 11.5px;
        color: var(--mod-texto);
        white-space: nowrap;
    }

    .caja-resumen .cr-actual strong {
        display: block;
        color: var(--mod-verde);
        font-size: 12.5px;
    }

    .caja-resumen .cr-actual.sin strong {
        color: var(--mod-ambar);
    }
</style>

<div class="modal-overlay" id="modalAsignarCaja" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mAsigTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mAsigTitulo">
                <span class="ico-titulo"><i class="fa-solid fa-user-tie"></i></span>
                <span style="color:#fff;">Asignar caja</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mAsigCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formAsignarCaja" novalidate autocomplete="off">

            <div class="modal-cuerpo">

                <div class="caja-resumen ancho-2">
                    <span class="cr-icono"><i class="fa-solid fa-cash-register"></i></span>
                    <span class="cr-texto">
                        <span class="cr-titulo" id="asResumenTitulo">Caja</span>
                        <span class="cr-sub" id="asResumenSucursal">Sucursal</span>
                    </span>
                    <span class="cr-actual" id="asResumenActual">
                        <span>Responsable actual</span>
                        <strong id="asResumenResponsable">Sin asignar</strong>
                    </span>
                </div>

                <div class="campo ancho-2" data-campo="id_usuario">
                    <label for="as_usuario">
                        <i class="fa-solid fa-user-check txt-ico"></i> Entregar a <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-user ico-izq"></i>
                        <select class="select-modern" id="as_usuario" name="id_usuario" required disabled>
                            <option value="">Cargando personal…</option>
                        </select>
                    </div>
                    <span class="ayuda">Sólo personal activo de la misma sucursal</span>
                </div>

                <div class="campo ancho-2" data-campo="observacion">
                    <label for="as_observacion">
                        <i class="fa-solid fa-align-left txt-ico"></i> Observación
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="as_observacion" name="observacion" maxlength="255" rows="2" placeholder="Turno mañana, reemplazo de vacaciones…"></textarea>
                    </div>
                    <span class="ayuda">Opcional · queda guardado en el historial</span>
                </div>

                <div class="caja-nota ancho-2">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Una persona sólo puede tener una caja activa. Al reasignar, la asignación anterior se cierra y queda registrada en el historial.</span>
                </div>

                <input type="hidden" id="as_id" value="" />
                <input type="hidden" id="as_sucursal" value="" />

            </div>

            <div class="modal-pie">
                <button type="button" class="btn-mod btn-mod-peligro" id="asLiberar" style="margin-right:auto; display:none;">
                    <i class="fa-solid fa-user-slash"></i> <span>Liberar caja</span>
                </button>
                <button type="button" class="btn-mod btn-mod-claro" id="mAsigCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="asGuardar">
                    <i class="fa-solid fa-user-check"></i> <span>Asignar</span>
                </button>
            </div>
        </form>

    </div>
</div>
