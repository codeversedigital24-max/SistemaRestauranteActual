<!-- =====================================================
     MODAL: CERRAR CAJA (ARQUEO)
     (se incluye desde sistema/arqueocaja.php)
     ===================================================== -->
<style>
    .arq-cierre-totales {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 8px;
        margin-bottom: 4px;
    }

    .arq-cierre-totales .act-item {
        padding: 8px 11px;
        border-radius: var(--mod-radio-sm);
        background: var(--mod-claro);
        border: 1px solid var(--mod-linea);
        line-height: 1.3;
    }

    .arq-cierre-totales .act-et {
        display: block;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        color: var(--mod-texto-tenue);
    }

    .arq-cierre-totales .act-val {
        font-size: 14px;
        font-weight: 800;
        color: var(--mod-tinta);
        font-variant-numeric: tabular-nums;
    }

    .arq-dif {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: var(--mod-radio-sm);
        border: 1px solid var(--mod-linea-fuerte);
        background: var(--mod-claro);
        font-size: 12.5px;
        color: var(--mod-texto);
    }

    .arq-dif .ad-valor {
        margin-left: auto;
        font-size: 18px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        color: var(--mod-tinta);
    }

    .arq-dif.cuadra {
        background: var(--mod-verde-fondo);
        border-color: #bfe6cd;
    }

    .arq-dif.cuadra .ad-valor {
        color: var(--mod-verde);
    }

    .arq-dif.sobra {
        background: var(--mod-ambar-fondo);
        border-color: #f1dfa6;
    }

    .arq-dif.sobra .ad-valor {
        color: var(--mod-ambar);
    }

    .arq-dif.falta {
        background: var(--mod-rojo-fondo);
        border-color: #f6c9cb;
    }

    .arq-dif.falta .ad-valor {
        color: var(--mod-primaria);
    }
</style>

<div class="modal-overlay" id="modalCierre" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mCieTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mCieTitulo">
                <span class="ico-titulo"><i class="fa-solid fa-lock"></i></span>
                <span style="color:#fff;">Cerrar caja · Arqueo</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mCieCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formCierre" novalidate autocomplete="off">

            <div class="modal-cuerpo">

                <div class="arq-caja-info ancho-2">
                    <span class="aci-icono" style="background:var(--mod-verde);"><i class="fa-solid fa-cash-register"></i></span>
                    <span class="aci-texto">
                        <span class="aci-titulo" id="cieCajaTitulo">Caja</span>
                        <span class="aci-sub" id="cieCajaSucursal">Sucursal</span>
                    </span>
                    <span class="aci-sugerido">
                        <span>Abierta desde</span>
                        <strong id="cieFecha" style="font-size:12px;">—</strong>
                    </span>
                </div>

                <div class="arq-cierre-totales ancho-2">
                    <div class="act-item"><span class="act-et">Total ventas</span><span class="act-val" id="cieVentasTotal">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">Efectivo</span><span class="act-val" id="cieVentas">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">Transferencia</span><span class="act-val" id="cieTransferencia">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">QR</span><span class="act-val" id="cieQr">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">Tarjeta</span><span class="act-val" id="cieTarjeta">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">Otros</span><span class="act-val" id="cieOtros">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">Egresos / gastos</span><span class="act-val" id="cieEgresos">Bs 0,00</span></div>
                    <div class="act-item"><span class="act-et">Monto inicial</span><span class="act-val" id="cieMontoApertura">Bs 0,00</span></div>
                    <div class="act-item">
                        <span class="act-et">Esperado en caja</span>
                        <span class="act-val" id="cieEsperado">Bs 0,00</span>
                    </div>
                </div>

                <div class="campo" data-campo="monto">
                    <label for="cie_monto">
                        <i class="fa-solid fa-hand-holding-dollar txt-ico"></i> Monto contado <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="cie_monto" name="monto" min="0" step="0.01" placeholder="0.00" required />
                    </div>
                    <span class="ayuda">Efectivo real que hay en la caja</span>
                </div>

                <div class="campo" data-campo="observacion">
                    <label for="cie_observacion">
                        <i class="fa-solid fa-align-left txt-ico"></i> Observación
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="cie_observacion" name="observacion" maxlength="255" rows="2" placeholder="Faltante por propina, sobrante por cambio…"></textarea>
                    </div>
                    <span class="ayuda">Opcional · explica cualquier diferencia</span>
                </div>

                <div class="arq-dif ancho-2" id="cieDiferenciaCaja">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <span>Diferencia (contado − esperado)</span>
                    <span class="ad-valor" id="cieDiferencia">Bs 0,00</span>
                </div>

                <div class="arq-nota ancho-2">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>El esperado se calcula con el monto inicial más las ventas en efectivo menos los egresos/gastos pagados en efectivo. Al cerrar, el arqueo queda bloqueado y podrás reimprimir su ticket.</span>
                </div>

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mCieNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mCieCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mCieGuardar">
                    <i class="fa-solid fa-lock"></i> <span>Cerrar caja</span>
                </button>
            </div>
        </form>

    </div>
</div>
