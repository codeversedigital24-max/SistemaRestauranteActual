/**
 * =====================================================
 * modelos/productos/productos.js
 * =====================================================
 * UI del módulo de productos:
 *   · 1 fetch → DataTable con acciones Ver / Editar /
 *     Disponibilidad / Dar de baja
 *   · Modal reutilizable de alta y edición (modulos.css)
 *   · Avisos con SweetAlert2 (siempre ENCIMA del modal)
 * =====================================================
 */
(function () {
  "use strict";

  var $s = function (sel, ctxo) {
    return (ctxo || document).querySelector(sel);
  };

  var tabla = null;
  var filas = [];
  var reintentos = 0;
  var modal = null;
  // Estado local de la galería
  var galeriaActual = []; // imágenes ya guardadas en BD
  var archivosNuevos = []; // File[] pendientes de subir
  var imagenesEliminar = []; // ids a borrar
  var principalMarcada = ""; // '' | 'e:ID' | 'n:INDICE'
  // =========================================================
  // AVISOS
  // =========================================================
  function pintarGaleria() {
    var grid = $s("#galeriaGrid");
    if (!grid) return;

    grid.innerHTML = "";

    // 1) Imágenes guardadas
    galeriaActual.forEach(function (img) {
      if (imagenesEliminar.indexOf(img.id) !== -1) return;

      var div = document.createElement("div");
      div.className =
        "galeria-item" + (img.principal === 1 ? " principal" : "");
      div.innerHTML =
        '<img src="' +
        escapar(img.url) +
        '" alt="">' +
        '<button type="button" class="g-estrella' +
        (img.principal === 1 ? " activa" : "") +
        '" data-accion-gal="principal" data-id="' +
        img.id +
        '" title="Marcar principal">' +
        '<i class="fa-solid fa-star"></i></button>' +
        '<button type="button" class="g-borrar" data-accion-gal="eliminar" data-id="' +
        img.id +
        '" title="Quitar"><i class="fa-solid fa-xmark"></i></button>';
      grid.appendChild(div);
    });

    // 2) Archivos nuevos (previsualización con blob URL)
    archivosNuevos.forEach(function (file, indice) {
      var url = file.__blobUrl || (file.__blobUrl = URL.createObjectURL(file));

      var div = document.createElement("div");
      div.className = "galeria-item g-nueva";
      div.innerHTML =
        '<img src="' +
        escapar(url) +
        '" alt="">' +
        '<button type="button" class="g-estrella' +
        (principalMarcada === "n:" + indice ? " activa" : "") +
        '" data-accion-gal="principal-nuevo" data-indice="' +
        indice +
        '" title="Marcar principal">' +
        '<i class="fa-solid fa-star"></i></button>' +
        '<button type="button" class="g-borrar" data-accion-gal="quitar-nuevo" data-indice="' +
        indice +
        '" title="Quitar"><i class="fa-solid fa-xmark"></i></button>';
      grid.appendChild(div);
    });

    // 3) Contador / aviso
    var total =
      galeriaActual.filter(function (i) {
        return imagenesEliminar.indexOf(i.id) === -1;
      }).length + archivosNuevos.length;
    var ayuda = $s("#galeriaAyuda");
    if (ayuda) {
      ayuda.textContent =
        total + " / 10 imágenes · la marcada con ★ será la principal";
      ayuda.classList.toggle("error", total > 10);
    }

    var add = $s("#galeriaAdd");
    if (add) add.style.display = total >= 10 ? "none" : "";
  }
  function aviso(titulo, texto, tipo, tiempo) {
    if (!window.Swal) {
      console.warn("[" + (tipo || "info") + "] " + titulo + ": " + texto);
      return;
    }
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: tipo || "info",
      title: titulo,
      text: texto,
      showConfirmButton: false,
      timer: tiempo || 4000,
      timerProgressBar: true,
    });
  }

  function avisoGrande(titulo, texto, tipo) {
    if (!window.Swal) return;
    return Swal.fire({
      icon: tipo || "success",
      title: titulo,
      text: texto,
      confirmButtonText: "Entendido",
      confirmButtonColor: "#d9232d",
    });
  }

  function escapar(valor) {
    var texto = String(valor == null ? "" : valor);
    var amp = String.fromCharCode(38);

    return texto
      .split(amp)
      .join(amp + "amp;")
      .split("<")
      .join(amp + "lt;")
      .split(">")
      .join(amp + "gt;")
      .split('"')
      .join(amp + "quot;");
  }

  function formatoBs(valor) {
    var n = Number(valor) || 0;
    return (
      "Bs " +
      n.toLocaleString("es-BO", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  function chipEstado(estado) {
    var ok = Number(estado) === 1;
    return (
      '<span class="badge-mod ' +
      (ok ? "badge-verde" : "badge-gris") +
      '">' +
      (ok ? "Activo" : "Inactivo") +
      "</span>"
    );
  }

  function chipDisponible(disponible) {
    var ok = Number(disponible) === 1;
    return (
      '<span class="badge-mod ' +
      (ok ? "badge-verde" : "badge-ambar") +
      '">' +
      (ok ? "Disponible" : "Pausado") +
      "</span>"
    );
  }

  function precioHtml(f) {
    var final = Number(f.precio_final);
    var base = Number(f.precio);

    if (Number(f.oferta_vigente) === 1 && Number(f.ahorro) > 0) {
      return (
        '<span class="prod-precio">' +
        "<strong>" +
        formatoBs(final) +
        "</strong>" +
        "<s>" +
        formatoBs(base) +
        "</s>" +
        "</span>"
      );
    }

    return (
      '<span class="prod-precio"><strong>' +
      formatoBs(base) +
      "</strong></span>"
    );
  }

  function ofertaHtml(f) {
    if (Number(f.oferta_vigente) === 1 && Number(f.ahorro) > 0) {
      return (
        '<span class="badge-mod badge-rojo"><i class="fa-solid fa-fire"></i> -' +
        Number(f.descuento_real).toFixed(0) +
        "%</span>"
      );
    }

    if (Number(f.oferta_activa) === 1) {
      return '<span class="badge-mod badge-ambar">Programada</span>';
    }

    return '<span class="vacio">—</span>';
  }

  function stockHtml(f) {
    if (!Number(f.control_stock)) {
      return '<span class="badge-mod badge-gris" title="Sin control de stock">∞</span>';
    }

    var stock = Number(f.stock);
    var minimo = Number(f.stock_minimo);

    if (stock <= 0) {
      return '<span class="badge-mod badge-rojo">Agotado</span>';
    }

    if (stock <= minimo) {
      return (
        '<span class="badge-mod badge-ambar" title="Bajo el mínimo">' +
        stock +
        "</span>"
      );
    }

    return '<span class="badge-mod badge-verde">' + stock + "</span>";
  }

  // =========================================================
  // CARGA
  // =========================================================
  function mostrarCarga(activar) {
    var caja = $s("#tablaCarga");
    if (caja) caja.classList.toggle("visible", Boolean(activar));
  }

  function cargar() {
    if (!window.ProductosAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.ProductosAPI.listar().then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso(
          "No se pudo cargar",
          rpta.message || "Error al leer productos",
          "error",
          5000,
        );
        pintar([]);
        return;
      }

      filas = rpta.data || [];
      pintar(filas);
    });
  }

  // =========================================================
  // ACCIONES DE FILA
  // =========================================================
  function acciones(fila) {
    var baja = Number(fila.estado) === 0;
    var disponible = Number(fila.disponible) === 1;

    return (
      '<span class="acciones-fila">' +
      '<button type="button" class="btn-tabla ver" data-accion="ver" data-id="' +
      fila.id +
      '" title="Ver detalle" aria-label="Ver detalle"><i class="fa-solid fa-eye"></i></button>' +
      '<button type="button" class="btn-tabla editar" data-accion="editar" data-id="' +
      fila.id +
      '" title="Modificar" aria-label="Modificar"><i class="fa-solid fa-pen"></i></button>' +
      '<button type="button" class="btn-tabla disp" data-accion="disponibilidad" data-id="' +
      fila.id +
      '" data-disponible="' +
      (disponible ? "1" : "0") +
      '"' +
      (baja ? " disabled" : "") +
      ' title="' +
      (disponible ? "Pausar venta" : "Volver a disponibilidad") +
      '" aria-label="Cambiar disponibilidad"><i class="fa-solid fa-' +
      (disponible ? "pause" : "play") +
      '"></i></button>' +
      '<button type="button" class="btn-tabla borrar" data-accion="eliminar" data-id="' +
      fila.id +
      '" title="' +
      (baja ? "Ya está dado de baja" : "Dar de baja") +
      '"' +
      (baja ? " disabled" : "") +
      ' aria-label="Dar de baja"><i class="fa-solid fa-trash-can"></i></button>' +
      "</span>"
    );
  }

  // =========================================================
  // TABLA
  // =========================================================
  function pintar(datos) {
    if (!window.jQuery || !jQuery.fn.DataTable) {
      if (reintentos++ < 50) {
        setTimeout(function () {
          pintar(datos);
        }, 60);
        return;
      }
      aviso(
        "Sin DataTables",
        "No se cargó la librería de tablas (¿conexión?)",
        "error",
        6000,
      );
      return;
    }

    reintentos = 0;

    if (tabla) {
      tabla.clear().rows.add(datos).draw(false);
      return;
    }

    tabla = jQuery("#tablaProductos").DataTable({
      data: datos,
      deferRender: true,
      responsive: { details: { type: "inline", target: "tr" } },
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [
        [8, 10, 25, 50, -1],
        [8, 10, 25, 50, "Todos"],
      ],
      order: [
        [3, "asc"],
        [2, "asc"],
      ],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json",
      },
      dom:
        "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "48px" },
        { targets: -1, width: "150px" },
      ],

      columns: [
        // #
        {
          data: "id",
          title: "#",
          className: "dt-num",
          responsivePriority: 10,
          searchable: false,
        },

        // Código
        {
          data: "codigo",
          responsivePriority: 4,
          render: function (t) {
            return (
              '<span class="badge-mod badge-ambar">' + escapar(t) + "</span>"
            );
          },
        },

        // Producto (nombre + destacado)
        {
          data: "nombre",
          responsivePriority: 1,
          render: function (t, type, fila) {
            var nombre =
              escapar(t).trim() || '<span class="vacio">Sin nombre</span>';
            var star =
              Number(fila.destacado) === 1
                ? ' <i class="fa-solid fa-star" style="color:#f0b400;font-size:11px" title="Destacado"></i>'
                : "";
            var tiempo =
              Number(fila.tiempo_preparacion) > 0
                ? '<span class="dt-subtexto"><i class="fa-regular fa-clock"></i> ' +
                  Number(fila.tiempo_preparacion) +
                  " min</span>"
                : "";

            var img = fila.imagen_principal_url
              ? '<img class="prod-thumb" src="' +
                escapar(fila.imagen_principal_url) +
                '" alt="" loading="lazy">'
              : '<span class="prod-thumb prod-thumb-vacia"><i class="fa-solid fa-image"></i></span>';

            return (
              '<span class="prod-fila">' +
              img +
              '<span class="prod-fila-txt"><span class="dt-texto">' +
              nombre +
              star +
              "</span>" +
              tiempo +
              "</span></span>"
            );
          },
        },

        // Categoría
        {
          data: "categoria",
          responsivePriority: 7,
          render: function (t, type, fila) {
            var icono = escapar(fila.categoria_icono || "fa-utensils");
            return (
              '<span class="dt-subtexto"><i class="fa-solid ' +
              icono +
              '"></i> ' +
              (escapar(t) || "—") +
              "</span>"
            );
          },
        },

        // Precio
        {
          data: "precio_final",
          responsivePriority: 5,
          className: "dt-num",
          render: function (t, type, fila) {
            return precioHtml(fila);
          },
        },

        // Oferta
        {
          data: "oferta_vigente",
          responsivePriority: 6,
          orderable: false,
          searchable: false,
          render: function (t, type, fila) {
            return ofertaHtml(fila);
          },
        },

        // Stock
        {
          data: "stock",
          responsivePriority: 8,
          className: "dt-num",
          render: function (t, type, fila) {
            return stockHtml(fila);
          },
        },

        // Disponible
        {
          data: "disponible",
          responsivePriority: 9,
          orderable: false,
          render: function (t) {
            return chipDisponible(t);
          },
        },

        // Estado
        {
          data: "estado",
          responsivePriority: 2,
          orderable: false,
          render: function (t) {
            return chipEstado(t);
          },
        },

        // Acciones
        {
          data: null,
          name: "acciones",
          orderable: false,
          searchable: false,
          responsivePriority: 3,
          render: function (fila) {
            return acciones(fila);
          },
        },
      ],
    });
  }

  // =========================================================
  // VER DETALLE
  // =========================================================
  function verDetalle(id) {
    var fila = filas.find(function (f) {
      return Number(f.id) === Number(id);
    });

    if (!fila) {
      aviso("Sin datos", "No se encontró el registro", "warning");
      return;
    }

    var campo = function (et, val) {
      return "<dt>" + et + "</dt><dd>" + (escapar(val) || "—") + "</dd>";
    };

    var oferta =
      Number(fila.oferta_vigente) === 1 && Number(fila.ahorro) > 0
        ? "Sí (" + Number(fila.descuento_real).toFixed(0) + " %)"
        : Number(fila.oferta_activa) === 1
          ? "No vigente"
          : "No";

    var ventana =
      fila.oferta_desde || fila.oferta_hasta
        ? (fila.oferta_desde || "—") + "  →  " + (fila.oferta_hasta || "—")
        : "Sin fechas";
    var galeriaHtml = "";
    if (Array.isArray(fila.imagenes) && fila.imagenes.length) {
      galeriaHtml =
        '<div class="prod-detalle-gal">' +
        fila.imagenes
          .map(function (img) {
            return (
              '<img src="' +
              escapar(img.url) +
              '" alt="' +
              (img.principal === 1 ? "Principal" : "") +
              '">'
            );
          })
          .join("") +
        "</div>";
    } else if (fila.imagen_principal_url) {
      galeriaHtml =
        '<div class="prod-detalle-gal">' +
        '<img src="' +
        escapar(fila.imagen_principal_url) +
        '" alt="">' +
        "</div>";
    }
    Swal.fire({
      icon: "info",
      title: fila.nombre || "Producto",
      html:
        '<div style="text-align:center;margin-bottom:6px">' +
        chipEstado(fila.estado) +
        " " +
        chipDisponible(fila.disponible) +
        "</div>" +
        '<dl class="dl-datos">' +
        campo("Código", fila.codigo) +
        campo("Categoría", fila.categoria) +
        campo("Descripción", fila.descripcion) +
        campo("Precio normal", formatoBs(fila.precio)) +
        campo("Precio final", formatoBs(fila.precio_final)) +
        campo("Descuento", Number(fila.descuento_real).toFixed(0) + " %") +
        campo("Oferta", oferta) +
        campo("Ventana de oferta", ventana) +
        campo("Costo", formatoBs(fila.costo)) +
        campo("Impuesto", Number(fila.impuesto).toFixed(2) + " %") +
        campo("Preparación", Number(fila.tiempo_preparacion) + " min") +
        campo(
          "Stock",
          Number(fila.control_stock)
            ? fila.stock + " (mín. " + fila.stock_minimo + ")"
            : "Ilimitado",
        ) +
        campo("Destacado", Number(fila.destacado) ? "Sí" : "No") +
        campo("Creado el", fila.creado) +
        "</dl>",
      confirmButtonText: "Cerrar",
      confirmButtonColor: "#d9232d",
    });
  }

  // =========================================================
  // MODAL (alta / edición)
  // =========================================================
  function abrirModal() {
    modal = $s("#modalProducto");
    limpiarErrores();
    if (!modal.classList.contains("abierto")) {
      // (solo si es alta; editar llamará cargarEnModal antes)
    }
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var primero = $s("#p_codigo");
      if (primero) primero.focus();
    }, 60);
  }

  function cerrarModal() {
    modal = modal || $s("#modalProducto");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    var form = document.getElementById("formProducto");
    if (form) {
      form.reset();
      form.dataset.accion = "crear";
      form.dataset.id = "";
    }

    setText("#mProdTexto", "Nuevo producto");
    setIcono("#mProdIcono", "fa-burger");
    setText("#mProdNota", "");
    actualizarPreview();
    archivosNuevos.forEach(function (f) {
      if (f.__blobUrl) URL.revokeObjectURL(f.__blobUrl);
    });
    archivosNuevos = [];
    imagenesEliminar = [];
    principalMarcada = "";
    galeriaActual = [];
    pintarGaleria();
  }

  function setText(sel, texto) {
    var el = $s(sel);
    if (el) el.textContent = texto;
  }

  function setIcono(sel, clase) {
    var el = $s(sel);
    if (el) el.innerHTML = '<i class="fa-solid ' + clase + '"></i>';
  }

  function editar(id) {
    window.ProductosAPI.obtener(id).then(function (rpta) {
      if (!rpta.success || !rpta.data || !rpta.data.producto) {
        aviso(
          "No se pudo cargar",
          rpta.message || "Producto no encontrado",
          "error",
        );
        return;
      }

      cargarEnModal(rpta.data.producto);
      abrirModal();
    });
  }

  function aInputFecha(valor) {
    if (!valor) return "";
    var texto = String(valor).replace(" ", "T");
    return texto.length >= 16 ? texto.slice(0, 16) : texto;
  }

  function cargarEnModal(p) {
    var mapa = {
      p_id: p.id,
      p_codigo: p.codigo,
      p_nombre: p.nombre,
      p_categoria: p.id_categoria,
      p_descripcion: p.descripcion,
      p_imagen: p.imagen,
      p_precio: p.precio,
      p_descuento: p.descuento,
      p_precio_oferta: p.precio_oferta == null ? "" : p.precio_oferta,
      p_costo: p.costo,
      p_impuesto: p.impuesto,
      p_tiempo_preparacion: p.tiempo_preparacion,
      p_stock: p.stock,
      p_stock_minimo: p.stock_minimo,
      p_disponible: p.disponible,
      p_estado: p.estado,
    };

    Object.keys(mapa).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = mapa[id] == null ? "" : mapa[id];
    });

    $s("#p_oferta_activa").checked = Number(p.oferta_activa) === 1;
    $s("#p_control_stock").checked = Number(p.control_stock) === 1;
    $s("#p_destacado").checked = Number(p.destacado) === 1;

    $s("#p_oferta_desde").value = aInputFecha(p.oferta_desde);
    $s("#p_oferta_hasta").value = aInputFecha(p.oferta_hasta);

    var form = document.getElementById("formProducto");
    form.dataset.accion = "actualizar";
    form.dataset.id = p.id;

    setText("#mProdTexto", "Modificar producto");
    setIcono("#mProdIcono", "fa-pen-to-square");
    setText("#mProdNota", "Editando: " + (p.nombre || ""));

    actualizarPreview();
    // Galería existente
    galeriaActual = Array.isArray(p.imagenes) ? p.imagenes.slice() : [];
    archivosNuevos = [];
    imagenesEliminar = [];

    // Marcar la principal actual
    principalMarcada = "";
    var principal = galeriaActual.find(function (i) {
      return i.principal === 1;
    });
    if (principal) principalMarcada = "e:" + principal.id;

    pintarGaleria();
  }

  // =========================================================
  // DISPONIBILIDAD / ELIMINAR
  // =========================================================
  function cambiarDisponibilidad(id, disponibleActual) {
    var nuevo = Number(disponibleActual) === 1 ? 0 : 1;
    var fila = filas.find(function (f) {
      return Number(f.id) === Number(id);
    });

    window.ProductosAPI.disponibilidad(id, nuevo).then(function (rpta) {
      if (rpta.success) {
        aviso(
          nuevo === 1 ? "Disponible" : "Pausado",
          (fila ? fila.nombre + " · " : "") + (rpta.message || ""),
          "success",
        );
        cargar();
      } else {
        aviso(
          "No se pudo cambiar",
          rpta.message || "Intenta de nuevo",
          "error",
          5000,
        );
      }
    });
  }

  function eliminar(id) {
    var fila = filas.find(function (f) {
      return Number(f.id) === Number(id);
    });

    Swal.fire({
      icon: "warning",
      title: "¿Dar de baja el producto?",
      html:
        "Se dará de baja <strong>" +
        (fila ? escapar(fila.nombre) : "#" + id) +
        "</strong>.<br>" +
        "<span style='font-size:13.5px;color:#8a8a8f'>Podrás reactivarlo editándolo desde la tabla.</span>",
      showCancelButton: true,
      confirmButtonText: "Sí, dar de baja",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (resp) {
      if (!resp.isConfirmed) return;

      window.ProductosAPI.eliminar(id).then(function (rpta) {
        if (rpta.success) {
          avisoGrande(
            "Baja realizada",
            rpta.message || "Producto dado de baja",
            "success",
          );
          cargar();
        } else {
          aviso(
            "No se pudo eliminar",
            rpta.message || "Intenta de nuevo",
            "error",
            5500,
          );
        }
      });
    });
  }

  // =========================================================
  // GUARDAR
  // =========================================================
  function numero(sel, porDefecto) {
    var el = $s(sel);
    if (!el) return porDefecto || 0;
    var v = parseFloat(el.value);
    return isNaN(v) ? porDefecto || 0 : v;
  }

  function leerFormulario() {
    var oferta = $s("#p_precio_oferta").value;

    return {
      id: Number($s("#p_id").value || "0") || 0,
      codigo: ($s("#p_codigo").value || "").trim().toUpperCase(),
      nombre: ($s("#p_nombre").value || "").trim(),
      id_categoria: Number($s("#p_categoria").value || "0") || 0,
      descripcion: ($s("#p_descripcion").value || "").trim(),
      imagen: ($s("#p_imagen").value || "").trim(),
      precio: numero("#p_precio"),
      descuento_porcentaje: numero("#p_descuento"),
      precio_oferta: oferta === "" ? null : parseFloat(oferta),
      oferta_activa: $s("#p_oferta_activa").checked ? 1 : 0,
      oferta_desde: $s("#p_oferta_desde").value || "",
      oferta_hasta: $s("#p_oferta_hasta").value || "",
      costo: numero("#p_costo"),
      impuesto_porcentaje: numero("#p_impuesto"),
      tiempo_preparacion:
        parseInt($s("#p_tiempo_preparacion").value || "0", 10) || 0,
      control_stock: $s("#p_control_stock").checked ? 1 : 0,
      stock: parseInt($s("#p_stock").value || "0", 10) || 0,
      stock_minimo: parseInt($s("#p_stock_minimo").value || "0", 10) || 0,
      disponible: Number($s("#p_disponible").value || "1"),
      destacado: $s("#p_destacado").checked ? 1 : 0,
      estado: Number($s("#p_estado").value || "1"),
    };
  }

  function validar(d) {
    limpiarErrores();
    var fallos = [];

    if (d.codigo !== "" && !/^[A-Z0-9][A-Z0-9\-_]{1,19}$/.test(d.codigo)) {
      marcarError("codigo", "2-20 caracteres: letras, números, - o _");
      fallos.push("código");
    }

    if (d.nombre.length < 3) {
      marcarError("nombre", "Mínimo 3 caracteres");
      fallos.push("nombre");
    }

    if (!d.id_categoria) {
      marcarError("id_categoria", "Selecciona una categoría");
      fallos.push("categoría");
    }

    if (!(d.precio > 0)) {
      marcarError("precio", "El precio debe ser mayor que cero");
      fallos.push("precio");
    }

    if (d.descuento_porcentaje < 0 || d.descuento_porcentaje > 100) {
      marcarError("descuento", "Entre 0 y 100 %");
      fallos.push("descuento");
    }

    if (d.precio_oferta !== null) {
      if (isNaN(d.precio_oferta) || d.precio_oferta <= 0) {
        marcarError("precio_oferta", "Precio de oferta inválido");
        fallos.push("precio de oferta");
      } else if (d.precio_oferta >= d.precio) {
        marcarError("precio_oferta", "Debe ser menor al precio normal");
        fallos.push("precio de oferta");
      }
    }

    if (
      d.oferta_activa === 1 &&
      d.descuento_porcentaje <= 0 &&
      (d.precio_oferta === null || d.precio_oferta <= 0)
    ) {
      marcarError("oferta_activa", "Indica un descuento o un precio de oferta");
      fallos.push("oferta");
    }

    if (
      d.oferta_desde &&
      d.oferta_hasta &&
      new Date(d.oferta_hasta) < new Date(d.oferta_desde)
    ) {
      marcarError("oferta_hasta", "No puede ser anterior al inicio");
      fallos.push("fechas de oferta");
    }

    if (d.stock < 0) {
      marcarError("stock", "No puede ser negativo");
      fallos.push("stock");
    }

    if (d.stock_minimo < 0) {
      marcarError("stock_minimo", "No puede ser negativo");
      fallos.push("stock mínimo");
    }

    return fallos;
  }

  function enviar(ev) {
    ev.preventDefault();

    var form = document.getElementById("formProducto");
    var modoEdicion = form.dataset.accion === "actualizar";
    var datos = leerFormulario();
    var fallos = validar(datos);

    if (fallos.length) {
      aviso(
        "Revisa el formulario",
        "Faltan datos: " + fallos.join(", "),
        "warning",
        4000,
      );
      var primerError = document.querySelector(
        ".campo.error input, .campo.error select",
      );
      if (primerError) primerError.focus();
      return;
    }

    if (!window.ProductosAPI) {
      aviso("Error", "Módulo de red no disponible", "error");
      return;
    }

    var boton = $s("#mProdGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

    var promesa = modoEdicion
      ? window.ProductosAPI.actualizar(datos)
      : window.ProductosAPI.crear(datos);

    promesa
      .then(function (rpta) {
        if (!rpta.success) {
          throw new Error(rpta.message || "No se pudo guardar el producto");
        }

        var idProducto = Number(rpta.data && rpta.data.id) || datos.id;

        if (!idProducto) {
          throw new Error("No se obtuvo el id del producto");
        }

        var hayCambiosGaleria =
          archivosNuevos.length > 0 ||
          imagenesEliminar.length > 0 ||
          principalMarcada !== "";

        if (!hayCambiosGaleria) {
          return rpta;
        }

        return window.ProductosAPI.imagenesGuardar(
          idProducto,
          archivosNuevos,
          imagenesEliminar,
          principalMarcada,
        );
      })
      .then(function (rptaGal) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.html;

        if (rptaGal && rptaGal.success === false) {
          aviso(
            "Producto guardado",
            "Pero la galería falló: " + rptaGal.message,
            "warning",
            6000,
          );
          cargar();
          return;
        }

        cerrarModal();
        avisoGrande(
          modoEdicion ? "Actualizado" : "Producto creado",
          "Los datos y la galería se guardaron correctamente",
          "success",
        );
        cargar();
      })
      .catch(function (err) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.html;
        aviso(
          "No se pudo guardar",
          err.message || "Revisa los datos",
          "error",
          5500,
        );
      });
  }

  // =========================================================
  // PREVISUALIZACIÓN DEL PRECIO FINAL
  // =========================================================
  function actualizarPreview() {
    if (!$s("#prodPreviewFinal")) return;

    var precio = numero("#p_precio");
    var descuento = numero("#p_descuento");
    var oferta = numero("#p_precio_oferta");
    var activa = $s("#p_oferta_activa") && $s("#p_oferta_activa").checked;

    var final = precio;

    if (activa) {
      if (oferta > 0 && oferta < precio) {
        final = oferta;
      } else if (descuento > 0) {
        final = precio * (1 - descuento / 100);
      }
    }

    final = Math.max(0, Math.min(precio, final));
    var ahorro = Math.max(0, precio - final);

    $s("#prodPreviewFinal").textContent = formatoBs(final);

    var etiqueta = $s("#prodPreviewAhorro");
    if (etiqueta) {
      etiqueta.textContent =
        ahorro > 0
          ? "Ahorro: " +
            formatoBs(ahorro) +
            " (" +
            ((ahorro / precio) * 100).toFixed(0) +
            " %)"
          : "Sin descuento aplicado";
    }
  }

  // =========================================================
  // VALIDACIÓN EN LÍNEA
  // =========================================================
  function marcarError(campo, mensaje) {
    var cont = document.querySelector('[data-campo="' + campo + '"]');
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
      } else {
        ayuda.classList.remove("error");
      }
    }
  }

  function limpiarErrores() {
    document.querySelectorAll(".campo.error").forEach(function (c) {
      marcarError(c.getAttribute("data-campo"), "");
    });
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    modal = $s("#modalProducto");

    cargar();

    var btnNuevo = $s("#btnNuevoProducto");
    if (btnNuevo) {
      btnNuevo.addEventListener("click", function () {
        galeriaActual = [];
        archivosNuevos.forEach(function (f) {
          if (f.__blobUrl) URL.revokeObjectURL(f.__blobUrl);
        });
        archivosNuevos = [];
        imagenesEliminar = [];
        principalMarcada = "";
        pintarGaleria();
        abrirModal();
      });
    }

    var btnCancelar = $s("#mProdCancelar");
    if (btnCancelar) btnCancelar.addEventListener("click", cerrarModal);

    var btnCerrar = $s("#mProdCerrar");
    if (btnCerrar) btnCerrar.addEventListener("click", cerrarModal);

    if (modal) {
      modal.addEventListener("click", function (ev) {
        if (ev.target === modal) cerrarModal();
      });
    }

    var form = document.getElementById("formProducto");
    if (form) {
      form.addEventListener("submit", enviar);

      form.addEventListener("input", function (ev) {
        var caja = ev.target.closest(".campo");
        if (caja && caja.classList.contains("error")) {
          marcarError(caja.getAttribute("data-campo"), "");
        }

        if (
          ev.target.id === "p_precio" ||
          ev.target.id === "p_descuento" ||
          ev.target.id === "p_precio_oferta"
        ) {
          actualizarPreview();
        }
      });

      form.addEventListener("change", function (ev) {
        if (
          ev.target.id === "p_oferta_activa" ||
          ev.target.id === "p_precio" ||
          ev.target.id === "p_descuento" ||
          ev.target.id === "p_precio_oferta"
        ) {
          actualizarPreview();
        }
      });
    }

    // Código en mayúsculas mientras se escribe
    var codigo = $s("#p_codigo");
    if (codigo) {
      codigo.addEventListener("input", function () {
        var ini = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(ini, ini);
      });
    }

    actualizarPreview();

    // Acciones por delegación (sobrevive los draw de DataTables)
    var tbody = document.querySelector("#tablaProductos tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton || boton.disabled) return;

        var id = boton.getAttribute("data-id");
        var acc = boton.getAttribute("data-accion");

        if (acc === "ver") verDetalle(id);
        else if (acc === "editar") editar(id);
        else if (acc === "disponibilidad")
          cambiarDisponibilidad(id, boton.getAttribute("data-disponible"));
        else if (acc === "eliminar") eliminar(id);
      });
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;
      var abierto = document.querySelector(".modal-overlay.abierto");
      if (!abierto) return;
      if (abierto.id === "modalProducto") cerrarModal();
    });
    // --- Galería ---
    var inputArchivos = $s("#p_archivos");
    if (inputArchivos) {
      inputArchivos.addEventListener("change", function () {
        var files = Array.prototype.slice.call(inputArchivos.files || []);
        inputArchivos.value = ""; // permite volver a elegir el mismo

        var validos = ["image/jpeg", "image/png", "image/webp"];

        files.forEach(function (file) {
          if (validos.indexOf(file.type) === -1) {
            aviso(
              "Formato no válido",
              file.name + " · usa JPG, PNG o WEBP",
              "warning",
            );
            return;
          }
          if (file.size > 5 * 1024 * 1024) {
            aviso("Imagen muy grande", file.name + " supera 5 MB", "warning");
            return;
          }
          if (archivosNuevos.length >= 10) {
            aviso(
              "Límite alcanzado",
              "Máximo 10 imágenes por producto",
              "warning",
            );
            return;
          }
          archivosNuevos.push(file);
        });

        pintarGaleria();
      });
    }

    var grid = $s("#galeriaGrid");
    if (grid) {
      grid.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion-gal]");
        if (!boton) return;

        var acc = boton.getAttribute("data-accion-gal");

        if (acc === "principal") {
          var id = Number(boton.getAttribute("data-id"));
          principalMarcada = "e:" + id;
        } else if (acc === "principal-nuevo") {
          var idx = Number(boton.getAttribute("data-indice"));
          principalMarcada = "n:" + idx;
        } else if (acc === "eliminar") {
          var idDel = Number(boton.getAttribute("data-id"));
          if (imagenesEliminar.indexOf(idDel) === -1)
            imagenesEliminar.push(idDel);
          if (principalMarcada === "e:" + idDel) principalMarcada = "";
        } else if (acc === "quitar-nuevo") {
          var iDel = Number(boton.getAttribute("data-indice"));
          if (archivosNuevos[iDel] && archivosNuevos[iDel].__blobUrl) {
            URL.revokeObjectURL(archivosNuevos[iDel].__blobUrl);
          }
          archivosNuevos.splice(iDel, 1);
          if (principalMarcada === "n:" + iDel) principalMarcada = "";
          // Reajustar índice de principal si era mayor
          if (principalMarcada.indexOf("n:") === 0) {
            var n = Number(principalMarcada.slice(2));
            if (n > iDel) principalMarcada = "n:" + (n - 1);
          }
        }

        pintarGaleria();
      });
    }
  });
})();
