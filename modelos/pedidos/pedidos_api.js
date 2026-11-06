(function (global) {
  "use strict";

  var RUTAS = {
    obtener: "../modelos/pedidos/obtener_pedidos.php",
    api: "../modelos/pedidos/pedidos_api.php",
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
    var reloj = setTimeout(function () {
      control.abort();
    }, 20000);

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
      .then(function (r) {
        return r
          .json()
          .catch(function () {
            return null;
          })
          .then(function (j) {
            if (!j)
              return {
                success: false,
                message: "Respuesta inválida",
                data: {},
              };
            return Object.assign({}, j, { status: r.status });
          });
      })
      .catch(function (e) {
        var t = e && e.name === "AbortError";
        return {
          success: false,
          data: {},
          message: t
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

  global.PedidosAPI = {
    cargar: function (idSucursal) {
      return llamar(RUTAS.obtener + qs({ id_sucursal: idSucursal }), {
        method: "GET",
      });
    },
    abrir: function (d) {
      return accion("abrir", d);
    },
    agregarItems: function (d) {
      return accion("agregar_items", d);
    },
    despachar: function (id) {
      return accion("despachar", { id_pedido: id });
    },
    detalle: function (id) {
      return accion("detalle", { id_pedido: id });
    },
    enviarACaja: function (id) {
      return accion("enviar_a_caja", { id_pedido: id });
    },
    ticketUrl: function (idVenta, descargar) {
      return (
        "../modelos/pos/ticket_pdf.php?id=" +
        encodeURIComponent(idVenta) +
        (descargar ? "&descargar=1" : "")
      );
    },
    cobrar: function (d) {
      return accion("cobrar", d);
    },
    cancelar: function (id) {
      return accion("cancelar", { id_pedido: id });
    },
  };
})(window);
