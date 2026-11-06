/**
 * =====================================================
 * modelos/pos/pos.js
 * -----------------------------------------------------
 * UI del Punto de venta:
 *   · Catálogo con ofertas, promociones, IVA y stock
 *   · Carrito con cantidades y descuentos por línea
 *   · Cobro simple o mixto (efectivo / QR / tarjeta)
 *   · Tipo de pedido (mesa / llevar / delivery)
 *   · Ticket PDF al finalizar
 *
 * Todos los importes se recalculan en el servidor antes
 * de guardar; aquí sólo se muestra y se valida.
 * =====================================================
 */
(function () {
  "use strict";

  var $s = function (sel, ctx) {
    return (ctx || document).querySelector(sel);
  };

  var $todos = function (sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  };

  var estado = {
    productos: [],
    categorias: [],
    promociones: [],
    carrito: [],
    sucursales: [],
    usuario: null,
    caja: null,
    arqueo: null,
    empresa: null,
    idSucursal: 0,
    filtro: "",
    categoria: "",
    tipoPedido: "mesa",
    deliveryNombre: "",
    metodo: "efectivo",
    enviando: false,
    cargando: false,
    ultimaVenta: null,
    idempotencia: "",
  };

  var TIPOS = {
    mesa: { texto: "Para mesa", icono: "fa-utensils" },
    llevar: { texto: "Para llevar", icono: "fa-bag-shopping" },
    delivery: { texto: "Delivery", icono: "fa-motorcycle" },
  };

  var METODOS = {
    efectivo: "Efectivo",
    qr: "QR",
    tarjeta: "Tarjeta",
    mixto: "Mixto",
    otro: "Otro",
  };

  // =========================================================
  // UTILIDADES
  // =========================================================
  function escapar(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function numero(v) {
    var n = Number(v);
    return isFinite(n) ? n : 0;
  }

  function n2(v) {
    return Math.round((numero(v) + Number.EPSILON) * 100) / 100;
  }

  /** Clave única por cobro: evita registrar dos veces la misma venta. */
  function nuevaClaveIdempotencia() {
    try {
      if (window.crypto && typeof window.crypto.randomUUID === "function") {
        return window.crypto.randomUUID();
      }

      if (window.crypto && typeof window.crypto.getRandomValues === "function") {
        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (b) {
          return ("0" + b.toString(16)).slice(-2);
        }).join("");
      }
    } catch (e) {
      // Sin crypto: se usa el respaldo de abajo.
    }

    return "pos-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 12);
  }

  /** Stock con el que opera el POS: global del producto (productos.stock). */
  function stockProducto(p) {
    if (!p) return 0;
    return numero(p.stock_pos != null ? p.stock_pos : p.stock_sucursal);
  }

  function bs(v) {
    var n = numero(v);
    return (
      "Bs " +
      n.toLocaleString("es-BO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  function bsNeg(v) {
    return "− " + bs(Math.abs(numero(v)));
  }

  function normalizar(texto) {
    var s = String(texto == null ? "" : texto).toLowerCase();
    if (s.normalize) {
      s = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }
    return s.trim();
  }

  function plural(n, singular, plural_) {
    return n === 1 ? singular : plural_ || singular + "s";
  }

  function aviso(titulo, texto, tipo, tiempo) {
    if (!window.Swal) {
      return console.warn(titulo + ": " + texto);
    }
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

  function iconoCategoria(id) {
    for (var i = 0; i < estado.categorias.length; i++) {
      if (Number(estado.categorias[i].id) === Number(id)) {
        return estado.categorias[i].icono || "fa-utensils";
      }
    }
    return "fa-utensils";
  }

  function productoPorId(id) {
    for (var i = 0; i < estado.productos.length; i++) {
      if (Number(estado.productos[i].id) === Number(id)) {
        return estado.productos[i];
      }
    }
    return null;
  }

  // =========================================================
  // CARGA
  // =========================================================
  function mostrarCarga(activo) {
    var c = $s("#posCarga");
    if (!c) return;
    c.classList.toggle("visible", !!activo);
  }

  function mostrarContenido(activo) {
    var c = $s("#posContenido");
    if (!c) return;
    c.classList.toggle("visible", !!activo);
  }

  /**
   * Vuelve a enlazar el carrito con el catálogo recién cargado:
   * actualiza precios, promociones y stock; quita lo que ya no
   * existe o se quedó sin stock.
   */
  function sincronizarCarrito() {
    var avisos = 0;

    estado.carrito = estado.carrito
      .map(function (l) {
        var p = productoPorId(l.id_producto);

        if (!p) {
          avisos++;
          return null;
        }

        var controla = Number(p.control_stock) === 1;
        var stock = stockProducto(p);
        var cantidad = numero(l.cantidad);

        if (controla && stock + 0.0001 < cantidad) {
          cantidad = stock;
          if (cantidad <= 0) {
            avisos++;
            return null;
          }
          avisos++;
        }

        l.nombre = p.nombre;
        l.codigo = p.codigo || "";
        l.precio_base = numero(p.precio_base);
        l.precio_unitario = numero(p.precio_pos);
        l.descuento_unitario = numero(p.descuento_pos);
        l.descuento_pct = numero(p.descuento_pct_pos);
        l.impuesto_pct = numero(p.impuesto_pos);
        l.promo = p.promocion || "";
        l.promo_etiqueta = p.promocion_etiqueta || "";
        l.controla = controla;
        l.stock = stock;
        l.cantidad = cantidad;

        return l;
      })
      .filter(function (l) {
        return l !== null;
      });

    if (avisos > 0) {
      aviso(
        "Carrito actualizado",
        "Algunos productos cambiaron de precio, stock o disponibilidad.",
        "info",
        4200
      );
    }
  }

  function cargar(idSucursal) {
    if (!window.PosAPI) {
      aviso("Error", "No se cargó el módulo de red del POS", "error");
      return;
    }

    if (estado.cargando) return;
    estado.cargando = true;
    mostrarCarga(true);

    window.PosAPI.cargar(idSucursal || 0)
      .then(function (rpta) {
        if (!rpta || !rpta.success) {
          throw new Error((rpta && rpta.message) || "No se pudo cargar el POS");
        }

        var d = rpta.data || {};
        estado.productos = Array.isArray(d.productos) ? d.productos : [];
        estado.categorias = Array.isArray(d.categorias) ? d.categorias : [];
        estado.promociones = Array.isArray(d.promociones) ? d.promociones : [];
        estado.sucursales = Array.isArray(d.sucursales) ? d.sucursales : [];
        estado.usuario = d.usuario || null;
        estado.caja = d.mi_caja || null;
        estado.arqueo = d.arqueo_abierto || null;
        estado.empresa = d.empresa || null;
        estado.idSucursal = Number(d.id_sucursal || 0);

        prepararSucursal();
        actualizarCabecera();
        pintarCategorias();
        sincronizarCarrito();
        pintarProductos();
        pintarCarrito();

        mostrarCarga(false);
        mostrarContenido(true);
      })
      .catch(function (e) {
        mostrarCarga(false);
        mostrarContenido(true);
        aviso("No se pudo cargar", (e && e.message) || "Intenta de nuevo", "error", 5000);
      })
      .finally(function () {
        estado.cargando = false;
      });
  }

  function prepararSucursal() {
    var sel = $s("#posSucursal");
    if (!sel) return;

    var esSuper = !!(estado.usuario && estado.usuario.es_superadmin);
    var lista = estado.sucursales || [];

    if (!esSuper || lista.length <= 1) {
      sel.style.display = "none";
      return;
    }

    sel.innerHTML = lista
      .map(function (s) {
        var id = Number(s.id_sucursal || s.id || 0);
        var nombre = s.nombre_sucursal || s.nombre || ("Sucursal " + id);
        return (
          '<option value="' + id + '"' +
          (id === Number(estado.idSucursal) ? " selected" : "") +
          ">" + escapar(nombre) + "</option>"
        );
      })
      .join("");

    sel.style.display = "";
  }

  function actualizarCabecera() {
    var usr = estado.usuario || {};
    var caja = estado.caja;
    var arqueo = estado.arqueo;

    var usuarioEl = $s("#posUsuario");
    if (usuarioEl) {
      var nombre = usr.nombre || "Cajero";
      var sucursal = (caja && caja.sucursal) || "";
      usuarioEl.textContent = sucursal ? nombre + " · " + sucursal : nombre;
    }

    var estadoEl = $s("#posEstadoCaja");
    var textoEl = $s("#posEstadoTexto");
    var bloqueo = $s("#posBloqueo");
    var bloqueoMsg = $s("#posBloqueoMsg");
    var abierta = !!arqueo;

    if (estadoEl) {
      estadoEl.classList.remove("ok", "mal");
      estadoEl.classList.add(abierta ? "ok" : "mal");
    }

    if (textoEl) {
      if (!caja) {
        textoEl.textContent = "Sin caja asignada";
      } else if (!abierta) {
        textoEl.textContent = "Caja cerrada";
      } else {
        textoEl.textContent = "Caja " + (caja.codigo || "") + " abierta";
      }
    }

    if (bloqueo) {
      bloqueo.classList.toggle("visible", !abierta);
    }

    if (!abierta && bloqueoMsg) {
      bloqueoMsg.textContent = !caja
        ? "No tienes una caja asignada. Pide a un administrador que te asigne una para vender."
        : "Tu caja está cerrada. Abre el arqueo para empezar a vender.";
    }

    var cobrar = $s("#posCobrar");
    if (cobrar) {
      cobrar.disabled = !abierta || estado.carrito.length === 0;
    }

    var nota = $s("#cobroNota");
    if (nota) {
      nota.textContent = caja
        ? "Caja " + (caja.codigo || "") + " · " + (caja.sucursal || "")
        : "";
    }
  }

  // =========================================================
  // CATÁLOGO
  // =========================================================
  function pintarCategorias() {
    var cont = $s("#posCategorias");
    if (!cont) return;

    var html =
      '<button type="button" class="' +
      (estado.categoria === "" ? "activa" : "") +
      '" data-cat=""><i class="fas fa-layer-group"></i> Todas</button>';

    html += estado.categorias
      .map(function (c) {
        var activa = String(c.id) === String(estado.categoria);
        return (
          '<button type="button" class="' + (activa ? "activa" : "") +
          '" data-cat="' + Number(c.id) + '">' +
          '<i class="fas ' + escapar(c.icono || "fa-utensils") + '"></i> ' +
          escapar(c.nombre) +
          "</button>"
        );
      })
      .join("");

    cont.innerHTML = html;
  }

  function productosFiltrados() {
    var texto = normalizar(estado.filtro);
    var cat = estado.categoria;

    return estado.productos.filter(function (p) {
      if (cat !== "" && String(p.id_categoria) !== String(cat)) {
        return false;
      }
      if (texto === "") return true;

      var bolsa = normalizar(
        [p.nombre, p.codigo, p.categoria, p.descripcion].join(" ")
      );

      return bolsa.indexOf(texto) !== -1;
    });
  }

  function etiquetaStock(p) {
    var controla = Number(p.control_stock) === 1;
    var stock = stockProducto(p);

    if (!controla) {
      return '<span class="stock-ok"><i class="fas fa-circle-check"></i> Disponible</span>';
    }

    if (stock <= 0) {
      return '<span class="stock-cero"><i class="fas fa-circle-xmark"></i> Agotado</span>';
    }

    if (stock <= 5) {
      return '<span class="stock-bajo"><i class="fas fa-triangle-exclamation"></i> Quedan ' + stock + "</span>";
    }

    return '<span class="stock-ok"><i class="fas fa-box"></i> Stock ' + stock + "</span>";
  }

  function pintarProductos() {
    var cont = $s("#posProductos");
    var vacio = $s("#posVacio");
    var conteo = $s("#posConteo");
    var vacioTexto = $s("#posVacioTexto");
    if (!cont) return;

    var lista = productosFiltrados();

    if (conteo) conteo.textContent = lista.length;

    if (vacio) {
      vacio.classList.toggle("visible", lista.length === 0);
    }

    if (vacioTexto) {
      vacioTexto.textContent = estado.filtro
        ? 'No encontramos nada para "' + estado.filtro + '".'
        : "Esta categoría todavía no tiene productos.";
    }

    cont.innerHTML = lista
      .map(function (p) {
        var controla = Number(p.control_stock) === 1;
        var stock = stockProducto(p);
        var agotado = controla && stock <= 0;
        var imagen = p.imagen_principal_url || p.imagen_url || "";
        var icono = iconoCategoria(p.id_categoria);
        var descuento = numero(p.descuento_pos);
        var tienePromo = !!p.promocion;
        var iva = numero(p.impuesto_pos);

        var badges = "";

        if (tienePromo) {
          badges +=
            '<span class="pos-badge promo" title="' + escapar(p.promocion) + '">' +
            '<i class="fas fa-tag"></i> Promo</span>';
        } else if (descuento > 0.004) {
          badges +=
            '<span class="pos-badge"><i class="fas fa-bolt"></i> Oferta</span>';
        }

        return (
          '<button type="button" class="pos-producto' + (agotado ? " agotado" : "") +
          '" data-id="' + Number(p.id) + '" ' +
          'aria-label="' + escapar(p.nombre) + (agotado ? " (agotado)" : "") + '">' +

            '<span class="pos-producto-media">' +
              '<span class="sin-imagen"><i class="fas ' + escapar(icono) + '"></i></span>' +
              (imagen
                ? '<img src="' + escapar(imagen) + '" alt="" loading="lazy" onerror="this.remove()">'
                : "") +
              (badges
                ? '<span class="pos-producto-badges">' + badges + "</span>"
                : "") +
            "</span>" +

            '<span class="pos-producto-cuerpo">' +
              '<span class="pos-producto-nombre">' + escapar(p.nombre) + "</span>" +
              '<span class="pos-producto-codigo">' + escapar(p.codigo || "") + "</span>" +
              '<span class="pos-producto-precios">' +
                '<span class="pos-producto-precio">' + bs(p.precio_pos) + "</span>" +
                (descuento > 0.004
                  ? '<span class="pos-producto-antes">' + bs(p.precio_base) + "</span>"
                  : "") +
              "</span>" +
              '<span class="pos-producto-meta">' +
                etiquetaStock(p) +
                (iva > 0 ? '<span>IVA ' + iva + "%</span>" : "") +
              "</span>" +
            "</span>" +

            '<span class="pos-agregar"><i class="fas fa-plus"></i></span>' +
          "</button>"
        );
      })
      .join("");
  }

  function filtrar() {
    var limpiar = $s("#posBuscarLimpiar");
    if (limpiar) {
      limpiar.classList.toggle("visible", estado.filtro.length > 0);
    }
    pintarProductos();
  }

  // =========================================================
  // CARRITO
  // =========================================================
  function importeLinea(l) {
    var cantidad = numero(l.cantidad);
    var bruto = n2(numero(l.precio_base) * cantidad);
    var neto = n2(numero(l.precio_unitario) * cantidad);
    var descuento = n2(numero(l.descuento_unitario) * cantidad);
    var iva = n2(neto * (numero(l.impuesto_pct) / 100));
    var total = n2(neto + iva);

    return {
      bruto: bruto,
      neto: neto,
      descuento: descuento,
      iva: iva,
      total: total,
    };
  }

  function calcularTotales() {
    var t = {
      bruto: 0,
      neto: 0,
      descuento: 0,
      iva: 0,
      total: 0,
      unidades: 0,
      lineas: estado.carrito.length,
    };

    estado.carrito.forEach(function (l) {
      var i = importeLinea(l);
      t.bruto += i.bruto;
      t.neto += i.neto;
      t.descuento += i.descuento;
      t.iva += i.iva;
      t.total += i.total;
      t.unidades += numero(l.cantidad);
    });

    t.bruto = n2(t.bruto);
    t.neto = n2(t.neto);
    t.descuento = n2(t.descuento);
    t.iva = n2(t.iva);
    t.total = n2(t.total);
    t.unidades = n2(t.unidades);

    return t;
  }

  function agregarProducto(id) {
    var p = productoPorId(id);
    if (!p) return;

    var controla = Number(p.control_stock) === 1;
    var stock = stockProducto(p);
    var linea = null;

    for (var i = 0; i < estado.carrito.length; i++) {
      if (Number(estado.carrito[i].id_producto) === Number(id)) {
        linea = estado.carrito[i];
        break;
      }
    }

    var actual = linea ? numero(linea.cantidad) : 0;

    if (controla && actual + 1 > stock + 0.0001) {
      return aviso(
        stock <= 0 ? "Agotado" : "Sin stock suficiente",
        stock <= 0
          ? '"' + p.nombre + '" no tiene stock en esta sucursal.'
          : '"' + p.nombre + '": sólo quedan ' + stock + " unidades.",
        "warning"
      );
    }

    if (linea) {
      linea.cantidad = n2(actual + 1);
    } else {
      estado.carrito.push({
        id_producto: Number(p.id),
        nombre: p.nombre,
        codigo: p.codigo || "",
        precio_base: numero(p.precio_base),
        precio_unitario: numero(p.precio_pos),
        descuento_unitario: numero(p.descuento_pos),
        descuento_pct: numero(p.descuento_pct_pos),
        impuesto_pct: numero(p.impuesto_pos),
        promo: p.promocion || "",
        promo_etiqueta: p.promocion_etiqueta || "",
        icono: iconoCategoria(p.id_categoria),
        controla: controla,
        stock: stock,
        cantidad: 1,
      });
    }

    pintarCarrito();
  }

  function cambiarCantidad(id, delta) {
    var indice = -1;

    for (var i = 0; i < estado.carrito.length; i++) {
      if (Number(estado.carrito[i].id_producto) === Number(id)) {
        indice = i;
        break;
      }
    }

    if (indice === -1) return;

    var linea = estado.carrito[indice];
    var nueva = n2(numero(linea.cantidad) + delta);

    if (nueva <= 0) {
      estado.carrito.splice(indice, 1);
      return pintarCarrito();
    }

    if (linea.controla && nueva > numero(linea.stock) + 0.0001) {
      return aviso(
        "Sin stock suficiente",
        '"' + linea.nombre + '": sólo quedan ' + linea.stock + " unidades.",
        "warning"
      );
    }

    linea.cantidad = nueva;
    pintarCarrito();
  }

  function quitarLinea(id) {
    estado.carrito = estado.carrito.filter(function (l) {
      return Number(l.id_producto) !== Number(id);
    });
    pintarCarrito();
  }

  function vaciarCarrito() {
    if (estado.carrito.length === 0) return;

    if (window.Swal) {
      Swal.fire({
        icon: "question",
        title: "¿Vaciar el carrito?",
        text: "Se quitarán todos los productos agregados.",
        showCancelButton: true,
        confirmButtonText: "Sí, vaciar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d9232d",
      }).then(function (r) {
        if (r.isConfirmed) {
          estado.carrito = [];
          pintarCarrito();
        }
      });
      return;
    }

    estado.carrito = [];
    pintarCarrito();
  }

  function pintarCarrito() {
    var t = calcularTotales();
    var lineas = $s("#posCarritoLineas");
    var vacio = $s("#posCarritoVacio");
    var count = $s("#posCarritoCount");
    var resumen = $s("#posCarritoResumen");
    var subtotal = $s("#posSubtotal");
    var descuento = $s("#posDescuento");
    var impuesto = $s("#posImpuesto");
    var total = $s("#posTotal");
    var cobrarTotal = $s("#posCobrarTotal");
    var cobrar = $s("#posCobrar");
    var fabTotal = $s("#posFabTotal");
    var fabCount = $s("#posFabCount");

    if (lineas) {
      lineas.style.display = t.lineas === 0 ? "none" : "";
      lineas.innerHTML = estado.carrito
        .map(function (l) {
          var imp = importeLinea(l);
          var chips = [];

          if (l.promo) {
            chips.push(
              '<span class="pos-linea-promo" title="' + escapar(l.promo) + '">' +
              '<i class="fas fa-tag"></i><span>' + escapar(l.promo) + "</span></span>"
            );
          } else if (numero(l.descuento_unitario) > 0.004) {
            chips.push(
              '<span class="pos-linea-promo"><i class="fas fa-bolt"></i><span>Oferta</span></span>'
            );
          }

          return (
            '<div class="pos-linea" data-id="' + Number(l.id_producto) + '">' +
              '<div class="pos-linea-nombre">' + escapar(l.nombre) + "</div>" +
              '<div class="pos-linea-precio">' + bs(imp.total) + "</div>" +
              '<div class="pos-linea-meta">' +
                "<span>" + bs(l.precio_unitario) + " c/u</span>" +
                (numero(l.impuesto_pct) > 0
                  ? "<span>· IVA " + numero(l.impuesto_pct) + "%</span>"
                  : "") +
                chips.join("") +
              "</div>" +
              '<div class="pos-linea-controles">' +
                '<div class="pos-stepper">' +
                  '<button type="button" data-accion="menos" aria-label="Quitar una unidad"><i class="fas fa-minus"></i></button>' +
                  "<b>" + l.cantidad + "</b>" +
                  '<button type="button" data-accion="mas" aria-label="Agregar una unidad"><i class="fas fa-plus"></i></button>' +
                "</div>" +
                '<button type="button" class="pos-linea-quitar" data-accion="quitar" aria-label="Quitar producto">' +
                  '<i class="fas fa-trash-can"></i>' +
                "</button>" +
              "</div>" +
            "</div>"
          );
        })
        .join("");
    }

    if (vacio) vacio.classList.toggle("visible", t.lineas === 0);
    if (count) count.textContent = t.unidades;
    if (resumen) {
      resumen.textContent =
        t.unidades === 0
          ? "0 artículos"
          : t.unidades + " " + plural(t.unidades, "artículo", "artículos");
    }
    if (subtotal) subtotal.textContent = bs(t.bruto);
    if (descuento) descuento.textContent = bsNeg(t.descuento);
    if (impuesto) impuesto.textContent = bs(t.iva);
    if (total) total.textContent = bs(t.total);
    if (cobrarTotal) cobrarTotal.textContent = bs(t.total);
    if (fabTotal) fabTotal.textContent = bs(t.total);
    if (fabCount) {
      fabCount.textContent =
        t.unidades === 0
          ? "Carrito vacío"
          : t.unidades + " " + plural(t.unidades, "artículo", "artículos");
    }
    if (cobrar) {
      cobrar.disabled = t.lineas === 0 || !estado.arqueo;
    }

    actualizarTipoPedidoPill();
  }

  function actualizarTipoPedidoPill() {
    var el = $s("#posTipoPedidoTexto");
    var pill = $s("#posPedidoPill");
    if (!el) return;
    var tipo = TIPOS[estado.tipoPedido] || TIPOS.mesa;
    el.textContent = tipo.texto;
    if (pill) {
      var icono = pill.querySelector("i");
      if (icono) icono.className = "fas " + tipo.icono;
    }
  }

  function abrirCarrito() {
    if (window.innerWidth > 900) return;
    var c = $s("#posCarrito");
    var b = $s("#posFabBackdrop");
    var f = $s("#posFabCarrito");
    if (c) c.classList.add("abierto");
    if (b) b.classList.add("visible");
    if (f) f.style.display = "none";
    document.body.style.overflow = "hidden";
  }

  function cerrarCarrito() {
    var c = $s("#posCarrito");
    var b = $s("#posFabBackdrop");
    var f = $s("#posFabCarrito");
    if (c) c.classList.remove("abierto");
    if (b) b.classList.remove("visible");
    if (f) f.style.display = "";
    if (!$s("#modalCobro.abierto") && !$s("#modalExito.abierto")) {
      document.body.style.overflow = "";
    }
  }

  // =========================================================
  // COBRO
  // =========================================================
  function abrirCobro() {
    if (!estado.arqueo) {
      return aviso("Caja cerrada", "Abre el arqueo antes de cobrar.", "warning");
    }

    if (estado.carrito.length === 0) {
      return aviso("Carrito vacío", "Agrega al menos un producto.", "info");
    }

    var t = calcularTotales();

    estado.metodo = "efectivo";
    estado.enviando = false;
    estado.idempotencia = nuevaClaveIdempotencia();

    texto("#cobroTotal", bs(t.total));
    texto(
      "#cobroItems",
      t.lineas + " " + plural(t.lineas, "producto", "productos") +
        " · " + t.unidades + " " + plural(t.unidades, "artículo", "artículos")
    );
    texto(
      "#cobroResumenDesc",
      t.descuento > 0.004 ? "Ahorro " + bs(t.descuento) : "Sin descuentos"
    );

    campo("#cobroRecibido", t.total > 0 ? t.total.toFixed(2) : "");
    campo("#cobroRef", "");
    campo("#cobroRefMixto", "");
    campo("#cobroObs", "");
    campo("#mixtoEfectivo", t.total > 0 ? t.total.toFixed(2) : "");
    campo("#mixtoQr", "");
    campo("#mixtoTarjeta", "");

    seleccionarTipo(estado.tipoPedido);
    if ($s("#cobroDelivery")) {
      $s("#cobroDelivery").value = estado.deliveryNombre || "";
    }

    aplicarMetodo();

    var modal = $s("#modalCobro");
    if (!modal) return;
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var r = $s("#cobroRecibido");
      if (r) {
        r.focus();
        r.select();
      }
    }, 90);
  }

  function cerrarCobro() {
    var modal = $s("#modalCobro");
    if (modal) {
      modal.classList.remove("abierto");
      modal.setAttribute("aria-hidden", "true");
    }
    if (!$s("#modalExito.abierto") && !$s("#posCarrito.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function texto(sel, valor) {
    var el = $s(sel);
    if (el) el.textContent = valor;
  }

  function campo(sel, valor) {
    var el = $s(sel);
    if (el) el.value = valor;
  }

  function seleccionarTipo(tipo) {
    var clave = TIPOS[tipo] ? tipo : "mesa";
    estado.tipoPedido = clave;

    $todos("#cobroTipos .cobro-tipo").forEach(function (b) {
      b.classList.toggle("activa", b.getAttribute("data-tipo") === clave);
    });

    var wrap = $s("#cobroDeliveryWrap");
    if (wrap) wrap.classList.toggle("visible", clave === "delivery");

    actualizarTipoPedidoPill();
  }

  function aplicarMetodo() {
    $todos("#cobroMetodos .cobro-metodo").forEach(function (b) {
      b.classList.toggle("activa", b.getAttribute("data-metodo") === estado.metodo);
    });

    mostrar("#cobroEfectivo", estado.metodo === "efectivo");
    mostrar("#cobroReferencia", estado.metodo === "qr" || estado.metodo === "tarjeta");
    mostrar("#cobroMixto", estado.metodo === "mixto");

    if (estado.metodo === "mixto") {
      var ef = numero(($s("#mixtoEfectivo") || {}).value);
      var qr = numero(($s("#mixtoQr") || {}).value);
      var tj = numero(($s("#mixtoTarjeta") || {}).value);

      if (ef + qr + tj <= 0.004) {
        var total = calcularTotales().total;
        campo("#mixtoEfectivo", total > 0 ? total.toFixed(2) : "");
      }
      actualizarMixto();
    }

    actualizarCambio();
    actualizarAviso();
  }

  function mostrar(sel, visible) {
    var el = $s(sel);
    if (el) el.style.display = visible ? "" : "none";
  }

  function actualizarCambio() {
    var total = calcularTotales().total;
    var caja = $s("#cobroCambioCaja");
    var recibidoEl = $s("#cobroRecibido");
    var recibido = numero(recibidoEl ? recibidoEl.value : 0);
    var diferencia = n2(recibido - total);

    if (!caja) return;

    caja.classList.toggle("negativo", diferencia < -0.004);
    texto("#cobroCambio", diferencia < -0.004 ? bsNeg(diferencia) : bs(diferencia));

    if (estado.metodo === "efectivo") {
      actualizarAviso();
    }
  }

  function actualizarMixto() {
    var total = calcularTotales().total;
    var ef = numero(($s("#mixtoEfectivo") || {}).value);
    var qr = numero(($s("#mixtoQr") || {}).value);
    var tj = numero(($s("#mixtoTarjeta") || {}).value);
    var suma = n2(ef + qr + tj);
    var diferencia = n2(total - suma);
    var caja = $s("#mixtoEstado");

    texto("#mixtoSuma", bs(suma));

    if (!caja) return;

    caja.classList.remove("ok", "mal");

    if (Math.abs(diferencia) <= 0.011) {
      caja.classList.add("ok");
      texto("#mixtoTexto", "Pago completo");
    } else if (diferencia > 0) {
      caja.classList.add("mal");
      texto("#mixtoTexto", "Falta " + bs(diferencia));
    } else {
      caja.classList.add("mal");
      texto("#mixtoTexto", "Excede " + bs(-diferencia));
    }

    actualizarAviso();
  }

  function actualizarAviso() {
    var total = calcularTotales().total;
    var textoAviso = "";

    if (estado.metodo === "efectivo") {
      var recibido = numero(($s("#cobroRecibido") || {}).value);
      if (recibido + 0.005 < total) {
        textoAviso = "Faltan " + bs(total - recibido) + " para cubrir el total.";
      } else {
        textoAviso = "Recibido " + bs(recibido) + " · Cambio " + bs(recibido - total) + ".";
      }
    } else if (estado.metodo === "mixto") {
      textoAviso = "Reparte " + bs(total) + " entre los medios. La suma debe coincidir exactamente.";
    } else {
      textoAviso = "Cobra " + bs(total) + " por " + METODOS[estado.metodo] + ". La referencia es opcional.";
    }

    texto("#cobroAvisoTexto", textoAviso);
  }

  function restarResto(medio) {
    var total = calcularTotales().total;
    var ids = {
      efectivo: "#mixtoEfectivo",
      qr: "#mixtoQr",
      tarjeta: "#mixtoTarjeta",
    };
    var otros = 0;

    Object.keys(ids).forEach(function (k) {
      if (k === medio) return;
      otros += numero(($s(ids[k]) || {}).value);
    });

    var resto = n2(Math.max(0, total - otros));
    campo(ids[medio], resto > 0 ? resto.toFixed(2) : "");
    actualizarMixto();
  }

  function construirPayload() {
    var total = calcularTotales().total;
    var tipo = estado.tipoPedido;
    var deliveryEl = $s("#cobroDelivery");
    var delivery = deliveryEl ? deliveryEl.value.trim() : "";
    var obsEl = $s("#cobroObs");
    var observaciones = obsEl ? obsEl.value.trim() : "";

    if (tipo === "delivery" && delivery === "") {
      throw new Error("Escribe el nombre de la persona del delivery.");
    }

    if (!estado.idempotencia) {
      estado.idempotencia = nuevaClaveIdempotencia();
    }

    var payload = {
      id_sucursal: estado.idSucursal,
      idempotencia: estado.idempotencia,
      tipo_pedido: tipo,
      delivery_nombre: delivery,
      observaciones: observaciones,
      items: estado.carrito.map(function (l) {
        return {
          id_producto: Number(l.id_producto),
          cantidad: numero(l.cantidad),
        };
      }),
    };

    if (estado.metodo === "mixto") {
      var pagos = [];

      [
        ["efectivo", "#mixtoEfectivo"],
        ["qr", "#mixtoQr"],
        ["tarjeta", "#mixtoTarjeta"],
      ].forEach(function (par) {
        var monto = numero(($s(par[1]) || {}).value);
        if (monto > 0.004) {
          pagos.push({ metodo: par[0], monto: n2(monto) });
        }
      });

      var suma = n2(
        pagos.reduce(function (a, p) {
          return a + p.monto;
        }, 0)
      );

      if (pagos.length === 0) {
        throw new Error("Indica cómo se divide el pago mixto.");
      }

      if (Math.abs(suma - total) > 0.011) {
        throw new Error(
          "La suma del pago mixto es " + bs(suma) + " y el total es " + bs(total) + "."
        );
      }

      payload.metodo_pago = "mixto";
      payload.pagos = pagos;
      payload.referencia_pago = (($s("#cobroRefMixto") || {}).value || "").trim();
      return payload;
    }

    if (estado.metodo === "efectivo") {
      var recibidoEl = $s("#cobroRecibido");
      var recibido = numero(recibidoEl ? recibidoEl.value : total);

      if (recibidoEl && recibidoEl.value.trim() === "") {
        recibido = total;
      }

      if (recibido + 0.005 < total) {
        throw new Error("El efectivo recibido es menor que el total.");
      }

      payload.metodo_pago = "efectivo";
      payload.recibido = n2(recibido);
      return payload;
    }

    payload.metodo_pago = estado.metodo;
    payload.referencia_pago = (($s("#cobroRef") || {}).value || "").trim();
    return payload;
  }

  function confirmarCobro(evento) {
    if (evento) evento.preventDefault();

    // Candado GLOBAL (compartido aunque el script se cargue dos veces):
    // un solo cobro por envío. Evita ventas duplicadas por doble clic o
    // por listeners duplicados.
    if (estado.enviando || window.__POS_COBRO_EN_CURSO) return;

    var boton = $s("#cobroConfirmar");
    var etiqueta = $s("#cobroConfirmarTexto");
    var payload;

    try {
      payload = construirPayload();
    } catch (e) {
      return aviso("Revisa el cobro", e.message, "warning", 4200);
    }

    if (!window.PosAPI) {
      return aviso("Error", "No se cargó el módulo de red del POS", "error");
    }

    estado.enviando = true;
    window.__POS_COBRO_EN_CURSO = true;
    if (boton) boton.disabled = true;
    texto("#cobroConfirmarTexto", "Registrando…");

    window.PosAPI.registrarVenta(payload)
      .then(function (rpta) {
        if (!rpta || !rpta.success) {
          throw new Error((rpta && rpta.message) || "No se pudo registrar la venta");
        }
        return rpta.data || {};
      })
      .then(function (data) {
        var venta = data.venta || null;
        estado.ultimaVenta = venta;

        // Descontar stock local de los productos que lo controlan.
        estado.carrito.forEach(function (l) {
          if (!l.controla) return;
          var p = productoPorId(l.id_producto);
          if (p) {
            var restante = n2(stockProducto(p) - numero(l.cantidad));
            p.stock_pos = restante;
            p.stock_sucursal = restante;
          }
        });

        estado.carrito = [];
        estado.idempotencia = "";
        pintarCarrito();
        pintarProductos();

        cerrarCobro();
        cerrarCarrito();
        mostrarExito(venta);

        aviso("Venta registrada", venta ? venta.codigo : "Cobro guardado", "success");
      })
      .catch(function (e) {
        var msg = (e && e.message) || "Intenta de nuevo";
        aviso("No se pudo cobrar", msg, "error", 5200);

        if (/caja|sucursal|arqueo/i.test(msg)) {
          cargar(estado.idSucursal);
        }
      })
      .finally(function () {
        estado.enviando = false;
        window.__POS_COBRO_EN_CURSO = false;
        if (boton) boton.disabled = false;
        texto("#cobroConfirmarTexto", "Confirmar cobro");
      });
  }

  // =========================================================
  // ÉXITO / TICKET
  // =========================================================
  function mostrarExito(venta) {
    if (!venta) return;

    texto("#exitoCodigo", venta.codigo || "—");
    texto("#exitoTotal", bs(venta.total));

    var pedido = venta.tipo_pedido_label || (TIPOS[venta.tipo_pedido] ? TIPOS[venta.tipo_pedido].texto : "Para mesa");
    if (venta.tipo_pedido === "delivery" && venta.delivery_nombre) {
      pedido += " · " + venta.delivery_nombre;
    }
    texto("#exitoPedido", pedido);

    var pago = "";
    if (venta.metodo_pago === "mixto" && Array.isArray(venta.pagos)) {
      pago = venta.pagos
        .map(function (p) {
          return p.etiqueta + " " + bs(p.monto);
        })
        .join(" + ");
    } else {
      pago = venta.metodo_pago_label || METODOS[venta.metodo_pago] || "—";
    }
    texto("#exitoPago", pago);

    var mensaje = "El cobro se guardó correctamente.";
    if (numero(venta.cambio) > 0.004) {
      mensaje = "Cobro guardado · Cambio " + bs(venta.cambio);
    }
    texto("#exitoMensaje", mensaje);

    var modal = $s("#modalExito");
    if (!modal) return;
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var b = $s("#exitoNueva");
      if (b) b.focus();
    }, 90);
  }

  function cerrarExito() {
    var modal = $s("#modalExito");
    if (modal) {
      modal.classList.remove("abierto");
      modal.setAttribute("aria-hidden", "true");
    }
    if (!$s("#modalCobro.abierto") && !$s("#posCarrito.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function abrirTicket(descargar) {
    var v = estado.ultimaVenta;
    if (!v || !v.id || !window.PosAPI) return;
    var url = window.PosAPI.ticketUrl(v.id, !!descargar);
    window.open(url, "_blank", "noopener");
  }

  // =========================================================
  // EVENTOS
  // =========================================================
  function iniciar() {
    if (!window.PosAPI) {
      aviso("Error", "No se cargó el módulo de red del POS", "error");
      return;
    }

    cargar(0);

    // ---- Buscador ----
    var buscar = $s("#posBuscar");
    if (buscar) {
      buscar.addEventListener("input", function () {
        estado.filtro = this.value;
        filtrar();
      });
    }

    var limpiarBusqueda = $s("#posBuscarLimpiar");
    if (limpiarBusqueda) {
      limpiarBusqueda.addEventListener("click", function () {
        if (buscar) {
          buscar.value = "";
          buscar.focus();
        }
        estado.filtro = "";
        filtrar();
      });
    }

    // ---- Categorías ----
    var categorias = $s("#posCategorias");
    if (categorias) {
      categorias.addEventListener("click", function (e) {
        var b = e.target.closest("button[data-cat]");
        if (!b) return;

        estado.categoria = b.getAttribute("data-cat") || "";
        pintarCategorias();
        pintarProductos();
      });
    }

    // ---- Productos ----
    var grid = $s("#posProductos");
    if (grid) {
      grid.addEventListener("click", function (e) {
        var card = e.target.closest(".pos-producto");
        if (!card) return;

        if (card.classList.contains("agotado")) {
          return aviso("Agotado", "Este producto no tiene stock en la sucursal.", "warning");
        }

        agregarProducto(card.getAttribute("data-id"));
      });
    }

    // ---- Carrito ----
    var lineas = $s("#posCarritoLineas");
    if (lineas) {
      lineas.addEventListener("click", function (e) {
        var boton = e.target.closest("button[data-accion]");
        var fila = e.target.closest(".pos-linea");
        if (!boton || !fila) return;

        var id = fila.getAttribute("data-id");
        var accion = boton.getAttribute("data-accion");

        if (accion === "mas") cambiarCantidad(id, 1);
        else if (accion === "menos") cambiarCantidad(id, -1);
        else if (accion === "quitar") quitarLinea(id);
      });
    }

    var cobrar = $s("#posCobrar");
    if (cobrar) cobrar.addEventListener("click", abrirCobro);

    var vaciar = $s("#posLimpiar");
    if (vaciar) vaciar.addEventListener("click", vaciarCarrito);

    var cerrar = $s("#posCarritoCerrar");
    if (cerrar) cerrar.addEventListener("click", cerrarCarrito);

    var fab = $s("#posFabCarrito");
    if (fab) fab.addEventListener("click", abrirCarrito);

    var backdrop = $s("#posFabBackdrop");
    if (backdrop) backdrop.addEventListener("click", cerrarCarrito);

    var refrescar = $s("#posRefrescar");
    if (refrescar) {
      refrescar.addEventListener("click", function () {
        cargar(estado.idSucursal);
      });
    }

    var sucursal = $s("#posSucursal");
    if (sucursal) {
      sucursal.addEventListener("change", function () {
        estado.idSucursal = Number(this.value) || 0;
        estado.carrito = [];
        pintarCarrito();
        cargar(estado.idSucursal);
      });
    }

    // ---- Modal de cobro ----
    var form = $s("#formCobro");
    if (form) form.addEventListener("submit", confirmarCobro);

    ["#cobroCerrar", "#cobroCancelar"].forEach(function (sel) {
      var b = $s(sel);
      if (b) b.addEventListener("click", cerrarCobro);
    });

    var tipos = $s("#cobroTipos");
    if (tipos) {
      tipos.addEventListener("click", function (e) {
        var b = e.target.closest("button[data-tipo]");
        if (!b) return;
        seleccionarTipo(b.getAttribute("data-tipo"));
        if (estado.tipoPedido === "delivery") {
          setTimeout(function () {
            var input = $s("#cobroDelivery");
            if (input) input.focus();
          }, 60);
        }
      });
    }

    var metodos = $s("#cobroMetodos");
    if (metodos) {
      metodos.addEventListener("click", function (e) {
        var b = e.target.closest("button[data-metodo]");
        if (!b) return;
        estado.metodo = b.getAttribute("data-metodo") || "efectivo";
        aplicarMetodo();
      });
    }

    var recibido = $s("#cobroRecibido");
    if (recibido) recibido.addEventListener("input", actualizarCambio);

    var billetes = $s("#cobroBilletes");
    if (billetes) {
      billetes.addEventListener("click", function (e) {
        var b = e.target.closest("button[data-billete]");
        if (!b) return;

        var valor = b.getAttribute("data-billete");
        var total = calcularTotales().total;
        var monto = valor === "exacto" ? total : numero(valor);

        campo("#cobroRecibido", monto > 0 ? monto.toFixed(2) : "");
        actualizarCambio();
      });
    }

    ["#mixtoEfectivo", "#mixtoQr", "#mixtoTarjeta"].forEach(function (sel) {
      var input = $s(sel);
      if (input) input.addEventListener("input", actualizarMixto);
    });

    var restos = document.querySelectorAll(".ml-resto");
    Array.prototype.forEach.call(restos, function (b) {
      b.addEventListener("click", function () {
        restarResto(b.getAttribute("data-resto") || "efectivo");
      });
    });

    var delivery = $s("#cobroDelivery");
    if (delivery) {
      delivery.addEventListener("input", function () {
        estado.deliveryNombre = this.value.trim();
      });
    }

    // ---- Modal de éxito ----
    var ticket = $s("#exitoTicket");
    if (ticket) ticket.addEventListener("click", function () { abrirTicket(false); });

    var descargar = $s("#exitoDescargar");
    if (descargar) descargar.addEventListener("click", function () { abrirTicket(true); });

    var nueva = $s("#exitoNueva");
    if (nueva) {
      nueva.addEventListener("click", function () {
        cerrarExito();
        window.scrollTo({ top: 0, behavior: "smooth" });
        var b = $s("#posBuscar");
        if (b) b.focus();
      });
    }

    // ---- Clic fuera de los modales ----
    ["#modalCobro", "#modalExito"].forEach(function (sel) {
      var modal = $s(sel);
      if (!modal) return;

      modal.addEventListener("click", function (e) {
        if (e.target !== modal) return;
        if (sel === "#modalCobro") cerrarCobro();
        else cerrarExito();
      });
    });

    // ---- Teclado ----
    document.addEventListener("keydown", function (e) {
      if (e.repeat) return;

      if (e.key === "Escape") {
        if ($s("#modalExito.abierto")) {
          cerrarExito();
          return;
        }
        if ($s("#modalCobro.abierto")) {
          cerrarCobro();
          return;
        }
        if ($s("#posCarrito.abierto")) {
          cerrarCarrito();
        }
        return;
      }

      if (e.key === "F2") {
        e.preventDefault();
        e.stopPropagation();

        if (estado.enviando) return;
        if ($s("#modalCobro.abierto") || $s("#modalExito.abierto")) return;

        abrirCobro();
        return;
      }

      if (e.key === "F8") {
        e.preventDefault();
        e.stopPropagation();

        if (!$s("#modalCobro.abierto") || estado.enviando) return;

        var confirmar = $s("#cobroConfirmar");
        if (confirmar && !confirmar.disabled) confirmar.click();
        return;
      }

      if (e.key === "F9") {
        e.preventDefault();
        e.stopPropagation();

        if ($s("#modalCobro.abierto") || $s("#modalExito.abierto")) return;

        vaciarCarrito();
        return;
      }
    });

    // ---- Responsive ----
    window.addEventListener("resize", function () {
      if (window.innerWidth > 900 && $s("#posCarrito.abierto")) {
        cerrarCarrito();
      }
    });
  }

  document.addEventListener("DOMContentLoaded", iniciar);
})();
