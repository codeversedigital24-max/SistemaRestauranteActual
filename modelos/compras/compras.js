/**
 * =====================================================
 * modelos/compras/compras.js
 * -----------------------------------------------------
 * UI del historial de compras:
 *   · tarjetas de resumen + filtros
 *   · tabla DataTables
 *   · detalle de compra (SweetAlert2)
 *   · alta / edición / baja de proveedores
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
    volverLista: false,
    proveedorEditando: 0,
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
      minimumFractionDigits: decimales,
      maximumFractionDigits: decimales,
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

  function avisoGrande(titulo, texto, tipo) {
    if (!window.Swal) return;
    Swal.fire({
      icon: tipo || "success",
      title: titulo,
      text: texto,
      confirmButtonText: "Entendido",
      confirmButtonColor: "#d9232d",
    });
  }

  function mostrarCarga(activar) {
    var caja = $s("#tablaCarga");
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

  function formaPago(valor) {
    var mapa = {
      contado: "Contado",
      credito: "Crédito",
      transferencia: "Transferencia",
      qr: "QR",
      otro: "Otro",
    };
    return mapa[valor] || valor || "—";
  }

  // =========================================================
  // CARGA + RESUMEN
  // =========================================================
  function filtros() {
    return {
      id_sucursal: $s("#fSucursal") ? $s("#fSucursal").value : "",
      id_proveedor: $s("#fProveedor") ? $s("#fProveedor").value : "",
      desde: $s("#fDesde") ? $s("#fDesde").value : "",
      hasta: $s("#fHasta") ? $s("#fHasta").value : "",
      estado: $s("#fEstado") ? $s("#fEstado").value : "",
    };
  }

  function cargar(alTerminar) {
    if (!window.ComprasAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.ComprasAPI.listar(filtros()).then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error al leer compras", "error", 5000);
        pintarTabla([]);
        if (typeof alTerminar === "function") alTerminar(false);
        return;
      }

      estado.datos = rpta.data || {};
      pintarResumen(estado.datos.resumen || {});
      llenarSucursales(estado.datos.sucursales || []);
      llenarProveedores(estado.datos.proveedores || []);
      pintarTabla(estado.datos.compras || []);
      renderProveedores(estado.datos.proveedores || []);

      if (typeof alTerminar === "function") alTerminar(true);
    });
  }

  function pintarResumen(r) {
    var set = function (sel, valor) {
      var el = $s(sel);
      if (el) el.textContent = valor;
    };

    set("#cmCompras", String(r.compras || 0));
    set("#cmTotal", bs(r.total));
    set("#cmPorPagar", bs(r.por_pagar));
    set("#cmMes", bs(r.mes));
    set("#cmUnidades", numero(r.unidades, 0));
    set("#cmProveedores", String(r.proveedores || 0));
  }

  function llenarSucursales(sucursales) {
    var select = $s("#fSucursal");
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
    var select = $s("#fProveedor");
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
  function chipEstado(valor) {
    return Number(valor) === 1
      ? '<span class="badge-mod badge-verde">Activo</span>'
      : '<span class="badge-mod badge-gris">Inactivo</span>';
  }

  function chipCompra(valor) {
    return valor === "anulada"
      ? '<span class="badge-mod badge-rojo">Anulada</span>'
      : '<span class="badge-mod badge-verde">Registrada</span>';
  }

  function chipCondicion(fila) {
    if (!fila || !fila.es_credito) {
      return '<span class="badge-mod badge-verde">Contado</span>';
    }

    var saldo = Number(fila.saldo || 0);

    if (saldo > 0.005) {
      return (
        '<span class="badge-mod badge-ambar">Crédito</span>' +
        '<span class="dt-subtexto" style="color:#c62828">Saldo ' + bs(saldo) + "</span>"
      );
    }

    return (
      '<span class="badge-mod badge-azul">Crédito</span>' +
      '<span class="dt-subtexto">Pagada</span>'
    );
  }

  function puedeGestionar(accion) {
    var acc = window.RM_ACCIONES || {};
    return !!acc[accion];
  }

  function acciones(fila) {
    var botones =
      '<button type="button" class="btn-tabla ver" data-accion="ver" data-id="' + fila.id +
      '" title="Ver detalle" aria-label="Ver detalle"><i class="fa-solid fa-eye"></i></button>' +
      '<button type="button" class="btn-tabla" data-accion="pdf" data-id="' + fila.id +
      '" title="Recibo PDF" aria-label="Recibo PDF" style="color:#1f6fd0"><i class="fa-solid fa-file-pdf"></i></button>';

    if (fila.estado === "registrada" && puedeGestionar("modificar")) {
      botones +=
        '<button type="button" class="btn-tabla" data-accion="editar" data-id="' + fila.id +
        '" title="Modificar compra" aria-label="Modificar compra" style="color:#b26a00"><i class="fa-solid fa-pen"></i></button>';
    }

    if (fila.estado === "registrada" && puedeGestionar("eliminar")) {
      botones +=
        '<button type="button" class="btn-tabla" data-accion="eliminar" data-id="' + fila.id +
        '" title="Eliminar compra" aria-label="Eliminar compra" style="color:#c62828"><i class="fa-solid fa-trash"></i></button>';
    }

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

    estado.tabla = jQuery("#tablaCompras").DataTable({
      data: datos,
      deferRender: true,
      responsive: { details: { type: "inline", target: "tr" } },
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [[8, 10, 25, 50, -1], [8, 10, 25, 50, "Todos"]],
      order: [[0, "desc"]],
      language: { url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
      dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "54px" },
        { targets: -1, width: "138px" },
      ],
      columns: [
        { data: "id", className: "dt-num", responsivePriority: 9, searchable: false },
        { data: "fecha_compra", responsivePriority: 2 },
        {
          data: "numero_documento",
          responsivePriority: 4,
          render: function (t, type, fila) {
            return (
              '<span class="comp-doc"><b>' + escapar(t || "Sin número") + "</b>" +
              '<span class="comp-sub">' + escapar(tipoDocumento(fila.tipo_documento)) + "</span></span>"
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
          responsivePriority: 6,
          render: function (t, type, fila) {
            return (
              '<span class="dt-texto">' + escapar(t || "—") + "</span>" +
              (fila.ciudad ? '<span class="dt-subtexto">' + escapar(fila.ciudad) + "</span>" : "")
            );
          },
        },
        {
          data: "items",
          className: "dt-num",
          responsivePriority: 7,
          render: function (t, type, fila) {
            return (
              "<b>" + Number(t || 0) + "</b>" +
              '<span class="dt-subtexto">' + numero(fila.unidades, 0) + " u.</span>"
            );
          },
        },
        {
          data: "total",
          className: "dt-num",
          responsivePriority: 3,
          render: function (t) {
            return '<b style="color:var(--mod-tinta)">' + bs(t) + "</b>";
          },
        },
        {
          data: "forma_pago",
          responsivePriority: 8,
          orderable: false,
          searchable: false,
          render: function (t, type, fila) {
            return chipCondicion(fila);
          },
        },
        {
          data: "estado",
          responsivePriority: 8,
          orderable: false,
          render: function (t) {
            return chipCompra(t);
          },
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          responsivePriority: 5,
          render: function (fila) {
            return acciones(fila);
          },
        },
      ],
    });
  }

  // =========================================================
  // DETALLE DE COMPRA
  // =========================================================
  function verCompra(id) {
    if (!window.ComprasAPI) return;

    window.ComprasAPI.obtenerCompra(id).then(function (rpta) {
      if (!rpta.success || !rpta.data || !rpta.data.compra) {
        aviso("No se pudo abrir", rpta.message || "Compra no encontrada", "error", 5000);
        return;
      }

      var c = rpta.data.compra;
      var filas = (c.detalle || []).map(function (d) {
        return (
          "<tr>" +
          '<td style="padding:7px 8px;border-bottom:1px solid #ececef">' +
          '<b style="display:block;font-size:12.5px">' + escapar(d.producto) + "</b>" +
          '<span style="font-size:10.5px;color:#a79ea2">' + escapar(d.codigo) + "</span>" +
          "</td>" +
          '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right">' + numero(d.cantidad, 2) + "</td>" +
          '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right">' + bs(d.costo_unitario) + "</td>" +
          '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right">' + bs(d.precio_venta) + "</td>" +
          '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right"><b>' + bs(d.subtotal) + "</b></td>" +
          "</tr>"
        );
      }).join("");

      var resumen =
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin-bottom:10px;text-align:left">' +
        tarjetaDetalle("Proveedor", c.proveedor + (c.proveedor_nit ? " · NIT " + c.proveedor_nit : "")) +
        tarjetaDetalle("Sucursal", c.sucursal + (c.ciudad ? " · " + c.ciudad : "")) +
        tarjetaDetalle("Documento", tipoDocumento(c.tipo_documento) + (c.numero_documento ? " · " + c.numero_documento : "")) +
        tarjetaDetalle("Fecha", c.fecha_compra) +
        tarjetaDetalle("Forma de pago", formaPago(c.forma_pago)) +
        tarjetaDetalle("Registró", c.usuario || "—") +
        "</div>";

      var tabla =
        '<div style="overflow-x:auto;border:1px solid #ececef;border-radius:10px">' +
        '<table style="width:100%;border-collapse:collapse;font-size:12.5px">' +
        "<thead><tr>" +
        '<th style="padding:8px;background:#f8f7f7;text-align:left;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Producto</th>' +
        '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Cant.</th>' +
        '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Costo</th>' +
        '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">P. venta</th>' +
        '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Subtotal</th>' +
        "</tr></thead><tbody>" + (filas || '<tr><td colspan="5" style="padding:14px;text-align:center;color:#a79ea2">Sin líneas</td></tr>') + "</tbody></table></div>";

      var totales =
        '<div style="display:flex;justify-content:flex-end;margin-top:10px">' +
        '<div style="min-width:250px;font-size:13px">' +
        lineaTotal("Subtotal", bs(c.subtotal)) +
        lineaTotal("Descuento", "− " + bs(c.descuento)) +
        lineaTotal("Impuesto (" + numero(c.impuesto_porcentaje, 2) + " %)", bs(c.impuesto)) +
        '<div style="display:flex;justify-content:space-between;gap:20px;padding:8px 0 0;border-top:2px solid #1c1416;margin-top:5px">' +
        "<b>Total</b><b style='font-size:16px;color:#d9232d'>" + bs(c.total) + "</b></div>" +
        "</div></div>";

      var credito = "";

      if (c.es_credito) {
        var pagos = c.pagos || [];
        var filasPagos = pagos.map(function (p) {
          return (
            "<tr>" +
            '<td style="padding:5px 8px;border-bottom:1px solid #eee">' + escapar(p.fecha_pago) + "</td>" +
            '<td style="padding:5px 8px;border-bottom:1px solid #eee;text-transform:capitalize">' + escapar(p.metodo_pago) + "</td>" +
            '<td style="padding:5px 8px;border-bottom:1px solid #eee">' + escapar(p.numero_guia || "—") + "</td>" +
            '<td style="padding:5px 8px;border-bottom:1px solid #eee">' + escapar(p.usuario || "—") + "</td>" +
            '<td style="padding:5px 8px;border-bottom:1px solid #eee;text-align:right"><b>' + bs(p.monto) + "</b></td>" +
            "</tr>"
          );
        }).join("");

        credito =
          '<div style="margin-top:10px;padding:10px 12px;background:#eef5ff;border:1px solid #d5e5fb;border-radius:10px;text-align:left">' +
          '<div style="display:flex;justify-content:space-between;gap:16px;font-size:12.5px;color:#28527a">' +
          "<span>Abono inicial: <b>" + bs(c.abono) + "</b></span>" +
          "<span>Pagos posteriores: <b>" + bs(c.pagado) + "</b></span>" +
          "<span>Saldo pendiente: <b style='color:#d9232d'>" + bs(c.saldo) + "</b></span>" +
          "</div>" +
          (filasPagos
            ? '<table style="width:100%;border-collapse:collapse;font-size:11.5px;margin-top:8px">' +
              '<thead><tr style="color:#7c8fa5;font-size:9.5px;text-transform:uppercase;letter-spacing:.4px">' +
              '<th style="text-align:left;padding:3px 8px">Fecha</th><th style="text-align:left;padding:3px 8px">Método</th>' +
              '<th style="text-align:left;padding:3px 8px">N° guía</th>' +
              '<th style="text-align:left;padding:3px 8px">Registró</th><th style="text-align:right;padding:3px 8px">Monto</th>' +
              "</tr></thead><tbody>" + filasPagos + "</tbody></table>"
            : '<p style="margin:8px 0 0;font-size:11.5px;color:#7c8fa5">Sin pagos posteriores registrados.</p>') +
          "</div>";
      }

      var observaciones = c.observaciones
        ? '<p style="margin:10px 0 0;padding:8px 10px;background:#f8f7f7;border-radius:8px;font-size:12px;color:#6b6165;text-align:left"><b>Observaciones:</b> ' + escapar(c.observaciones) + "</p>"
        : "";

      Swal.fire({
        title: "Compra #" + c.id,
        html: resumen + tabla + totales + credito + observaciones,
        width: 920,
        confirmButtonText: "Cerrar",
        confirmButtonColor: "#d9232d",
        showDenyButton: c.estado === "registrada",
        denyButtonText: '<i class="fa-solid fa-file-pdf"></i> Recibo PDF',
        denyButtonColor: "#1f6fd0",
      }).then(function (resp) {
        if (resp.isDenied) verRecibo(c.id);
      });
    });
  }

  function verRecibo(id) {
    if (!window.ComprasAPI) return;

    var ventana = window.open(window.ComprasAPI.reciboUrl(id), "_blank");

    if (!ventana) {
      aviso("Ventana bloqueada", "Permite las ventanas emergentes para abrir el recibo PDF", "warning", 6000);
    }
  }

  function editarCompra(id) {
    window.location.href = "nuevacompra.php?id=" + encodeURIComponent(id);
  }

  function eliminarCompra(id, fila) {
    Swal.fire({
      icon: "warning",
      title: "¿Eliminar la compra #" + id + "?",
      html:
        '<div style="font-size:13px;color:#6b6165;text-align:left">' +
        "La compra quedará <b>anulada</b> y el stock de la sucursal disminuirá según sus líneas.<br>" +
        "El movimiento quedará registrado en el kardex." +
        (fila && fila.es_credito && Number(fila.saldo) > 0
          ? "<br><b style='color:#c62828'>Saldo pendiente: " + bs(fila.saldo) + "</b>"
          : "") +
        "</div>",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#c62828",
      cancelButtonColor: "#6b6165",
      reverseButtons: true,
    }).then(function (resp) {
      if (!resp.isConfirmed) return;

      window.ComprasAPI.anularCompra(id).then(function (rpta) {
        if (!rpta.success) {
          aviso("No se pudo eliminar", rpta.message || "Intenta de nuevo", "error", 6000);
          return;
        }

        aviso("Compra eliminada", rpta.message || "El stock se actualizó", "success", 4200);
        cargar();
      });
    });
  }

  function tarjetaDetalle(et, valor) {
    return (
      '<div style="padding:7px 10px;background:#f8f7f7;border:1px solid #ececef;border-radius:8px">' +
      '<span style="display:block;font-size:9.5px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#a79ea2">' + escapar(et) + "</span>" +
      '<b style="display:block;font-size:12px;color:#1c1416;margin-top:1px">' + escapar(valor || "—") + "</b>" +
      "</div>"
    );
  }

  function lineaTotal(et, valor) {
    return (
      '<div style="display:flex;justify-content:space-between;gap:20px;padding:2px 0;color:#6b6165">' +
      "<span>" + escapar(et) + "</span><span>" + valor + "</span></div>"
    );
  }

  // =========================================================
  // PROVEEDORES: MODAL FORMULARIO
  // =========================================================
  function modalProveedor() {
    return $s("#modalProveedor");
  }

  function abrirProveedor(proveedor, volverLista) {
    var modal = modalProveedor();
    if (!modal) return;

    var form = $s("#formProveedor");
    form.reset();
    form.dataset.accion = proveedor ? "actualizar" : "crear";
    form.dataset.id = proveedor ? proveedor.id : "";

    estado.proveedorEditando = proveedor ? Number(proveedor.id) : 0;
    estado.volverLista = Boolean(volverLista);

    var campos = {
      pr_id: proveedor ? proveedor.id : "",
      pr_nombre: proveedor ? proveedor.nombre : "",
      pr_razon: proveedor ? proveedor.razon_social : "",
      pr_nit: proveedor ? proveedor.nit : "",
      pr_contacto: proveedor ? proveedor.contacto : "",
      pr_telefono: proveedor ? proveedor.telefono : "",
      pr_email: proveedor ? proveedor.email : "",
      pr_ciudad: proveedor ? proveedor.ciudad : "",
      pr_direccion: proveedor ? proveedor.direccion : "",
      pr_notas: proveedor ? proveedor.notas : "",
      pr_estado: proveedor ? proveedor.estado : 1,
    };

    Object.keys(campos).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = campos[id] == null ? "" : campos[id];
    });

    var titulo = $s("#mProvTexto");
    if (titulo) titulo.textContent = proveedor ? "Modificar proveedor" : "Nuevo proveedor";

    var nota = $s("#mProvNota");
    if (nota) nota.textContent = proveedor ? "Editando: " + proveedor.nombre : "";

    limpiarErroresProveedor();

    if ($s("#modalProveedores")) {
      $s("#modalProveedores").classList.remove("abierto");
      $s("#modalProveedores").setAttribute("aria-hidden", "true");
    }

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      if ($s("#pr_nombre")) $s("#pr_nombre").focus();
    }, 60);
  }

  function cerrarProveedor() {
    var modal = modalProveedor();
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");

    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function marcarErrorProveedor(campo, mensaje) {
    var cont = document.querySelector('#formProveedor [data-campo="' + campo + '"]');
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

  function limpiarErroresProveedor() {
    document.querySelectorAll("#formProveedor .campo.error").forEach(function (c) {
      marcarErrorProveedor(c.getAttribute("data-campo"), "");
    });
  }

  function leerProveedor() {
    var v = function (id) {
      var el = document.getElementById(id);
      return el ? el.value.trim() : "";
    };

    return {
      id: Number($s("#pr_id").value || "0") || 0,
      nombre: v("pr_nombre"),
      razon_social: v("pr_razon"),
      nit: v("pr_nit"),
      contacto: v("pr_contacto"),
      telefono: v("pr_telefono"),
      email: v("pr_email"),
      ciudad: v("pr_ciudad"),
      direccion: v("pr_direccion"),
      notas: v("pr_notas"),
      estado: Number($s("#pr_estado").value || "1"),
    };
  }

  function guardarProveedor(ev) {
    ev.preventDefault();

    var d = leerProveedor();
    limpiarErroresProveedor();

    var fallos = [];

    if (d.nombre.length < 3) {
      marcarErrorProveedor("nombre", "Mínimo 3 caracteres");
      fallos.push("nombre");
    }

    if (d.email !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)) {
      marcarErrorProveedor("email", "Correo no válido");
      fallos.push("correo");
    }

    if (fallos.length) {
      aviso("Revisa el formulario", "Faltan datos: " + fallos.join(", "), "warning", 4000);
      return;
    }

    var boton = $s("#mProvGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

    var promesa = d.id > 0
      ? window.ComprasAPI.proveedorActualizar(d)
      : window.ComprasAPI.proveedorCrear(d);

    promesa.then(function (rpta) {
      boton.disabled = false;
      boton.innerHTML = boton.dataset.html;

      if (!rpta.success) {
        aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5500);
        return;
      }

      cerrarProveedor();
      avisoGrande(d.id > 0 ? "Proveedor actualizado" : "Proveedor creado", rpta.message || "Guardado correctamente", "success");

      cargar(function () {
        if (estado.volverLista) {
          estado.volverLista = false;
          abrirListaProveedores();
        }
      });
    });
  }

  // =========================================================
  // PROVEEDORES: LISTA
  // =========================================================
  function abrirListaProveedores() {
    var modal = $s("#modalProveedores");
    if (!modal) return;

    renderProveedores((estado.datos && estado.datos.proveedores) || []);

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function cerrarListaProveedores() {
    var modal = $s("#modalProveedores");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");

    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function renderProveedores(proveedores) {
    var body = $s("#provListaBody");
    if (!body) return;

    if (!proveedores.length) {
      body.innerHTML = '<tr><td colspan="7" class="prov-lista-vacia">Aún no hay proveedores registrados.</td></tr>';
      return;
    }

    body.innerHTML = proveedores.map(function (p) {
      var inactivo = Number(p.estado) === 0;

      return (
        "<tr" + (inactivo ? ' style="opacity:.65"' : "") + ">" +
        "<td><b style='display:block;font-size:12.5px'>" + escapar(p.nombre) + "</b>" +
        (p.razon_social ? "<span style='font-size:10.5px;color:#a79ea2'>" + escapar(p.razon_social) + "</span>" : "") +
        "</td>" +
        "<td>" + (p.nit ? escapar(p.nit) : '<span class="vacio">—</span>') + "</td>" +
        "<td>" + (p.contacto ? escapar(p.contacto) : '<span class="vacio">—</span>') + "</td>" +
        "<td>" + (p.telefono ? escapar(p.telefono) : '<span class="vacio">—</span>') + "</td>" +
        '<td style="text-align:center"><span class="badge-mod badge-azul">' + Number(p.compras_count || 0) + "</span></td>" +
        "<td>" + chipEstado(p.estado) + "</td>" +
        '<td style="text-align:right;white-space:nowrap">' +
        '<button type="button" class="btn-tabla editar" data-prov-accion="editar" data-id="' + p.id + '" title="Modificar"><i class="fa-solid fa-pen"></i></button>' +
        (inactivo ? "" : '<button type="button" class="btn-tabla borrar" data-prov-accion="eliminar" data-id="' + p.id + '" title="Desactivar"><i class="fa-solid fa-ban"></i></button>') +
        "</td>" +
        "</tr>"
      );
    }).join("");
  }

  function eliminarProveedor(id) {
    var proveedores = (estado.datos && estado.datos.proveedores) || [];
    var p = proveedores.find(function (x) {
      return Number(x.id) === Number(id);
    });

    Swal.fire({
      icon: "warning",
      title: "¿Desactivar el proveedor?",
      html:
        "Se desactivará <strong>" + escapar(p ? p.nombre : "#" + id) + "</strong>.<br>" +
        "<span style='font-size:13px;color:#8a8a8f'>Sus compras históricas se conservan intactas.</span>",
      showCancelButton: true,
      confirmButtonText: "Sí, desactivar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (resp) {
      if (!resp.isConfirmed) return;

      window.ComprasAPI.proveedorEliminar(id).then(function (rpta) {
        if (!rpta.success) {
          aviso("No se pudo desactivar", rpta.message || "Intenta de nuevo", "error", 5000);
          return;
        }

        aviso("Proveedor desactivado", rpta.message || "", "success");
        cargar();
      });
    });
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    cargar();

    var btnFiltrar = $s("#btnFiltrar");
    if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

    var btnLimpiar = $s("#btnLimpiar");
    if (btnLimpiar) {
      btnLimpiar.addEventListener("click", function () {
        ["fProveedor", "fDesde", "fHasta", "fEstado"].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = "";
        });

        var suc = $s("#fSucursal");
        if (suc && !suc.disabled) suc.value = "";

        cargar();
      });
    }

    ["fSucursal", "fProveedor", "fEstado"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener("change", function () {
          if (el.disabled) return;
          cargar();
        });
      }
    });

    var btnProveedores = $s("#btnProveedores");
    if (btnProveedores) btnProveedores.addEventListener("click", abrirListaProveedores);

    var listaNuevo = $s("#mProvListaNuevo");
    if (listaNuevo) {
      listaNuevo.addEventListener("click", function () {
        abrirProveedor(null, true);
      });
    }

    ["mProvListaCerrar", "mProvListaCerrar2"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", cerrarListaProveedores);
    });

    var modalLista = $s("#modalProveedores");
    if (modalLista) {
      modalLista.addEventListener("click", function (ev) {
        if (ev.target === modalLista) cerrarListaProveedores();
      });
    }

    var body = $s("#provListaBody");
    if (body) {
      body.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-prov-accion]");
        if (!boton) return;

        var id = Number(boton.getAttribute("data-id"));
        var accion = boton.getAttribute("data-prov-accion");

        if (accion === "editar") {
          var proveedores = (estado.datos && estado.datos.proveedores) || [];
          var p = proveedores.find(function (x) {
            return Number(x.id) === id;
          });
          if (p) abrirProveedor(p, true);
        } else if (accion === "eliminar") {
          eliminarProveedor(id);
        }
      });
    }

    var formProv = $s("#formProveedor");
    if (formProv) {
      formProv.addEventListener("submit", guardarProveedor);

      formProv.addEventListener("input", function (ev) {
        var caja = ev.target.closest(".campo");
        if (caja && caja.classList.contains("error")) {
          marcarErrorProveedor(caja.getAttribute("data-campo"), "");
        }
      });
    }

    ["mProvCerrar", "mProvCancelar"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) {
        btn.addEventListener("click", function () {
          cerrarProveedor();
          if (estado.volverLista) {
            estado.volverLista = false;
            setTimeout(abrirListaProveedores, 150);
          }
        });
      }
    });

    var modalProv = $s("#modalProveedor");
    if (modalProv) {
      modalProv.addEventListener("click", function (ev) {
        if (ev.target === modalProv) cerrarProveedor();
      });
    }

    var tbody = document.querySelector("#tablaCompras tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton || boton.disabled) return;

        var accion = boton.getAttribute("data-accion");
        var id = boton.getAttribute("data-id");

        if (accion === "ver") {
          verCompra(id);
          return;
        }

        if (accion === "pdf") {
          verRecibo(id);
          return;
        }

        if (accion === "editar") {
          editarCompra(id);
          return;
        }

        if (accion === "eliminar") {
          var fila = null;

          if (estado.tabla) {
            var tr = boton.closest("tr");
            if (tr) fila = estado.tabla.row(tr).data();
          }

          eliminarCompra(id, fila || {});
        }
      });
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;

      var formProvAbierto = $s("#modalProveedor.abierto");
      if (formProvAbierto) {
        cerrarProveedor();
        return;
      }

      var listaAbierta = $s("#modalProveedores.abierto");
      if (listaAbierta) cerrarListaProveedores();
    });
  });
})();
