/**
 * =====================================================
 * modelos/finanzas/finanzas.js
 * UI de 3 pestañas: monedas / medios / bancos
 * =====================================================
 */
(function () {
    "use strict";

    var $s = function (sel, ctxo) { return (ctxo || document).querySelector(sel); };

    var estado = {
        monedas: [],
        medios: [],
        bancos: [],
    };

    // =========================================================
    // UTILIDADES
    // =========================================================
    function escapar(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function aviso(titulo, texto, tipo, tiempo) {
        if (!window.Swal) return console.warn(titulo + ": " + texto);
        Swal.fire({
            toast: true, position: "top-end", icon: tipo || "info",
            title: titulo, text: texto, timer: tiempo || 3200,
            timerProgressBar: true, showConfirmButton: false,
        });
    }

    function avisoGrande(titulo, texto, tipo) {
        if (!window.Swal) return;
        return Swal.fire({
            icon: tipo || "success", title: titulo, text: texto,
            confirmButtonText: "Entendido", confirmButtonColor: "#d9232d",
        });
    }

    function chipEstado(e) {
        return e === 1
            ? '<span class="fin-chip-activo"><i class="fa-solid fa-circle-check"></i> Activo</span>'
            : '<span class="fin-chip-inactivo"><i class="fa-solid fa-circle-xmark"></i> Inactivo</span>';
    }

    // =========================================================
    // TABS
    // =========================================================
    function activarTab(nombre) {
        document.querySelectorAll(".fin-tabs button").forEach(function (b) {
            b.classList.toggle("activa", b.getAttribute("data-tab") === nombre);
        });
        document.querySelectorAll(".fin-panel").forEach(function (p) {
            p.classList.toggle("activo", p.id === "panel-" + nombre);
        });
    }

    // =========================================================
    // CARGA
    // =========================================================
    function cargar() {
        if (!window.FinanzasAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);

        window.FinanzasAPI.cargar().then(function (rpta) {
            mostrarCarga(false);

            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.monedas = rpta.data.monedas || [];
            estado.medios  = rpta.data.medios  || [];
            estado.bancos  = rpta.data.bancos  || [];

            pintarMonedas();
            pintarMedios();
            pintarBancos();
            llenarSelectMonedas();
        });
    }

    function mostrarCarga(activar) {
        var c = $s("#finCarga");
        if (c) c.classList.toggle("visible", Boolean(activar));
    }

    // =========================================================
    // TABLA MONEDAS
    // =========================================================
    function pintarMonedas() {
        var tbody = $s("#tablaMonedas");
        if (!tbody) return;

        if (!estado.monedas.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="fin-vacio">Aún no hay monedas registradas.</td></tr>';
            return;
        }

        tbody.innerHTML = estado.monedas.map(function (m) {
            return (
                "<tr>" +
                "<td><span class='fin-codigo'>" + escapar(m.codigo) + "</span></td>" +
                "<td><div class='fin-nombre'><i class='fa-solid fa-money-bill-1'></i>" + escapar(m.nombre) + "</div></td>" +
                "<td>" + escapar(m.simbolo) + "</td>" +
                "<td>" + m.decimales + "</td>" +
                "<td><span class='fin-tasa'>" + Number(m.tasa_cambio).toFixed(6) + "</span></td>" +
                "<td>" + chipEstado(m.estado) + (m.es_base ? ' <span class="fin-chip-base">BASE</span>' : '') + "</td>" +
                "<td class='fin-acciones'>" +
                    "<button type='button' data-editar='moneda' data-id='" + m.id + "'><i class='fa-solid fa-pen'></i></button>" +
                    "<button type='button' class='peligro' data-eliminar='moneda' data-id='" + m.id + "'><i class='fa-solid fa-trash-can'></i></button>" +
                "</td>" +
                "</tr>"
            );
        }).join("");
    }

    // =========================================================
    // TABLA MEDIOS
    // =========================================================
    function pintarMedios() {
        var tbody = $s("#tablaMedios");
        if (!tbody) return;

        if (!estado.medios.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="fin-vacio">Aún no hay medios de pago registrados.</td></tr>';
            return;
        }

        tbody.innerHTML = estado.medios.map(function (m) {
            return (
                "<tr>" +
                "<td><span class='fin-codigo'>" + escapar(m.codigo) + "</span></td>" +
                "<td><div class='fin-nombre'><i class='fa-solid " + escapar(m.icono) + "'></i>" + escapar(m.nombre) + "</div></td>" +
                "<td>" + (m.afecta_caja ? '<i class="fa-solid fa-circle-check" style="color:#14803d"></i> Sí' : '<i class="fa-solid fa-circle-xmark" style="color:#a79ea2"></i> No') + "</td>" +
                "<td>" + (m.requiere_referencia ? 'Sí' : '—') + "</td>" +
                "<td>" + m.orden + "</td>" +
                "<td>" + chipEstado(m.estado) + "</td>" +
                "<td class='fin-acciones'>" +
                    "<button type='button' data-editar='medio' data-id='" + m.id + "'><i class='fa-solid fa-pen'></i></button>" +
                    "<button type='button' class='peligro' data-eliminar='medio' data-id='" + m.id + "'><i class='fa-solid fa-trash-can'></i></button>" +
                "</td>" +
                "</tr>"
            );
        }).join("");
    }

    // =========================================================
    // TABLA BANCOS
    // =========================================================
    function pintarBancos() {
        var tbody = $s("#tablaBancos");
        if (!tbody) return;

        if (!estado.bancos.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="fin-vacio">Aún no hay bancos registrados.</td></tr>';
            return;
        }

        tbody.innerHTML = estado.bancos.map(function (b) {
            var moneda = b.moneda_codigo
                ? '<span class="fin-codigo">' + escapar(b.moneda_codigo) + '</span> ' + escapar(b.moneda_simbolo)
                : '<span class="vacio">—</span>';

            return (
                "<tr>" +
                "<td><div class='fin-nombre'><i class='fa-solid fa-building-columns'></i>" + escapar(b.nombre) + "</div>" +
                    (b.codigo ? "<span style='font-size:10.5px;color:#a79ea2'>" + escapar(b.codigo) + "</span>" : "") + "</td>" +
                "<td><span class='fin-tasa'>" + escapar(b.nro_cuenta) + "</span></td>" +
                "<td>" + (b.titular || '<span class="vacio">—</span>') + "</td>" +
                "<td>" + moneda + "</td>" +
                "<td>" + chipEstado(b.estado) + "</td>" +
                "<td class='fin-acciones'>" +
                    "<button type='button' data-editar='banco' data-id='" + b.id + "'><i class='fa-solid fa-pen'></i></button>" +
                    "<button type='button' class='peligro' data-eliminar='banco' data-id='" + b.id + "'><i class='fa-solid fa-trash-can'></i></button>" +
                "</td>" +
                "</tr>"
            );
        }).join("");
    }

    function llenarSelectMonedas() {
        var sel = $s("#b_moneda");
        if (!sel) return;

        var opciones = ['<option value="">— Sin moneda —</option>'];
        estado.monedas.filter(function (m) { return m.estado === 1; }).forEach(function (m) {
            opciones.push('<option value="' + m.id + '">' + escapar(m.codigo) + ' · ' + escapar(m.nombre) + '</option>');
        });
        sel.innerHTML = opciones.join("");
    }

    // =========================================================
    // MODALES
    // =========================================================
    function abrirModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.add("abierto");
        m.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function cerrarModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.remove("abierto");
        m.setAttribute("aria-hidden", "true");
        if (!document.querySelector(".modal-overlay.abierto")) document.body.style.overflow = "";
    }

    // -------- Moneda --------
    function nuevaMoneda() {
        var form = $s("#formMoneda");
        form.reset();
        $s("#m_id").value = "";
        $s("#mMonTitulo").textContent = "Nueva moneda";
        $s("#m_decimales").value = 2;
        $s("#m_tasa_cambio").value = "1.000000";
        $s("#m_es_base").checked = false;
        $s("#m_estado").value = "1";
        abrirModal("modalMoneda");
    }

    function editarMoneda(id) {
        var m = estado.monedas.find(function (x) { return x.id === Number(id); });
        if (!m) return;

        $s("#m_id").value = m.id;
        $s("#m_codigo").value = m.codigo;
        $s("#m_nombre").value = m.nombre;
        $s("#m_simbolo").value = m.simbolo;
        $s("#m_decimales").value = m.decimales;
        $s("#m_tasa_cambio").value = m.tasa_cambio;
        $s("#m_es_base").checked = m.es_base === 1;
        $s("#m_estado").value = String(m.estado);
        $s("#mMonTitulo").textContent = "Editar moneda";

        abrirModal("modalMoneda");
    }

       function guardarMoneda(ev) {
        ev.preventDefault();

        var d = {
            id: Number($s("#m_id").value || "0") || 0,
            codigo: ($s("#m_codigo").value || "").trim().toUpperCase(),
            nombre: ($s("#m_nombre").value || "").trim(),
            simbolo: ($s("#m_simbolo").value || "").trim(),
            decimales: Number($s("#m_decimales").value || "2") || 2,
            tasa_cambio: Number($s("#m_tasa_cambio").value || "1") || 1,
            es_base: $s("#m_es_base").checked ? 1 : 0,
            estado: Number($s("#m_estado").value || "1"),
        };

        if (!d.codigo || !d.nombre || !d.simbolo) {
            aviso("Faltan datos", "Código, nombre y símbolo son obligatorios", "warning");
            return;
        }

        var boton = $s("#mGuardarMoneda");
        boton.disabled = true;
        if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
        boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

        window.FinanzasAPI.monedaGuardar(d).then(function (r) {
            boton.disabled = false;
            boton.innerHTML = boton.dataset.html;

            if (!r.success) {
                aviso("No se pudo guardar", r.message || "Revisa los datos", "error", 5000);
                return;
            }

            cerrarModal("modalMoneda");
            avisoGrande(d.id ? "Moneda actualizada" : "Moneda creada", r.message || "Guardado", "success");
            cargar();
        });
    }

    // -------- Medio --------
    function nuevoMedio() {
        var form = $s("#formMedio");
        form.reset();
        $s("#md_id").value = "";
        $s("#md_icono").value = "fa-money-bill-wave";
        $s("#md_afecta_caja").checked = true;
        $s("#md_requiere_ref").checked = false;
        $s("#md_orden").value = 0;
        $s("#md_estado").value = "1";
        $s("#mMedTitulo").textContent = "Nuevo medio de pago";
        abrirModal("modalMedio");
    }

    function editarMedio(id) {
        var m = estado.medios.find(function (x) { return x.id === Number(id); });
        if (!m) return;

        $s("#md_id").value = m.id;
        $s("#md_codigo").value = m.codigo;
        $s("#md_nombre").value = m.nombre;
        $s("#md_icono").value = m.icono;
        $s("#md_afecta_caja").checked = m.afecta_caja === 1;
        $s("#md_requiere_ref").checked = m.requiere_referencia === 1;
        $s("#md_orden").value = m.orden;
        $s("#md_estado").value = String(m.estado);
        $s("#mMedTitulo").textContent = "Editar medio de pago";

        abrirModal("modalMedio");
    }

    function guardarMedio(ev) {
        ev.preventDefault();

        var d = {
            id: Number($s("#md_id").value || "0") || 0,
            codigo: ($s("#md_codigo").value || "").trim().toLowerCase(),
            nombre: ($s("#md_nombre").value || "").trim(),
            icono: ($s("#md_icono").value || "fa-money-bill-wave").trim(),
            afecta_caja: $s("#md_afecta_caja").checked ? 1 : 0,
            requiere_referencia: $s("#md_requiere_ref").checked ? 1 : 0,
            orden: Number($s("#md_orden").value || "0") || 0,
            estado: Number($s("#md_estado").value || "1"),
        };

        if (!d.codigo || !d.nombre) {
            aviso("Faltan datos", "Código y nombre son obligatorios", "warning");
            return;
        }

        var boton = $s("#mGuardarMedio");
        boton.disabled = true;
        if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
        boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

        window.FinanzasAPI.medioGuardar(d).then(function (r) {
            boton.disabled = false;
            boton.innerHTML = boton.dataset.html;

            if (!r.success) {
                aviso("No se pudo guardar", r.message || "Revisa los datos", "error", 5000);
                return;
            }

            cerrarModal("modalMedio");
            avisoGrande(d.id ? "Medio actualizado" : "Medio creado", r.message || "Guardado", "success");
            cargar();
        });
    }

    // -------- Banco --------
    function nuevoBanco() {
        var form = $s("#formBanco");
        form.reset();
        $s("#b_id").value = "";
        $s("#b_estado").value = "1";
        $s("#mBanTitulo").textContent = "Nuevo banco";
        abrirModal("modalBanco");
    }

    function editarBanco(id) {
        var b = estado.bancos.find(function (x) { return x.id === Number(id); });
        if (!b) return;

        $s("#b_id").value = b.id;
        $s("#b_nombre").value = b.nombre;
        $s("#b_codigo").value = b.codigo;
        $s("#b_nro_cuenta").value = b.nro_cuenta;
        $s("#b_titular").value = b.titular;
        $s("#b_moneda").value = b.id_moneda ? String(b.id_moneda) : "";
        $s("#b_observaciones").value = b.observaciones;
        $s("#b_estado").value = String(b.estado);
        $s("#mBanTitulo").textContent = "Editar banco";

        abrirModal("modalBanco");
    }

    function guardarBanco(ev) {
        ev.preventDefault();

        var d = {
            id: Number($s("#b_id").value || "0") || 0,
            nombre: ($s("#b_nombre").value || "").trim(),
            codigo: ($s("#b_codigo").value || "").trim(),
            nro_cuenta: ($s("#b_nro_cuenta").value || "").trim(),
            titular: ($s("#b_titular").value || "").trim(),
            id_moneda: Number($s("#b_moneda").value || "0") || 0,
            observaciones: ($s("#b_observaciones").value || "").trim(),
            estado: Number($s("#b_estado").value || "1"),
        };

        if (!d.nombre || !d.nro_cuenta) {
            aviso("Faltan datos", "Nombre del banco y N° de cuenta son obligatorios", "warning");
            return;
        }

        var boton = $s("#mGuardarBanco");
        boton.disabled = true;
        if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
        boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

        window.FinanzasAPI.bancoGuardar(d).then(function (r) {
            boton.disabled = false;
            boton.innerHTML = boton.dataset.html;

            if (!r.success) {
                aviso("No se pudo guardar", r.message || "Revisa los datos", "error", 5000);
                return;
            }

            cerrarModal("modalBanco");
            avisoGrande(d.id ? "Banco actualizado" : "Banco creado", r.message || "Guardado", "success");
            cargar();
        });
    }

    // =========================================================
    // ELIMINAR (baja lógica o definitivo)
    // =========================================================
    function eliminar(tipo, id) {
        var nombres = {
            moneda: { singular: "moneda", plural: "monedas", fn: "monedaEliminar", listado: estado.monedas },
            medio:  { singular: "medio de pago", plural: "medios", fn: "medioEliminar", listado: estado.medios },
            banco:  { singular: "banco", plural: "bancos", fn: "bancoEliminar", listado: estado.bancos },
        };

        var info = nombres[tipo];
        if (!info) return;

        var reg = info.listado.find(function (x) { return x.id === Number(id); });
        var etiqueta = reg ? (reg.codigo || reg.nombre || "#" + id) : "#" + id;

        Swal.fire({
            icon: "warning",
            title: "¿Eliminar " + info.singular + "?",
            html:
                "Selecciona cómo eliminar <strong>" + escapar(etiqueta) + "</strong>:<br>" +
                "<span style='font-size:12.5px;color:#8a8a8f'>" +
                "· <b>Dar de baja</b> lo desactiva pero conserva el historial.<br>" +
                "· <b>Borrar definitivo</b> lo elimina; no se podrá si tiene uso." +
                "</span>",
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: "Dar de baja",
            denyButtonText: "Borrar definitivo",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#d9232d",
            denyButtonColor: "#a32020",
            cancelButtonColor: "#9aa0a6",
            reverseButtons: true,
        }).then(function (r) {
            if (r.isConfirmed) {
                ejecutarEliminar(tipo, id, false);
            } else if (r.isDenied) {
                ejecutarEliminar(tipo, id, true);
            }
        });
    }

    function ejecutarEliminar(tipo, id, definitivo) {
        var metodo = {
            moneda: window.FinanzasAPI.monedaEliminar,
            medio:  window.FinanzasAPI.medioEliminar,
            banco:  window.FinanzasAPI.bancoEliminar,
        }[tipo];

        if (!metodo) return;

        metodo(id, definitivo).then(function (r) {
            if (!r.success) {
                aviso("No se pudo eliminar", r.message || "Intenta de nuevo", "error", 5500);
                return;
            }
            avisoGrande(definitivo ? "Eliminado" : "Desactivado", r.message || "Operación exitosa", "success");
            cargar();
        });
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        // Tabs
        document.querySelectorAll(".fin-tabs button").forEach(function (b) {
            b.addEventListener("click", function () {
                activarTab(b.getAttribute("data-tab"));
            });
        });

        // Botones "+ Nuevo"
        document.querySelectorAll("[data-nuevo]").forEach(function (b) {
            b.addEventListener("click", function () {
                var tipo = b.getAttribute("data-nuevo");
                if (tipo === "moneda") nuevaMoneda();
                else if (tipo === "medio") nuevoMedio();
                else if (tipo === "banco") nuevoBanco();
            });
        });

        // Cerrar modales
        document.querySelectorAll("[data-cerrar]").forEach(function (b) {
            b.addEventListener("click", function () {
                cerrarModal("modal" + b.getAttribute("data-cerrar").charAt(0).toUpperCase() + b.getAttribute("data-cerrar").slice(1));
            });
        });

        // Submit de formularios
        var fMon = $s("#formMoneda");
        if (fMon) fMon.addEventListener("submit", guardarMoneda);

        var fMed = $s("#formMedio");
        if (fMed) fMed.addEventListener("submit", guardarMedio);

        var fBan = $s("#formBanco");
        if (fBan) fBan.addEventListener("submit", guardarBanco);

        // Delegación de acciones en las 3 tablas
        ["#tablaMonedas", "#tablaMedios", "#tablaBancos"].forEach(function (sel) {
            var tb = document.querySelector(sel);
            if (!tb) return;

            tb.addEventListener("click", function (ev) {
                var btn = ev.target.closest("[data-editar],[data-eliminar]");
                if (!btn) return;

                if (btn.hasAttribute("data-editar")) {
                    var tipo = btn.getAttribute("data-editar");
                    var id = btn.getAttribute("data-id");
                    if (tipo === "moneda") editarMoneda(id);
                    else if (tipo === "medio") editarMedio(id);
                    else if (tipo === "banco") editarBanco(id);
                } else if (btn.hasAttribute("data-eliminar")) {
                    eliminar(btn.getAttribute("data-eliminar"), btn.getAttribute("data-id"));
                }
            });
        });

        // Escape
        document.addEventListener("keydown", function (ev) {
            if (ev.key !== "Escape") return;
            var abierto = document.querySelector(".modal-overlay.abierto");
            if (abierto) {
                abierto.classList.remove("abierto");
                abierto.setAttribute("aria-hidden", "true");
                if (!document.querySelector(".modal-overlay.abierto")) document.body.style.overflow = "";
            }
        });
    });
})();