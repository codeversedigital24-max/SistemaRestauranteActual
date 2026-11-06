(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $todos = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    var estado = {
        promociones: [],
        productos: [],
        categorias: [],
        catalogoCargado: false,
    };

    // =========================================================
    // UTILS
    // =========================================================
    function escapar(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function aviso(titulo, mensaje, tipo, tiempo) {
        if (!window.Swal) return console.warn(titulo + ": " + mensaje);
        Swal.fire({
            toast: true,
            position: "top-end",
            icon: tipo || "info",
            title: titulo,
            text: mensaje,
            timer: tiempo || 3200,
            timerProgressBar: true,
            showConfirmButton: false,
        });
    }

    function avisoGrande(titulo, mensaje, tipo) {
        if (!window.Swal) return;
        Swal.fire({
            icon: tipo || "success",
            title: titulo,
            text: mensaje || "",
            timer: 1900,
            showConfirmButton: false,
        });
    }

    function mostrarCarga(activo) {
        var caja = $s("#prCarga");
        if (caja) caja.classList.toggle("visible", Boolean(activo));
    }

    function chipSituacion(p) {
        var mapa = {
            vigente:    ["vigente",    "Vigente",    "fa-circle-check"],
            programada: ["programada", "Programada", "fa-clock"],
            vencida:    ["vencida",    "Vencida",    "fa-circle-minus"],
            inactiva:   ["inactiva",   "Inactiva",   "fa-circle-pause"],
        };
        var info = mapa[p.situacion] || mapa.inactiva;
        return '<span class="promo-chip ' + info[0] + '"><i class="fa-solid ' + info[2] + '"></i> ' + info[1] + '</span>';
    }

    function chipAlcance(p) {
        return p.alcance === "categoria"
            ? '<span class="promo-chip alcance"><i class="fa-solid fa-layer-group"></i> Categoría</span>'
            : '<span class="promo-chip alcance"><i class="fa-solid fa-burger"></i> Producto</span>';
    }

    function tipoLabel(tipo) {
        return tipo === "monto" ? "Monto fijo" : "Porcentaje";
    }

    // =========================================================
    // CARGA
    // =========================================================
    function cargar() {
        if (!window.PromocionesAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);

        window.PromocionesAPI.listar().then(function (rpta) {
            mostrarCarga(false);

            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error al leer promociones", "error", 5000);
                pintarTabla([]);
                return;
            }

            var d = rpta.data || {};
            estado.promociones = Array.isArray(d.promociones) ? d.promociones : [];
            estado.productos   = Array.isArray(d.productos) ? d.productos : [];
            estado.categorias  = Array.isArray(d.categorias) ? d.categorias : [];

            llenarCatalogos();
            pintarResumen(d.resumen || {});
            pintarTabla(filtrarPromociones());
        });
    }

    function llenarCatalogos() {
        var selProd = $s("#pr_producto");
        var selCat  = $s("#pr_categoria");

        if (selProd && selProd.dataset.cargado !== "1") {
            var productos = ['<option value="">— Selecciona un producto —</option>'];
            estado.productos.forEach(function (p) {
                productos.push(
                    '<option value="' + Number(p.id_producto) + '">' +
                    escapar(p.nombre) + (p.codigo ? " · " + escapar(p.codigo) : "") +
                    "</option>"
                );
            });
            selProd.innerHTML = productos.join("");
            selProd.dataset.cargado = "1";
        }

        if (selCat && selCat.dataset.cargado !== "1") {
            var categorias = ['<option value="">— Selecciona una categoría —</option>'];
            estado.categorias.forEach(function (c) {
                categorias.push(
                    '<option value="' + Number(c.id_categoria) + '">' +
                    escapar(c.nombre_categoria) + "</option>"
                );
            });
            selCat.innerHTML = categorias.join("");
            selCat.dataset.cargado = "1";
        }
    }

    function pintarResumen(r) {
        var set = function (sel, v) {
            var el = $s(sel);
            if (el) el.textContent = String(v || 0);
        };
        set("#prTotal", r.total);
        set("#prActivas", r.activas);
        set("#prVigentes", r.vigentes);
        set("#prProgramadas", r.programadas);
        set("#prVencidas", r.vencidas);
        set("#prInactivas", r.inactivas);
    }

    // =========================================================
    // TABLA / FILTROS
    // =========================================================
    function filtrarPromociones() {
        var texto = ($s("#prBuscar") ? $s("#prBuscar").value : "").trim().toLowerCase();
        var situacion = ($s("#prFiltroEstado") ? $s("#prFiltroEstado").value : "");
        var alcance = ($s("#prFiltroAlcance") ? $s("#prFiltroAlcance").value : "");

        return estado.promociones.filter(function (p) {
            if (situacion && String(p.situacion) !== situacion) return false;
            if (alcance && String(p.alcance) !== alcance) return false;

            if (!texto) return true;

            return (
                String(p.nombre || "").toLowerCase().indexOf(texto) !== -1 ||
                String(p.descripcion || "").toLowerCase().indexOf(texto) !== -1 ||
                String(p.objetivo || "").toLowerCase().indexOf(texto) !== -1
            );
        });
    }

    function acciones(p) {
        var acc = window.RM_ACCIONES || {};
        var botones = "";

        if (acc.modificar) {
            botones +=
                '<button type="button" class="btn-tabla editar" data-accion="editar" data-id="' + p.id +
                '" title="Modificar" aria-label="Modificar"><i class="fa-solid fa-pen"></i></button>';

            botones += Number(p.activo) === 1
                ? '<button type="button" class="btn-tabla baja" data-accion="estado" data-id="' + p.id +
                  '" data-activo="0" title="Desactivar" aria-label="Desactivar"><i class="fa-solid fa-toggle-on"></i></button>'
                : '<button type="button" class="btn-tabla alta" data-accion="estado" data-id="' + p.id +
                  '" data-activo="1" title="Activar" aria-label="Activar"><i class="fa-solid fa-toggle-off"></i></button>';
        }

        if (acc.eliminar) {
            botones +=
                '<button type="button" class="btn-tabla borrar" data-accion="eliminar" data-id="' + p.id +
                '" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash-can"></i></button>';
        }

        return '<span class="acciones-fila">' + botones + "</span>";
    }

    function pintarTabla(lista) {
        var body = $s("#prBody");
        var contador = $s("#prContador");
        if (!body) return;

        if (contador) {
            contador.textContent = lista.length + (lista.length === 1 ? " promoción" : " promociones");
        }

        if (!lista.length) {
            body.innerHTML = '<tr><td colspan="7" class="pr-vacio"><i class="fa-solid fa-tags"></i> No hay promociones con estos filtros.</td></tr>';
            return;
        }

        body.innerHTML = lista.map(function (p) {
            var vigencia = (p.desde ? escapar(p.desde) : "Sin inicio") +
                '<span class="pr-flecha">→</span>' +
                (p.hasta ? escapar(p.hasta) : "Sin fin");

            return (
                "<tr>" +
                '<td><div class="pr-prod">' +
                    '<b title="' + escapar(p.nombre) + '">' + escapar(p.nombre) + "</b>" +
                    (p.descripcion ? "<small>" + escapar(p.descripcion) + "</small>" : "") +
                "</div></td>" +
                "<td>" + chipAlcance(p) +
                    '<span class="pr-objetivo" title="' + escapar(p.objetivo) + '">' + escapar(p.objetivo) + "</span></td>" +
                '<td><span class="pr-descuento">' + escapar(p.etiqueta) + "</span>" +
                    "<small>" + tipoLabel(p.tipo_descuento) + "</small></td>" +
                '<td class="num">' + Number(p.prioridad || 0) + "</td>" +
                '<td class="pr-vigencia">' + vigencia + "</td>" +
                "<td>" + chipSituacion(p) + "</td>" +
                '<td class="pr-acciones">' + acciones(p) + "</td>" +
                "</tr>"
            );
        }).join("");
    }

    // =========================================================
    // MODAL
    // =========================================================
    function abrirModal() {
        var modal = $s("#modalPromocion");
        if (!modal) return;
        modal.classList.add("abierto");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";

        setTimeout(function () {
            var primero = $s("#pr_nombre");
            if (primero) primero.focus();
        }, 60);
    }

    function cerrarModal() {
        var modal = $s("#modalPromocion");
        if (!modal) return;
        modal.classList.remove("abierto");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";

        var form = $s("#formPromocion");
        if (form) {
            form.reset();
            form.dataset.accion = "crear";
        }

        var id = $s("#pr_id");
        if (id) id.value = "";

        toggleAlcance();
        toggleTipo();
    }

    function toggleAlcance() {
        var alcance = $s("#pr_alcance") ? $s("#pr_alcance").value : "producto";
        var wrapProd = $s("#prProductoWrap");
        var wrapCat = $s("#prCategoriaWrap");
        var selProd = $s("#pr_producto");
        var selCat = $s("#pr_categoria");

        var esProducto = alcance === "producto";

        if (wrapProd) wrapProd.style.display = esProducto ? "" : "none";
        if (wrapCat) wrapCat.style.display = esProducto ? "none" : "";

        if (selProd) selProd.required = esProducto;
        if (selCat) selCat.required = !esProducto;
    }

    function toggleTipo() {
        var tipo = $s("#pr_tipo") ? $s("#pr_tipo").value : "porcentaje";
        var valor = $s("#pr_valor");
        var ayuda = $s("#prValorAyuda");

        if (!valor) return;

        if (tipo === "monto") {
            valor.max = "";
            valor.placeholder = "5.00";
            valor.step = "0.01";
            if (ayuda) ayuda.textContent = "Monto fijo de descuento en Bs";
        } else {
            valor.max = "100";
            valor.placeholder = "15";
            valor.step = "0.01";
            if (ayuda) ayuda.textContent = "Porcentaje de descuento (0 a 100)";
        }
    }

    function abrirNuevo() {
        var form = $s("#formPromocion");
        if (form) {
            form.reset();
            form.dataset.accion = "crear";
        }

        var id = $s("#pr_id");
        if (id) id.value = "";

        if ($s("#pr_alcance")) $s("#pr_alcance").value = "producto";
        if ($s("#pr_tipo")) $s("#pr_tipo").value = "porcentaje";
        if ($s("#pr_prioridad")) $s("#pr_prioridad").value = "0";
        if ($s("#pr_activo")) $s("#pr_activo").value = "1";

        var titulo = $s("#prModalTexto");
        var icono = $s("#prModalIcono");
        if (titulo) titulo.textContent = "Nueva promoción";
        if (icono) icono.innerHTML = '<i class="fa-solid fa-tags"></i>';

        var pie = $s("#prPieNota");
        if (pie) pie.textContent = "";

        toggleAlcance();
        toggleTipo();
        abrirModal();
    }

    function abrirEditar(id) {
        var p = estado.promociones.find(function (x) {
            return Number(x.id) === Number(id);
        });

        if (!p) {
            aviso("Sin datos", "No se encontró la promoción", "warning");
            return;
        }

        var form = $s("#formPromocion");
        if (form) {
            form.reset();
            form.dataset.accion = "actualizar";
        }

        var set = function (sel, v) {
            var el = $s(sel);
            if (el) el.value = v == null ? "" : v;
        };

        set("#pr_id", p.id);
        set("#pr_nombre", p.nombre);
        set("#pr_descripcion", p.descripcion);
        set("#pr_alcance", p.alcance);
        set("#pr_producto", p.id_producto > 0 ? p.id_producto : "");
        set("#pr_categoria", p.id_categoria > 0 ? p.id_categoria : "");
        set("#pr_tipo", p.tipo_descuento);
        set("#pr_valor", p.valor);
        set("#pr_prioridad", p.prioridad);
        set("#pr_desde", p.desde_iso || "");
        set("#pr_hasta", p.hasta_iso || "");
        set("#pr_activo", p.activo);

        var titulo = $s("#prModalTexto");
        var icono = $s("#prModalIcono");
        if (titulo) titulo.textContent = "Modificar promoción";
        if (icono) icono.innerHTML = '<i class="fa-solid fa-pen-to-square"></i>';

        var pie = $s("#prPieNota");
        if (pie) pie.textContent = "Editando: " + (p.nombre || "");

        toggleAlcance();
        toggleTipo();
        abrirModal();
    }

    // =========================================================
    // GUARDAR
    // =========================================================
    function guardar(evento) {
        evento.preventDefault();

        var form = $s("#formPromocion");
        var esEdicion = form && form.dataset.accion === "actualizar";

        var datos = {
            id: Number(($s("#pr_id") || {}).value || 0),
            nombre: (($s("#pr_nombre") || {}).value || "").trim(),
            descripcion: (($s("#pr_descripcion") || {}).value || "").trim(),
            alcance: (($s("#pr_alcance") || {}).value || "producto"),
            id_producto: Number((($s("#pr_producto") || {}).value) || 0),
            id_categoria: Number((($s("#pr_categoria") || {}).value) || 0),
            tipo_descuento: (($s("#pr_tipo") || {}).value || "porcentaje"),
            valor: Number((($s("#pr_valor") || {}).value) || 0),
            prioridad: Number((($s("#pr_prioridad") || {}).value) || 0),
            desde: (($s("#pr_desde") || {}).value || ""),
            hasta: (($s("#pr_hasta") || {}).value || ""),
            activo: Number((($s("#pr_activo") || {}).value) === "0" ? 0 : 1),
        };

        if (datos.nombre.length < 2) {
            aviso("Revisa el formulario", "Escribe el nombre de la promoción", "warning");
            if ($s("#pr_nombre")) $s("#pr_nombre").focus();
            return;
        }

        if (datos.alcance === "producto" && datos.id_producto <= 0) {
            aviso("Revisa el formulario", "Selecciona el producto", "warning");
            if ($s("#pr_producto")) $s("#pr_producto").focus();
            return;
        }

        if (datos.alcance === "categoria" && datos.id_categoria <= 0) {
            aviso("Revisa el formulario", "Selecciona la categoría", "warning");
            if ($s("#pr_categoria")) $s("#pr_categoria").focus();
            return;
        }

        if (datos.valor <= 0) {
            aviso("Revisa el formulario", "El valor del descuento debe ser mayor que cero", "warning");
            if ($s("#pr_valor")) $s("#pr_valor").focus();
            return;
        }

        if (datos.tipo_descuento === "porcentaje" && datos.valor > 100) {
            aviso("Revisa el formulario", "El porcentaje no puede superar el 100 %", "warning");
            if ($s("#pr_valor")) $s("#pr_valor").focus();
            return;
        }

        if (datos.desde && datos.hasta && new Date(datos.desde) > new Date(datos.hasta)) {
            aviso("Revisa las fechas", "La fecha inicial no puede ser posterior a la final", "warning");
            return;
        }

        var boton = $s("#prGuardar");
        if (boton) boton.disabled = true;

        var promesa = esEdicion
            ? window.PromocionesAPI.actualizar(datos)
            : window.PromocionesAPI.crear(datos);

        promesa.then(function (rpta) {
            if (boton) boton.disabled = false;

            if (rpta.success) {
                cerrarModal();
                avisoGrande(
                    esEdicion ? "Promoción actualizada" : "Promoción creada",
                    rpta.message || "Los datos se guardaron correctamente"
                );
                cargar();
            } else {
                aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5500);
            }
        });
    }

    // =========================================================
    // ESTADO / ELIMINAR
    // =========================================================
    function cambiarEstado(id, activo) {
        var p = estado.promociones.find(function (x) { return Number(x.id) === Number(id); });
        var nombre = p ? escapar(p.nombre) : "#" + id;
        var activar = Number(activo) === 1;

        Swal.fire({
            icon: "question",
            title: activar ? "¿Activar promoción?" : "¿Desactivar promoción?",
            html: (activar ? "Se activará" : "Se desactivará") + " <strong>" + nombre + "</strong>." +
                "<br><span style='font-size:13px;color:#8a8a8f'>" +
                (activar ? "Volverá a aplicarse en el POS si está dentro de sus fechas." : "Dejará de aplicarse en el POS de inmediato.") +
                "</span>",
            showCancelButton: true,
            confirmButtonText: activar ? "Sí, activar" : "Sí, desactivar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: activar ? "#14803d" : "#b57a00",
            cancelButtonColor: "#9aa0a6",
            reverseButtons: true,
        }).then(function (r) {
            if (!r.isConfirmed) return;
            window.PromocionesAPI.cambiarEstado(id, activo).then(function (rpta) {
                if (rpta.success) {
                    avisoGrande(activar ? "Promoción activa" : "Promoción inactiva", rpta.message || "");
                    cargar();
                } else {
                    aviso("No se pudo cambiar", rpta.message || "Intenta de nuevo", "error", 5000);
                }
            });
        });
    }

    function eliminar(id) {
        var p = estado.promociones.find(function (x) { return Number(x.id) === Number(id); });
        var nombre = p ? escapar(p.nombre) : "#" + id;

        Swal.fire({
            icon: "warning",
            title: "¿Eliminar promoción?",
            html: "Se eliminará permanentemente <strong>" + nombre + "</strong>." +
                "<br><span style='font-size:13px;color:#8a8a8f'>Esta acción no se puede deshacer.</span>",
            showCancelButton: true,
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#d9232d",
            cancelButtonColor: "#9aa0a6",
            reverseButtons: true,
        }).then(function (r) {
            if (!r.isConfirmed) return;
            window.PromocionesAPI.eliminar(id).then(function (rpta) {
                if (rpta.success) {
                    avisoGrande("Promoción eliminada", rpta.message || "");
                    cargar();
                } else {
                    aviso("No se pudo eliminar", rpta.message || "Intenta de nuevo", "error", 5000);
                }
            });
        });
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        var btnNuevo = $s("#btnNuevaPromocion");
        if (btnNuevo) btnNuevo.addEventListener("click", abrirNuevo);

        var btnFiltrar = $s("#prFiltrar");
        if (btnFiltrar) btnFiltrar.addEventListener("click", function () {
            pintarTabla(filtrarPromociones());
        });

        var btnLimpiar = $s("#prLimpiar");
        if (btnLimpiar) btnLimpiar.addEventListener("click", function () {
            ["prBuscar", "prFiltroEstado", "prFiltroAlcance"].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            pintarTabla(filtrarPromociones());
        });

        var buscar = $s("#prBuscar");
        if (buscar) {
            var timer = null;
            buscar.addEventListener("input", function () {
                clearTimeout(timer);
                timer = setTimeout(function () { pintarTabla(filtrarPromociones()); }, 250);
            });
        }

        ["prFiltroEstado", "prFiltroAlcance"].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener("change", function () { pintarTabla(filtrarPromociones()); });
        });

        var body = $s("#prBody");
        if (body) {
            body.addEventListener("click", function (ev) {
                var boton = ev.target.closest("[data-accion]");
                if (!boton) return;

                var accion = boton.getAttribute("data-accion");
                var id = boton.getAttribute("data-id");

                if (accion === "editar") abrirEditar(id);
                else if (accion === "estado") cambiarEstado(id, boton.getAttribute("data-activo"));
                else if (accion === "eliminar") eliminar(id);
            });
        }

        var modal = $s("#modalPromocion");
        if (modal) {
            modal.addEventListener("click", function (ev) {
                if (ev.target === modal) cerrarModal();
            });
        }

        var cerrarX = $s("#prCerrarX");
        if (cerrarX) cerrarX.addEventListener("click", cerrarModal);

        var cancelar = $s("#prCancelar");
        if (cancelar) cancelar.addEventListener("click", cerrarModal);

        var alcance = $s("#pr_alcance");
        if (alcance) alcance.addEventListener("change", toggleAlcance);

        var tipo = $s("#pr_tipo");
        if (tipo) tipo.addEventListener("change", toggleTipo);

        var form = $s("#formPromocion");
        if (form) form.addEventListener("submit", guardar);

        document.addEventListener("keydown", function (ev) {
            if (ev.key === "Escape") {
                var m = $s("#modalPromocion");
                if (m && m.classList.contains("abierto")) cerrarModal();
            }
        });
    });
})();
