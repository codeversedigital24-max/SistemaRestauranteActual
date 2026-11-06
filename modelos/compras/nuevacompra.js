/**
 * =====================================================
 * modelos/compras/nuevacompra.js
 * -----------------------------------------------------
 * Formulario de nueva compra:
 *   · catálogo filtrable por texto/categoría
 *   · carrito editable (cantidad, costo, precio venta)
 *   · totales con descuento e impuesto
 *   · validaciones cliente + confirmación
 * =====================================================
 */
(function () {
  "use strict";

  var $s = function (sel, ctxo) {
    return (ctxo || document).querySelector(sel);
  };

  var estado = {
    datos: null,
    productos: [],
    proveedores: [],
    carrito: [],
    categorias: [],
    guardando: false,
    editId: 0,
    compraOriginal: null,
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

  function redondear(valor, decimales) {
    var factor = Math.pow(10, decimales);
    return Math.round((Number(valor) + Number.EPSILON) * factor) / factor;
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
    var caja = $s("#ncCarga");
    var form = $s("#formCompra");

    if (caja) caja.classList.toggle("visible", Boolean(activar));
    if (form) form.style.display = activar ? "none" : "";
  }

  // =========================================================
  // CARGA
  // =========================================================
  function cargar(idSucursal, preservar) {
    if (!window.ComprasAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.ComprasAPI.datosNuevaCompra(idSucursal, estado.editId).then(function (rpta) {
      if (!rpta.success) {
        mostrarCarga(false);
        avisoGrande("No se pudo cargar", rpta.message || "Revisa la sucursal e intenta de nuevo", "error");
        return;
      }

      var d = rpta.data || {};
      estado.datos = d;
      estado.productos = d.productos || [];
      estado.proveedores = (d.proveedores || []).filter(function (p) {
        return Number(p.estado) === 1;
      });

      llenarSucursales(d.sucursales || [], d.id_sucursal);
      llenarProveedores(preservar ? preservar.id_proveedor : "");
      llenarCategorias();

      if ($s("#ncFecha")) $s("#ncFecha").value = (preservar && preservar.fecha_compra) || d.fecha_hoy || "";
      if ($s("#ncImpuestoPct")) $s("#ncImpuestoPct").value = preservar ? preservar.impuesto : (d.impuesto || 0);
      if ($s("#ncDescuento")) $s("#ncDescuento").value = preservar ? preservar.descuento || 0 : 0;
      if ($s("#ncTipo")) $s("#ncTipo").value = (preservar && preservar.tipo_documento) || "factura";
      if ($s("#ncPago")) $s("#ncPago").value = (preservar && preservar.forma_pago) || "contado";
      if ($s("#ncNumero")) $s("#ncNumero").value = (preservar && preservar.numero_documento) || "";
      if ($s("#ncObs")) $s("#ncObs").value = (preservar && preservar.observaciones) || "";
      if ($s("#ncAbono")) $s("#ncAbono").value = 0;

      if (estado.editId > 0 && d.compra) {
        aplicarEdicion(d.compra);
      }

      actualizarBadgeSucursal();
      renderCatalogo();
      renderCarrito();
      recalcularTotales();
      actualizarAbono();

      mostrarCarga(false);

      if (estado.proveedores.length === 0) {
        aviso("Sin proveedores activos", "Registra uno con el botón + para poder comprar", "warning", 5200);
      }

      limpiarErroresCabecera();
    });
  }

  /**
   * Precarga el formulario y el carrito con la compra a modificar.
   */
  function aplicarEdicion(c) {
    estado.compraOriginal = c;

    var fecha = String(c.fecha_compra_iso || "").replace(" ", "T").slice(0, 16);

    if ($s("#ncProveedor")) $s("#ncProveedor").value = String(c.id_proveedor || "");
    if ($s("#ncTipo")) $s("#ncTipo").value = c.tipo_documento || "factura";
    if ($s("#ncNumero")) $s("#ncNumero").value = c.numero_documento || "";
    if ($s("#ncFecha")) $s("#ncFecha").value = fecha;
    if ($s("#ncPago")) $s("#ncPago").value = c.forma_pago || "contado";
    if ($s("#ncObs")) $s("#ncObs").value = c.observaciones || "";
    if ($s("#ncDescuento")) $s("#ncDescuento").value = Number(c.descuento || 0);
    if ($s("#ncImpuestoPct")) $s("#ncImpuestoPct").value = Number(c.impuesto_porcentaje || 0);
    if ($s("#ncAbono")) $s("#ncAbono").value = Number(c.abono || 0);

    estado.carrito = (c.detalle || []).map(function (det) {
      var p = estado.productos.find(function (x) {
        return Number(x.id) === Number(det.id_producto);
      }) || {};

      return {
        id: det.id_producto,
        codigo: det.codigo || p.codigo || "",
        nombre: det.producto || p.nombre || "",
        imagen_url: det.imagen_url || p.imagen_url || "",
        stock: Number(p.stock || 0),
        cantidad: Number(det.cantidad || 0),
        costo: Number(det.costo_unitario || 0),
        precio: Number(det.precio_venta || 0),
      };
    });

    var boton = $s("#ncGuardar span");
    if (boton) boton.textContent = "Guardar cambios";
  }

  function actualizarAbono() {
    var credito = $s("#ncPago") && $s("#ncPago").value === "credito";
    var filaAbono = $s("#ncAbonoFila");
    var filaSaldo = $s("#ncSaldoFila");

    if (filaAbono) filaAbono.style.display = credito ? "" : "none";
    if (filaSaldo) filaSaldo.style.display = credito ? "" : "none";

    recalcularTotales();
  }

  function llenarSucursales(sucursales, seleccionada) {
    var select = $s("#ncSucursal");
    if (!select) return;

    var esSuper = Boolean(estado.datos && estado.datos.usuario && estado.datos.usuario.es_superadmin);

    if (!esSuper && sucursales.length === 1) {
      select.innerHTML = '<option value="' + Number(sucursales[0].id_sucursal) + '">' +
        escapar(sucursales[0].nombre_sucursal) + (sucursales[0].ciudad ? " · " + escapar(sucursales[0].ciudad) : "") +
        "</option>";
      select.value = String(sucursales[0].id_sucursal);
      select.disabled = true;
      return;
    }

    select.disabled = false;
    select.innerHTML = sucursales.map(function (s) {
      return '<option value="' + Number(s.id_sucursal) + '">' +
        escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
        "</option>";
    }).join("");

    if (seleccionada) {
      select.value = String(seleccionada);
    }

    // En edición la sucursal no cambia: el stock se ajusta
    // sobre el inventario donde se registró la compra.
    if (estado.editId > 0) {
      select.disabled = true;
    }
  }

  function llenarProveedores(seleccion) {
    var select = $s("#ncProveedor");
    if (!select) return;

    if (!estado.proveedores.length) {
      select.innerHTML = '<option value="">— Registra un proveedor —</option>';
      return;
    }

    var opciones = ['<option value="">— Selecciona el proveedor —</option>'];

    estado.proveedores.forEach(function (p) {
      opciones.push(
        '<option value="' + Number(p.id) + '">' + escapar(p.nombre) +
        (p.nit ? " · NIT " + escapar(p.nit) : "") +
        "</option>"
      );
    });

    select.innerHTML = opciones.join("");

    if (seleccion && select.querySelector('option[value="' + seleccion + '"]')) {
      select.value = String(seleccion);
    }
  }

  function llenarCategorias() {
    var select = $s("#ncCategoria");
    if (!select) return;

    var mapa = {};
    estado.categorias = [];

    estado.productos.forEach(function (p) {
      if (!mapa[p.id_categoria]) {
        mapa[p.id_categoria] = true;
        estado.categorias.push({ id: p.id_categoria, nombre: p.categoria });
      }
    });

    var actual = select.value;

    select.innerHTML = '<option value="">Todas las categorías</option>' +
      estado.categorias.map(function (c) {
        return '<option value="' + Number(c.id) + '">' + escapar(c.nombre) + "</option>";
      }).join("");

    if (actual && select.querySelector('option[value="' + actual + '"]')) {
      select.value = actual;
    }
  }

  function actualizarBadgeSucursal() {
    var select = $s("#ncSucursal");
    var badge = $s("#ncSucursalBadge");
    if (!select || !badge) return;

    var opcion = select.options[select.selectedIndex];
    var texto = opcion ? opcion.textContent : "";

    if (texto) {
      badge.style.display = "";
      var span = badge.querySelector("span");
      if (span) span.textContent = texto;
    } else {
      badge.style.display = "none";
    }
  }

  // =========================================================
  // CATÁLOGO
  // =========================================================
  function productosFiltrados() {
    var texto = ($s("#ncBuscar") ? $s("#ncBuscar").value : "").trim().toLowerCase();
    var categoria = $s("#ncCategoria") ? $s("#ncCategoria").value : "";

    return estado.productos.filter(function (p) {
      if (categoria && String(p.id_categoria) !== String(categoria)) return false;

      if (!texto) return true;

      return (
        String(p.nombre).toLowerCase().indexOf(texto) !== -1 ||
        String(p.codigo).toLowerCase().indexOf(texto) !== -1 ||
        String(p.categoria).toLowerCase().indexOf(texto) !== -1
      );
    });
  }

  function estaEnCarrito(id) {
    return estado.carrito.some(function (l) {
      return Number(l.id) === Number(id);
    });
  }

  function renderCatalogo() {
    var caja = $s("#ncProductos");
    var contador = $s("#ncProductosCount");
    if (!caja) return;

    var lista = productosFiltrados();

    if (contador) contador.textContent = String(lista.length);

    if (!lista.length) {
      caja.innerHTML =
        '<div class="nc-vacio"><i class="fa-solid fa-box-open"></i>' +
        "<span>No hay productos que coincidan con el filtro.</span>" +
        '<span style="font-size:11px">Revisa la categoría o el texto de búsqueda.</span></div>';
      return;
    }

    var scroll = caja.scrollTop;

    caja.innerHTML = '<div class="nc-prod-grid">' + lista.map(function (p) {
      var enCarrito = estado.carrito.find(function (l) {
        return Number(l.id) === Number(p.id);
      });

      var costo = Number(p.costo_sucursal) > 0 ? Number(p.costo_sucursal) : Number(p.costo);
      var precio = Number(p.precio_sucursal) > 0 ? Number(p.precio_sucursal) : Number(p.precio);
      var margen = precio > 0 ? ((precio - costo) / precio) * 100 : 0;
      var stock = Number(p.stock_global != null ? p.stock_global : p.stock);

      var imagen = p.imagen_url
        ? '<img src="' + escapar(p.imagen_url) + '" alt="" loading="lazy" />'
        : '<i class="fa-solid ' + escapar(p.categoria_icono || "fa-utensils") + '"></i>';

      return (
        '<article class="nc-prod' + (enCarrito ? " nc-en-carrito" : "") + '">' +
        '<span class="np-img">' + imagen + "</span>" +
        '<div class="np-info">' +
        '<span class="np-nombre" title="' + escapar(p.nombre) + '">' + escapar(p.nombre) + "</span>" +
        '<span class="np-meta">' +
        '<span class="badge-mod badge-ambar" style="font-size:9px;padding:1px 6px">' + escapar(p.codigo) + "</span>" +
        "<span>" + escapar(p.categoria) + "</span>" +
        "</span>" +
        '<span class="np-precios">' +
        "<span>Costo: <b>" + bs(costo) + "</b></span>" +
        "<span>Venta: <b>" + bs(precio) + "</b></span>" +
        "</span>" +
        '<span class="np-pie">' +
        '<span class="np-margen' + (margen < 0 ? " bajo" : "") + '">' +
        (margen < 0 ? "Bajo costo" : "Margen " + numero(margen, 0) + " %") +
        "</span>" +
        '<span class="np-meta" style="font-size:9.5px">Stock: <b style="color:' +
        (stock > 0 ? "#14803d" : "#c62828") + '">' + numero(stock, 2) + "</b></span>" +
        "</span>" +
        '<div style="display:flex;justify-content:flex-end;margin-top:3px">' +
        '<button type="button" class="np-agregar' + (enCarrito ? " agregado" : "") + '" data-agregar="' + Number(p.id) + '">' +
        '<i class="fa-solid fa-' + (enCarrito ? "check" : "plus") + '"></i> ' +
        (enCarrito ? "Agregado · " + numero(enCarrito.cantidad, 2) : "Agregar") +
        "</button>" +
        "</div>" +
        "</div>" +
        "</article>"
      );
    }).join("") + "</div>";

    caja.scrollTop = scroll;
  }

  // =========================================================
  // CARRITO
  // =========================================================
  function agregarProducto(id) {
    var p = estado.productos.find(function (x) {
      return Number(x.id) === Number(id);
    });

    if (!p) return;

    var linea = estado.carrito.find(function (l) {
      return Number(l.id) === Number(id);
    });

    if (linea) {
      linea.cantidad = redondear(Number(linea.cantidad) + 1, 3);
    } else {
      estado.carrito.push({
        id: p.id,
        codigo: p.codigo,
        nombre: p.nombre,
        imagen_url: p.imagen_url,
        stock: Number(p.stock_global != null ? p.stock_global : p.stock),
        cantidad: 1,
        costo: Number(p.costo_sucursal) > 0 ? Number(p.costo_sucursal) : Number(p.costo),
        precio: Number(p.precio_sucursal) > 0 ? Number(p.precio_sucursal) : Number(p.precio),
      });
    }

    renderCarrito();
    renderCatalogo();
    recalcularTotales();
  }

  function renderCarrito() {
    var caja = $s("#ncCarrito");
    var contador = $s("#ncCarritoCount");
    if (!caja) return;

    if (contador) {
      contador.textContent = estado.carrito.length + (estado.carrito.length === 1 ? " ítem" : " ítems");
    }

    if (!estado.carrito.length) {
      caja.innerHTML =
        '<div class="nc-vacio"><i class="fa-solid fa-cart-plus"></i>' +
        "<span>Agrega productos desde el catálogo</span></div>";
      return;
    }

    var filas = estado.carrito.map(function (l) {
      return (
        '<tr data-linea="' + Number(l.id) + '">' +
        '<td><span class="nl-nombre" title="' + escapar(l.nombre) + '">' + escapar(l.nombre) + "</span>" +
        '<span class="nl-codigo">' + escapar(l.codigo) + "</span></td>" +
        '<td><input type="number" min="0.001" step="0.001" value="' + l.cantidad + '" data-campo="cantidad" data-id="' + Number(l.id) + '" aria-label="Cantidad" /></td>' +
        '<td><input type="number" min="0" step="0.01" value="' + l.costo + '" data-campo="costo" data-id="' + Number(l.id) + '" aria-label="Costo unitario" /></td>' +
        '<td><input type="number" min="0.01" step="0.01" value="' + l.precio + '" data-campo="precio" data-id="' + Number(l.id) + '" aria-label="Precio de venta" /></td>' +
        '<td><span class="nl-sub" id="nlSub-' + Number(l.id) + '">' + bs(l.cantidad * l.costo) + "</span></td>" +
        '<td><button type="button" class="nc-quitar" data-quitar="' + Number(l.id) + '" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button></td>' +
        "</tr>"
      );
    }).join("");

    caja.innerHTML =
      '<table class="nc-cart-table">' +
      "<thead><tr><th>Producto</th><th>Cant.</th><th>Costo</th><th>P. venta</th><th>Subtotal</th><th></th></tr></thead>" +
      "<tbody>" + filas + "</tbody></table>";
  }

  function actualizarLinea(id, campo, valor) {
    var linea = estado.carrito.find(function (l) {
      return Number(l.id) === Number(id);
    });

    if (!linea) return;

    var n = parseFloat(String(valor).replace(",", "."));
    linea[campo] = isNaN(n) ? 0 : n;

    var sub = $s("#nlSub-" + id);
    if (sub) sub.textContent = bs(redondear(linea.cantidad * linea.costo, 2));

    recalcularTotales();
  }

  function quitarLinea(id) {
    estado.carrito = estado.carrito.filter(function (l) {
      return Number(l.id) !== Number(id);
    });

    renderCarrito();
    renderCatalogo();
    recalcularTotales();
  }

  function recalcularTotales() {
    var subtotal = 0;

    estado.carrito.forEach(function (l) {
      subtotal += redondear(Number(l.cantidad) * Number(l.costo), 2);
    });

    subtotal = redondear(subtotal, 2);

    var descuento = Number($s("#ncDescuento") ? $s("#ncDescuento").value : 0) || 0;
    var pct = Number($s("#ncImpuestoPct") ? $s("#ncImpuestoPct").value : 0) || 0;

    if (descuento < 0) descuento = 0;
    if (pct < 0) pct = 0;
    if (pct > 100) pct = 100;

    var base = Math.max(0, redondear(subtotal - descuento, 2));
    var impuesto = redondear(base * (pct / 100), 2);
    var total = redondear(base + impuesto, 2);

    var set = function (sel, texto) {
      var el = $s(sel);
      if (el) el.textContent = texto;
    };

    set("#ncSubtotal", bs(subtotal));
    set("#ncImpuesto", bs(impuesto));
    set("#ncTotal", bs(total));

    var credito = $s("#ncPago") && $s("#ncPago").value === "credito";
    var abono = Number($s("#ncAbono") ? $s("#ncAbono").value : 0) || 0;

    if (abono < 0) abono = 0;
    if (abono > total) {
      abono = total;
      if ($s("#ncAbono")) $s("#ncAbono").value = redondear(abono, 2);
    }

    var saldo = credito ? Math.max(0, redondear(total - abono, 2)) : 0;
    set("#ncSaldo", bs(saldo));

    var descEl = $s("#ncDescuento");
    if (descEl) descEl.classList.toggle("error", descuento > subtotal);

    return { subtotal: subtotal, descuento: descuento, pct: pct, impuesto: impuesto, total: total, abono: abono, saldo: saldo };
  }

  // =========================================================
  // VALIDACIÓN Y ENVÍO
  // =========================================================
  function marcarCabecera(campo, mensaje) {
    var cont = document.querySelector('#formCompra [data-campo="' + campo + '"]');
    if (!cont) return;

    cont.classList.toggle("error", Boolean(mensaje));

    var ayuda = cont.querySelector(".ayuda");
    if (!ayuda) return;

    if (mensaje) {
      if (!ayuda.dataset.original) ayuda.dataset.original = ayuda.textContent;
      ayuda.textContent = mensaje;
      ayuda.classList.add("error");
    } else if (ayuda.dataset.original) {
      ayuda.textContent = ayuda.dataset.original;
      ayuda.classList.remove("error");
    }
  }

  function limpiarErroresCabecera() {
    document.querySelectorAll("#formCompra .nc-cabecera .campo.error").forEach(function (c) {
      marcarCabecera(c.getAttribute("data-campo"), "");
    });

    document.querySelectorAll("#ncCarrito input.error").forEach(function (i) {
      i.classList.remove("error");
    });
  }

  function leerCompra() {
    var totales = recalcularTotales();
    var esCredito = ($s("#ncPago") ? $s("#ncPago").value : "contado") === "credito";

    return {
      id: estado.editId,
      id_sucursal: Number($s("#ncSucursal") ? $s("#ncSucursal").value : 0) || 0,
      id_proveedor: Number($s("#ncProveedor") ? $s("#ncProveedor").value : 0) || 0,
      tipo_documento: $s("#ncTipo") ? $s("#ncTipo").value : "factura",
      numero_documento: $s("#ncNumero") ? $s("#ncNumero").value.trim() : "",
      fecha_compra: $s("#ncFecha") ? $s("#ncFecha").value : "",
      forma_pago: $s("#ncPago") ? $s("#ncPago").value : "contado",
      abono: esCredito ? (Number($s("#ncAbono") ? $s("#ncAbono").value : 0) || 0) : totales.total,
      observaciones: $s("#ncObs") ? $s("#ncObs").value.trim() : "",
      descuento: totales.descuento,
      impuesto_porcentaje: totales.pct,
      items: estado.carrito.map(function (l) {
        return {
          id_producto: l.id,
          cantidad: Number(l.cantidad),
          costo_unitario: Number(l.costo),
          precio_venta: Number(l.precio),
        };
      }),
      totales: totales,
      pdf: Boolean($s("#ncPdf") && $s("#ncPdf").checked),
    };
  }

  function validar(d) {
    limpiarErroresCabecera();
    var fallos = [];

    if (!d.id_sucursal) {
      marcarCabecera("sucursal", "Selecciona la sucursal");
      fallos.push("sucursal");
    }

    if (!d.id_proveedor) {
      marcarCabecera("proveedor", "Selecciona el proveedor");
      fallos.push("proveedor");
    }

    if (!d.fecha_compra) {
      marcarCabecera("fecha", "Indica la fecha de compra");
      fallos.push("fecha");
    } else if (new Date(d.fecha_compra) > new Date()) {
      marcarCabecera("fecha", "La fecha no puede ser futura");
      fallos.push("fecha");
    }

    if (d.numero_documento.length > 60) {
      marcarCabecera("numero_documento", "Máximo 60 caracteres");
      fallos.push("número de documento");
    }

    if (!d.items.length) {
      aviso("Carrito vacío", "Agrega al menos un producto a la compra", "warning", 4500);
      fallos.push("productos");
    }

    var invalidas = 0;

    d.items.forEach(function (item) {
      var fila = document.querySelector('#ncCarrito tr[data-linea="' + item.id_producto + '"]');
      var inputs = fila ? fila.querySelectorAll("input") : [];

      inputs.forEach(function (i) {
        i.classList.remove("error");
      });

      if (!(item.cantidad > 0) || item.cantidad > 999999) {
        invalidas++;
        if (fila) fila.querySelector('[data-campo="cantidad"]').classList.add("error");
      }

      if (item.costo_unitario < 0) {
        invalidas++;
        if (fila) fila.querySelector('[data-campo="costo"]').classList.add("error");
      }

      if (!(item.precio_venta > 0)) {
        invalidas++;
        if (fila) fila.querySelector('[data-campo="precio"]').classList.add("error");
      }
    });

    if (invalidas) {
      aviso("Revisa el carrito", "Cantidad, costo y precio de venta deben ser válidos", "warning", 5000);
      fallos.push("líneas");
    }

    if (d.descuento > d.totales.subtotal) {
      aviso("Descuento inválido", "No puede superar el subtotal de la compra", "warning", 4500);
      fallos.push("descuento");
    }

    if (d.impuesto_porcentaje < 0 || d.impuesto_porcentaje > 100) {
      aviso("Impuesto inválido", "Debe estar entre 0 y 100 %", "warning", 4500);
      fallos.push("impuesto");
    }

    if (d.forma_pago === "credito") {
      var abono = Number(d.abono || 0);

      if (abono < 0) {
        aviso("Abono inválido", "El abono inicial no puede ser negativo", "warning", 4500);
        fallos.push("abono");
      } else if (abono > d.totales.total + 0.005) {
        aviso("Abono inválido", "El abono no puede superar el total de la compra", "warning", 4500);
        fallos.push("abono");
      }
    }

    return fallos;
  }

  function resumenConfirmacion(d) {
    var proveedor = estado.proveedores.find(function (p) {
      return Number(p.id) === Number(d.id_proveedor);
    });

    var sucursal = estado.datos && estado.datos.sucursales
      ? estado.datos.sucursales.find(function (s) {
        return Number(s.id_sucursal) === Number(d.id_sucursal);
      })
      : null;

    var pago = "";

    if (d.forma_pago === "credito") {
      pago =
        '<div style="margin-top:5px;padding:7px 9px;background:#e8f2fe;border-radius:8px;font-size:12px;color:#1f4f8a">' +
        "<b>Crédito:</b> abono inicial " + bs(d.abono) +
        " · saldo pendiente <b>" + bs(d.totales.saldo) + "</b>" +
        "</div>";
    }

    return (
      '<div style="text-align:left;font-size:13px;line-height:1.6">' +
      "<div><b>Proveedor:</b> " + escapar(proveedor ? proveedor.nombre : "—") + "</div>" +
      "<div><b>Sucursal:</b> " + escapar(sucursal ? sucursal.nombre_sucursal : "—") + "</div>" +
      "<div><b>Líneas:</b> " + d.items.length + " · <b>Total:</b> " +
      '<b style="color:#d9232d">' + bs(d.totales.total) + "</b></div>" +
      pago +
      '<div style="margin-top:6px;padding:7px 9px;background:#fff4d6;border-radius:8px;font-size:12px;color:#9a6b00">' +
      (estado.editId > 0
        ? "Se ajustará el stock por la diferencia de cantidades y se conservará el historial del kardex."
        : "Se actualizará el stock, el costo promedio, el precio de venta y el kardex de esta sucursal.") +
      "</div></div>"
    );
  }

  function enviar(d) {
    if (estado.guardando) return;

    estado.guardando = true;

    var boton = $s("#ncGuardar");
    if (boton) {
      boton.disabled = true;
      if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
      boton.innerHTML = '<span class="mini-spin"></span> <span>' +
        (estado.editId > 0 ? "Guardando…" : "Registrando…") + "</span>";
    }

    var promesa = estado.editId > 0
      ? window.ComprasAPI.actualizarCompra(d)
      : window.ComprasAPI.registrarCompra(d);

    promesa.then(function (rpta) {
      estado.guardando = false;

      if (boton) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.html;
      }

      if (!rpta.success) {
        avisoGrande(
          estado.editId > 0 ? "No se pudo actualizar" : "No se pudo registrar",
          rpta.message || "Revisa los datos e intenta de nuevo",
          "error"
        );
        return;
      }

      var compra = (rpta.data && rpta.data.compra) || {};
      var id = Number((rpta.data && rpta.data.id) || estado.editId || 0);
      var pdfUrl = window.ComprasAPI.reciboUrl(id);

      Swal.fire({
        icon: "success",
        title: estado.editId > 0 ? "Compra actualizada" : "Compra registrada",
        html:
          '<div style="font-size:14px">' +
          "Compra <b>#" + id + "</b> por <b style='color:#d9232d'>" +
          bs(compra.total || d.totales.total) + "</b>.<br>" +
          '<span style="font-size:12.5px;color:#6b6165">' +
          (estado.editId > 0
            ? "El stock y el kardex se ajustaron por la diferencia."
            : "El kardex de la sucursal ya refleja la entrada.") +
          "</span></div>",
        showDenyButton: true,
        confirmButtonText: "Ir al historial",
        denyButtonText: "Ver recibo PDF",
        cancelButtonText: estado.editId > 0 ? "Seguir aquí" : "Registrar otra",
        showCancelButton: true,
        confirmButtonColor: "#d9232d",
        denyButtonColor: "#1f6fd0",
        cancelButtonColor: "#6b6165",
        reverseButtons: true,
        allowOutsideClick: false,
      }).then(function (resp) {
        if (resp.isDenied) {
          var ventana = window.open(pdfUrl, "_blank");

          if (!ventana) {
            aviso("Ventana bloqueada", "Permite las ventanas emergentes para ver el recibo PDF", "warning", 6000);
          }

          return;
        }

        if (resp.isConfirmed) {
          window.location.href = "compras.php";
          return;
        }

        if (estado.editId > 0) {
          return;
        }

        // Registrar otra: conserva proveedor, tipo, pago e impuesto.
        var preservar = {
          id_proveedor: d.id_proveedor,
          tipo_documento: d.tipo_documento,
          forma_pago: d.forma_pago,
          impuesto: d.impuesto_porcentaje,
          descuento: 0,
          numero_documento: "",
          observaciones: "",
        };

        estado.carrito = [];
        renderCarrito();
        recalcularTotales();
        actualizarAbono();
        cargar(d.id_sucursal, preservar);
      });
    });
  }

  function submitir(ev) {
    ev.preventDefault();

    var d = leerCompra();
    var fallos = validar(d);

    if (fallos.length) {
      aviso("Revisa el formulario", "Faltan datos: " + fallos.join(", "), "warning", 5000);
      return;
    }

    Swal.fire({
      icon: "question",
      title: "¿Registrar esta compra?",
      html: resumenConfirmacion(d),
      showCancelButton: true,
      confirmButtonText: "Sí, registrar",
      cancelButtonText: "Revisar",
      confirmButtonColor: "#d9232d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (resp) {
      if (resp.isConfirmed) enviar(d);
    });
  }

  // =========================================================
  // PROVEEDOR RÁPIDO
  // =========================================================
  function abrirProveedorRapido() {
    var modal = $s("#modalProveedor");
    if (!modal) return;

    var form = $s("#formProveedor");
    if (form) form.reset();

    var id = $s("#pr_id");
    if (id) id.value = "";

    var titulo = $s("#mProvTexto");
    if (titulo) titulo.textContent = "Nuevo proveedor";

    var nota = $s("#mProvNota");
    if (nota) nota.textContent = "Se guardará y quedará seleccionado";

    limpiarErroresProveedor();

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      if ($s("#pr_nombre")) $s("#pr_nombre").focus();
    }, 60);
  }

  function cerrarProveedorRapido() {
    var modal = $s("#modalProveedor");
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

  function guardarProveedorRapido(ev) {
    ev.preventDefault();

    var valor = function (id) {
      var el = document.getElementById(id);
      return el ? el.value.trim() : "";
    };

    var d = {
      nombre: valor("pr_nombre"),
      razon_social: valor("pr_razon"),
      nit: valor("pr_nit"),
      contacto: valor("pr_contacto"),
      telefono: valor("pr_telefono"),
      email: valor("pr_email"),
      ciudad: valor("pr_ciudad"),
      direccion: valor("pr_direccion"),
      notas: valor("pr_notas"),
      estado: 1,
    };

    limpiarErroresProveedor();

    if (d.nombre.length < 3) {
      marcarErrorProveedor("nombre", "Mínimo 3 caracteres");
      aviso("Revisa el formulario", "El nombre del proveedor es obligatorio", "warning");
      return;
    }

    if (d.email !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)) {
      marcarErrorProveedor("email", "Correo no válido");
      aviso("Revisa el formulario", "El correo no es válido", "warning");
      return;
    }

    var boton = $s("#mProvGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

    window.ComprasAPI.proveedorCrear(d).then(function (rpta) {
      boton.disabled = false;
      boton.innerHTML = boton.dataset.html;

      if (!rpta.success) {
        aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5000);
        return;
      }

      var nuevoId = Number(rpta.data.id || 0);

      cerrarProveedorRapido();
      aviso("Proveedor creado", d.nombre + " quedó seleccionado", "success");

      window.ComprasAPI.proveedores().then(function (res) {
        if (!res.success) return;

        estado.proveedores = (res.data.proveedores || []).filter(function (p) {
          return Number(p.estado) === 1;
        });

        llenarProveedores(nuevoId);
      });
    });
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    var formulario = $s("#formCompra");
    estado.editId = Number(formulario ? formulario.getAttribute("data-id") : 0) || 0;

    cargar();

    var productos = $s("#ncProductos");
    if (productos) {
      productos.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-agregar]");
        if (!boton) return;
        agregarProducto(Number(boton.getAttribute("data-agregar")));
      });
    }

    var carrito = $s("#ncCarrito");
    if (carrito) {
      carrito.addEventListener("input", function (ev) {
        var input = ev.target.closest("input[data-campo]");
        if (!input) return;
        input.classList.remove("error");
        actualizarLinea(Number(input.getAttribute("data-id")), input.getAttribute("data-campo"), input.value);
      });

      carrito.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-quitar]");
        if (!boton) return;
        quitarLinea(Number(boton.getAttribute("data-quitar")));
      });
    }

    var buscar = $s("#ncBuscar");
    if (buscar) {
      buscar.addEventListener("input", function () {
        renderCatalogo();
      });
    }

    var categoria = $s("#ncCategoria");
    if (categoria) categoria.addEventListener("change", renderCatalogo);

    ["ncDescuento", "ncImpuestoPct"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("input", recalcularTotales);
    });

    var pago = $s("#ncPago");
    if (pago) pago.addEventListener("change", actualizarAbono);

    var abono = $s("#ncAbono");
    if (abono) abono.addEventListener("input", recalcularTotales);

    var sucursal = $s("#ncSucursal");
    if (sucursal) {
      sucursal.addEventListener("change", function () {
        var nueva = Number(sucursal.value || 0);

        if (!nueva) return;

        var cambiar = function () {
          estado.carrito = [];
          renderCarrito();
          recalcularTotales();
          cargar(nueva);
        };

        if (!estado.carrito.length) {
          cambiar();
          return;
        }

        Swal.fire({
          icon: "warning",
          title: "¿Cambiar de sucursal?",
          text: "El carrito se vaciará porque el stock y los precios son independientes por sucursal.",
          showCancelButton: true,
          confirmButtonText: "Sí, cambiar",
          cancelButtonText: "Cancelar",
          confirmButtonColor: "#d9232d",
          cancelButtonColor: "#9aa0a6",
          reverseButtons: true,
        }).then(function (resp) {
          if (resp.isConfirmed) {
            cambiar();
          } else {
            sucursal.value = String(estado.datos.id_sucursal || "");
            actualizarBadgeSucursal();
          }
        });
      });
    }

    var btnProv = $s("#ncNuevoProveedor");
    if (btnProv) btnProv.addEventListener("click", abrirProveedorRapido);

    var formProv = $s("#formProveedor");
    if (formProv) {
      formProv.addEventListener("submit", guardarProveedorRapido);

      formProv.addEventListener("input", function (ev) {
        var caja = ev.target.closest(".campo");
        if (caja && caja.classList.contains("error")) {
          marcarErrorProveedor(caja.getAttribute("data-campo"), "");
        }
      });
    }

    ["mProvCerrar", "mProvCancelar"].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.addEventListener("click", cerrarProveedorRapido);
    });

    var modalProv = $s("#modalProveedor");
    if (modalProv) {
      modalProv.addEventListener("click", function (ev) {
        if (ev.target === modalProv) cerrarProveedorRapido();
      });
    }

    var form = $s("#formCompra");
    if (form) {
      form.addEventListener("submit", submitir);
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;
      if ($s("#modalProveedor.abierto")) cerrarProveedorRapido();
    });
  });
})();
