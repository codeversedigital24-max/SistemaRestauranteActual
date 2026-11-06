(function (global) {
    "use strict";

    var RUTAS = {
        api: "../modelos/pos/promociones_api.php",
    };

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = (meta && meta.content) || "";

    function qs(p) {
        var r = [];
        Object.keys(p || {}).forEach(function (k) {
            var v = p[k];
            if (v === null || v === undefined || v === "") return;
            r.push(encodeURIComponent(k) + "=" + encodeURIComponent(v));
        });
        return r.length ? "?" + r.join("&") : "";
    }

    function llamar(url, opciones) {
        opciones = opciones || {};
        var control = new AbortController();
        var reloj = setTimeout(function () { control.abort(); }, 20000);

        var cabeceras = Object.assign(
            { Accept: "application/json" },
            opciones.headers || {},
            csrf ? { "X-CSRF-Token": csrf } : {}
        );

        return fetch(url, Object.assign({ credentials: "same-origin" }, opciones, {
            headers: cabeceras,
            signal: control.signal,
        }))
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (j) {
                    if (!j) return { success: false, message: "Respuesta inválida", data: {} };
                    return Object.assign({}, j, { status: r.status });
                });
            })
            .catch(function (e) {
                var t = e && e.name === "AbortError";
                return {
                    success: false,
                    data: {},
                    message: t ? "El servidor tardó demasiado" : "Sin conexión con el servidor",
                };
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

    global.PromocionesAPI = {
        listar:    function () { return llamar(RUTAS.api + qs({ accion: "listar" }), { method: "GET" }); },
        obtener:   function (id) { return llamar(RUTAS.api + qs({ accion: "obtener", id: id }), { method: "GET" }); },
        crear:     function (datos) { return accion("crear", datos); },
        actualizar: function (datos) { return accion("actualizar", datos); },
        cambiarEstado: function (id, activo) { return accion("cambiar_estado", { id: id, activo: activo }); },
        eliminar:  function (id) { return accion("eliminar", { id: id }); },
    };
})(window);
