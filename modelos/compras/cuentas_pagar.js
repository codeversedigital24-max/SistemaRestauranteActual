/**
 * =====================================================
 * modelos/compras/cuentas_pagar.js
 * -----------------------------------------------------
 * UI de Cuentas por pagar:
 *   · tarjetas de resumen y filtros
 *   · tabla de compras al crédito con saldo
 *   · registro de abonos con N° de guía del proveedor
 *   · historial de abonos + comprobante PDF
 * =====================================================
 */
(function () {
  "use strict";

  var $s = function (sel, ctxo) {
    return (ctxo || document).querySelector(sel);
  };

  var estado = {
    datos: null,
    tabla: null,
    reintentos: 0,
    compras: {},
    compraActual: 0,
  };

  // =========================================================
  // UTILIDADES
  // =========================================================
  function escapar(valor) {
    return String(valor == null ? "" : valor)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function numero(valor, decimales) {
    var n = Number(valor || 0);
    return n.toLocaleString("es-ES", {
      minimumFractionDigits: decimales == null ? 2 : decimales,
      maximumFractionDigits: decimales == null ? 2 : decimales,
    });
  }

  function bs(valor) {
    return "Bs " + numero(valor, 2);
  }

  function aviso(titulo, texto, tipo, tiempo) {
    if (!window.Swal) return console.warn(titulo + ": " + texto);
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: tipo || "info",
      title: titulo,
      text: texto,
      timer: tiempo || 3200,
      timerProgressBar: true,
      showConfirmButton: false,
    });
  }

  function mostrarCarga(activar) {
    var caja = $s("#cpCarga");
    if (caja) caja.classList.toggle("visible", Boolean(activar));
  }

  function tipoDocumento(valor) {
    var mapa = {
      factura: "Factura",
      recibo: "Recibo",
      nota: "Nota",
      otro: "Otro",
    };
    return mapa[valor] || valor || "—";
  }

  function metodoPago(valor) {
    var mapa = {
      efectivo: "Efectivo",
      transferencia: "Transferencia",
      qr: "QR",
      otro: "Otro",
    };
    return mapa[valor] || valor || "—";
  }

  function ahoraLocal() {
    var d = new Date();
    var p = function (n) {
      return String(n).padStart(2, "0");
    };

    return (
      d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate()) +
      "T" + p(d.getHours()) + ":" + p(d.getMinutes())
    );
  }

  function abrirModal(id) {
    var modal = $s(id);
    if (!modal) return;
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function cerrarModal(id) {
    var modal = $s(id);
    if (!modal) return;
    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");

    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function abrirPdf(url) {
    var ventana = window.open(url, "_blank");

    if (!ventana) {
      aviso("Ventana bloqueada", "Permite las ventanas emergentes para abrir el PDF", "warning", 6000);
    }
  }

  // =========================================================
  // CARGA + RESUMEN
  // =========================================================
  function filtros() {
    return {
      id_sucursal: $s("#cpSucursal") ? $s("#cpSucursal").value : "",
      id_proveedor: $s("#cpProveedorFiltro") ? $s("#cpProveedorFiltro").value : "",
      buscar: $s("#cpBuscar") ? $s("#cpBuscar").value.trim() : "",
    };
  }

  function cargar(alTerminar) {
    if (!window.ComprasAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.ComprasAPI.cuentasPagar(filtros()).then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error al leer las cuentas por pagar", "error", 5000);
        pintarTabla([]);
        if (typeof alTerminar === "function") alTerminar(false);
        return;
      }

      estado.datos = rpta.data || {};
      estado.compras = {};

      (estado.datos.cuentas || []).forEach(function (c) {
        estado.compras[Number(c.id)] = c;
      });

      pintarResumen(estado.datos.resumen || {});
      llenarSucursales(estado.datos.sucursales || []);
      llenarProveedores(estado.datos.proveedores || []);
      pintarTabla(estado.datos.cuentas || []);

      if (typeof alTerminar === "function") alTerminar(true);
    });
  }

  function pintarResumen(r) {
    var set = function (sel, valor) {
      var el = $s(sel);
      if (el) el.textContent = valor;
    };

    set("#cpTotal", bs(r.total));
    set("#cpCompras", String(r.compras || 0));
    set("#cpProveedores", String(r.proveedores || 0));
    set("#cpSucursales", String(r.sucursales || 0));
  }

  function llenarSucursales(sucursales) {
    var select = $s("#cpSucursal");
    if (!select) return;

    var actual = select.value;
    var esSuper = Boolean(estado.datos && estado.datos.usuario && estado.datos.usuario.es_superadmin);
    var opciones = [];

    if (esSuper) {
      opciones.push('<option value="">Todas las sucursales</option>');
    }

    sucursales.forEach(function (s) {
      opciones.push(
        '<option value="' + Number(s.id_sucursal) + '">' +
        escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
        "</option>"
      );
    });

    select.innerHTML = opciones.join("");

    if (!esSuper && sucursales.length === 1) {
      select.value = String(sucursales[0].id_sucursal);
      select.disabled = true;
    } else {
      select.disabled = false;
      if (actual && select.querySelector('option[value="' + actual + '"]')) {
        select.value = actual;
      }
    }
  }

  function llenarProveedores(proveedores) {
    var select = $s("#cpProveedorFiltro");
    if (!select) return;

    var actual = select.value;
    var opciones = ['<option value="">Todos los proveedores</option>'];

    proveedores.forEach(function (p) {
      opciones.push(
        '<option value="' + Number(p.id) + '">' +
        escapar(p.nombre) + (Number(p.estado) === 1 ? "" : " (inactivo)") +
        "</option>"
      );
    });

    select.innerHTML = opciones.join("");

    if (actual && select.querySelector('option[value="' + actual + '"]')) {
      select.value = actual;
    }
  }

  // =========================================================
  // TABLA
  // =========================================================
  function acciones(fila) {
    var botones =
      '<button type="button" class="btn-tabla" data-accion="abonar" data-id="' + fila.id +
      '" title="Registrar abono" aria-label="Registrar abono" style="color:#24703a"><i class="fa-solid fa-hand-holding-dollar"></i></button>' +
      '<button type="button" class="btn-tabla" data-accion="pagos" data-id="' + fila.id +
      '" title="Ver abonos" aria-label="Ver abonos" style="color:#1f6fd0"><i class="fa-solid fa-list-check"></i></button>' +
      '<button type="button" class="btn-tabla" data-accion="pdf" data-id="' + fila.id +
      '" title="Recibo de compra PDF" aria-label="Recibo de compra PDF" style="color:#c62828"><i class="fa-solid fa-file-pdf"></i></button>';

    return '<span class="acciones-fila">' + botones + "</span>";
  }

  function pintarTabla(datos) {
    if (!window.jQuery || !jQuery.fn.DataTable) {
      if (estado.reintentos++ < 50) {
        setTimeout(function () {
          pintarTabla(datos);
        }, 60);
        return;
      }
      aviso("Sin DataTables", "No se cargó la librería de tablas", "error", 6000);
      return;
    }

    estado.reintentos = 0;

    if (estado.tabla) {
      estado.tabla.clear().rows.add(datos).draw(false);
      return;
    }

    estado.tabla = jQuery("#tablaCuentas").DataTable({
      data: datos,
      deferRender: true,
      responsive: { details: { type: "inline", target: "tr" } },
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [[8, 10, 25, 50, -1], [8, 10, 25, 50, "Todos"]],
      order: [[0, "asc"]],
      language: { url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
      dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "54px" },
        { targets: -1, width: "128px" },
      ],
      columns: [
        { data: "id", className: "dt-num", responsivePriority: 9, searchable: false },
        { data: "fecha_compra", responsivePriority: 4 },
        {
          data: "numero_documento",
          responsivePriority: 5,
          render: function (t, type, fila) {
            return (
              '<span class="cp-doc"><b>' + escapar(t || "Sin número") + "</b>" +
              '<span class="cp-sub">' + escapar(tipoDocumento(fila.tipo_documento)) + "</span></span>"
            );
          },
        },
        {
          data: "proveedor",
          responsivePriority: 1,
          render: function (t, type, fila) {
            return (
              '<span class="dt-texto">' + escapar(t || "—") + "</span>" +
              (fila.proveedor_nit ? '<span class="dt-subtexto">NIT ' + escapar(fila.proveedor_nit) + "</span>" : "")
            );
          },
        },
        {
          data: "sucursal",
          responsivePriority: 7,
          render: function (t, type, fila) {
            return (
              '<span class="dt-texto">' + escapar(t || "—") + "</span>" +
              (fila.ciudad ? '<span class="dt-subtexto">' + escapar(fila.ciudad) + "</span>" : "")
            );
          },
        },
        {
          data: "total",
          className: "dt-num",
          responsivePriority: 3,
          render: function (t) {
            return '<b>' + bs(t) + "</b>";
          },
        },
        {
          data: "abono",
          className: "dt-num",
          responsivePriority: 8,
          render: function (t) {
            return bs(t);
          },
        },
        {
          data: "pagado",
          className: "dt-num",
          responsivePriority: 8,
          render: function (t) {
            return bs(t);
          },
        },
        {
          data: "saldo",
          className: "dt-num",
          responsivePriority: 2,
          render: function (t) {
            return '<span class="' + (Number(t) > 0.005 ? "cp-saldo" : "cp-saldo-cero") + '">' + bs(t) + "</span>";
          },
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          responsivePriority: 6,
          render: function (fila) {
            return acciones(fila);
          },
        },
      ],
    });
  }

  // =========================================================
  // MODAL ABONO
  // =========================================================
  function abrirAbono(id) {
    var compra = estado.compras[Number(id)];
    if (!compra) {
      aviso("Compra no disponible", "Vuelve a cargar la lista", "warning");
      return;
    }

    estado.compraActual = Number(id);

    var form = $s("#formAbono");
    if (form) form.reset();

    $s("#abonoIdCompra").value = String(id);
    $s("#abonoMonto").value = Number(compra.saldo).toFixed(2);
    $s("#abonoMonto").max = Number(compra.saldo).toFixed(2);
    $s("#abonoMetodo").value = "efectivo";
    $s("#abonoGuia").value = "";
    $s("#abonoReferencia").value = "";
    $s("#abonoObservaciones").value = "";
    $s("#abonoFecha").value = ahoraLocal();

    $s("#abonoResumen").innerHTML =
      dato("Proveedor", compra.proveedor || "—") +
      dato("Compra", "#" + compra.id + " · " + (compra.fecha_compra || "—")) +
      dato("Total", bs(compra.total)) +
      dato("Abono inicial", bs(compra.abono)) +
      dato("Pagado", bs(compra.pagado)) +
      dato("Saldo pendiente", bs(compra.saldo));

    $s("#abonoMontoAyuda").textContent = "Máximo permitido: " + bs(compra.saldo);
    $s("#abonoNota").textContent = "Compra #" + compra.id + " · saldo " + bs(compra.saldo);

    limpiarErroresAbono();
    abrirModal("#modalAbono");

    setTimeout(function () {
      if ($s("#abonoGuia")) $s("#abonoGuia").focus();
    }, 80);
  }

  function dato(et, valor) {
    return '<div class="dato"><span>' + escapar(et) + "</span><b>" + escapar(valor) + "</b></div>";
  }

  function marcarError(campo, mensaje) {
    var cont = document.querySelector('#formAbono [data-campo="' + campo + '"]');
    if (!cont) return;
    cont.classList.toggle("error", Boolean(mensaje));

    var ayuda = cont.querySelector(".ayuda");
    if (ayuda) {
      if (mensaje) {
        if (!ayuda.dataset.original) ayuda.dataset.original = ayuda.textContent;
        ayuda.textContent = mensaje;
        ayuda.classList.add("error");
      } else if (ayuda.dataset.original) {
        ayuda.textContent = ayuda.dataset.original;
        ayuda.classList.remove("error");
      }
    }
  }

  function limpiarErroresAbono() {
    document.querySelectorAll("#formAbono .campo.error").forEach(function (c) {
      marcarError(c.getAttribute("data-campo"), "");
    });
  }

  function guardarAbono(ev) {
    ev.preventDefault();

    var compra = estado.compras[estado.compraActual];
    if (!compra) return;

    limpiarErroresAbono();

    var monto = Number(String($s("#abonoMonto").value).replace(",", "."));
    var guia = $s("#abonoGuia").value.trim();
    var saldo = Number(compra.saldo);
    var fallos = [];

    if (!isFinite(monto) || monto <= 0) {
      marcarError("monto", "El monto debe ser mayor que cero");
      fallos.push("monto");
    } else if (monto > saldo + 0.005) {
      marcarError("monto", "No puede superar el saldo de " + bs(saldo));
      fallos.push("monto");
    }

    if (guia.length < 1) {
      marcarError("numero_guia", "Ingresa el N° de guía del recibo del proveedor");
      fallos.push("guía");
    }

    if (fallos.length) {
      aviso("Revisa el formulario", "Faltan datos: " + fallos.join(", "), "warning", 4200);
      return;
    }

    var boton = $s("#mAbonoGuardar");
    if (boton) {
      boton.disabled = true;
      if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
      boton.innerHTML = '<span class="mini-spin"></span> <span>Registrando…</span>';
    }

    var datos = {
      id_compra: estado.compraActual,
      monto: monto,
      metodo_pago: $s("#abonoMetodo").value,
      numero_guia: guia,
      referencia: $s("#abonoReferencia").value.trim(),
      fecha_pago: $s("#abonoFecha").value,
      observaciones: $s("#abonoObservaciones").value.trim(),
    };

    window.ComprasAPI.registrarPago(datos).then(function (rpta) {
      boton.disabled = false;
      boton.innerHTML = boton.dataset.html;

      if (!rpta.success) {
        aviso("No se pudo registrar", rpta.message || "Revisa los datos", "error", 6000);
        return;
      }

      cerrarModal("#modalAbono");

      var idPago = Number(rpta.data && rpta.data.id_pago ? rpta.data.id_pago : 0);
      var nuevoSaldo = rpta.data && rpta.data.saldo != null ? rpta.data.saldo : 0;

      cargar();

      if (!window.Swal) {
        if (idPago > 0) abrirPdf(window.ComprasAPI.comprobanteAbonoUrl(idPago));
        return;
      }

      Swal.fire({
        icon: "success",
        title: "Abono registrado",
        html:
          '<div style="text-align:left;font-size:13px;color:#6b6165">' +
          "<p style='margin:0 0 6px'>Monto: <b>" + bs(monto) + "</b></p>" +
          "<p style='margin:0 0 6px'>N° guía: <b>" + escapar(guia) + "</b></p>" +
          "<p style='margin:0'>Saldo pendiente: <b style='color:#d9232d'>" + bs(nuevoSaldo) + "</b></p>" +
          "</div>",
        showDenyButton: true,
        confirmButtonText: "Cerrar",
        denyButtonText: '<i class="fa-solid fa-file-pdf"></i> Comprobante PDF',
        confirmButtonColor: "#d9232d",
        denyButtonColor: "#1f6fd0",
      }).then(function (resp) {
        if (resp.isDenied && idPago > 0) {
          abrirPdf(window.ComprasAPI.comprobanteAbonoUrl(idPago));
        }
      });
    });
  }

  // =========================================================
  // MODAL ABONOS
  // =========================================================
  function abrirPagos(id) {
    var compra = estado.compras[Number(id)];

    if (!compra) {
      aviso("Compra no disponible", "Vuelve a cargar la lista", "warning");
      return;
    }

    estado.compraActual = Number(id);

    var titulo = $s("#mPagosTitulo");
    if (titulo) {
      titulo.querySelector("span:last-child").textContent = "Abonos de la compra #" + compra.id;
    }

    $s("#pagosResumen").innerHTML =
      '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:7px">' +
      dato("Proveedor", compra.proveedor || "—") +
      dato("Total", bs(compra.total)) +
      dato("Abono inicial", bs(compra.abono)) +
      dato("Saldo", bs(compra.saldo)) +
      "</div>";

    $s("#pagosBody").innerHTML = '<tr><td colspan="8" class="cp-vacio">Cargando...</td></tr>';
    abrirModal("#modalPagos");

    cargarPagos(compra.id);
  }

  function cargarPagos(id) {
    var carga = $s("#pagosCarga");
    if (carga) carga.classList.add("visible");

    window.ComprasAPI.pagosCompra(id).then(function (rpta) {
      if (carga) carga.classList.remove("visible");

      if (!rpta.success || !rpta.data) {
        $s("#pagosBody").innerHTML =
          '<tr><td colspan="8" class="cp-vacio">' + escapar(rpta.message || "No se pudieron cargar los abonos") + "</td></tr>";
        return;
      }

      var pagos = rpta.data.pagos || [];
      var body = $s("#pagosBody");

      if (!pagos.length) {
        body.innerHTML = '<tr><td colspan="8" class="cp-vacio">Esta compra todavía no tiene abonos posteriores.</td></tr>';
        return;
      }

      body.innerHTML = pagos.map(function (p) {
        return (
          "<tr>" +
          '<td class="dt-num"><b>' + Number(p.id) + "</b></td>" +
          "<td>" + escapar(p.fecha_pago) + "</td>" +
          "<td>" + escapar(metodoPago(p.metodo_pago)) + "</td>" +
          '<td><span class="cp-guia">' + escapar(p.numero_guia || "SIN GUÍA") + "</span></td>" +
          "<td>" + escapar(p.referencia || "—") + "</td>" +
          "<td>" + escapar(p.usuario || "—") + "</td>" +
          '<td style="text-align:right"><b>' + bs(p.monto) + "</b></td>" +
          '<td style="text-align:right;white-space:nowrap">' +
          '<button type="button" class="btn-tabla" data-pago-accion="pdf" data-id="' + p.id +
          '" title="Comprobante PDF" style="color:#1f6fd0"><i class="fa-solid fa-file-pdf"></i></button>' +
          '<button type="button" class="btn-tabla" data-pago-accion="eliminar" data-id="' + p.id +
          '" title="Eliminar abono" style="color:#c62828"><i class="fa-solid fa-trash"></i></button>' +
          "</td>" +
          "</tr>"
        );
      }).join("");
    });
  }

  function eliminarPago(id) {
    Swal.fire({
      icon: "warning",
      title: "¿Eliminar el abono #" + id + "?",
      html:
        '<div style="font-size:13px;color:#6b6165;text-align:left">' +
        "El saldo de la compra volverá a aumentar por el monto del abono." +
        "</div>",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#c62828",
      cancelButtonColor: "#6b6165",
      reverseButtons: true,
    }).then(function (resp) {
      if (!resp.isConfirmed) return;

      window.ComprasAPI.eliminarPago(id).then(function (rpta) {
        if (!rpta.success) {
          aviso("No se pudo eliminar", rpta.message || "Intenta de nuevo", "error", 6000);
          return;
        }

        aviso("Abono eliminado", rpta.message || "El saldo se restableció", "success", 4200);
        cargar();

        if (estado.compraActual > 0) cargarPagos(estado.compraActual);
      });
    });
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    cargar();

    var btnFiltrar = $s("#cpFiltrar");
    if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

    var btnLimpiar = $s("#cpLimpiar");
    if (btnLimpiar) {
      btnLimpiar.addEventListener("click", function () {
        ["cpProveedorFiltro", "cpBuscar"].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = "";
        });

        var suc = $s("#cpSucursal");
        if (suc && !suc.disabled) suc.value = "";

        cargar();
      });
    }

    var buscar = $s("#cpBuscar");
    if (buscar) {
      buscar.addEventListener("keydown", function (ev) {
        if (ev.key === "Enter") {
          ev.preventDefault();
          cargar();
        }
      });
    }

    ["cpSucursal", "cpProveedorFiltro"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener("change", function () {
          if (el.disabled) return;
          cargar();
        });
      }
    });

    var tbody = document.querySelector("#tablaCuentas tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton || boton.disabled) return;

        var accion = boton.getAttribute("data-accion");
        var id = Number(boton.getAttribute("data-id"));

        if (accion === "abonar") {
          abrirAbono(id);
          return;
        }

        if (accion === "pagos") {
          abrirPagos(id);
          return;
        }

        if (accion === "pdf") {
          abrirPdf(window.ComprasAPI.reciboUrl(id));
        }
      });
    }

    var formAbono = $s("#formAbono");
    if (formAbono) {
      formAbono.addEventListener("submit", guardarAbono);

      formAbono.addEventListener("input", function (ev) {
        var caja = ev.target.closest(".campo");
        if (caja && caja.classList.contains("error")) {
          marcarError(caja.getAttribute("data-campo"), "");
        }
      });
    }

    ["mAbonoCerrar", "mAbonoCancelar"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", function () { cerrarModal("#modalAbono"); });
    });

    ["mPagosCerrar", "mPagosCerrar2"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", function () { cerrarModal("#modalPagos"); });
    });

    ["#modalAbono", "#modalPagos"].forEach(function (sel) {
      var modal = $s(sel);
      if (!modal) return;

      modal.addEventListener("click", function (ev) {
        if (ev.target === modal) cerrarModal(sel);
      });
    });

    var pagosBody = $s("#pagosBody");
    if (pagosBody) {
      pagosBody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-pago-accion]");
        if (!boton) return;

        var accion = boton.getAttribute("data-pago-accion");
        var id = Number(boton.getAttribute("data-id"));

        if (accion === "pdf") {
          abrirPdf(window.ComprasAPI.comprobanteAbonoUrl(id));
        } else if (accion === "eliminar") {
          eliminarPago(id);
        }
      });
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;

      if ($s("#modalAbono.abierto")) {
        cerrarModal("#modalAbono");
        return;
      }

      if ($s("#modalPagos.abierto")) cerrarModal("#modalPagos");
    });
  });
})();
