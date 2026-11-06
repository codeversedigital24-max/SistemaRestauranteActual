/**
 * =====================================================
 * modelos/configuracion/configuracion_api.js
 * Capa de red del módulo de configuración.
 * =====================================================
 */
(function (global) {
    "use strict";

    var RUTAS = {
        obtener: "../modelos/configuracion/obtener_configuracion.php",
        api:     "../modelos/configuracion/configuracion_api.php",
    };

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = (meta && meta.content) || "";

    function llamar(ruta, opciones) {
        opciones = opciones || {};
        var control = new AbortController();
        var reloj = setTimeout(function () { control.abort(); }, 20000);

        var cabeceras = Object.assign(
            { Accept: "application/json" },
            opciones.headers || {},
            csrf ? { "X-CSRF-Token": csrf } : {}
        );

        return fetch(ruta, Object.assign({ credentials: "same-origin" }, opciones, {
            headers: cabeceras,
            signal: control.signal,
        }))
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (j) {
                    if (!j) return { success:false, message:"Respuesta inválida", data:{} };
                    return Object.assign({}, j, { status: r.status });
                });
            })
            .catch(function (e) {
                var t = e && e.name === "AbortError";
                return { success:false, data:{}, message: t ? "El servidor tardó demasiado" : "Sin conexión con el servidor" };
            })
            .finally(function () { clearTimeout(reloj); });
    }

    function accion(tipo, datos) {
        return llamar(RUTAS.api, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ accion: tipo, datos: datos || {} }),
        });
    }

    function subirImagen(campo, file) {
        var fd = new FormData();
        fd.append("accion", "subir_imagen");
        fd.append("campo", campo);
        fd.append("archivo", file);

        return llamar(RUTAS.api, {
            method: "POST",
            body: fd,   // sin Content-Type → el navegador pone el boundary
        });
    }

    global.ConfiguracionAPI = {
        cargar:        function () { return llamar(RUTAS.obtener, { method: "GET" }); },
        guardar:       function (datos) { return accion("guardar", datos); },
        subirImagen:   subirImagen,
    };
})(window);