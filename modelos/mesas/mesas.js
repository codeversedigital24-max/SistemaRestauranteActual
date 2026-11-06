/**
 * =====================================================
 * modelos/mesas/mesas.js
 * UI: grid de tarjetas + modales piso/mesa + filtros
 * =====================================================
 */
(function () {
    "use strict";

    var $s = function (sel, ctxo) { return (ctxo || document).querySelector(sel); };

    var estado = {
        usuario: null,
        sucursales: [],
        pisos: [],
        mesas: [],
        filtros: { id_sucursal: "", id_piso: "", estado_mesa: "" },
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

    function chipEstadoMesa(e) {
        var mapa = {
            libre: ["libre", "Libre", "fa-circle-check"],
            ocupada: ["ocupada", "Ocupada", "fa-user"],
            reservada: ["reservada", "Reservada", "fa-clock"],
            inactiva: ["inactiva", "Inactiva", "fa-circle-xmark"],
        };
        var info = mapa[e] || ["inactiva", "Inactiva", "fa-circle-xmark"];
        return '<span class="mc-chip ' + info[0] + '"><i class="fa-solid ' + info[2] + '"></i> ' + info[1] + '</span>';
    }

    // =========================================================
    // CARGA
    // =========================================================
    function mostrarCarga(a) {
        var c = $s("#mesCarga");
        var cont = $s("#mesContenido");
        if (c) c.classList.toggle("visible", Boolean(a));
        if (cont) cont.style.display = a ? "none" : "";
    }

    function cargar() {
        if (!window.MesasAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);

        window.MesasAPI.cargar(estado.filtros).then(function (rpta) {
            mostrarCarga(false);

            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            var d = rpta.data || {};
            estado.usuario    = d.usuario    || null;
            estado.sucursales = d.sucursales || [];
            estado.pisos      = d.pisos      || [];
            estado.mesas      = d.mesas      || [];

            llenarSucursales();
            llenarPisosFiltro();
            llenarPisosChips();
            llenarSelectsModales();
            pintarGrid();
        });
    }

    function llenarSucursales() {
        var sel = $s("#mfSucursal");
        if (!sel) return;

        var esSuper = estado.usuario && estado.usuario.es_superadmin;
        if (!esSuper) {
            var wrap = $s("#mfSucursalWrap");
            if (wrap) wrap.style.display = "none";
            return;
        }

        if (sel.dataset.cargado === "1") return;
        var opciones = ['<option value="">Todas las sucursales</option>'];
        estado.sucursales.forEach(function (s) {
            opciones.push('<option value="' + Number(s.id_sucursal) + '">' +
                escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
                "</option>");
        });
        sel.innerHTML = opciones.join("");
        sel.dataset.cargado = "1";
    }

    function llenarPisosFiltro() {
        var sel = $s("#mfPiso");
        if (!sel) return;

        var actual = sel.value;
        var opciones = ['<option value="">Todos los pisos</option>'];
        estado.pisos.forEach(function (p) {
            opciones.push('<option value="' + p.id + '">' + escapar(p.nombre) + '</option>');
        });
        sel.innerHTML = opciones.join("");
        if (actual && sel.querySelector('option[value="' + actual + '"]')) sel.value = actual;
    }

    function llenarPisosChips() {
        var cont = $s("#mesPisosChips");
        if (!cont) return;

        if (!estado.pisos.length) {
            cont.innerHTML = '<span style="color:var(--mod-texto-tenue);font-size:12px">No hay pisos creados. Pulsa "Pisos" para crear el primero.</span>';
            return;
        }

        cont.innerHTML = estado.pisos.map(function (p) {
            return '<span class="mes-piso-chip">' +
                '<i class="fa-solid ' + escapar(p.icono) + '" style="color:#d9232d"></i>' +
                '<b>' + escapar(p.nombre) + '</b>' +
                '<span style="color:#a79ea2;font-size:11px">' + p.mesas + ' mesas</span>' +
                '<button type="button" class="btn-x" data-piso-edit="' + p.id + '"><i class="fa-solid fa-pen"></i></button>' +
                '<button type="button" class="btn-x" data-piso-del="' + p.id + '"><i class="fa-solid fa-trash-can"></i></button>' +
                '</span>';
        }).join("");
    }

       function llenarSelectsModales() {
        var opts = ['<option value="">— Selecciona —</option>'];
        estado.sucursales.forEach(function (s) {
            opts.push('<option value="' + s.id_sucursal + '">' +
                escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
                "</option>");
        });

        var htmlOpts = opts.join("");
        if ($s("#piso_sucursal")) $s("#piso_sucursal").innerHTML = htmlOpts;
        if ($s("#mesa_sucursal")) $s("#mesa_sucursal").innerHTML = htmlOpts;

        // Pisos por sucursal (se actualiza al elegir sucursal en el modal)
        actualizarPisosPorSucursal(
            $s("#mesa_sucursal") ? $s("#mesa_sucursal").value : 0
        );
    }

    function actualizarPisosPorSucursal(idSucursal) {
        var sel = $s("#mesa_piso");
        if (!sel) return;

        var opciones = ['<option value="">— Sin piso —</option>'];
        estado.pisos
            .filter(function (p) {
                return !idSucursal || String(p.id_sucursal) === String(idSucursal);
            })
            .forEach(function (p) {
                opciones.push('<option value="' + p.id + '">' + escapar(p.nombre) + '</option>');
            });

        var actual = sel.value;
        sel.innerHTML = opciones.join("");
        if (actual && sel.querySelector('option[value="' + actual + '"]')) sel.value = actual;
    }

    // =========================================================
    // GRID
    // =========================================================
    function pintarGrid() {
        var grid = $s("#mesGrid");
        if (!grid) return;

        var mesas = estado.mesas.slice();

        if (estado.filtros.id_piso) {
            mesas = mesas.filter(function (m) {
                return String(m.id_piso) === String(estado.filtros.id_piso);
            });
        }
        if (estado.filtros.estado_mesa) {
            mesas = mesas.filter(function (m) {
                return m.estado_mesa === estado.filtros.estado_mesa;
            });
        }
        if (estado.filtros.id_sucursal) {
            mesas = mesas.filter(function (m) {
                return String(m.id_sucursal) === String(estado.filtros.id_sucursal);
            });
        }

        var html = "";

        if (!mesas.length) {
            html = '<div class="mes-vacio">No hay mesas con estos filtros.<br>Pulsa <b>Nueva mesa</b> para crear la primera.</div>';
        } else {
            html = mesas.map(function (m) {
                var claseExtra =
                    m.estado === 0 ? " inactiva-mesa" :
                    m.estado_mesa === "ocupada" ? " ocupada" :
                    m.estado_mesa === "reservada" ? " reservada" : "";

                var foto = m.imagen_url
                    ? '<img src="' + escapar(m.imagen_url) + '" alt="' + escapar(m.nombre) + '">'
                    : '<i class="fa-solid ' + escapar(m.icono) + '"></i>';

                var formaIcono = {
                    redonda:     "fa-circle",
                    cuadrada:    "fa-square",
                    rectangular: "fa-rectangle-landscape",
                }[m.forma] || "fa-circle";

                return (
                    '<article class="mes-card' + claseExtra + '" data-id="' + m.id + '">' +
                        '<div class="mc-foto">' +
                            foto +
                            (m.piso ? '<span class="mc-piso"><i class="fa-solid ' + escapar(m.piso_icono) + '"></i> ' + escapar(m.piso) + '</span>' : '') +
                            '<span class="mc-forma"><i class="fa-solid ' + formaIcono + '"></i> ' + escapar(m.forma) + '</span>' +
                        '</div>' +
                        '<div class="mc-body">' +
                            '<div class="mc-codigo">' + escapar(m.codigo) + ' · ' + escapar(m.sucursal) + '</div>' +
                            '<div class="mc-nombre">' + escapar(m.nombre) + '</div>' +
                            '<div class="mc-meta">' +
                                '<span><i class="fa-solid fa-user-group"></i>' + m.capacidad + ' pers.</span>' +
                                chipEstadoMesa(m.estado_mesa) +
                            '</div>' +
                            '<div class="mc-acciones">' +
                                '<button type="button" data-mesa-edit="' + m.id + '"><i class="fa-solid fa-pen"></i> Editar</button>' +
                                '<button type="button" class="peligro" data-mesa-del="' + m.id + '"><i class="fa-solid fa-trash-can"></i></button>' +
                            '</div>' +
                        '</div>' +
                    '</article>'
                );
            }).join("");
        }

        // Tarjeta "Añadir mesa"
        html +=
            '<div class="mes-add" id="mesAddCard">' +
                '<i class="fa-solid fa-plus"></i>' +
                '<span>Nueva mesa</span>' +
            '</div>';

        grid.innerHTML = html;

        var add = $s("#mesAddCard");
        if (add) add.addEventListener("click", nuevaMesa);
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

    // -------- PISO --------
    function nuevoPiso() {
        var form = $s("#formPiso");
        form.reset();
        $s("#piso_id").value = "";
        $s("#piso_icono").value = "fa-layer-group";
        $s("#piso_orden").value = 0;
        $s("#piso_estado").value = "1";
        $s("#pisoTitulo").textContent = "Nuevo piso";

        // Preseleccionar la sucursal filtrada
        if (estado.filtros.id_sucursal) $s("#piso_sucursal").value = estado.filtros.id_sucursal;

        abrirModal("modalPiso");
    }

    function editarPiso(id) {
        var p = estado.pisos.find(function (x) { return x.id === Number(id); });
        if (!p) return;

        $s("#piso_id").value = p.id;
        $s("#piso_sucursal").value = p.id_sucursal;
        $s("#piso_nombre").value = p.nombre;
        $s("#piso_icono").value = p.icono;
        $s("#piso_orden").value = p.orden;
        $s("#piso_estado").value = String(p.estado);
        $s("#pisoTitulo").textContent = "Editar piso";

        abrirModal("modalPiso");
    }

    function guardarPiso(ev) {
        ev.preventDefault();

        var d = {
            id: Number($s("#piso_id").value || "0") || 0,
            id_sucursal: Number($s("#piso_sucursal").value || "0") || 0,
            nombre: ($s("#piso_nombre").value || "").trim(),
            icono: ($s("#piso_icono").value || "fa-layer-group").trim(),
            orden: Number($s("#piso_orden").value || "0") || 0,
            estado: Number($s("#piso_estado").value || "1"),
        };

        if (!d.id_sucursal) { aviso("Falta", "Selecciona la sucursal", "warning"); return; }
        if (d.nombre.length < 2) { aviso("Falta", "Nombre del piso", "warning"); return; }

        var btn = $s("#pisoGuardar");
        btn.disabled = true;
        if (!btn.dataset.html) btn.dataset.html = btn.innerHTML;
        btn.innerHTML = '<span class="mini-spin"></span> Guardando…';

        window.MesasAPI.pisoGuardar(d).then(function (r) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.html;

            if (!r.success) { aviso("No se pudo guardar", r.message, "error", 5000); return; }
            cerrarModal("modalPiso");
            avisoGrande(d.id ? "Piso actualizado" : "Piso creado", r.message, "success");
            cargar();
        });
    }

    function eliminarPiso(id) {
        var p = estado.pisos.find(function (x) { return x.id === Number(id); });
        if (!p) return;

        Swal.fire({
            icon: "warning",
            title: "¿Eliminar " + escapar(p.nombre) + "?",
            html: "Selecciona cómo eliminarlo:<br><span style='font-size:12.5px;color:#8a8a8f'>Dar de baja lo desactiva; borrar definitivo no se podrá si tiene mesas.</span>",
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
            if (r.isConfirmed) ejecutarEliminarPiso(id, false);
            else if (r.isDenied) ejecutarEliminarPiso(id, true);
        });
    }

    function ejecutarEliminarPiso(id, definitivo) {
        window.MesasAPI.pisoEliminar(id, definitivo).then(function (r) {
            if (!r.success) { aviso("No se pudo eliminar", r.message, "error", 5500); return; }
            avisoGrande(definitivo ? "Eliminado" : "Desactivado", r.message, "success");
            cargar();
        });
    }

    // -------- MESA --------
    function nuevaMesa() {
        var form = $s("#formMesa");
        form.reset();
        $s("#mesa_id").value = "";
        $s("#mesa_capacidad").value = 4;
        $s("#mesa_forma").value = "redonda";
        $s("#mesa_icono").value = "fa-chair";
        $s("#mesa_estado_mesa").value = "libre";
        $s("#mesa_orden").value = 0;
        $s("#mesa_estado").value = "1";
        $s("#mesa_imagen").value = "";
        pintarPreviewImagen("");
        $s("#mesaTitulo").textContent = "Nueva mesa";

        if (estado.filtros.id_sucursal) $s("#mesa_sucursal").value = estado.filtros.id_sucursal;
        actualizarPisosPorSucursal($s("#mesa_sucursal").value);
        if (estado.filtros.id_piso) $s("#mesa_piso").value = estado.filtros.id_piso;

        abrirModal("modalMesa");
    }

    function editarMesa(id) {
        var m = estado.mesas.find(function (x) { return x.id === Number(id); });
        if (!m) return;

        $s("#mesa_id").value = m.id;
        $s("#mesa_sucursal").value = m.id_sucursal;
        actualizarPisosPorSucursal(m.id_sucursal);
        $s("#mesa_piso").value = m.id_piso || "";
        $s("#mesa_codigo").value = m.codigo;
        $s("#mesa_nombre").value = m.nombre;
        $s("#mesa_capacidad").value = m.capacidad;
        $s("#mesa_forma").value = m.forma;
        $s("#mesa_icono").value = m.icono;
        $s("#mesa_estado_mesa").value = m.estado_mesa;
        $s("#mesa_orden").value = m.orden;
        $s("#mesa_estado").value = String(m.estado);
        $s("#mesa_imagen").value = m.imagen || "";
        pintarPreviewImagen(m.imagen_url || "");
        $s("#mesaTitulo").textContent = "Editar mesa";

        abrirModal("modalMesa");
    }

    function pintarPreviewImagen(url) {
        var prev = $s("#mesaImgPrev");
        if (!prev) return;
        prev.innerHTML = url
            ? '<img src="' + escapar(url) + '" alt="">'
            : '<i class="fa-solid fa-image"></i>';
    }

    function guardarMesa(ev) {
        ev.preventDefault();

        var d = {
            id: Number($s("#mesa_id").value || "0") || 0,
            id_sucursal: Number($s("#mesa_sucursal").value || "0") || 0,
            id_piso: Number($s("#mesa_piso").value || "0") || 0,
            codigo: ($s("#mesa_codigo").value || "").trim().toUpperCase(),
            nombre: ($s("#mesa_nombre").value || "").trim(),
            capacidad: Number($s("#mesa_capacidad").value || "4") || 4,
            forma: $s("#mesa_forma").value,
            icono: ($s("#mesa_icono").value || "fa-chair").trim(),
            imagen: $s("#mesa_imagen").value,
            estado_mesa: $s("#mesa_estado_mesa").value,
            orden: Number($s("#mesa_orden").value || "0") || 0,
            estado: Number($s("#mesa_estado").value || "1"),
        };

        if (!d.id_sucursal) { aviso("Falta", "Selecciona la sucursal", "warning"); return; }
        if (!d.codigo) { aviso("Falta", "Código de mesa", "warning"); return; }
        if (d.nombre.length < 2) { aviso("Falta", "Nombre de mesa", "warning"); return; }

        var btn = $s("#mesaGuardar");
        btn.disabled = true;
        if (!btn.dataset.html) btn.dataset.html = btn.innerHTML;
        btn.innerHTML = '<span class="mini-spin"></span> Guardando…';

        window.MesasAPI.mesaGuardar(d).then(function (r) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.html;

            if (!r.success) { aviso("No se pudo guardar", r.message, "error", 5000); return; }
            cerrarModal("modalMesa");
            avisoGrande(d.id ? "Mesa actualizada" : "Mesa creada", r.message, "success");
            cargar();
        });
    }

    function eliminarMesa(id) {
        var m = estado.mesas.find(function (x) { return x.id === Number(id); });
        if (!m) return;

        Swal.fire({
            icon: "warning",
            title: "¿Eliminar " + escapar(m.nombre) + "?",
            html: "Selecciona cómo eliminarla:<br><span style='font-size:12.5px;color:#8a8a8f'>Dar de baja la desactiva; borrar definitivo no se podrá si tiene pedidos.</span>",
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
            if (r.isConfirmed) ejecutarEliminarMesa(id, false);
            else if (r.isDenied) ejecutarEliminarMesa(id, true);
        });
    }

    function ejecutarEliminarMesa(id, definitivo) {
        window.MesasAPI.mesaEliminar(id, definitivo).then(function (r) {
            if (!r.success) { aviso("No se pudo eliminar", r.message, "error", 5500); return; }
            avisoGrande(definitivo ? "Eliminada" : "Desactivada", r.message, "success");
            cargar();
        });
    }

    // =========================================================
    // IMAGEN MESA
    // =========================================================
    function engancharImagen() {
        var btn = $s("#mesaImgElegir");
        var input = $s("#mesaImgFile");

        if (btn && input) {
            btn.addEventListener("click", function () { input.click(); });

            input.addEventListener("change", function () {
                var file = input.files && input.files[0];
                if (!file) return;

                if (file.size > 3 * 1024 * 1024) {
                    aviso("Imagen muy grande", "Máximo 3 MB", "warning");
                    input.value = "";
                    return;
                }

                var tipos = ["image/jpeg", "image/png", "image/webp"];
                if (tipos.indexOf(file.type) === -1) {
                    aviso("Formato no válido", "Usa JPG, PNG o WEBP", "warning");
                    input.value = "";
                    return;
                }

                var idMesa = Number($s("#mesa_id").value || "0") || 0;

                if (idMesa <= 0) {
                    // Mesa nueva: guardamos el archivo en memoria y subimos al crear
                    var previewUrl = URL.createObjectURL(file);
                    pintarPreviewImagen(previewUrl);
                    input.dataset.pending = "1";
                    return;
                }

                // Mesa existente: subimos al instante
                var url = URL.createObjectURL(file);
                pintarPreviewImagen(url);

                window.MesasAPI.mesaImagen(idMesa, file).then(function (r) {
                    input.value = "";

                    if (!r.success) {
                        aviso("No se pudo subir", r.message, "error", 5000);
                        pintarPreviewImagen(estado.mesas.find(function (x) { return x.id === idMesa; })?.imagen_url || "");
                        return;
                    }

                    $s("#mesa_imagen").value = r.data.ruta;
                    pintarPreviewImagen(r.data.url);
                    aviso("Listo", "Imagen actualizada", "success");
                });
            });
        }

        var quitar = $s("#mesaImgQuitar");
        if (quitar) {
            quitar.addEventListener("click", function () {
                $s("#mesa_imagen").value = "";
                pintarPreviewImagen("");

                var idMesa = Number($s("#mesa_id").value || "0") || 0;
                if (idMesa > 0) {
                    window.MesasAPI.mesaImagenQuitar(idMesa).then(function (r) {
                        if (r.success) aviso("Listo", "Imagen eliminada", "success");
                    });
                }
            });
        }
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        // Botones cabecera
        var btnPisos = $s("#btnPisos");
        if (btnPisos) btnPisos.addEventListener("click", function () {
            Swal.fire({
                icon: "question",
                title: "Pisos",
                html: "¿Qué deseas hacer?",
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: "Nuevo piso",
                denyButtonText: "Administrar pisos",
                cancelButtonText: "Cerrar",
                confirmButtonColor: "#d9232d",
                denyButtonColor: "#1f6fd0",
                cancelButtonColor: "#9aa0a6",
                reverseButtons: true,
            }).then(function (r) {
                if (r.isConfirmed) nuevoPiso();
                else if (r.isDenied) {
                    // Scroll al bloque de pisos
                    var bloque = $s(".mes-pisos-card");
                    if (bloque) bloque.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            });
        });

        var btnNueva = $s("#btnNuevaMesa");
        if (btnNueva) btnNueva.addEventListener("click", nuevaMesa);

        // Filtros
        ["mfSucursal", "mfPiso", "mfEstado"].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener("change", function () {
                estado.filtros.id_sucursal = $s("#mfSucursal").value;
                estado.filtros.id_piso = $s("#mfPiso").value;
                estado.filtros.estado_mesa = $s("#mfEstado").value;
                cargar();
            });
        });

        var btnLimpiar = $s("#mfLimpiar");
        if (btnLimpiar) {
            btnLimpiar.addEventListener("click", function () {
                ["mfSucursal", "mfPiso", "mfEstado"].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = "";
                });
                estado.filtros = { id_sucursal: "", id_piso: "", estado_mesa: "" };
                cargar();
            });
        }

        // Cambio de sucursal en modal mesa
        var selSucMesa = $s("#mesa_sucursal");
        if (selSucMesa) {
            selSucMesa.addEventListener("change", function () {
                actualizarPisosPorSucursal(selSucMesa.value);
            });
        }

        // Formularios
        var fPiso = $s("#formPiso");
        if (fPiso) fPiso.addEventListener("submit", guardarPiso);

        var fMesa = $s("#formMesa");
        if (fMesa) fMesa.addEventListener("submit", guardarMesa);

        // Botones cerrar
        document.querySelectorAll("[data-cerrar]").forEach(function (b) {
            b.addEventListener("click", function () {
                var tipo = b.getAttribute("data-cerrar");
                cerrarModal("modal" + tipo.charAt(0).toUpperCase() + tipo.slice(1));
            });
        });

        // Delegación: chips de pisos + grid de mesas
        document.body.addEventListener("click", function (ev) {
            var b = ev.target.closest("[data-piso-edit],[data-piso-del],[data-mesa-edit],[data-mesa-del]");
            if (!b) return;

            if (b.hasAttribute("data-piso-edit")) editarPiso(b.getAttribute("data-piso-edit"));
            else if (b.hasAttribute("data-piso-del")) eliminarPiso(b.getAttribute("data-piso-del"));
            else if (b.hasAttribute("data-mesa-edit")) editarMesa(b.getAttribute("data-mesa-edit"));
            else if (b.hasAttribute("data-mesa-del")) eliminarMesa(b.getAttribute("data-mesa-del"));
        });

        // Imagen
        engancharImagen();

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