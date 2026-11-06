(function (global) {
    "use strict";

    var RUTAS = {
        listar:  "../modelos/comprasxfechas/obtener_compras_reporte.php",
        detalle: "../modelos/comprasxfechas/reporte_pdf.php",  // se usa en ventana nueva
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

    global.ComprasReporteAPI = {
        cargar: function (filtros) {
            return llamar(RUTAS.listar + qs(filtros), { method: "GET" });
        },
        detallePdf: function (filtros) {
            return RUTAS.detalle + qs(filtros);
        },
    };
})(window);