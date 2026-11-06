/**
 * =====================================================
 * modelos/ventas/ventas_api.js
 * Capa de red del módulo de ventas (fetch puro).
 * =====================================================
 */
(function (global) {
    "use strict";

    var RUTAS = {
        listar: "../modelos/ventas/obtener_ventas.php",
        api:    "../modelos/ventas/ventas_api.php",
    };

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = (meta && meta.content) || "";

    function qs(params) {
        var p = [];
        Object.keys(params || {}).forEach(function (k) {
            var v = params[k];
            if (v === null || v === undefined || v === "") return;
            p.push(encodeURIComponent(k) + "=" + encodeURIComponent(v));
        });
        return p.length ? "?" + p.join("&") : "";
    }

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
                    if (!j) return { success: false, message: "Respuesta inválida", data: {} };
                    return Object.assign({}, j, { status: r.status });
                });
            })
            .catch(function (e) {
                var t = e && e.name === "AbortError";
                return {
                    success: false, data: {},
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

    global.VentasAPI = {
        listar:       function (filtros) { return llamar(RUTAS.listar + qs(filtros), { method: "GET" }); },
        ventaDetalle: function (id) { return accion("venta_detalle", { id: id }); },
        anularVenta:  function (id) { return accion("anular_venta", { id: id }); },
    };
})(window);