/**
 * =====================================================
 * modelos/cajas/cajas.js
 * =====================================================
 * UI del módulo de cajas:
 *   · 1 fetch → DataTable con filtro por sucursal
 *   · Modal de alta / edición (modulos.css)
 *   · Modal para asignar la caja a un personal
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
  var modalAsignar = null;
  var sucursalesCargadas = false;
  var cajaAsignando = null;

  // =========================================================
  // AVISOS
  // =========================================================
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
      (ok ? "Activa" : "Inactiva") +
      "</span>"
    );
  }

  function chipResponsable(fila) {
    if (Number(fila.id_usuario_asignado) > 0) {
      var nombre = escapar(fila.responsable || fila.username);
      var rol = fila.rol
        ? '<span class="dt-subtexto"><i class="fa-solid fa-id-badge"></i> ' + escapar(fila.rol) + "</span>"
        : "";
      return '<span class="dt-texto">' + nombre + "</span>" + rol;
    }

    return '<span class="badge-mod badge-ambar"><i class="fa-solid fa-user-slash"></i> Sin asignar</span>';
  }

  // =========================================================
  // CARGA
  // =========================================================
  function mostrarCarga(activar) {
    var caja = $s("#tablaCarga");
    if (caja) caja.classList.toggle("visible", Boolean(activar));
  }

  function cargar() {
    if (!window.CajasAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.CajasAPI.listar().then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error al leer las cajas", "error", 5000);
        filas = [];
        pintar();
        return;
      }

      filas = rpta.data || [];

      pintarResumen(rpta.resumen || {});
      cargarFiltroSucursales(rpta.sucursales || []);
      pintar();
    });
  }

  function pintarResumen(r) {
    var mapa = {
      cajasTotal: r.total,
      cajasActivas: r.activas,
      cajasAsignadas: r.asignadas,
      cajasLibres: r.libres,
    };

    Object.keys(mapa).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = Number(mapa[id] || 0);
    });
  }

  function cargarFiltroSucursales(sucursales) {
    var sel = $s("#filtroSucursal");
    if (!sel || sucursalesCargadas) return;

    sucursales.forEach(function (suc) {
      var op = document.createElement("option");
      op.value = String(suc.id_sucursal);
      op.textContent = suc.nombre_sucursal + (suc.ciudad ? " · " + suc.ciudad : "");
      sel.appendChild(op);
    });

    sucursalesCargadas = true;
  }

  function datosFiltrados() {
    var sel = $s("#filtroSucursal");
    var suc = sel ? sel.value : "";

    if (!suc) return filas;

    return filas.filter(function (f) {
      return String(f.id_sucursal) === suc;
    });
  }

  function actualizarContador(datos) {
    var el = $s("#cajaFiltroTexto");
    if (!el) return;

    var total = filas.length;
    var mostradas = datos.length;

    el.textContent = mostradas === total
      ? total + " caja(s)"
      : "Mostrando " + mostradas + " de " + total;
  }

  // =========================================================
  // ACCIONES DE FILA
  // =========================================================
  function acciones(fila) {
    var baja = Number(fila.estado) === 0;
    var asignada = Number(fila.id_usuario_asignado) > 0;

    return (
      '<span class="acciones-fila">' +
      '<button type="button" class="btn-tabla ver" data-accion="ver" data-id="' + fila.id +
      '" title="Ver detalle" aria-label="Ver detalle"><i class="fa-solid fa-eye"></i></button>' +
      '<button type="button" class="btn-tabla editar" data-accion="editar" data-id="' + fila.id +
      '" title="Modificar" aria-label="Modificar"><i class="fa-solid fa-pen"></i></button>' +
      '<button type="button" class="btn-tabla asignar" data-accion="asignar" data-id="' + fila.id +
      '" title="Asignar a un personal" aria-label="Asignar caja"' +
      (baja ? " disabled" : "") +
      '><i class="fa-solid fa-user-tie"></i></button>' +
      '<button type="button" class="btn-tabla borrar" data-accion="eliminar" data-id="' + fila.id +
      '" title="' + (baja ? "Ya está dada de baja" : "Dar de baja") + '"' +
      (baja || asignada ? " disabled" : "") +
      ' aria-label="Dar de baja"><i class="fa-solid fa-trash-can"></i></button>' +
      "</span>"
    );
  }

  // =========================================================
  // TABLA
  // =========================================================
  function pintar() {
    var datos = datosFiltrados();
    actualizarContador(datos);

    if (!window.jQuery || !jQuery.fn.DataTable) {
      if (reintentos++ < 50) {
        setTimeout(function () {
          pintar();
        }, 60);
        return;
      }
      aviso("Sin DataTables", "No se cargó la librería de tablas (¿conexión?)", "error", 6000);
      return;
    }

    reintentos = 0;

    if (tabla) {
      tabla.clear().rows.add(datos).draw(false);
      return;
    }

    tabla = jQuery("#tablaCajas").DataTable({
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
        [1, "asc"],
      ],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json",
      },
      dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "44px" },
        { targets: -1, width: "150px" },
      ],
      columns: [
        { data: "id", title: "#", className: "dt-num", responsivePriority: 10, searchable: false },

        {
          data: "codigo",
          render: function (t) {
            return '<span class="badge-mod badge-ambar">' + escapar(t) + "</span>";
          },
          responsivePriority: 4,
        },

        {
          data: "nombre",
          responsivePriority: 1,
          render: function (t, type, fila) {
            var nombre = escapar(t) || '<span class="vacio">Sin nombre</span>';
            var desc = fila.descripcion
              ? '<span class="dt-subtexto"><i class="fa-solid fa-circle-info"></i> ' + escapar(fila.descripcion) + "</span>"
              : "";
            return '<span class="dt-texto">' + nombre + "</span>" + desc;
          },
        },

        {
          data: "sucursal",
          responsivePriority: 3,
          render: function (t, type, fila) {
            var extra = escapar(fila.sucursal_codigo || "");
            if (fila.ciudad) extra += (extra ? " · " : "") + escapar(fila.ciudad);
            return (
              '<span class="dt-texto"><i class="fa-solid fa-store" style="color:#d9232d;font-size:11px"></i> ' +
              (escapar(t) || "—") +
              "</span>" +
              (extra ? '<span class="dt-subtexto">' + extra + "</span>" : "")
            );
          },
        },

        {
          data: "responsable",
          responsivePriority: 2,
          render: function (t, type, fila) {
            return chipResponsable(fila);
          },
        },

        {
          data: "monto_inicial_sugerido",
          className: "dt-num",
          responsivePriority: 7,
          render: function (t) {
            return '<span class="dt-texto">' + formatoBs(t) + "</span>";
          },
        },

        {
          data: "estado",
          responsivePriority: 6,
          orderable: false,
          render: function (t) {
            return chipEstado(t);
          },
        },

        {
          data: null,
          name: "acciones",
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
  // MODAL ALTA / EDICIÓN
  // =========================================================
  function abrirModal() {
    modal = $s("#modalCaja");
    if (!modal) return;

    limpiarErrores();
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var primero = $s("#c_codigo");
      if (primero) primero.focus();
    }, 60);
  }

  function cerrarModal() {
    modal = modal || $s("#modalCaja");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    var form = document.getElementById("formCaja");
    if (form) {
      form.reset();
      form.dataset.accion = "crear";
      form.dataset.id = "";
    }

    setText("#mCajaTexto", "Nueva caja");
    setIcono("#mCajaIcono", "fa-cash-register");
    setText("#mCajaNota", "");
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
    window.CajasAPI.obtener(id).then(function (rpta) {
      if (!rpta.success || !rpta.data || !rpta.data.caja) {
        aviso("No se pudo cargar", rpta.message || "Caja no encontrada", "error");
        return;
      }

      cargarEnModal(rpta.data.caja);
      abrirModal();
    });
  }

  function cargarEnModal(c) {
    var mapa = {
      c_id: c.id,
      c_sucursal: c.id_sucursal,
      c_codigo: c.codigo,
      c_nombre: c.nombre,
      c_descripcion: c.descripcion,
      c_monto: c.monto_inicial_sugerido,
      c_estado: c.estado,
    };

    Object.keys(mapa).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = mapa[id] == null ? "" : mapa[id];
    });

    var form = document.getElementById("formCaja");
    form.dataset.accion = "actualizar";
    form.dataset.id = c.id;

    setText("#mCajaTexto", "Modificar caja");
    setIcono("#mCajaIcono", "fa-pen-to-square");
    setText("#mCajaNota", "Editando: " + (c.nombre || ""));
  }

  // =========================================================
  // MODAL ASIGNAR
  // =========================================================
  function abrirAsignar(caja) {
    modalAsignar = $s("#modalAsignarCaja");
    if (!modalAsignar) return;

    cajaAsignando = caja;

    var titulo = caja.codigo + " · " + caja.nombre;
    setText("#asResumenTitulo", titulo);
    setText(
      "#asResumenSucursal",
      (caja.sucursal || "Sucursal") + (caja.ciudad ? " · " + caja.ciudad : "")
    );
    setText(
      "#asResumenResponsable",
      Number(caja.id_usuario_asignado) > 0 ? caja.responsable || caja.username : "Sin asignar"
    );

    var cajaActual = $s("#asResumenActual");
    if (cajaActual) cajaActual.classList.toggle("sin", !(Number(caja.id_usuario_asignado) > 0));

    document.getElementById("as_id").value = caja.id;
    document.getElementById("as_sucursal").value = caja.id_sucursal;
    document.getElementById("as_observacion").value = "";

    var liberar = $s("#asLiberar");
    if (liberar) liberar.style.display = Number(caja.id_usuario_asignado) > 0 ? "" : "none";

    var sel = $s("#as_usuario");
    sel.innerHTML = '<option value="">Cargando personal…</option>';
    sel.disabled = true;

    modalAsignar.classList.add("abierto");
    modalAsignar.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    window.CajasAPI.usuarios(caja.id_sucursal, caja.id).then(function (rpta) {
      if (!rpta.success) {
        sel.innerHTML = '<option value="">No se pudo cargar el personal</option>';
        aviso("Sin personal", rpta.message || "Intenta de nuevo", "error", 5000);
        return;
      }

      pintarUsuarios(rpta.data.usuarios || [], caja.id_usuario_asignado);
    });
  }

  function pintarUsuarios(usuarios, idActual) {
    var sel = $s("#as_usuario");
    if (!sel) return;

    sel.innerHTML = '<option value="">— Selecciona un responsable —</option>';

    if (!usuarios.length) {
      sel.innerHTML = '<option value="">La sucursal no tiene personal activo</option>';
      sel.disabled = true;
      return;
    }

    usuarios.forEach(function (u) {
      var op = document.createElement("option");
      op.value = String(u.id);

      var etiqueta = u.nombre + " (" + u.username + ") · " + u.rol;
      if (!u.disponible) etiqueta += " — ya tiene " + u.caja_actual;

      op.textContent = etiqueta;
      op.disabled = !u.disponible;
      op.selected = Number(u.id) === Number(idActual);

      sel.appendChild(op);
    });

    sel.disabled = false;
  }

  function cerrarAsignar() {
    modalAsignar = modalAsignar || $s("#modalAsignarCaja");
    if (!modalAsignar) return;

    modalAsignar.classList.remove("abierto");
    modalAsignar.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    cajaAsignando = null;
  }

  function guardarAsignacion(ev) {
    ev.preventDefault();

    if (!cajaAsignando) return;

    limpiarErrores();

    var idUsuario = Number($s("#as_usuario").value || "0");
    var observacion = ($s("#as_observacion").value || "").trim();

    if (!idUsuario) {
      marcarError("id_usuario", "Selecciona a quién se entrega la caja");
      aviso("Falta el responsable", "Selecciona un personal de la sucursal", "warning");
      return;
    }

    var boton = $s("#asGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Asignando…</span>';

    window.CajasAPI.asignar(cajaAsignando.id, idUsuario, observacion).then(function (rpta) {
      boton.disabled = false;
      boton.innerHTML = boton.dataset.html;

      if (rpta.success) {
        cerrarAsignar();
        avisoGrande("Caja asignada", rpta.message || "La caja ya tiene responsable", "success");
        cargar();
        return;
      }

      aviso("No se pudo asignar", rpta.message || "Revisa los datos", "error", 5500);
    });
  }

  function liberarCaja() {
    if (!cajaAsignando) return;

    Swal.fire({
      icon: "warning",
      title: "¿Liberar la caja?",
      html:
        "La caja <strong>" +
        escapar(cajaAsignando.codigo + " · " + cajaAsignando.nombre) +
        "</strong> quedará sin responsable.<br>" +
        "<span style='font-size:13.5px;color:#8a8a8f'>El historial de la asignación se conserva.</span>",
      showCancelButton: true,
      confirmButtonText: "Sí, liberar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (resp) {
      if (!resp.isConfirmed) return;

      window.CajasAPI.liberar(cajaAsignando.id).then(function (rpta) {
        if (rpta.success) {
          cerrarAsignar();
          avisoGrande("Caja libre", rpta.message || "La caja quedó sin responsable", "success");
          cargar();
          return;
        }

        aviso("No se pudo liberar", rpta.message || "Intenta de nuevo", "error", 5500);
      });
    });
  }

  // =========================================================
  // VER DETALLE + HISTORIAL
  // =========================================================
  function verDetalle(id) {
    var fila = filas.find(function (f) {
      return Number(f.id) === Number(id);
    });

    if (!fila) {
      aviso("Sin datos", "No se encontró el registro", "warning");
      return;
    }

    window.CajasAPI.historial(id).then(function (rpta) {
      var historial = rpta.success ? rpta.data.historial || [] : [];

      var campo = function (et, val) {
        return "<dt>" + et + "</dt><dd>" + (escapar(val) || "—") + "</dd>";
      };

      var html =
        '<div style="text-align:center;margin-bottom:6px">' +
        chipEstado(fila.estado) +
        " " +
        (Number(fila.id_usuario_asignado) > 0
          ? '<span class="badge-mod badge-verde"><i class="fa-solid fa-user-check"></i> Asignada</span>'
          : '<span class="badge-mod badge-ambar"><i class="fa-solid fa-user-slash"></i> Libre</span>') +
        "</div>" +
        '<dl class="dl-datos">' +
        campo("Código", fila.codigo) +
        campo("Caja", fila.nombre) +
        campo("Sucursal", fila.sucursal + (fila.ciudad ? " · " + fila.ciudad : "")) +
        campo("Descripción", fila.descripcion) +
        campo("Responsable", fila.responsable || fila.username || "Sin asignar") +
        campo("Monto inicial sugerido", formatoBs(fila.monto_inicial_sugerido)) +
        campo("Creada el", fila.creada) +
        "</dl>" +
        '<div class="caja-hist">' +
        '<div class="caja-hist-titulo"><i class="fa-solid fa-clock-rotate-left"></i> Historial de asignaciones</div>' +
        (historial.length
          ? historial
              .map(function (h) {
                return (
                  '<div class="caja-hist-fila' + (Number(h.activa) === 1 ? " activa" : "") + '">' +
                  '<span class="chf-quien">' +
                  escapar(h.usuario) +
                  (Number(h.activa) === 1 ? ' <span class="badge-mod badge-verde">Actual</span>' : "") +
                  "</span>" +
                  '<span class="chf-fecha">' +
                  h.desde +
                  " → " +
                  (h.hasta || "sin fin") +
                  "</span>" +
                  (h.observacion ? '<span class="chf-obs">' + escapar(h.observacion) + "</span>" : "") +
                  "</div>"
                );
              })
              .join("")
          : '<div class="caja-hist-vacio">Todavía no tiene asignaciones registradas.</div>') +
        "</div>";

      Swal.fire({
        icon: "info",
        title: fila.nombre || "Caja",
        html: html,
        width: 560,
        confirmButtonText: "Cerrar",
        confirmButtonColor: "#d9232d",
      });
    });
  }

  // =========================================================
  // BAJA
  // =========================================================
  function eliminar(id) {
    var fila = filas.find(function (f) {
      return Number(f.id) === Number(id);
    });

    Swal.fire({
      icon: "warning",
      title: "¿Dar de baja la caja?",
      html:
        "Se dará de baja <strong>" +
        (fila ? escapar(fila.codigo + " · " + fila.nombre) : "#" + id) +
        "</strong>.<br>" +
        "<span style='font-size:13.5px;color:#8a8a8f'>Podrás reactivarla editándola desde la tabla.</span>",
      showCancelButton: true,
      confirmButtonText: "Sí, dar de baja",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (resp) {
      if (!resp.isConfirmed) return;

      window.CajasAPI.eliminar(id).then(function (rpta) {
        if (rpta.success) {
          avisoGrande("Baja realizada", rpta.message || "Caja dada de baja", "success");
          cargar();
          return;
        }

        aviso("No se pudo eliminar", rpta.message || "Intenta de nuevo", "error", 5500);
      });
    });
  }

  // =========================================================
  // GUARDAR CAJA
  // =========================================================
  function leerFormulario() {
    return {
      id: Number($s("#c_id").value || "0") || 0,
      id_sucursal: Number($s("#c_sucursal").value || "0") || 0,
      codigo: ($s("#c_codigo").value || "").trim().toUpperCase(),
      nombre: ($s("#c_nombre").value || "").trim(),
      descripcion: ($s("#c_descripcion").value || "").trim(),
      monto_inicial_sugerido: Number($s("#c_monto").value || "0") || 0,
      estado: Number($s("#c_estado").value || "1"),
    };
  }

  function validar(d) {
    limpiarErrores();
    var fallos = [];

    if (!d.id_sucursal) {
      marcarError("id_sucursal", "Selecciona la sucursal donde estará la caja");
      fallos.push("sucursal");
    }

    if (!/^[A-Z0-9][A-Z0-9\-_]{1,19}$/.test(d.codigo)) {
      marcarError("codigo", "2-20 caracteres: letras, números, - o _");
      fallos.push("código");
    }

    if (d.nombre.length < 3) {
      marcarError("nombre", "Mínimo 3 caracteres");
      fallos.push("nombre");
    }

    if (d.monto_inicial_sugerido < 0) {
      marcarError("monto_inicial_sugerido", "No puede ser negativo");
      fallos.push("monto");
    }

    return fallos;
  }

  function enviar(ev) {
    ev.preventDefault();

    var form = document.getElementById("formCaja");
    var modoEdicion = form.dataset.accion === "actualizar";
    var datos = leerFormulario();
    var fallos = validar(datos);

    if (fallos.length) {
      aviso("Revisa el formulario", "Faltan datos: " + fallos.join(", "), "warning", 4000);
      var primerError = document.querySelector(".campo.error input, .campo.error select");
      if (primerError) primerError.focus();
      return;
    }

    var boton = $s("#mCajaGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

    var promesa = modoEdicion
      ? window.CajasAPI.actualizar(datos)
      : window.CajasAPI.crear(datos);

    promesa.then(function (rpta) {
      boton.disabled = false;
      boton.innerHTML = boton.dataset.html;

      if (!rpta.success) {
        aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5500);
        return;
      }

      cerrarModal();
      cargar();

      if (modoEdicion) {
        avisoGrande("Caja actualizada", rpta.message || "Los datos se guardaron", "success");
        return;
      }

      // Flujo natural: crear → asignar al personal
      var nueva = {
        id: rpta.data.id,
        codigo: datos.codigo,
        nombre: datos.nombre,
        descripcion: datos.descripcion,
        id_sucursal: datos.id_sucursal,
        sucursal: nombreSucursal(datos.id_sucursal),
        ciudad: ciudadSucursal(datos.id_sucursal),
        id_usuario_asignado: 0,
        responsable: "",
        username: "",
      };

      Swal.fire({
        icon: "success",
        title: "Caja creada",
        text: "¿Quieres asignarla ahora a un personal de la sucursal?",
        showCancelButton: true,
        confirmButtonText: "Asignar ahora",
        cancelButtonText: "Después",
        confirmButtonColor: "#d9232d",
        cancelButtonColor: "#9aa0a6",
        reverseButtons: true,
      }).then(function (resp) {
        if (resp.isConfirmed) abrirAsignar(nueva);
      });
    });
  }

  function nombreSucursal(id) {
    var sel = $s("#c_sucursal");
    if (!sel) return "";
    var op = sel.querySelector('option[value="' + id + '"]');
    if (!op) return "";
    return op.textContent.split("·")[0].trim();
  }

  function ciudadSucursal(id) {
    var sel = $s("#c_sucursal");
    if (!sel) return "";
    var op = sel.querySelector('option[value="' + id + '"]');
    if (!op) return "";
    var partes = op.textContent.split("·");
    return partes.length > 1 ? partes[1].trim() : "";
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
    modal = $s("#modalCaja");
    modalAsignar = $s("#modalAsignarCaja");

    cargar();

    var btnNuevo = $s("#btnNuevaCaja");
    if (btnNuevo) {
      btnNuevo.addEventListener("click", function () {
        var sel = $s("#filtroSucursal");
        if (sel && sel.value) {
          var sugerida = $s("#c_sucursal");
          if (sugerida) sugerida.value = sel.value;
        }
        abrirModal();
      });
    }

    var btnCancelar = $s("#mCajaCancelar");
    if (btnCancelar) btnCancelar.addEventListener("click", cerrarModal);

    var btnCerrar = $s("#mCajaCerrar");
    if (btnCerrar) btnCerrar.addEventListener("click", cerrarModal);

    if (modal) {
      modal.addEventListener("click", function (ev) {
        if (ev.target === modal) cerrarModal();
      });
    }

    var form = document.getElementById("formCaja");
    if (form) {
      form.addEventListener("submit", enviar);

      form.addEventListener("input", function (ev) {
        var caja = ev.target.closest(".campo");
        if (caja && caja.classList.contains("error")) {
          marcarError(caja.getAttribute("data-campo"), "");
        }
      });
    }

    // Código en mayúsculas mientras se escribe
    var codigo = $s("#c_codigo");
    if (codigo) {
      codigo.addEventListener("input", function () {
        var ini = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(ini, ini);
      });
    }

    // Modal asignar
    var formAsignar = document.getElementById("formAsignarCaja");
    if (formAsignar) formAsignar.addEventListener("submit", guardarAsignacion);

    var cerrarAsig = $s("#mAsigCerrar");
    if (cerrarAsig) cerrarAsig.addEventListener("click", cerrarAsignar);

    var cancelarAsig = $s("#mAsigCancelar");
    if (cancelarAsig) cancelarAsig.addEventListener("click", cerrarAsignar);

    var liberar = $s("#asLiberar");
    if (liberar) liberar.addEventListener("click", liberarCaja);

    if (modalAsignar) {
      modalAsignar.addEventListener("click", function (ev) {
        if (ev.target === modalAsignar) cerrarAsignar();
      });
    }

    // Filtro por sucursal
    var filtro = $s("#filtroSucursal");
    if (filtro) {
      filtro.addEventListener("change", function () {
        if (tabla) {
          var datos = datosFiltrados();
          actualizarContador(datos);
          tabla.clear().rows.add(datos).draw(false);
        }
      });
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;
      var abierto = document.querySelector(".modal-overlay.abierto");
      if (!abierto) return;
      if (abierto.id === "modalCaja") cerrarModal();
      else if (abierto.id === "modalAsignarCaja") cerrarAsignar();
    });

    // Acciones por delegación (sobrevive los draw de DataTables)
    var tbody = document.querySelector("#tablaCajas tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton || boton.disabled) return;

        var id = boton.getAttribute("data-id");
        var acc = boton.getAttribute("data-accion");

        if (acc === "ver") {
          verDetalle(id);
          return;
        }

        var fila = filas.find(function (f) {
          return Number(f.id) === Number(id);
        });

        if (acc === "editar") editar(id);
        else if (acc === "asignar" && fila) abrirAsignar(fila);
        else if (acc === "eliminar") eliminar(id);
      });
    }
  });
})();
