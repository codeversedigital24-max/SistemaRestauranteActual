(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $todos = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    var estado = {
        perfil: null,
        fotoBlob: null,
        fotoQuitada: false,
    };

    // =========================================================
    // UTILS
    // =========================================================
    function escapar(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }
    function aviso(t, m, tipo, time) {
        if (!window.Swal) return console.warn(t + ": " + m);
        Swal.fire({
            toast: true, position: "top-end", icon: tipo || "info",
            title: t, text: m, timer: time || 3000,
            timerProgressBar: true, showConfirmButton: false,
        });
    }
    function avisoGrande(t, m, tipo) {
        if (!window.Swal) return;
        return Swal.fire({
            icon: tipo || "success", title: t, text: m,
            confirmButtonText: "Entendido", confirmButtonColor: "#d9232d",
        });
    }
    function mostrarCarga(a) {
        var c = $s("#perfCarga");
        var cont = $s("#perfContenido");
        if (c) c.classList.toggle("visible", !!a);
        if (cont) cont.style.display = a ? "none" : "";
    }

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
        if (!document.querySelector(".modal-overlay.abierto")) {
            document.body.style.overflow = "";
        }
    }

    // =========================================================
    // CARGA
    // =========================================================
    function cargar() {
        if (!window.PerfilAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);
        window.PerfilAPI.cargar().then(function (rpta) {
            mostrarCarga(false);
            if (!rpta.success || !rpta.data.perfil) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.perfil = rpta.data.perfil;
            llenarFormulario(estado.perfil);
            pintarLateral(estado.perfil);
        });
    }

    function llenarFormulario(p) {
        var set = function (id, v) {
            var el = document.getElementById(id);
            if (el) el.value = v == null ? "" : v;
        };

        set("p_nombres", p.nombres);
        set("p_apellidos", p.apellidos);
        set("p_cedula", p.cedula);
        set("p_fecha_nacimiento", p.fecha_nacimiento ? String(p.fecha_nacimiento).slice(0, 10) : "");
        set("p_genero", p.genero);
        set("p_telefono", p.telefono);
        set("p_telefono_fijo", p.telefono_fijo);
        set("p_direccion", p.direccion);
        set("p_username", p.username);
        set("p_email", p.email);
        set("p_password_actual", "");
        set("p_password", "");
        set("p_password2", "");
    }

    function pintarLateral(p) {
        var nombre = ((p.nombres || "") + " " + (p.apellidos || "")).trim() || p.username;
        $s("#perfNombre").textContent = nombre;
        $s("#perfRol").textContent = p.rol || "—";
        $s("#perfSucursal").textContent = p.sucursal ? p.sucursal + (p.ciudad ? " · " + p.ciudad : "") : "—";
        $s("#perfUsername").textContent = p.username || "—";
        $s("#perfEmail").textContent = p.email || "—";
        $s("#perfUltimoLogin").textContent = p.ultimo_login || "Nunca";
        pintarAvatar(p.foto_public || "");
    }

    function pintarAvatar(url) {
        var caja = $s("#perfAvatar");
        if (!caja) return;
        var boton = $s("#perfAbrirFoto");
        caja.innerHTML = "";
        if (url) {
            var img = document.createElement("img");
            img.src = url;
            img.alt = "Foto de perfil";
            caja.appendChild(img);
        } else {
            caja.innerHTML = '<i class="fas fa-user"></i>';
        }
        if (boton) caja.appendChild(boton);
    }

    // =========================================================
    // GUARDAR
    // =========================================================
    function leerFormulario() {
        var v = function (id) {
            var el = document.getElementById(id);
            return el ? el.value.trim() : "";
        };

        return {
            nombres:          v("p_nombres"),
            apellidos:        v("p_apellidos"),
            cedula:           v("p_cedula"),
            fecha_nacimiento: v("p_fecha_nacimiento"),
            genero:           v("p_genero"),
            telefono:         v("p_telefono"),
            telefono_fijo:    v("p_telefono_fijo"),
            direccion:        v("p_direccion"),
            username:         v("p_username"),
            email:            v("p_email"),
            password_actual:  document.getElementById("p_password_actual").value || "",
            password:         document.getElementById("p_password").value || "",
            password2:        document.getElementById("p_password2").value || "",
        };
    }

    function guardar(ev) {
        ev.preventDefault();
        if (!window.PerfilAPI) return;

        var datos = leerFormulario();
        var fallos = [];

        if (datos.nombres.length < 2) fallos.push("nombres");
        if (datos.apellidos.length < 2) fallos.push("apellidos");
        if (datos.username.length < 3 || datos.username.length > 50 || /\s/.test(datos.username)) fallos.push("usuario");
        if (!/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(datos.email)) fallos.push("correo");

        if (datos.password !== "" || datos.password2 !== "") {
            if (datos.password.length < 8) fallos.push("contraseña (mín. 8)");
            if (datos.password !== datos.password2) fallos.push("confirmación de contraseña");
            if (datos.password_actual === "") fallos.push("contraseña actual");
        }

        if (fallos.length) {
            return aviso("Revisa el formulario", "Revisa: " + fallos.join(", "), "warning");
        }

        var btn = $s("#perfGuardar");
        btn.disabled = true;
        if (!btn.dataset.html) btn.dataset.html = btn.innerHTML;
        btn.innerHTML = '<span class="mini-spin"></span> Guardando…';

        window.PerfilAPI.actualizar(datos).then(function (rpta) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.html;

            if (!rpta.success) {
                aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5000);
                return;
            }

            avisoGrande("Perfil actualizado", rpta.message || "Los cambios se guardaron", "success");

            if (rpta.data && rpta.data.perfil) {
                estado.perfil = rpta.data.perfil;
                pintarLateral(estado.perfil);
            }
            cargar();
        });
    }

    // =========================================================
    // FOTO
    // =========================================================
    function abrirModalFoto() {
        estado.fotoBlob = null;
        estado.fotoQuitada = false;
        pintarPreviewModal(estado.perfil ? estado.perfil.foto_public || "" : "");
        abrirModal("modalFoto");
    }

    function pintarPreviewModal(url) {
        var caja = $s("#fotoPreviewModal");
        if (!caja) return;
        if (url) {
            caja.innerHTML = '<img src="' + escapar(url) + '" alt="">';
            caja.classList.add("con-foto");
        } else {
            caja.innerHTML = '<i class="fas fa-user"></i>';
            caja.classList.remove("con-foto");
        }
    }

    function engancharFoto() {
        var input = $s("#fotoInput");
        var btnElegir = $s("#fotoElegir");
        var btnQuitar = $s("#fotoQuitar");

        if (btnElegir) {
            btnElegir.addEventListener("click", function () {
                input.click();
            });
        }

        if (input) {
            input.addEventListener("change", function () {
                var file = input.files && input.files[0];
                if (!file) return;

                var tipos = ["image/jpeg", "image/png", "image/webp"];
                if (tipos.indexOf(file.type) === -1) {
                    aviso("Formato no válido", "Usa JPG, PNG o WEBP", "warning");
                    input.value = "";
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    aviso("Imagen muy grande", "El máximo es 2 MB", "warning");
                    input.value = "";
                    return;
                }

                var preview = URL.createObjectURL(file);
                pintarPreviewModal(preview);

                var btn = $s("#fotoElegir");
                btn.disabled = true;
                if (!btn.dataset.html) btn.dataset.html = btn.innerHTML;
                btn.innerHTML = '<span class="mini-spin"></span> Subiendo…';

                window.PerfilAPI.subirFoto(file).then(function (rpta) {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.html;
                    input.value = "";

                    if (!rpta.success) {
                        aviso("No se pudo subir", rpta.message || "Intenta de nuevo", "error", 5000);
                        pintarPreviewModal(estado.perfil ? estado.perfil.foto_public || "" : "");
                        return;
                    }

                    if (rpta.data && rpta.data.perfil) {
                        estado.perfil = rpta.data.perfil;
                        pintarAvatar(rpta.data.url || "");
                        pintarLateral(estado.perfil);
                    }

                    aviso("Foto actualizada", "Tu foto de perfil cambió", "success");
                    cerrarModal("modalFoto");
                });
            });
        }

        if (btnQuitar) {
            btnQuitar.addEventListener("click", function () {
                if (!estado.perfil || !estado.perfil.foto_public) {
                    return aviso("Sin foto", "Ya tienes el avatar por defecto", "info");
                }

                Swal.fire({
                    icon: "warning",
                    title: "¿Quitar la foto?",
                    showCancelButton: true,
                    confirmButtonText: "Sí, quitar",
                    cancelButtonText: "Cancelar",
                    confirmButtonColor: "#d9232d",
                    cancelButtonColor: "#9aa0a6",
                    reverseButtons: true,
                }).then(function (r) {
                    if (!r.isConfirmed) return;

                    window.PerfilAPI.quitarFoto().then(function (rpta) {
                        if (!rpta.success) {
                            aviso("No se pudo quitar", rpta.message, "error");
                            return;
                        }
                        if (rpta.data && rpta.data.perfil) {
                            estado.perfil = rpta.data.perfil;
                            pintarAvatar("");
                            pintarLateral(estado.perfil);
                        }
                        pintarPreviewModal("");
                        aviso("Foto eliminada", "Tu avatar quedó por defecto", "success");
                        cerrarModal("modalFoto");
                    });
                });
            });
        }
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();
        engancharFoto();

        var form = $s("#formPerfil");
        if (form) form.addEventListener("submit", guardar);

        var btnReset = $s("#perfReset");
        if (btnReset) {
            btnReset.addEventListener("click", function () {
                if (!estado.perfil) return;
                llenarFormulario(estado.perfil);
                aviso("Cambios descartados", "", "info", 2000);
            });
        }

        var btnFoto = $s("#perfAbrirFoto");
        if (btnFoto) btnFoto.addEventListener("click", abrirModalFoto);

        var cerrar = $s("#modalFotoCerrar");
        if (cerrar) cerrar.addEventListener("click", function () { cerrarModal("modalFoto"); });

        var modalFoto = $s("#modalFoto");
        if (modalFoto) modalFoto.addEventListener("click", function (e) {
            if (e.target === modalFoto) cerrarModal("modalFoto");
        });

        $todos(".ojo").forEach(function (boton) {
            boton.addEventListener("click", function () {
                var input = document.getElementById(boton.getAttribute("data-ojo"));
                if (!input) return;
                var visible = input.type === "text";
                input.type = visible ? "password" : "text";
                boton.innerHTML = visible
                    ? '<i class="fas fa-eye"></i>'
                    : '<i class="fas fa-eye-slash"></i>';
            });
        });

        document.addEventListener("keydown", function (ev) {
            if (ev.key !== "Escape") return;
            var abierto = document.querySelector(".modal-overlay.abierto");
            if (abierto) cerrarModal(abierto.id);
        });
    });
})();