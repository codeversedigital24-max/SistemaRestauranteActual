/**
 * =====================================================
 * modelos/compras/compras_api.js
 * -----------------------------------------------------
 * Capa de red de Compras + Kardex (fetch puro).
 * Siempre devuelve: { success, message, data, status }
 * Se carga con <defer> antes de compras.js / kardex.js
 * =====================================================
 */
(function (global) {
  "use strict";

  var RUTAS = {
    compras: "../modelos/compras/obtener_compras.php",
    nueva: "../modelos/compras/nuevacompra_datos.php",
    kardex: "../modelos/compras/obtener_kardex.php",
    api: "../modelos/compras/compras_api.php",
    recibo: "../modelos/compras/recibo_pdf.php",
    comprobanteAbono: "../modelos/compras/comprobante_abono_pdf.php",
    cuentas: "../modelos/compras/obtener_cuentas_pagar.php",
    cuentasApi: "../modelos/compras/cuentas_pagar_api.php",
  };

  var ESPERA_MS = 25000;

  var meta = document.querySelector('meta[name="csrf-token"]');
  var csrf = (meta && meta.content) || "";

  function qs(params) {
    var partes = [];

    Object.keys(params || {}).forEach(function (clave) {
      var valor = params[clave];

      if (valor === null || valor === undefined || valor === "") return;

      partes.push(encodeURIComponent(clave) + "=" + encodeURIComponent(valor));
    });

    return partes.length ? "?" + partes.join("&") : "";
  }

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
              return { success: false, message: "Respuesta inválida del servidor", data: {}, status: respuesta.status };
            }
            return Object.assign({}, json, { status: respuesta.status });
          });
      })
      .catch(function (error) {
        var tardado = error && error.name === "AbortError";
        return {
          success: false,
          data: {},
          message: tardado ? "El servidor tardó demasiado" : "Sin conexión con el servidor",
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

  function accionCuentas(tipo, datos) {
    return llamar(RUTAS.cuentasApi, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ accion: tipo, datos: datos || {} }),
    });
  }

  global.ComprasAPI = {
    // Compras
    listar: function (filtros) {
      return llamar(RUTAS.compras + qs(filtros), { method: "GET" });
    },
    datosNuevaCompra: function (idSucursal, idCompra) {
      return llamar(RUTAS.nueva + qs({ id_sucursal: idSucursal, id_compra: idCompra }), { method: "GET" });
    },
    registrarCompra: function (datos) {
      return accion("registrar_compra", datos);
    },
    actualizarCompra: function (datos) {
      return accion("actualizar_compra", datos);
    },
    anularCompra: function (id) {
      return accion("anular_compra", { id: id });
    },
    obtenerCompra: function (id) {
      return accion("obtener_compra", { id: id });
    },
    reciboUrl: function (id, descargar) {
      return RUTAS.recibo + qs({ id: id, descargar: descargar ? 1 : "" });
    },
    comprobanteAbonoUrl: function (idPago, descargar) {
      return RUTAS.comprobanteAbono + qs({ id_pago: idPago, descargar: descargar ? 1 : "" });
    },

    // Cuentas por pagar
    cuentasPagar: function (filtros) {
      return llamar(RUTAS.cuentas + qs(filtros), { method: "GET" });
    },
    registrarPago: function (datos) {
      return accionCuentas("registrar_pago", datos);
    },
    registrarPagoProveedor: function (datos) {
      return accionCuentas("registrar_pago_proveedor", datos);
    },
    pagosCompra: function (id) {
      return accionCuentas("pagos_compra", { id: id });
    },
    eliminarPago: function (id) {
      return accionCuentas("eliminar_pago", { id: id });
    },

    // Kardex
    kardex: function (filtros) {
      return llamar(RUTAS.kardex + qs(filtros), { method: "GET" });
    },

    // Proveedores
    proveedores: function () {
      return accion("proveedores", {});
    },
    proveedorObtener: function (id) {
      return accion("proveedor_obtener", { id: id });
    },
    proveedorCrear: function (datos) {
      return accion("proveedor_crear", datos);
    },
    proveedorActualizar: function (datos) {
      return accion("proveedor_actualizar", datos);
    },
    proveedorEliminar: function (id) {
      return accion("proveedor_eliminar", { id: id });
    },
  };
})(window);
