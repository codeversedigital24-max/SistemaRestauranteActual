// =========================================================
// POS - PRODUCT MODAL FIX
// =========================================================
(function () {
  "use strict";

  var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $todos = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  var estado = {
    modal: null,
    productos: [],
    cargando: false,
    sucursal: 0,
    filtros: {
      busqueda: "",
      categoria: "",
    },
  };

  // =========================================================
  // UTILIDADES
  // =========================================================
  function escapar(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function bs(n) {
    n = Number(n) || 0;
    return "Bs " + n.toLocaleString("es-BO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatoBs(valor) {
    return "Bs " + Number(valor || 0).toLocaleString("es-BO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function chipHtml(texto, tipo) {
    var classes = "badge-mod " + (tipo === "rojo" ? "badge-rojo" : (tipo === "verde" ? "badge-verde" : (tipo === "ambar" ? "badge-ambar" : "badge-gris")));
    return '<span class="' + classes + '">' + escapar(texto) + "</span>";
  }

  function normalizar(texto) {
    var s = String(texto || "").toLowerCase();
    if (s.normalize) s = s.normalize("NFD").replace(/[\\u0300-\\u036f]/g, "");
    return s.trim();
  }

  // =========================================================
  // CARGA
  // =========================================================
  function cargarProductos() {
    if (estado.cargando) return;
    estado.cargando = true;

    var carga = $s("#posModalCargaProductos");
    if (carga) carga.style.display = "flex";

    // Usar la API del POS para obtener los productos del POS
    if (!window.PosAPI) {
      aviso("Error", "Módulo de POS no disponible", "error");
      if (carga) carga.style.display = "none";
      estado.cargando = false;
      return;
    }

    // Obtener los productos del POS a través de la API
    window.PosAPI.cargar(estado.sucursal).then(function (rpta) {
      if (carga) carga.style.display = "none";
      estado.cargando = false;

      if (!rpta.success) {
        aviso("Error", rpta.message || "No se pudieron cargar los productos", "error");
        return;
      }

      // Extraer los productos del POS
      estado.productos = Array.isArray(rpta.data.productos) ? rpta.data.productos : [];
      pintarModalProductos();
      pintarCategoriasModal();
    }).catch(function () {
      if (carga) carga.style.display = "none";
      estado.cargando = false;
      aviso("Error", "Error de red al cargar productos", "error");
    });
  }

  // =========================================================
  // MODAL
  // =========================================================
  function abrirModal() {
    var modal = $s("#posModalProductos");
    if (!modal) return;

    estado.modal = modal;

    // Cargar productos cuando se abre el modal
    if (!estado.productos.length) {
      cargarProductos();
    } else {
      pintarModalProductos();
      pintarCategoriasModal();
    }

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    // Enfocar el buscador si existe
    var buscador = $s("#posModalBuscar");
    if (buscador) buscador.focus();
  }

  function cerrarModal() {
    var modal = $s("#posModalProductos");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");

    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    estado.modal = null;
  }

  // =========================================================
  // CATÁLOGO DE PRODUCTOS (MODAL)
  // =========================================================
  function pintarModalProductos() {
    var grid = $s("#posModalGridProductos");
    var vacio = $s("#posModalVacioProductos");
    var conteo = $s("#posModalConteoProductos");

    if (!grid) return;

    var productosFiltrados = estado.productos.filter(function (p) {
      var busqueda = normalizar(estado.filtros.busqueda);
      var categoria = estado.filtros.categoria;

      if (categoria && String(p.id_categoria) !== String(categoria)) {
        return false;
      }

      if (!busqueda) return true;

      var bolsa = normalizar(p.nombre + " " + (p.codigo || "") + " " + (p.descripcion || "") + " " + (p.categoria || ""));
      return bolsa.indexOf(busqueda) !== -1;
    });

    if (conteo) conteo.textContent = productosFiltrados.length;

    if (vacio) {
      vacio.style.display = productosFiltrados.length === 0 ? "flex" : "none";
    }

    // Si no hay productos en absoluto, intentar cargar
    if (estado.productos.length === 0 && !estado.cargando) {
      cargarProductos();
      return;
    }

    // Renderizar productos con diseño mejorado para móvil
    grid.innerHTML = productosFiltrados.map(function (p) {
      var image = p.imagen_principal_url || p.imagen_url || "";
      var hayImagen = !!image;
      var esDestacado = p.destacado === 1;
      var hayPromo = p.oferta_vigente === 1 && p.descuento_real > 0;
      var hayOferta = p.descuento_pos > 0.004;
      var controlaStock = p.control_stock === 1;
      var stock = controlaStock ? (p.stock_sucursal || 0) : 0;
      var stockBajo = controlaStock && stock > 0 && stock <= 5;
      var agotado = controlaStock && stock <= 0;
      var iva = p.impuesto_pos || 0;

      return (
        '<button type="button" class="pos-modal-prod" data-id="' + p.id + '" ' + (agotado ? 'disabled title="Agotado"' : '') + '>\n' +
        '  <div class="pmp-media">\n' +
        (hayImagen
          ? '    <img src="' + escapar(image) + '" alt="" loading="lazy" onerror="this.remove(); this.insertAdjacentHTML(\'afterend\', \'<span class=\\"sin-imagen\\"><i class=\\"fas fa-image\\"></i></span>\')">\n'
          : '    <span class="sin-imagen"><i class="fas fa-image"></i></span>\n') +
        (esDestacado || hayPromo || hayOferta
          ? '    <div class="pmp-badges">\n' +
            (esDestacado
              ? '      <span class="badge-mod badge-rojo"><i class="fas fa-star"></i> Destacado</span>\n'
              : '') +
            (hayPromo
              ? '      <span class="badge-mod badge-verde"><i class="fas fa-bolt"></i> -' + p.descuento_real + '%</span>\n'
              : '') +
            (hayOferta && !hayPromo
              ? '      <span class="badge-mod"><i class="fas fa-bolt"></i> Oferta</span>\n'
              : '') +
            '    </div>\n'
          : '') +
        '  </div>\n' +
        '  <div class="pmp-cuerpo">\n' +
        '    <h4 class="pmp-nombre">' + escapar(p.nombre) + '</h4>\n' +
        '    <div class="pmp-precios">\n' +
        '      <span class="pmp-precio">' + bs(p.precio_pos) + '</span>\n' +
        (hayOferta
          ? '      <span class="pmp-antes">' + bs(p.precio_base) + '</span>\n'
          : '') +
        '    </div>\n' +
        '    <div class="pmp-meta">\n' +
        '      <span class="pmp-stock">\n' +
        (controlaStock
          ? (agotado
            ? '        <span class="stock-cero">Agotado</span>\n'
            : stockBajo
              ? '        <span class="stock-bajo">Quedan ' + stock + '</span>\n'
              : '        <span class="stock-ok">Stock ' + stock + '</span>\n')
          : '        <span class="stock-ok">∞ Disponible</span>\n') +
        '      </span>\n' +
        (iva > 0
          ? '      <span class="pmp-iva">IVA ' + iva + '%</span>\n'
          : '') +
        '    </div>\n' +
        '  </div>\n' +
        '  <div class="pmp-agregar">\n' +
        '    <i class="fas fa-plus"></i>\n' +
        '  </div>\n' +
        '</button>'
      );
    }).join("");
  }

  function pintarCategoriasModal() {
    var cont = $s("#posModalCategorias");
    if (!cont) return;

    var html = '<button type="button" class="' + (estado.filtros.categoria === "" ? "activa" : "") + '" data-cat=""><i class="fas fa-layer-group"></i> Todas</button>';

    html += estado.productos.filter(function (p) {
      return p.id_categoria && p.id_categoria > 0;
    }).reduce(function (categorias, p) {
      if (!categorias.includes(p.id_categoria)) {
        categorias.push(p.id_categoria);
        return categorias;
      }
      return categorias;
    }, []).map(function (idCat) {
      var categoria = estado.productos.find(function (p) { return p.id_categoria === idCat; });
      if (!categoria) return '';

      var activa = String(idCat) === String(estado.filtros.categoria);
      return '<button type="button" class="' + (activa ? "activa" : "") + '" data-cat="' + idCat + '">\n' +
        '    <i class="fas ' + escapar(categoria.categoria_icono || "fa-utensils") + '"></i> \n' +
        '    ' + escapar(categoria.categoria) + '\n' +
        '</button>';
    }).join("");

    cont.innerHTML = html;
  }

  // =========================================================
  // VER DETALLE
  // =========================================================
  function verDetalleProducto(id) {
    var producto = estado.productos.find(function (p) { return p.id === id; });
    if (!producto) return;

    // Construir contenido del modal detallado
    var galeriaHtml = "";
    if (Array.isArray(producto.imagenes) && producto.imagenes.length > 0) {
      galeriaHtml = (
        '<div class="pmd-galeria">' +
        producto.imagenes.map(function (img) {
          return (
            '<div class="pmd-g-item">' +
            '<img src="' + escapar(img.url) + '" alt="" loading="lazy">' +
            (img.principal === 1 ? '<span class="pmd-principal-badge">★ Principal</span>' : "") +
            "</div>"
          );
        }).join("") +
        "</div>"
      );
    } else if (producto.imagen_principal_url) {
      galeriaHtml = (
        '<div class="pmd-galeria">' +
        '<div class="pmd-g-item">' +
        '<img src="' + escapar(producto.imagen_principal_url) + '" alt="" loading="lazy">' +
        '<span class="pmd-principal-badge">★ Principal</span>' +
        "</div>" +
        "</div>"
      );
    }

    // Mostrar modal detallado
    Swal.fire({
      icon: "info",
      title: escapar(producto.nombre || "Producto"),
      html:
        '<div style="text-align:center; margin-bottom:6px">' +
        chipHtml(producto.estado === 1 ? "Disponible" : "No disponible", producto.estado === 1 ? "verde" : "rojo") + " " +
        chipHtml(producto.disponible === 1 ? "Activo" : "Pausado", producto.disponible === 1 ? "verde" : "ambar") +
        "</div>" +
        '<div style="display:grid; gap:8px; text-align:left; max-height:60vh; overflow-y:auto; padding:4px;">\n' +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Código:</span>\n' +
        '<b>' + escapar(producto.codigo || "—") + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Categoría:</span>\n' +
        '<b>' + escapar(producto.categoria || "—") + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Descripción:</span>\n' +
        '<b style="text-align:right; max-width:60%;">' + escapar(producto.descripcion || "—") + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Precio normal:</span>\n' +
        '<b>' + formatoBs(producto.precio) + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Precio final:</span>\n' +
        '<b style="color:#d9232d;">' + formatoBs(producto.precio_final) + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Descuento:</span>\n' +
        '<b>' + Number(producto.descuento_real).toFixed(0) + "%</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Costo:</span>\n' +
        '<b>' + formatoBs(producto.costo) + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Impuesto:</span>\n' +
        '<b>' + Number(producto.impuesto).toFixed(2) + "%</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Preparación:</span>\n' +
        '<b>' + Number(producto.tiempo_preparacion) + " min</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Stock:</span>\n' +
        '<b>' + (producto.control_stock
          ? (producto.stock + " (mín. " + producto.stock_minimo + ")")
          : "Ilimitado") + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Destacado:</span>\n' +
        '<b>' + (producto.destacado ? "Sí" : "No") + "</b>\n" +
        "</div>\n" +
        '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #e6d9db;">\n' +
        '<span style="color:#a79ea2;">Creado:</span>\n' +
        '<b>' + (producto.creado || "—") + "</b>\n" +
        "</div>\n" +
        "</div>\n" +
        (galeriaHtml
          ? '<div style="margin-top:12px; padding-top:8px; border-top:1px dashed #e6d9db;">\n' +
            '<div style="font-size:11px; color:#a79ea2; margin-bottom:4px;">Galería de imágenes:</div>\n' +
            galeriaHtml +
            "</div>\n"
          : "") +
        '<div style="margin-top:12px; padding:8px 10px; background:#f8f4f4; border-radius:8px; font-size:11px; color:#6b6165; line-height:1.4;">\n' +
        '<i class="fas fa-circle-info"></i> \n' +
        '<span style="margin-left:4px;">Haz clic en cualquier producto para agregarlo al carrito.</span>\n' +
        "</div>",
      confirmButtonText: "Cerrar",
      confirmButtonColor: "#d9232d",
      width: "90%",
      maxWidth: "800px",
      customClass: {
        popup: "pos-modal-detalle",
      },
    });
  }

  function aviso(titulo, texto, tipo, tiempo) {
    if (!window.Swal) return console.warn(titulo + ": " + texto);
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: tipo || "info",
      title: titulo,
      text: texto,
      timer: tiempo || 3000,
      timerProgressBar: true,
      showConfirmButton: false,
    });
  }

  // =========================================================
  // EVENTOS
  // =========================================================
  function iniciar() {
    // Abrir modal de productos
    var btnProductos = $s("#posBtnProductosModal");
    if (btnProductos) {
      btnProductos.addEventListener("click", function (e) {
        e.preventDefault();
        abrirModal();
      });
    }

    // Cerrar modal
    var cerrar = $s("#posCerrarModalProductos");
    if (cerrar) {
      cerrar.addEventListener("click", cerrarModal);
    }

    // Clic en producto del catálogo
    var grid = $s("#posModalGridProductos");
    if (grid) {
      grid.addEventListener("click", function (e) {
        var btn = e.target.closest(".pos-modal-prod");
        if (!btn) return;

        var id = Number(btn.getAttribute("data-id"));
        verDetalleProducto(id);
      });
    }

    // Buscador
    var buscador = $s("#posModalBuscar");
    if (buscador) {
      buscador.addEventListener("input", function () {
        estado.filtros.busqueda = this.value;
        pintarModalProductos();
      });
    }

    // Limpiador de buscador
    var limpiar = $s("#posModalBuscarLimpiar");
    if (limpiar) {
      limpiar.addEventListener("click", function () {
        if (buscador) buscador.value = "";
        estado.filtros.busqueda = "";
        pintarModalProductos();
      });
    }

    // Categorías
    var categorias = $s("#posModalCategorias");
    if (categorias) {
      categorias.addEventListener("click", function (e) {
        var b = e.target.closest("[data-cat]");
        if (!b) return;

        estado.filtros.categoria = b.getAttribute("data-cat") || "";
        pintarCategoriasModal();
        pintarModalProductos();
      });
    }

    // Escape
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && $s("#posModalProductos.abierto")) {
        cerrarModal();
      }
    });

    // Clic fuera del modal
    var modal = $s("#posModalProductos");
    if (modal) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) cerrarModal();
      });
    }

    // Actualizar la sucursal actual
    estado.sucursal = estado.idSucursal || 0;
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    iniciar();
  });
})();