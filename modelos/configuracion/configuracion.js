/**
 * =====================================================
 * modelos/configuracion/configuracion.js
 * -----------------------------------------------------
 * UI del formulario único de configuración:
 *   · Precarga la fila
 *   · Previsualiza logos / favicon / banner
 *   · Sube imagen al elegir archivo (inmediato)
 *   · Guarda todo con un solo submit
 * =====================================================
 */
(function () {
    "use strict";

    var $s = function (sel, ctxo) { return (ctxo || document).querySelector(sel); };

    var estado = {
        config: null,
        guardando: false,
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

    // =========================================================
    // CARGA
    // =========================================================
    function cargar() {
        if (!window.ConfiguracionAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);

        window.ConfiguracionAPI.cargar().then(function (rpta) {
            mostrarCarga(false);

            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.config = rpta.data.configuracion || {};
            llenarFormulario(estado.config);
            $s("#formConfiguracion").style.display = "";
        });
    }

    function mostrarCarga(activar) {
        var c = $s("#cfgCarga");
        if (c) c.classList.toggle("visible", Boolean(activar));
    }

    function llenarFormulario(c) {
        var setVal = function (id, valor) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = valor == null ? "" : valor;
        };

        setVal("cfg_id", c.id_config || "");
        setVal("cfg_razon_social", c.razon_social);
        setVal("cfg_nombre_comercial", c.nombre_comercial);
        setVal("cfg_nit", c.nit);
        setVal("cfg_regimen", c.regimen);
        setVal("cfg_actividad_economica", c.actividad_economica);
        setVal("cfg_telefono1", c.telefono1);
        setVal("cfg_telefono2", c.telefono2);
        setVal("cfg_email_principal", c.email_principal);
        setVal("cfg_email_secundario", c.email_secundario);
        setVal("cfg_sitio_web", c.sitio_web);
        setVal("cfg_pais", c.pais);
        setVal("cfg_departamento", c.departamento);
        setVal("cfg_provincia", c.provincia);
        setVal("cfg_ciudad", c.ciudad);
        setVal("cfg_zona", c.zona);
        setVal("cfg_direccion", c.direccion);
        setVal("cfg_codigo_postal", c.codigo_postal);
        setVal("cfg_autorizacion_factura", c.autorizacion_factura);
        setVal("cfg_numero_autorizacion", c.numero_autorizacion);
        setVal("cfg_fecha_autorizacion", c.fecha_autorizacion ? String(c.fecha_autorizacion).slice(0, 10) : "");
        setVal("cfg_llave_dosificacion", c.llave_dosificacion);
        setVal("cfg_numero_sucursal", c.numero_sucursal);
        setVal("cfg_codigo_actividad", c.codigo_actividad);
        setVal("cfg_matricula_comercio", c.matricula_comercio);
        setVal("cfg_certificado_digital", c.certificado_digital);
        setVal("cfg_facebook", c.facebook);
        setVal("cfg_instagram", c.instagram);
        setVal("cfg_twitter", c.twitter);
        setVal("cfg_youtube", c.youtube);
        setVal("cfg_linkedin", c.linkedin);
        setVal("cfg_tiktok", c.tiktok);
        setVal("cfg_whatsapp", c.whatsapp);
        setVal("cfg_moneda_principal", c.moneda_principal);
        setVal("cfg_zona_horaria", c.zona_horaria);
        setVal("cfg_formato_fecha", c.formato_fecha);
        setVal("cfg_formato_hora", c.formato_hora);
        setVal("cfg_estado", String(c.estado ?? 1));

        // Logos
        ["logo", "logo_favicon", "logo_blanco", "banner"].forEach(function (k) {
            var ruta = c[k] || "";
            var url  = c[k + "_url"] || "";
            setVal("cfg_" + k, ruta);
            pintarPreview(k, url);
        });

        actualizarIdentidad(c);
    }

    function pintarPreview(campo, url) {
        var caja = document.getElementById("prev_" + campo);
        if (!caja) return;

        if (url) {
            caja.innerHTML = '<img src="' + escapar(url) + '" alt="' + campo + '">';
        } else {
            caja.innerHTML = '<i class="fa-solid fa-image"></i>';
        }
    }

    // =========================================================
    // IDENTIDAD DE LA EMPRESA (tarjeta superior)
    // =========================================================
    function fechaBonita(valor) {
        if (!valor) return "";
        var f = new Date(String(valor).replace(" ", "T"));
        if (isNaN(f.getTime())) return "";

        var fecha = f.toLocaleDateString("es-BO", { day: "2-digit", month: "2-digit", year: "numeric" });
        var hora = f.toLocaleTimeString("es-BO", { hour: "2-digit", minute: "2-digit" });
        return fecha + " · " + hora;
    }

    function actualizarIdentidad(c) {
        c = c || {};

        var nombre = String(c.nombre_comercial || c.razon_social || "").trim();
        var razon = String(c.razon_social || "").trim();
        var nit = String(c.nit || "").trim();
        var activo = Number(c.estado) !== 0;

        var logo = $s("#cfgIdLogo");
        if (logo) {
            var url = String(c.logo_url || "");
            logo.innerHTML = url
                ? '<img src="' + escapar(url) + '" alt="Logo">'
                : '<i class="fa-solid fa-building"></i>';
        }

        var nom = $s("#cfgIdNombre");
        if (nom) nom.textContent = nombre || "Sin nombre configurado";

        var meta = nit !== "" ? "NIT: " + nit : "Sin NIT";
        if (razon !== "" && razon !== nombre) {
            meta += " · " + razon;
        }

        var met = $s("#cfgIdMeta");
        if (met) met.textContent = meta;

        var badge = $s("#cfgIdEstado");
        if (badge) {
            badge.textContent = activo ? "Activo" : "Inactivo";
            badge.classList.toggle("inactivo", !activo);
        }

        var nota = $s("#cfgNota");
        if (nota) {
            var actualizado = fechaBonita(c.updated_at);
            nota.textContent = actualizado !== ""
                ? "Última actualización: " + actualizado
                : "Los cambios se guardan todos juntos.";
        }
    }

    function identidadDesdeFormulario() {
        var v = function (id) {
            var el = document.getElementById(id);
            return el ? el.value : "";
        };

        actualizarIdentidad({
            razon_social: v("cfg_razon_social"),
            nombre_comercial: v("cfg_nombre_comercial"),
            nit: v("cfg_nit"),
            estado: Number(v("cfg_estado") || 1),
            logo_url: estado.config ? estado.config.logo_url : "",
            updated_at: estado.config ? estado.config.updated_at : "",
        });
    }

    // =========================================================
    // SUBIR IMAGEN (inmediato al elegir)
    // =========================================================
    function engancharLogos() {
        document.querySelectorAll("[data-logo-pick]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var campo = btn.getAttribute("data-logo-pick");
                var input = document.getElementById("file_" + campo);
                if (input) input.click();
            });
        });

        document.querySelectorAll("[data-logo-clear]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var campo = btn.getAttribute("data-logo-clear");
                Swal.fire({
                    icon: "warning",
                    title: "¿Quitar la imagen?",
                    showCancelButton: true,
                    confirmButtonText: "Sí, quitar",
                    cancelButtonText: "Cancelar",
                    confirmButtonColor: "#d9232d",
                    cancelButtonColor: "#9aa0a6",
                    reverseButtons: true,
                }).then(function (r) {
                    if (!r.isConfirmed) return;

                    // Subimos un archivo vacío para forzar el borrado
                    var fake = new File([new Blob([" "], { type: "text/plain" })], "clear.txt");

                    // En su lugar: marcamos el campo oculto como vacío y dejamos que se guarde
                    var hidden = document.getElementById("cfg_" + campo);
                    if (hidden) hidden.value = "";
                    pintarPreview(campo, "");

                    if (campo === "logo" && estado.config) {
                        estado.config.logo = "";
                        estado.config.logo_url = "";
                        actualizarIdentidad(estado.config);
                    }
                    // el borrado físico se hará al guardar la configuración con el campo vacío
                });
            });
        });

        ["logo", "logo_favicon", "logo_blanco", "banner"].forEach(function (campo) {
            var input = document.getElementById("file_" + campo);
            if (!input) return;

            input.addEventListener("change", function () {
                var file = input.files && input.files[0];
                if (!file) return;

                if (file.size > 3 * 1024 * 1024) {
                    aviso("Imagen muy grande", "El máximo es 3 MB", "warning");
                    input.value = "";
                    return;
                }

                var tipos = [
                    "image/jpeg","image/png","image/webp","image/svg+xml",
                    "image/x-icon","image/vnd.microsoft.icon",
                ];

                if (tipos.indexOf(file.type) === -1) {
                    aviso("Formato no válido", "Usa JPG, PNG, WEBP, SVG o ICO", "warning");
                    input.value = "";
                    return;
                }

                // Preview local mientras sube
                var blobUrl = URL.createObjectURL(file);
                pintarPreview(campo, blobUrl);

                window.ConfiguracionAPI.subirImagen(campo, file).then(function (rpta) {
                    input.value = "";

                    if (!rpta.success) {
                        aviso("No se pudo subir", rpta.message || "Intenta de nuevo", "error", 5000);
                        pintarPreview(campo, estado.config[campo + "_url"] || "");
                        return;
                    }

                    var url = rpta.data.url || "";
                    var ruta = rpta.data.ruta || "";

                    var hidden = document.getElementById("cfg_" + campo);
                    if (hidden) hidden.value = ruta;

                    if (estado.config) {
                        estado.config[campo] = ruta;
                        estado.config[campo + "_url"] = url;
                    }

                    if (campo === "logo") {
                        actualizarIdentidad(estado.config || {});
                    }

                    pintarPreview(campo, url);
                    aviso("Listo", "Imagen actualizada", "success");
                });
            });
        });
    }

    // =========================================================
    // GUARDAR TODO
    // =========================================================
    function leerFormulario() {
        var v = function (id) {
            var el = document.getElementById(id);
            return el ? el.value : "";
        };

        return {
            razon_social:        v("cfg_razon_social").trim(),
            nombre_comercial:    v("cfg_nombre_comercial").trim(),
            nit:                 v("cfg_nit").trim(),
            regimen:             v("cfg_regimen").trim(),
            actividad_economica: v("cfg_actividad_economica").trim(),
            telefono1:           v("cfg_telefono1").trim(),
            telefono2:           v("cfg_telefono2").trim(),
            email_principal:     v("cfg_email_principal").trim(),
            email_secundario:    v("cfg_email_secundario").trim(),
            sitio_web:           v("cfg_sitio_web").trim(),
            pais:                v("cfg_pais").trim(),
            departamento:        v("cfg_departamento").trim(),
            provincia:           v("cfg_provincia").trim(),
            ciudad:              v("cfg_ciudad").trim(),
            zona:                v("cfg_zona").trim(),
            direccion:           v("cfg_direccion").trim(),
            codigo_postal:       v("cfg_codigo_postal").trim(),
            logo:                v("cfg_logo"),
            logo_favicon:        v("cfg_logo_favicon"),
            logo_blanco:         v("cfg_logo_blanco"),
            banner:              v("cfg_banner"),
            autorizacion_factura: v("cfg_autorizacion_factura").trim(),
            numero_autorizacion: v("cfg_numero_autorizacion").trim(),
            fecha_autorizacion:  v("cfg_fecha_autorizacion"),
            llave_dosificacion:  v("cfg_llave_dosificacion").trim(),
            numero_sucursal:     v("cfg_numero_sucursal").trim(),
            codigo_actividad:    v("cfg_codigo_actividad").trim(),
            matricula_comercio:  v("cfg_matricula_comercio").trim(),
            certificado_digital: v("cfg_certificado_digital").trim(),
            facebook:            v("cfg_facebook").trim(),
            instagram:           v("cfg_instagram").trim(),
            twitter:             v("cfg_twitter").trim(),
            youtube:             v("cfg_youtube").trim(),
            linkedin:            v("cfg_linkedin").trim(),
            tiktok:              v("cfg_tiktok").trim(),
            whatsapp:            v("cfg_whatsapp").trim(),
            moneda_principal:    v("cfg_moneda_principal"),
            zona_horaria:        v("cfg_zona_horaria").trim(),
            formato_fecha:       v("cfg_formato_fecha"),
            formato_hora:        v("cfg_formato_hora"),
            estado:              Number(v("cfg_estado") || 1),
        };
    }

    function validar(d) {
        var fallos = [];

        if (d.razon_social.length < 3) fallos.push("razón social");
        if (d.email_principal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email_principal)) fallos.push("correo principal");
        if (d.email_secundario && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email_secundario)) fallos.push("correo secundario");

        return fallos;
    }

    function guardar(ev) {
        ev.preventDefault();
        if (estado.guardando) return;

        var datos = leerFormulario();
        var fallos = validar(datos);

        if (fallos.length) {
            aviso("Revisa el formulario", "Faltan datos: " + fallos.join(", "), "warning");
            return;
        }

        estado.guardando = true;

        var btn = $s("#cfgGuardar");
        if (btn) {
            btn.disabled = true;
            if (!btn.dataset.html) btn.dataset.html = btn.innerHTML;
            btn.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';
        }

        window.ConfiguracionAPI.guardar(datos).then(function (rpta) {
            estado.guardando = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.html;
            }

            if (!rpta.success) {
                aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5500);
                return;
            }

            avisoGrande("Configuración guardada", rpta.message || "Los cambios se aplicaron", "success");
            cargar();
        });
    }

    // =========================================================
    // DESCARTAR
    // =========================================================
    function descartar() {
        Swal.fire({
            icon: "warning",
            title: "¿Descartar cambios?",
            text: "Se volverá a cargar la configuración guardada.",
            showCancelButton: true,
            confirmButtonText: "Sí, descartar",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#d9232d",
            cancelButtonColor: "#9aa0a6",
            reverseButtons: true,
        }).then(function (r) {
            if (r.isConfirmed) cargar();
        });
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        var form = $s("#formConfiguracion");
        if (form) form.addEventListener("submit", guardar);

        var btnReset = $s("#cfgReset");
        if (btnReset) btnReset.addEventListener("click", descartar);

        engancharLogos();

        ["cfg_razon_social", "cfg_nombre_comercial", "cfg_nit", "cfg_estado"].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(el.tagName === "SELECT" ? "change" : "input", identidadDesdeFormulario);
        });
    });
})();