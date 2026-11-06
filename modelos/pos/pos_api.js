/**
 * =====================================================
 * modelos/pos/pos_api.js
 * Capa de red del POS (fetch puro).
 * =====================================================
 */
(function (global) {
    "use strict";

    var RUTAS = {
        obtener: "../modelos/pos/obtener_pos.php",
        api:     "../modelos/pos/pos_api.php",
        ticket:  "../modelos/pos/ticket_pdf.php",
    };

    var ESPERA_MS = 20000;

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = (meta && meta.content) || "";

    function qs(params) {
        var partes = [];
        Object.keys(params || {}).forEach(function (k) {
            var v = params[k];
            if (v === null || v === undefined || v === "") return;
            partes.push(encodeURIComponent(k) + "=" + encodeURIComponent(v));
        });
        return partes.length ? "?" + partes.join("&") : "";
    }

    function llamar(ruta, opciones) {
        opciones = opciones || {};
        var control = new AbortController();
        var reloj = setTimeout(function () { control.abort(); }, ESPERA_MS);

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
                return r.json().catch(function () { return null; })
                    .then(function (json) {
                        if (!json) {
                            return { success:false, message:"Respuesta inválida del servidor", data:{} };
                        }
                        return Object.assign({}, json, { status: r.status });
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

    global.PosAPI = {
        cargar: function (idSucursal) {
            return llamar(RUTAS.obtener + qs({ id_sucursal: idSucursal }), { method: "GET" });
        },
        registrarVenta: function (datos) {
            return accion("registrar_venta", datos);
        },
        obtenerVenta: function (id) {
            return accion("obtener_venta", { id: id });
        },
        anularVenta: function (id) {
            return accion("anular_venta", { id: id });
        },
        ticketUrl: function (id, descargar) {
            return RUTAS.ticket + qs({ id: id, descargar: descargar ? 1 : "" });
        },
    };
})(window);