(function (global) {
    "use strict";

    var RUTAS = {
        obtener: "../modelos/mesas/obtener_mesas.php",
        api:     "../modelos/mesas/mesas_api.php",
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

    global.MesasAPI = {
        cargar:          function (filtros) { return llamar(RUTAS.obtener + qs(filtros), { method: "GET" }); },

        pisoGuardar:     function (d) { return accion("piso_guardar", d); },
        pisoEliminar:    function (id, definitivo) { return accion("piso_eliminar", { id: id, definitivo: !!definitivo }); },

        mesaGuardar:     function (d) { return accion("mesa_guardar", d); },
        mesaEliminar:    function (id, definitivo) { return accion("mesa_eliminar", { id: id, definitivo: !!definitivo }); },

        mesaImagen: function (idMesa, file) {
            var fd = new FormData();
            fd.append("accion", "mesa_imagen");
            fd.append("id_mesa", idMesa);
            fd.append("archivo", file);
            return llamar(RUTAS.api, { method: "POST", body: fd });
        },

        mesaImagenQuitar: function (idMesa) {
            return accion("mesa_imagen_quitar", { id_mesa: idMesa });
        },
    };
})(window);