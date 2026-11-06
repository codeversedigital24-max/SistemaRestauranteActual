/**
 * =====================================================
 * modelos/sucursales/sucursales.js
 * =====================================================
 * UI del módulo de sucursales (misma receta que usuarios):
 *   · 1 fetch → DataTable con acciones Ver / Editar / Eliminar
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

  // =========================================================
  // CARGA
  // =========================================================
  function mostrarCarga(activar) {
    var caja = $s("#tablaCarga");
    if (caja) caja.classList.toggle("visible", Boolean(activar));
  }

  function cargar() {
    if (!window.SucursalesAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.SucursalesAPI.listar().then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso(
          "No se pudo cargar",
          rpta.message || "Error al leer sucursales",
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

    return (
      '<span class="acciones-fila">' +
      '<button type="button" class="btn-tabla ver" data-accion="ver" data-id="' +
      fila.id +
      '" title="Ver detalle" aria-label="Ver detalle"><i class="fa-solid fa-eye"></i></button>' +
      '<button type="button" class="btn-tabla editar" data-accion="editar" data-id="' +
      fila.id +
      '" title="Modificar" aria-label="Modificar"><i class="fa-solid fa-pen"></i></button>' +
      '<button type="button" class="btn-tabla borrar" data-accion="eliminar" data-id="' +
      fila.id +
      '" title="' +
      (baja ? "Reactivar (use Editar)" : "Dar de baja") +
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

    tabla = jQuery("#tablaSucursales").DataTable({
      data: datos,
      deferRender: true,
      responsive: { details: { type: "inline", target: "tr" } },
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [
        [8, 10, 25, 50, -1],
        [8, 10, 25, 50, "Todos"],
      ],
      order: [[2, "asc"]],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json",
      },
      dom:
        "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "48px" },
        { targets: -1, width: "120px" },
      ],

      columns: [
        // # (se oculta primero en móvil)
        {
          data: "id",
          title: "#",
          className: "dt-num",
          responsivePriority: 9,
          searchable: false,
        },

        // Código (pill)
        {
          data: "codigo",
          responsivePriority: 4,
          render: function (t) {
            return (
              '<span class="badge-mod badge-ambar">' + escapar(t) + "</span>"
            );
          },
        },

        // Nombre (IMANTE en móvil: prioridad 1)
        {
          data: "nombre",
          responsivePriority: 1,
          render: function (t) {
            var limpio = escapar(t).trim();
            return limpio
              ? '<span class="dt-texto">' + limpio + "</span>"
              : '<span class="vacio">Sin nombre</span>';
          },
        },
        // Nombre (IMANTE en móvil: prioridad 1)
        {
          data: "direccion",
          responsivePriority: 5,
          defaultContent: "",
          render: function (t) {
            var c = escapar(t).trim();
            return c ? c : '<span class="vacio">—</span>';
          },
        },
        // Ciudad
        {
          data: "ciudad",
          responsivePriority: 5,
          defaultContent: "",
          render: function (t) {
            var c = escapar(t).trim();
            return c ? c : '<span class="vacio">—</span>';
          },
        },

        // Teléfono
        {
          data: "telefono",
          responsivePriority: 6,
          defaultContent: "",
          render: function (t) {
            var c = escapar(t).trim();
            return c ? c : '<span class="vacio">—</span>';
          },
        },

        // Correo
        {
          data: "email",
          responsivePriority: 7,
          className: "dt-subtexto-col",
          render: function (t) {
            var c = escapar(t).trim();
            return c
              ? '<span class="dt-subtexto">' + c + "</span>"
              : '<span class="vacio">—</span>';
          },
        },

        // Usuarios asignados
        {
          data: "usuarios",
          responsivePriority: 8,
          className: "dt-num",
          render: function (n) {
            var total = Number(n) || 0;
            return total
              ? '<span class="badge-mod badge-azul">' + total + "</span>"
              : '<span class="vacio">0</span>';
          },
        },

        // Estado
        {
          data: "estado",
          responsivePriority: 2,
          orderable: false,
          render: function (e) {
            return chipEstado(e);
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

    Swal.fire({
      icon: "info",
      title: fila.nombre || "Sucursal",
      html:
        '<div style="text-align:center;margin-bottom:6px">' +
        chipEstado(fila.estado) +
        "</div>" +
        '<dl class="dl-datos">' +
        campo("Código", fila.codigo) +
        campo("Ciudad", fila.ciudad) +
        campo("Dirección", fila.direccion) +
        campo("Teléfono", fila.telefono) +
        campo("Correo", fila.email) +
        campo("Usuarios", fila.usuarios) +
        campo("Creada el", fila.creada) +
        "</dl>",
      confirmButtonText: "Cerrar",
      confirmButtonColor: "#d9232d",
    });
  }

  // =========================================================
  // MODAL (alta / edición)
  // =========================================================
  function abrirModal() {
    modal = $s("#modalSucursal");
    limpiarErrores();
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var primero = $s("#s_codigo");
      if (primero) primero.focus();
    }, 60);
  }

  function cerrarModal() {
    modal = modal || $s("#modalSucursal");
    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    var form = document.getElementById("formSucursal");
    if (form) {
      form.reset();
      form.dataset.accion = "crear";
      form.dataset.id = "";
    }

    setText("#mSucTexto", "Nueva sucursal");
    setIcono("#mSucIcono", "fa-store");
    setText("#mSucNota", "");
    setText("#s_codigo", "");
    var est = $s("#s_estado");
    if (est) est.value = "1";
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
    window.SucursalesAPI.obtener(id).then(function (rpta) {
      if (!rpta.success || !rpta.data || !rpta.data.sucursal) {
        aviso(
          "No se pudo cargar",
          rpta.message || "Sucursal no encontrada",
          "error",
        );
        return;
      }

      cargarEnModal(rpta.data.sucursal);
      abrirModal();
    });
  }

  function cargarEnModal(s) {
    var mapa = {
      s_id: s.id,
      s_codigo: s.codigo,
      s_nombre: s.nombre,
      s_ciudad: s.ciudad,
      s_telefono: s.telefono,
      s_email: s.email,
      s_direccion: s.direccion,
      s_estado: s.estado,
    };

    Object.keys(mapa).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = mapa[id] == null ? "" : mapa[id];
    });

    var form = document.getElementById("formSucursal");
    form.dataset.accion = "actualizar";
    form.dataset.id = s.id;

    setText("#mSucTexto", "Modificar sucursal");
    setIcono("#mSucIcono", "fa-store");
    setText("#mSucNota", "Editando: " + (s.nombre || ""));
  }

  // =========================================================
  // ELIMINAR (baja lógica)
  // =========================================================
  function eliminar(id) {
    var fila = filas.find(function (f) {
      return Number(f.id) === Number(id);
    });

    Swal.fire({
      icon: "warning",
      title: "¿Dar de baja la sucursal?",
      html:
        "Se dará de baja <strong>" +
        (fila ? escapar(fila.nombre) : "#" + id) +
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

      window.SucursalesAPI.eliminar(id).then(function (rpta) {
        if (rpta.success) {
          avisoGrande(
            "Baja realizada",
            rpta.message || "Sucursal dada de baja",
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
  function leerFormulario() {
    return {
      id: Number($s("#s_id").value || "0") || 0,
      codigo: ($s("#s_codigo").value || "").trim().toUpperCase(),
      nombre: ($s("#s_nombre").value || "").trim(),
      ciudad: ($s("#s_ciudad").value || "").trim(),
      telefono: ($s("#s_telefono").value || "").trim(),
      email: ($s("#s_email").value || "").trim(),
      direccion: ($s("#s_direccion").value || "").trim(),
      estado: Number($s("#s_estado").value || 1),
    };
  }

  function validar(d) {
    limpiarErrores();
    var fallos = [];

    if (!/^[A-Z0-9][A-Z0-9\-_]{2,19}$/.test(d.codigo)) {
      marcarError("codigo", "3-20 caracteres: letras, números, - o _");
      fallos.push("código");
    }

    if (d.nombre.length < 3) {
      marcarError("nombre", "Mínimo 3 caracteres");
      fallos.push("nombre");
    }

    if (d.email !== "" && !/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(d.email)) {
      marcarError("email", "El correo no es válido");
      fallos.push("correo");
    }

    return fallos;
  }

  function enviar(ev) {
    ev.preventDefault();

    var form = document.getElementById("formSucursal");
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
      var primerError = document.querySelector(".campo.error input");
      if (primerError) primerError.focus();
      return;
    }

    if (!window.SucursalesAPI) {
      aviso("Error", "Módulo de red no disponible", "error");
      return;
    }

    var boton = $s("#mSucGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

    var promesa = modoEdicion
      ? window.SucursalesAPI.actualizar(datos)
      : window.SucursalesAPI.crear(datos);

    promesa.then(function (rpta) {
      boton.disabled = false;
      boton.innerHTML = boton.dataset.html;

      if (rpta.success) {
        cerrarModal();
        avisoGrande(
          modoEdicion ? "Actualizada" : "Sucursal creada",
          rpta.message || "Los datos se guardaron correctamente",
          "success",
        );
        cargar();
        return;
      }

      aviso(
        "No se pudo guardar",
        rpta.message || "Revisa los datos",
        "error",
        5500,
      );
    });
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
    modal = $s("#modalSucursal");

    cargar();

    var btnNuevo = $s("#btnNueva");
    if (btnNuevo) btnNuevo.addEventListener("click", abrirModal);

    var btnCancelar = $s("#mSucCancelar");
    if (btnCancelar) btnCancelar.addEventListener("click", cerrarModal);

    var btnCerrar = $s("#mSucCerrar");
    if (btnCerrar) btnCerrar.addEventListener("click", cerrarModal);

    if (modal) {
      modal.addEventListener("click", function (ev) {
        if (ev.target === modal) cerrarModal();
      });
    }

    var form = document.getElementById("formSucursal");
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
    var codigo = $s("#s_codigo");
    if (codigo) {
      codigo.addEventListener("input", function () {
        var ini = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(ini, ini);
      });
    }

    // Acciones por delegación (sobrevive los draw de DataTables)
    var tbody = document.querySelector("#tablaSucursales tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton || boton.disabled) return;

        var id = boton.getAttribute("data-id");
        var acc = boton.getAttribute("data-accion");

        if (acc === "ver") verDetalle(id);
        else if (acc === "editar") editar(id);
        else if (acc === "eliminar") eliminar(id);
      });
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;
      var abierto = document.querySelector(".modal-overlay.abierto");
      if (!abierto) return;
      if (abierto.id === "modalSucursal") cerrarModal();
    });
  });
})();
