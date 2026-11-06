<!-- =====================================================
     MODAL: REGISTRAR EGRESO / GASTO DE CAJA
     (se incluye desde sistema/arqueocaja.php)
     ===================================================== -->
<div class="modal-overlay" id="modalMovimiento" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="mMovTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="mMovTitulo">
                <span class="ico-titulo"><i class="fa-solid fa-receipt"></i></span>
                <span style="color:#fff;">Registrar egreso / gasto</span>
            </h2>
            <button type="button" class="modal-cerrar" id="mMovCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <form id="formMovimiento" novalidate autocomplete="off">

            <div class="modal-cuerpo">

                <div class="arq-caja-info ancho-2">
                    <span class="aci-icono" style="background:var(--mod-ambar,#9a6b00);"><i class="fa-solid fa-money-bill-transfer"></i></span>
                    <span class="aci-texto">
                        <span class="aci-titulo" id="movCajaTitulo">Caja</span>
                        <span class="aci-sub" id="movCajaSucursal">Sucursal</span>
                    </span>
                    <span class="aci-sugerido">
                        <span>Turno</span>
                        <strong style="font-size:12px;">Abierto</strong>
                    </span>
                </div>

                <div class="campo" data-campo="mov_tipo">
                    <label for="movTipo">
                        <i class="fa-solid fa-tags txt-ico"></i> Tipo <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-list ico-izq"></i>
                        <select class="input-modern" id="movTipo" name="tipo" required>
                            <option value="egreso">Egreso (salida de dinero)</option>
                            <option value="gasto">Gasto (compra / servicio)</option>
                        </select>
                    </div>
                    <span class="ayuda">Clasifica el movimiento para el reporte del turno</span>
                </div>

                <div class="campo" data-campo="mov_concepto">
                    <label for="movConcepto">
                        <i class="fa-solid fa-align-left txt-ico"></i> Concepto <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <input class="input-modern" type="text" id="movConcepto" name="concepto" maxlength="200"
                            placeholder="Compra de hielo, pago de mensajería…" required />
                    </div>
                    <span class="ayuda">Detalle breve del egreso o gasto</span>
                </div>

                <div class="campo" data-campo="mov_monto">
                    <label for="movMonto">
                        <i class="fa-solid fa-money-bill-wave txt-ico"></i> Monto <span class="req">*</span>
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-bs ico-izq" style="font-weight:800;font-size:11px;">Bs</i>
                        <input class="input-modern" type="number" id="movMonto" name="monto" min="0.01" step="0.01"
                            placeholder="0.00" required />
                    </div>
                    <span class="ayuda">Monto que sale de la caja</span>
                </div>

                <div class="campo" data-campo="mov_metodo">
                    <label for="movMetodo">
                        <i class="fa-solid fa-credit-card txt-ico"></i> Medio de pago
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-wallet ico-izq"></i>
                        <select class="input-modern" id="movMetodo" name="metodo_pago">
                            <option value="efectivo">Efectivo (afecta el esperado)</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="qr">QR</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <span class="ayuda">Sólo el efectivo reduce el efectivo esperado del cierre</span>
                </div>

                <div class="campo" data-campo="mov_referencia">
                    <label for="movReferencia">
                        <i class="fa-solid fa-hashtag txt-ico"></i> Referencia
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-barcode ico-izq"></i>
                        <input class="input-modern" type="text" id="movReferencia" name="referencia" maxlength="100"
                            placeholder="N° de recibo, factura o comprobante" />
                    </div>
                    <span class="ayuda">Opcional · facilita la auditoría del movimiento</span>
                </div>

                <div class="campo ancho-2" data-campo="mov_observacion">
                    <label for="movObservacion">
                        <i class="fa-solid fa-align-left txt-ico"></i> Observación
                    </label>
                    <div class="wrap-input">
                        <i class="fa-solid fa-pen ico-izq"></i>
                        <textarea class="input-modern" id="movObservacion" name="observacion" maxlength="255" rows="2"
                            placeholder="Detalle adicional del egreso o gasto…"></textarea>
                    </div>
                    <span class="ayuda">Opcional · queda registrada en el arqueo</span>
                </div>

                <div class="arq-nota ancho-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Sólo puedes registrar egresos o gastos <strong>con el arqueo abierto</strong>. Los movimientos en efectivo se descuentan del efectivo esperado al cerrar.</span>
                </div>

            </div>

            <div class="modal-pie">
                <span class="txt-tenue" id="mMovNota" style="margin-right:auto; font-size:12px;"></span>
                <button type="button" class="btn-mod btn-mod-claro" id="mMovCancelar">
                    <i class="fa-solid fa-xmark"></i> Cancelar
                </button>
                <button type="submit" class="btn-mod btn-mod-primario" id="mMovGuardar">
                    <i class="fa-solid fa-receipt"></i> <span>Registrar movimiento</span>
                </button>
            </div>
        </form>

    </div>
</div>
