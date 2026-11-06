/**
 * =====================================================
 * modelos/cajas/cajas_api.js
 * -----------------------------------------------------
 * Capa de red del módulo de cajas (fetch puro).
 * Siempre devuelve: { success, message, data, status }
 * Se carga con <defer> antes que cajas.js.
 * =====================================================
 */
(function (global) {
  "use strict";

  var RUTAS = {
    obtener: "../modelos/cajas/obtener_cajas.php",
    api: "../modelos/cajas/cajas_api.php",
  };

  var ESPERA_MS = 15000;

  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = (meta && meta.content) || "";

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
      signal: control.signal,
    }))
      .then(function (respuesta) {
        return respuesta
          .json()
          .catch(function () {
            return null;
          })
          .then(function (json) {
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
            : "Sin conexión con el servidor",
        };
      })
      .finally(function () {
        clearTimeout(reloj);
      });
  }

  function accion(tipo, datos) {
    return llamar(RUTAS.api, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ accion: tipo, datos: datos || {} }),
    });
  }

  function listar() {
    return llamar(RUTAS.obtener, { method: "GET" });
  }

  function obtener(id) {
    return accion("obtener", { id: id });
  }

  function crear(datos) {
    return accion("crear", datos);
  }

  function actualizar(datos) {
    return accion("actualizar", datos);
  }

  function eliminar(id) {
    return accion("eliminar", { id: id });
  }

  function asignar(id, idUsuario, observacion) {
    return accion("asignar", {
      id: id,
      id_usuario: idUsuario,
      observacion: observacion || "",
    });
  }

  function liberar(id) {
    return accion("liberar", { id: id });
  }

  function usuarios(idSucursal, idCaja) {
    return accion("usuarios", {
      id_sucursal: idSucursal,
      id_caja: idCaja || 0,
    });
  }

  function historial(id) {
    return accion("historial", { id: id });
  }

  global.CajasAPI = {
    listar: listar,
    obtener: obtener,
    crear: crear,
    actualizar: actualizar,
    eliminar: eliminar,
    asignar: asignar,
    liberar: liberar,
    usuarios: usuarios,
    historial: historial,
  };
})(window);
