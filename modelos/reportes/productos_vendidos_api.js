(function (global) {
    "use strict";

    var RUTAS = {
        listar: "../modelos/reportes/obtener_productos_vendidos.php",
        pdf:    "../modelos/reportes/reporte_productos_vendidos_pdf.php",
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
            headers: cabeceras, signal: control.signal,
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

    global.ProductosVendidosAPI = {
        cargar: function (filtros) { return llamar(RUTAS.listar + qs(filtros), { method: "GET" }); },
        pdfUrl: function (filtros) { return RUTAS.pdf + qs(filtros); },
    };
})(window);