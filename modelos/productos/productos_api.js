/**
 * =====================================================
 * modelos/productos/productos_api.js
 * -----------------------------------------------------
 * Capa de red del módulo de productos (fetch puro).
 * Siempre devuelve: { success, message, data, status }
 * Se carga con <defer> antes que productos.js.
 * =====================================================
 */
(function (global) {
  "use strict";

  var RUTAS = {
    obtener: "../modelos/productos/obtener_productos.php",
    api: "../modelos/productos/productos_api.php",
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
      csrf ? { "X-CSRF-Token": csrf } : {},
    );

    return fetch(
      ruta,
      Object.assign({ credentials: "same-origin" }, opciones, {
        headers: cabeceras,
        signal: control.signal,
      }),
    )
      .then(function (respuesta) {
        return respuesta
          .json()
          .catch(function () {
            return null;
          })
          .then(function (json) {
            if (!json) {
              return {
                success: false,
                message: "Respuesta inválida del servidor",
                data: [],
              };
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

  function disponibilidad(id, disponible) {
    return accion("disponibilidad", { id: id, disponible: disponible });
  }

  function categorias() {
    return accion("categorias", {});
  }

 global.ProductosAPI = {
    listar: listar,
    obtener: obtener,
    crear: crear,
    actualizar: actualizar,
    eliminar: eliminar,
    disponibilidad: disponibilidad,
    categorias: categorias,
    imagenesListar: imagenesListar,
    imagenesGuardar: imagenesGuardar,
};
  var RUTA_IMAGENES = "../modelos/productos/imagenes_api.php";

  function imagenesListar(idProducto) {
    return llamar(
      RUTA_IMAGENES + "?id_producto=" + encodeURIComponent(idProducto),
      { method: "GET" },
    );
  }

  function imagenesGuardar(idProducto, archivos, eliminar, principal) {
    var fd = new FormData();
    fd.append("id_producto", idProducto);

    (archivos || []).forEach(function (file) {
      fd.append("archivos[]", file);
    });

    (eliminar || []).forEach(function (id) {
      fd.append("eliminar[]", id);
    });

    if (principal) {
      fd.append("principal", principal); // '' | 'e:ID' | 'n:INDICE'
    }

    // OJO: no poner Content-Type; el navegador añade el boundary.
    return llamar(RUTA_IMAGENES, {
      method: "POST",
      body: fd,
    });
  }
})(window);
