<!-- =====================================================
     MODAL: APERTURAR CAJA
     (se incluye desde sistema/arqueocaja.php)
     ===================================================== -->
<style>
    .arq-caja-info {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px 14px;
        margin-bottom: 4px;
        border-radius: var(--mod-radio-sm);
        background: var(--mod-primaria-suave);
        border: 1px solid #f6c9cb;
    }

    .arq-caja-info .aci-icono {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        border-radius: 10px;
        background: var(--mod-primaria);
        color: #fff;
        font-size: 18px;
    }

    .arq-caja-info .aci-texto {
        display: flex;
        flex-direction: column;
        line-height: 1.3;
        min-width: 0;
    }

    .arq-caja-info .aci-titulo {
        font-size: 14px;
        font-weight: 800;
        color: var(--mod-tinta);
    }

    .arq-caja-info .aci-sub {
        font-size: 12px;
        color: var(--mod-texto);
    }

    .arq-caja-info .aci-sugerido {
        margin-left: auto;
        text-align: right;
        font-size: 11px;
        color: var(--mod-texto);
        white-space: nowrap;
    }

    .arq-caja-info .aci-sugerido strong {
        display: block;
        font-size: 15px;
        color: var(--mod-primaria);
        font-variant-numeric: tabular-nums;
    }

    .arq-nota {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 9px 12px;
        border-radius: var(--mod-radio-sm);
        background: var(--mod-ambar-fondo, #fff4d6);
        border: 1px solid #f1dfa6;
        font-size: 12px;
        line-height: 1.45;
        color: var(--mod-ambar, #9a6b00);
    }

    .arq-nota i {
        margin-top: 2px;
    }
</style>

<div class="modal-overlay" id="modalApertura" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mApeTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mApeTitulo">
                <span class="ico-titulo"><i class="fa-solid fa-lock-open"></i></span>
                <span style="color:#fff;">Aperturar caja</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mApeCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formApertura" novalidate autocomplete="off">

            <div class="modal-cuerpo">

                <div class="arq-caja-info ancho-2">
                    <span class="aci-icono"><i class="fa-solid fa-cash-register"></i></span>
                    <span class="aci-texto">
                        <span class="aci-titulo" id="apeCajaTitulo">Caja</span>
                        <span class="aci-sub" id="apeCajaSucursal">Sucursal</span>
                    </span>
                    <span class="aci-sugerido">
                        <span>Sugerido</span>
                        <strong id="apeSugerido">Bs 0,00</strong>
                    </span>
                </div>

                <div class="campo" data-campo="monto">
                    <label for="ape_monto">
                        <i class="fa-solid fa-money-bill-wave txt-ico"></i> Monto inicial en caja <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="ape_monto" name="monto" min="0" step="0.01" value="0" required />
                    </div>
                    <span class="ayuda">Efectivo con el que empiezas el turno</span>
                </div>

                <div class="campo" data-campo="observacion">
                    <label for="ape_observacion">
                        <i class="fa-solid fa-align-left txt-ico"></i> Observación
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="ape_observacion" name="observacion" maxlength="255" rows="2" placeholder="Turno mañana, cambio de cajero…"></textarea>
                    </div>
                    <span class="ayuda">Opcional · queda en el arqueo</span>
                </div>

                <div class="arq-nota ancho-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Al aperturar, el arqueo queda <strong>abierto</strong> a tu nombre. Sólo tú (o el responsable actual de la caja) podrás cerrarlo.</span>
                </div>

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mApeNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mApeCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mApeGuardar">
                    <i class="fa-solid fa-lock-open"></i> <span>Aperturar caja</span>
                </button>
            </div>
        </form>

    </div>
</div>
