(function () {
  "use strict";

  var $s = function (sel, ctx) {
    return (ctx || document).querySelector(sel);
  };
  var $todos = function (sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  };

  var estado = {
    datos: null,
    tab: "productos",
  };

  // =========================================================
  // UTILS
  // =========================================================
  function escapar(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }
  function bs(n) {
    n = Number(n) || 0;
    return (
      "Bs " +
      n.toLocaleString("es-BO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }
  function pct(n) {
    n = Number(n) || 0;
    return (
      n.toLocaleString("es-BO", {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
      }) + " %"
    );
  }
  function aviso(t, m, tipo, time) {
    if (!window.Swal) return console.warn(t + ": " + m);
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: tipo || "info",
      title: t,
      text: m,
      timer: time || 3000,
      timerProgressBar: true,
      showConfirmButton: false,
    });
  }
  function mostrarCarga(a) {
    var c = $s("#tablaCarga");
    if (c) c.classList.toggle("visible", !!a);
  }
  function margenHtml(m) {
    m = Number(m) || 0;
    var clase = m >= 30 ? "alto" : m >= 15 ? "medio" : "bajo";
    return '<span class="margen ' + clase + '">' + pct(m) + "</span>";
  }

  // =========================================================
  // CARGA
  // =========================================================
  function filtros() {
    return {
      id_sucursal: ($s("#ufSucursal") && $s("#ufSucursal").value) || "",
      id_arqueo: ($s("#ufCaja") && $s("#ufCaja").value) || "",
      id_usuario: ($s("#ufUsuario") && $s("#ufUsuario").value) || "",
      desde: ($s("#ufDesde") && $s("#ufDesde").value) || "",
      hasta: ($s("#ufHasta") && $s("#ufHasta").value) || "",
    };
  }

  function cargar() {
    if (!window.UtilidadesAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);
    window.UtilidadesAPI.cargar(filtros()).then(function (rpta) {
      mostrarCarga(false);
      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
        return;
      }

      estado.datos = rpta.data || {};
      llenarSucursales(estado.datos.sucursales || []);
      llenarCajas(estado.datos.cajas || []);
      llenarUsuarios(estado.datos.usuarios || []);
      pintarResumen(estado.datos.resumen || {});
      pintarProductos(estado.datos.productos || []);
      pintarVentas(estado.datos.ventas || []);
      pintarGraficas(estado.datos);
    });
  }

  function llenarSucursales(suc) {
    var sel = $s("#ufSucursal");
    if (!sel) return;

    var esSuper =
      estado.datos &&
      estado.datos.usuario &&
      estado.datos.usuario.es_superadmin;
    if (!esSuper) {
      var wrap = $s("#ufSucursalWrap");
      if (wrap) wrap.style.display = "none";
      return;
    }

    if (sel.dataset.cargado === "1") return;
    var opciones = ['<option value="">Todas las sucursales</option>'];
    suc.forEach(function (s) {
      opciones.push(
        '<option value="' +
          Number(s.id_sucursal) +
          '">' +
          escapar(s.nombre_sucursal) +
          (s.ciudad ? " · " + escapar(s.ciudad) : "") +
          "</option>",
      );
    });
    sel.innerHTML = opciones.join("");
    sel.dataset.cargado = "1";
  }

  function llenarCajas(cajas) {
    var sel = $s("#ufCaja");
    if (!sel) return;

    var actual = sel.value;
    var opciones = ['<option value="">Todas las cajas</option>'];
    cajas.forEach(function (c) {
      opciones.push(
        '<option value="' +
          c.id +
          '">' +
          escapar(c.codigo + " · " + c.nombre + " · " + c.fecha) +
          "</option>",
      );
    });
    sel.innerHTML = opciones.join("");
    if (actual && sel.querySelector('option[value="' + actual + '"]'))
      sel.value = actual;
  }

  function llenarUsuarios(usuarios) {
    var sel = $s("#ufUsuario");
    if (!sel) return;

    var actual = sel.value;
    var opciones = ['<option value="">Todos los vendedores</option>'];
    usuarios.forEach(function (u) {
      opciones.push(
        '<option value="' +
          u.id +
          '">' +
          escapar(u.nombre) +
          " (" +
          escapar(u.username) +
          ")" +
          "</option>",
      );
    });
    sel.innerHTML = opciones.join("");
    if (actual && sel.querySelector('option[value="' + actual + '"]'))
      sel.value = actual;
  }

  function pintarResumen(r) {
    var set = function (sel, v) {
      var el = $s(sel);
      if (el) el.textContent = v;
    };
    set("#uVentas", String(r.ventas || 0));
    set("#uVendedores", String(r.vendedores || 0) + " vendedores");
    set("#uVentaNeta", bs(r.venta_neta));
    set("#uCajas", String(r.cajas || 0) + " cajas");
    set("#uCosto", bs(r.costo));
    set("#uUtilidad", bs(r.utilidad));
    set("#uMargen", pct(r.margen));
    set("#uSucursales", String(r.sucursales || 0) + " sucursales");
  }

  // =========================================================
  // TABLA PRODUCTOS
  // =========================================================
  function pintarProductos(productos) {
    var body = $s("#uProductosBody");
    var foot = $s("#uProductosFoot");
    if (!body) return;

    if (!productos.length) {
      body.innerHTML =
        '<tr><td colspan="7" class="u-vacio">Sin productos vendidos en el rango.</td></tr>';
      if (foot) foot.style.display = "none";
      return;
    }

    var totVenta = 0,
      totCosto = 0,
      totUtilidad = 0;

    body.innerHTML = productos
      .map(function (p) {
        totVenta += Number(p.venta);
        totCosto += Number(p.costo);
        totUtilidad += Number(p.utilidad);

        return (
          "<tr>" +
          "<td><b>" +
          escapar(p.producto) +
          "</b><span style='display:block;font-size:10.5px;color:#a79ea2'>" +
          escapar(p.codigo) +
          "</span></td>" +
          "<td>" +
          (p.categoria
            ? escapar(p.categoria)
            : '<span style="color:#a79ea2">—</span>') +
          "</td>" +
          "<td class='num'>" +
          Number(p.cantidad).toLocaleString("es-BO") +
          "</td>" +
          "<td class='num'>" +
          bs(p.venta) +
          "</td>" +
          "<td class='num'>" +
          bs(p.costo) +
          "</td>" +
          "<td class='num " +
          (p.utilidad >= 0 ? "verde" : "rojo") +
          "'>" +
          bs(p.utilidad) +
          "</td>" +
          "<td class='num'>" +
          margenHtml(p.margen) +
          "</td>" +
          "</tr>"
        );
      })
      .join("");

    $s("#pVenta").textContent = bs(totVenta);
    $s("#pCosto").textContent = bs(totCosto);
    $s("#pUtilidad").textContent = bs(totUtilidad);
    $s("#pMargen").textContent = pct(
      totVenta > 0 ? (totUtilidad / totVenta) * 100 : 0,
    );
    if (foot) foot.style.display = "";
  }

  // =========================================================
  // TABLA VENTAS
  // =========================================================
  function pintarVentas(ventas) {
    var body = $s("#uVentasBody");
    var foot = $s("#uVentasFoot");
    if (!body) return;

    if (!ventas.length) {
      body.innerHTML =
        '<tr><td colspan="10" class="u-vacio">Sin ventas registradas en el rango.</td></tr>';
      if (foot) foot.style.display = "none";
      return;
    }

    var totVenta = 0,
      totCosto = 0,
      totUtilidad = 0;

    body.innerHTML = ventas
      .map(function (v) {
        totVenta += Number(v.subtotal);
        totCosto += Number(v.costo);
        totUtilidad += Number(v.utilidad);

        return (
          "<tr>" +
          "<td><span class='cod'>#" +
          v.id +
          "</span></td>" +
          "<td>" +
          escapar(v.fecha) +
          "</td>" +
          "<td>" +
          escapar(v.codigo) +
          "</td>" +
          "<td>" +
          escapar(v.usuario) +
          "</td>" +
          "<td>" +
          (v.caja_codigo
            ? escapar(v.caja_codigo) + " · " + escapar(v.caja_nombre)
            : "—") +
          "</td>" +
          "<td class='num'>" +
          bs(v.subtotal) +
          "</td>" +
          "<td class='num'>" +
          bs(v.costo) +
          "</td>" +
          "<td class='num " +
          (v.utilidad >= 0 ? "verde" : "rojo") +
          "'>" +
          bs(v.utilidad) +
          "</td>" +
          "<td class='num'>" +
          margenHtml(v.margen) +
          "</td>" +
          "<td class='u-acciones'><button type='button' data-ver='" +
          v.id +
          "'><i class='fa-solid fa-eye'></i></button></td>" +
          "</tr>"
        );
      })
      .join("");

    $s("#vVenta").textContent = bs(totVenta);
    $s("#vCosto").textContent = bs(totCosto);
    $s("#vUtilidad").textContent = bs(totUtilidad);
    if (foot) foot.style.display = "";
  }

  // =========================================================
  // GRÁFICAS (canvas nativo)
  // =========================================================
  function pintarGraficas(d) {
    dibujarBarras(
      document.getElementById("chartSucursal"),
      d.por_sucursal || [],
      "nombre",
    );
    dibujarBarras(
      document.getElementById("chartVend"),
      d.por_vend || [],
      "nombre",
    );
  }

  function dibujarBarras(canvas, datos, campo) {
    if (!canvas) return;
    var ctx = canvas.getContext("2d");
    var W = canvas.width,
      H = canvas.height;
    ctx.clearRect(0, 0, W, H);

    if (!datos.length) {
      ctx.fillStyle = "#a79ea2";
      ctx.font = "12px system-ui";
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.fillText("Sin datos", W / 2, H / 2);
      return;
    }

    var pad = { top: 20, right: 12, bottom: 44, left: 48 };
    var areaW = W - pad.left - pad.right;
    var areaH = H - pad.top - pad.bottom;

    var max = Math.max.apply(
      null,
      datos.map(function (d) {
        return d.utilidad;
      }),
    );
    if (max <= 0) max = 1;

    // Y-axis
    ctx.strokeStyle = "#ececef";
    ctx.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
      var y = pad.top + (areaH / 4) * i;
      ctx.beginPath();
      ctx.moveTo(pad.left, y);
      ctx.lineTo(W - pad.right, y);
      ctx.stroke();

      ctx.fillStyle = "#a79ea2";
      ctx.font = "9.5px system-ui";
      ctx.textAlign = "right";
      ctx.textBaseline = "middle";
      var v = max * (1 - i / 4);
      ctx.fillText(
        v >= 1000 ? (v / 1000).toFixed(1) + "k" : v.toFixed(0),
        pad.left - 5,
        y,
      );
    }

    var gap = areaW / datos.length;
    var barW = Math.min(38, gap * 0.7);

    datos.forEach(function (d, i) {
      var alto = (d.utilidad / max) * areaH;
      var x = pad.left + gap * i + (gap - barW) / 2;
      var y = pad.top + areaH - alto;

      var grad = ctx.createLinearGradient(0, y, 0, pad.top + areaH);
      grad.addColorStop(0, "#14803d");
      grad.addColorStop(1, "#35b46a");

      ctx.fillStyle = grad;
      ctx.beginPath();
      if (ctx.roundRect) ctx.roundRect(x, y, barW, alto, [4, 4, 0, 0]);
      else ctx.rect(x, y, barW, alto);
      ctx.fill();

      // Etiqueta nombre
      ctx.fillStyle = "#a79ea2";
      ctx.font = "9px system-ui";
      ctx.textAlign = "center";
      ctx.textBaseline = "top";
      var et = String(d[campo] || "").substring(0, 12);
      ctx.fillText(et, x + barW / 2, H - pad.bottom + 6);
    });
  }

  // =========================================================
  // DETALLE DE UNA VENTA
  // =========================================================
  function verDetalle(id) {
    var v = (estado.datos.ventas || []).find(function (x) {
      return Number(x.id) === Number(id);
    });
    if (!v) return;

    var fila = function (et, val) {
      return "<dt>" + et + "</dt><dd>" + (escapar(val) || "—") + "</dd>";
    };

    Swal.fire({
      icon: "info",
      title: "Venta " + v.codigo,
      width: 640,
      html:
        '<dl class="dl-datos">' +
        fila("Fecha", v.fecha) +
        fila("Sucursal", v.sucursal) +
        fila(
          "Caja",
          v.caja_codigo ? v.caja_codigo + " · " + v.caja_nombre : "—",
        ) +
        fila("Vendedor", v.usuario) +
        fila("Método", v.metodo) +
        fila("Subtotal", bs(v.subtotal)) +
        fila("Costo", bs(v.costo)) +
        fila("Utilidad", bs(v.utilidad)) +
        fila("Margen", pct(v.margen)) +
        fila("Total cobrado", bs(v.total)) +
        "</dl>",
      confirmButtonText: "Cerrar",
      confirmButtonColor: "#d9232d",
    });
  }

  // =========================================================
  // PDF
  // =========================================================
  function exportarPDF() {
    if (!window.UtilidadesAPI) return;
    var url = window.UtilidadesAPI.pdfUrl(filtros());
    var w = window.open(url, "_blank");
    if (!w)
      aviso(
        "Ventana bloqueada",
        "Permite las ventanas emergentes para ver el PDF",
        "warning",
        5000,
      );
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    var hoy = new Date();
    var desde = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);
    var iso = function (d) {
      return (
        d.getFullYear() +
        "-" +
        String(d.getMonth() + 1).padStart(2, "0") +
        "-" +
        String(d.getDate()).padStart(2, "0")
      );
    };
    if ($s("#ufDesde")) $s("#ufDesde").value = iso(desde);
    if ($s("#ufHasta")) $s("#ufHasta").value = iso(hoy);

    cargar();

    var btnFiltrar = $s("#ufFiltrar");
    if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

    var btnLimpiar = $s("#ufLimpiar");
    if (btnLimpiar)
      btnLimpiar.addEventListener("click", function () {
        ["ufSucursal", "ufCaja", "ufUsuario", "ufDesde", "ufHasta"].forEach(
          function (id) {
            var el = document.getElementById(id);
            if (el) el.value = "";
          },
        );
        cargar();
      });

    var btnPdf = $s("#btnPdf");
    if (btnPdf) btnPdf.addEventListener("click", exportarPDF);

    $todos(".u-tabs button").forEach(function (b) {
      b.addEventListener("click", function () {
        estado.tab = b.getAttribute("data-tab");
        $todos(".u-tabs button").forEach(function (x) {
          x.classList.toggle("activa", x === b);
        });
        var tabProd = $s("#tabProductos");
        var tabVent = $s("#tabVentas");
        if (tabProd)
          tabProd.style.display = estado.tab === "productos" ? "" : "none";
        if (tabVent)
          tabVent.style.display = estado.tab === "ventas" ? "" : "none";
      });
    });

    var tbodyVentas = $s("#uVentasBody");
    if (tbodyVentas)
      tbodyVentas.addEventListener("click", function (e) {
        var b = e.target.closest("[data-ver]");
        if (!b) return;
        verDetalle(b.getAttribute("data-ver"));
      });
  });
})();
