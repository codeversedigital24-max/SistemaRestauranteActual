/**
 * =====================================================
 * modelos/cajas/arqueo_api.js
 * -----------------------------------------------------
 * Capa de red del arqueo de caja (fetch puro).
 * Siempre devuelve: { success, message, data, status }
 * =====================================================
 */
(function (global) {
  "use strict";

  var RUTAS = {
    obtener: "../modelos/cajas/obtener_arqueos.php",
    api: "../modelos/cajas/arqueo_api.php",
    ticket: "../modelos/cajas/arqueo_pdf.php",
    movimientos_pdf: "../modelos/cajas/arqueo_movimientos_pdf.php",
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

  function listar(filtros) {
    filtros = filtros || {};

    var partes = [];

    if (filtros.id_sucursal) partes.push("id_sucursal=" + encodeURIComponent(filtros.id_sucursal));
    if (filtros.estado) partes.push("estado=" + encodeURIComponent(filtros.estado));
    if (filtros.desde) partes.push("desde=" + encodeURIComponent(filtros.desde));
    if (filtros.hasta) partes.push("hasta=" + encodeURIComponent(filtros.hasta));

    var url = RUTAS.obtener + (partes.length ? "?" + partes.join("&") : "");

    return llamar(url, { method: "GET" });
  }

  function abrir(monto, observacion) {
    return accion("abrir", {
      monto: monto,
      observacion: observacion || "",
    });
  }

  function cerrar(id, monto, observacion) {
    return accion("cerrar", {
      id: id,
      monto: monto,
      observacion: observacion || "",
    });
  }

  // =========================================================
  // EGRESOS / GASTOS
  // =========================================================
  function movimientoRegistrar(idArqueo, datos) {
    datos = datos || {};

    return accion("movimiento_registrar", {
      id_arqueo: idArqueo,
      tipo: datos.tipo || "egreso",
      concepto: datos.concepto || "",
      monto: datos.monto,
      metodo_pago: datos.metodo_pago || "efectivo",
      referencia: datos.referencia || "",
      observacion: datos.observacion || "",
    });
  }

  function movimientoEliminar(idArqueo, idMovimiento) {
    return accion("movimiento_eliminar", {
      id_arqueo: idArqueo,
      id_movimiento: idMovimiento,
    });
  }

  function movimientos(idArqueo) {
    return accion("movimientos", { id: idArqueo });
  }

  function resumenCierre(idArqueo) {
    return accion("resumen_cierre", { id: idArqueo });
  }

  // =========================================================
  // APERTURA / ARQUEO
  // =========================================================
  function actualizarApertura(idArqueo, monto, observacion) {
    return accion("apertura_actualizar", {
      id: idArqueo,
      monto: monto,
      observacion: observacion || "",
    });
  }

  function reabrirArqueo(idArqueo) {
    return accion("arqueo_reabrir", { id: idArqueo });
  }

  function eliminarArqueo(idArqueo) {
    return accion("arqueo_eliminar", { id: idArqueo });
  }

  /** URL del ticket de cierre (PDF) para abrir / reimprimir. */
  function ticketCierreUrl(idArqueo, descargar) {
    return (
      RUTAS.ticket +
      "?id=" +
      encodeURIComponent(idArqueo) +
      (descargar ? "&descargar=1" : "")
    );
  }

  /** URL del reporte de movimientos del turno (PDF carta). */
  function movimientosPdfUrl(idArqueo, descargar) {
    return (
      RUTAS.movimientos_pdf +
      "?id=" +
      encodeURIComponent(idArqueo) +
      (descargar ? "&descargar=1" : "")
    );
  }

  global.ArqueoCajaAPI = {
    listar: listar,
    abrir: abrir,
    cerrar: cerrar,
    movimientoRegistrar: movimientoRegistrar,
    movimientoEliminar: movimientoEliminar,
    movimientos: movimientos,
    resumenCierre: resumenCierre,
    actualizarApertura: actualizarApertura,
    reabrirArqueo: reabrirArqueo,
    eliminarArqueo: eliminarArqueo,
    ticketCierreUrl: ticketCierreUrl,
    movimientosPdfUrl: movimientosPdfUrl,
  };
})(window);
