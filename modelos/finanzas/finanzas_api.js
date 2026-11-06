/**
 * =====================================================
 * modelos/finanzas/finanzas_api.js
 * =====================================================
 */
(function (global) {
    "use strict";

    var RUTAS = {
        obtener: "../modelos/finanzas/obtener_finanzas.php",
        api:     "../modelos/finanzas/finanzas_api.php",
    };

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = (meta && meta.content) || "";

    function llamar(ruta, opciones) {
        opciones = opciones || {};
        var control = new AbortController();
        var reloj = setTimeout(function () { control.abort(); }, 15000);

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

    global.FinanzasAPI = {
        cargar:          function () { return llamar(RUTAS.obtener, { method: "GET" }); },

        monedaGuardar:   function (d) { return accion("moneda_guardar", d); },
        monedaEliminar:  function (id, definitivo) { return accion("moneda_eliminar", { id: id, definitivo: !!definitivo }); },

        medioGuardar:    function (d) { return accion("medio_guardar", d); },
        medioEliminar:   function (id, definitivo) { return accion("medio_eliminar", { id: id, definitivo: !!definitivo }); },

        bancoGuardar:    function (d) { return accion("banco_guardar", d); },
        bancoEliminar:   function (id, definitivo) { return accion("banco_eliminar", { id: id, definitivo: !!definitivo }); },
    };
})(window);