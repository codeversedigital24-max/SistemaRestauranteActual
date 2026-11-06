/**
 * =====================================================
 * modelos/usuarios/usuarios_api.js
 * -----------------------------------------------------
 * Capa de red del módulo de usuarios (sólo fetch, sin
 * jQuery). Devuelve SIEMPRE el mismo objeto:
 *
 *    { success, message, data, status }
 *
 * Se carga con <defer> antes que usuarios.js.
 * =====================================================
 */
(function (global) {
    "use strict";

    // Relativas a la página (sistema/usuarios.php)
    var RUTAS = {
        obtener: "../modelos/usuarios/obtener_usuarios.php",
        api: "../modelos/usuarios/usuarios_api.php"
    };

    var ESPERA_MS = 15000;

    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = (meta && meta.content) || "";

    /**
     * Envoltorio común de fetch con timeout + parseo JSON seguro.
     */
    function llamar(ruta, opciones) {
        opciones = opciones || {};

        var control = new AbortController();
        var reloj = setTimeout(function () {
            control.abort();
        }, ESPERA_MS);

        var cabeceras = Object.assign(
            { Accept: "application/json" },
            opciones.headers || {},
            csrf ? { "X-CSRF-Token": csrf } : {}
        );

        return fetch(ruta, Object.assign({ credentials: "same-origin" }, opciones, {
            headers: cabeceras,
            signal: control.signal
        }))
            .then(function (respuesta) {
                return respuesta.json().catch(function () {
                    return null;
                }).then(function (json) {
                    if (!json) {
                        return { success: false, message: "Respuesta inválida del servidor", data: [] };
                    }
                    return Object.assign({}, json, { status: respuesta.status });
                });
            })
            .catch(function (error) {
                var tardado = error && error.name === "AbortError";
                return {
                    success: false,
                    data: [],
                    message: tardado
                        ? "El servidor tardó demasiado"
                        : "Sin conexión con el servidor"
                };
            })
            .finally(function () {
                clearTimeout(reloj);
            });
    }

 function accion(tipo, datos) {
    // Si ya es FormData (alta/edición con foto), va por multipart
    if (datos instanceof FormData) {
        if (!datos.has("accion")) {
            datos.append("accion", tipo);
        }
        return postForm(datos);
    }

    // Objeto plano → JSON de siempre
    return llamar(RUTAS.api, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ accion: tipo, datos: datos || {} })
    });
}

function postForm(fd) {
    return llamar(RUTAS.api, {
        method: "POST",
        body: fd
        // OJO: sin Content-Type manual (el navegador pone el boundary)
    });
}

/**
 * POST multipart: NO fija Content-Type (el navegador añade el boundary).
 * Mantiene las cabeceras de CSRF y Accept del helper `llamar`.
 */
function postForm(fd) {
    return llamar(RUTAS.api, {
        method: "POST",
        body: fd
        // headers: los añade `llamar()` (Accept + X-CSRF-Token). Sin Content-Type.
    });
}
    /**
     * Índice de usuarios (1 sola petición para la tabla entera).
     */
    function listar() {
        return llamar(RUTAS.obtener, { method: "GET" });
    }

    /**
     * Ficha completa de un usuario (para precargar el modal).
     */
    function obtener(id) {
        return accion("obtener", { id: id });
    }

    /**
     * Alta de usuario.
     * @param {Object} datos {nombres, apellidos, email, username, password, ...}
     */
    function crear(datos) {
    return accion("crear", datos);          // datos puede ser FormData u objeto
}

    /**
     * Modificación (agrega id a los datos).
     */
  function actualizar(datos) {
    return accion("actualizar", datos);
}

    /**
     * Baja definitiva (sólo si no tiene historial).
     */
    function eliminar(id) {
        return accion("eliminar", { id: id });
    }

    /**
     * Alta / baja lógica sin borrar historial.
     * @param {number} id
     * @param {number} estado 1 = activo, 0 = de baja
     */
    function cambiarEstado(id, estado) {
        return accion("cambiar_estado", { id: id, estado: Number(estado) === 1 ? 1 : 0 });
    }

    /**
     * Catálogo + permisos vigentes de un usuario.
     * @param {number} id
     */
    function permisos(id) {
        return accion("permisos", { id: id });
    }

    /**
     * Reemplaza la lista completa de permisos del usuario.
     * @param {number} id
     * @param {Array<{perm:number, sub:?number, item:?number}>} lista
     */
    function guardarPermisos(id, lista) {
        return accion("guardarPermisos", { id: id, permisos: lista });
    }

    global.UsuariosAPI = {
        listar: listar,
        obtener: obtener,
        crear: crear,
        actualizar: actualizar,
        eliminar: eliminar,
        cambiarEstado: cambiarEstado,
        permisos: permisos,
        guardarPermisos: guardarPermisos
    };
})(window);