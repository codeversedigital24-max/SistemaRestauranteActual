<!-- =====================================================
     MODAL: PERMISOS / PRIVILEGIOS DE UN USUARIO
     (se incluye desde sistema/usuarios.php)
     ===================================================== -->
<style>
    /* Privilegios por ítem: ver + acciones */
    .perm-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 3px 6px;
        border-radius: 6px;
    }

    .perm-item:hover {
        background: #f7f5f5;
    }

    .perm-item-ver {
        display: flex;
        align-items: center;
        gap: 7px;
        flex: 1;
        cursor: pointer;
    }

    .perm-item-acciones {
        display: inline-flex;
        gap: 4px;
        margin-left: auto;
    }

    .perm-accion {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        cursor: pointer;
        border: 1px solid #e6e0e0;
        border-radius: 5px;
        padding: 1px 5px;
        font-size: 10.5px;
        color: #8a8084;
        user-select: none;
    }

    .perm-accion input {
        margin: 0;
        cursor: pointer;
    }

    .perm-accion:has(input:checked) {
        border-color: #d9232d;
        color: #d9232d;
        background: #fdecec;
        font-weight: 600;
    }
</style>
<div class="modal-overlay" id="modalPermisos" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="permTitulo">
    <div class="modal-caja">

        <div class="modal-cabecera">
            <h2 id="permTitulo">
                <span class="ico-titulo"><i class="fa-solid fa-user-shield"></i></span>
                <span id="permTexto" style="color:#fff;">Permisos de usuario</span>
            </h2>
            <button type="button" class="modal-cerrar" id="permCerrar" aria-label="Cerrar">&times;</button>
        </div>

        <div class="modal-cuerpo perm-cuerpo">

            <!-- Aviso de estado -->
            <div class="perm-resumen" id="permResumen">
                <span class="mini-spin" aria-hidden="true"></span> Cargando permisos…
            </div>

            <!-- Árbol Módulo ▸ Submenú ▸ Ítem -->
            <div class="perm-arbol" id="permArbol" aria-live="polite"></div>

            <!-- Accesos rápidos -->
            <div class="perm-acciones">
                <button type="button" class="btn-mod btn-mod-suave" id="permTodo">
                    <i class="fa-solid fa-check-double"></i> Marcar todo
                </button>
                <button type="button" class="btn-mod btn-mod-claro" id="permNada">
                    <i class="fa-solid fa-eraser"></i> Limpiar
                </button>
                <span class="perm-contador" id="permContador">0 seleccionados</span>
            </div>
        </div>

        <div class="modal-pie">
            <span class="txt-tenue" id="permNota" style="margin-right:auto; font-size:12px;"></span>
            <button type="button" class="btn-mod btn-mod-claro" id="permCancelar">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button type="button" class="btn-mod btn-mod-primario" id="permGuardar">
                <i class="fa-solid fa-floppy-disk"></i> <span>Guardar permisos</span>
            </button>
        </div>

    </div>
</div>