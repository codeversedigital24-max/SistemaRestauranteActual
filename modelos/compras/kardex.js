/**
 * =====================================================
 * modelos/compras/kardex.js
 * -----------------------------------------------------
 * UI del kardex por sucursal:
 *   · resumen de inventario y movimientos del mes
 *   · filtros por sucursal, producto, tipo y fechas
 *   · tabla de movimientos valorados
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

  function cantidad(valor) {
    return Number(valor || 0).toLocaleString("es-ES", {
      minimumFractionDigits: 0,
      maximumFractionDigits: 3,
    });
  }

  function costo(valor) {
    return Number(valor || 0).toLocaleString("es-ES", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 4,
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
    var caja = $s("#kxCarga");
    if (caja) caja.classList.toggle("visible", Boolean(activar));
  }

  // =========================================================
  // CARGA
  // =========================================================
  function filtros() {
    return {
      id_sucursal: $s("#kxFiltroSucursal") ? $s("#kxFiltroSucursal").value : "",
      id_producto: $s("#kxFiltroProducto") ? $s("#kxFiltroProducto").value : "",
      tipo: $s("#kxFiltroTipo") ? $s("#kxFiltroTipo").value : "",
      desde: $s("#kxFiltroDesde") ? $s("#kxFiltroDesde").value : "",
      hasta: $s("#kxFiltroHasta") ? $s("#kxFiltroHasta").value : "",
    };
  }

  function cargar() {
    if (!window.ComprasAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.ComprasAPI.kardex(filtros()).then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error al leer el kardex", "error", 5000);
        pintarTabla([]);
        return;
      }

      estado.datos = rpta.data || {};
      pintarResumen(estado.datos.resumen || {});
      llenarSucursales(estado.datos.sucursales || []);
      llenarProductos(estado.datos.productos || []);
      pintarTabla(estado.datos.movimientos || []);
    });
  }

  function pintarResumen(r) {
    var set = function (sel, valor) {
      var el = $s(sel);
      if (el) el.textContent = valor;
    };

    set("#kxProductos", String(r.productos || 0));
    set("#kxStock", cantidad(r.stock_total));
    set("#kxValor", bs(r.valor_inventario));
    set("#kxBajo", String(r.stock_bajo || 0));
    set("#kxEntradas", cantidad(r.entradas_mes));
    set("#kxSalidas", cantidad(r.salidas_mes));
  }

  function llenarSucursales(sucursales) {
    var select = $s("#kxFiltroSucursal");
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

  function llenarProductos(productos) {
    var select = $s("#kxFiltroProducto");
    if (!select) return;

    var actual = select.value;
    var opciones = ['<option value="">Todos los productos</option>'];

    productos.forEach(function (p) {
      opciones.push(
        '<option value="' + Number(p.id_producto) + '">' +
        escapar(p.nombre) + " · " + escapar(p.codigo) +
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
  function tipoHtml(m) {
    var clase = m.tipo === "entrada" ? "entrada" : m.tipo === "salida" ? "salida" : "ajuste";
    var icono = m.tipo === "entrada" ? "fa-arrow-down" : m.tipo === "salida" ? "fa-arrow-up" : "fa-sliders";

    var origen = {
      compra: "Compra",
      venta: "Venta",
      ajuste: "Ajuste",
      inicial: "Inicial",
    }[m.origen] || m.origen;

    return (
      '<span class="kx-tipo ' + clase + '"><i class="fa-solid ' + icono + '"></i> ' + escapar(m.tipo) + "</span>" +
      '<span class="dt-subtexto" style="margin-top:2px">' + escapar(origen) + "</span>"
    );
  }

  function cantidadHtml(valor) {
    var n = Number(valor || 0);
    var clase = n < 0 ? "negativo" : "positivo";
    var signo = n > 0 ? "+" : "";
    return '<span class="kx-cant ' + clase + '">' + signo + cantidad(n) + "</span>";
  }

  function valorHtml(valor) {
    var n = Number(valor || 0);
    var color = n < 0 ? "color:#c62828" : "color:var(--mod-tinta)";
    return '<span style="' + color + ';font-weight:700">' + bs(n) + "</span>";
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

    estado.tabla = jQuery("#tablaKardex").DataTable({
      data: datos,
      deferRender: true,
      responsive: { details: { type: "inline", target: "tr" } },
      autoWidth: false,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
      order: [[0, "desc"]],
      language: { url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
      dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columns: [
        { data: "fecha", responsivePriority: 2, className: "kx-num" },
        {
          data: "sucursal",
          responsivePriority: 8,
          render: function (t, type, fila) {
            return (
              '<span class="dt-texto">' + escapar(t || "—") + "</span>" +
              (fila.ciudad ? '<span class="dt-subtexto">' + escapar(fila.ciudad) + "</span>" : "")
            );
          },
        },
        {
          data: "producto",
          responsivePriority: 1,
          render: function (t, type, fila) {
            return (
              '<span class="kx-prod"><b title="' + escapar(t) + '">' + escapar(t) + "</b>" +
              "<span>" + escapar(fila.producto_codigo) + "</span></span>"
            );
          },
        },
        {
          data: "tipo",
          responsivePriority: 3,
          render: function (t, type, fila) {
            return tipoHtml(fila);
          },
        },
        {
          data: "detalle",
          responsivePriority: 10,
          render: function (t, type, fila) {
            return (
              '<span style="display:block;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="' + escapar(t) + '">' +
              (escapar(t) || '<span class="vacio">—</span>') + "</span>" +
              (fila.documento ? '<span class="dt-subtexto">Doc. ' + escapar(fila.documento) + "</span>" : "")
            );
          },
        },
        {
          data: "cantidad",
          responsivePriority: 4,
          className: "kx-num",
          render: function (t) {
            return cantidadHtml(t);
          },
        },
        {
          data: "costo_unitario",
          responsivePriority: 9,
          className: "kx-num",
          render: function (t) {
            return costo(t);
          },
        },
        {
          data: "precio_venta",
          responsivePriority: 11,
          className: "kx-num",
          render: function (t) {
            return t === null || t === undefined ? '<span class="vacio">—</span>' : "<b>" + bs(t) + "</b>";
          },
        },
        {
          data: "valor_movimiento",
          responsivePriority: 6,
          className: "kx-num",
          render: function (t) {
            return valorHtml(t);
          },
        },
        {
          data: "saldo_cantidad",
          responsivePriority: 7,
          className: "kx-num",
          render: function (t) {
            return "<b>" + cantidad(t) + "</b>";
          },
        },
        {
          data: "costo_promedio",
          responsivePriority: 12,
          className: "kx-num",
          render: function (t) {
            return costo(t);
          },
        },
        {
          data: "saldo_valor",
          responsivePriority: 5,
          className: "kx-num",
          render: function (t) {
            return "<b>" + bs(t) + "</b>";
          },
        },
        {
          data: "usuario",
          responsivePriority: 13,
          render: function (t) {
            return t ? escapar(t) : '<span class="vacio">—</span>';
          },
        },
      ],
    });
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    cargar();

    var btnFiltrar = $s("#kxFiltrar");
    if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

    var btnLimpiar = $s("#kxLimpiar");
    if (btnLimpiar) {
      btnLimpiar.addEventListener("click", function () {
        ["kxFiltroProducto", "kxFiltroTipo", "kxFiltroDesde", "kxFiltroHasta"].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = "";
        });

        var suc = $s("#kxFiltroSucursal");
        if (suc && !suc.disabled) suc.value = "";

        cargar();
      });
    }

    ["kxFiltroSucursal", "kxFiltroProducto", "kxFiltroTipo", "kxFiltroDesde", "kxFiltroHasta"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener("change", function () {
          if (el.disabled) return;
          cargar();
        });
      }
    });
  });
})();
