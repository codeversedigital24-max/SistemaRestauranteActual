/**
 * =====================================================
 * modelos/usuarios/usuarios.js
 * =====================================================
 * UI del módulo de usuarios (compacta, PC y móvil):
 *   · DataTable con 1 fetch, acciones Ver / Modificar / Baja-Alta / Eliminar
 *   · Modal reutilizable de alta y edición (assets/css/modulos.css)
 *   · Avisos con SweetAlert2 (quedan SIEMPRE ENCIMA del modal)
 * =====================================================
 */
(function () {
  "use strict";

  var $s = function (sel, ctxo) {
    return (ctxo || document).querySelector(sel);
  };

  var tabla = null;
  var filasActuales = []; // respaldo local para "Ver" sin ida y vuelta
  var reintentosPintado = 0;
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
    if (!window.Swal) {
      return;
    }
    return Swal.fire({
      icon: tipo || "success",
      title: titulo,
      text: texto,
      confirmButtonText: "Entendido",
      confirmButtonColor: "#d9232d",
    });
  }

  // =========================================================
  // SEGURIDAD E IMPRESIÓN
  // =========================================================
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

  // =========================================================
  // FOTO DE PERFIL (avatar en la fila y en el detalle)
  // =========================================================
var CARPETA_FOTOS = "../uploads/usuarios/";

  function iniciales(nombre) {
    var partes = String(nombre || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);

    if (partes.length >= 2) {
      return (partes[0][0] + partes[1][0]).toUpperCase();
    }
    return partes.length === 1 ? partes[0].substr(0, 2).toUpperCase() : "US";
  }

  // Color estable por usuario (mismo usuario = mismo color)
  function colorDe(texto) {
    var colores = ["#d9232d", "#1f6fd0", "#14803d", "#9a6b00", "#7030a0", "#0f8a8a"];
    var suma = 0;
    var cadena = String(texto || "");

    for (var i = 0; i < cadena.length; i++) {
      suma += cadena.charCodeAt(i);
    }

    return colores[suma % colores.length];
  }

function rutaFoto(valor) {
    if (!valor) return "";

    // URL absoluta (http, https, //)
    if (/^(https?:)?\/\//.test(valor)) return valor;

    // Si ya viene con la carpeta, no la dupliques
    if (valor.indexOf("uploads/usuarios/") === 0) {
        return "../" + valor;              // → ../uploads/usuarios/u_xxx.png
    }

    // Si ya empieza por "/", se respeta
    if (valor.charAt(0) === "/") return valor;

    // Nombre suelto: anteponer carpeta UNA sola vez
    return CARPETA_FOTOS + valor;          // ../uploads/usuarios/ + u_xxx.png
}

  function avatarHTML(fila, clase) {
    var nombre = fila.nombre || fila.username || "";
    var ini = iniciales(nombre);
    var color = colorDe(fila.email || nombre);
    var foto = rutaFoto(fila.foto);

    if (foto) {
      return (
        '<img class="' + clase + ' con-foto" src="' + escapar(foto) +
        '" alt="" loading="lazy" data-ini="' + escapar(ini) +
        '" data-color="' + color + '">'
      );
    }

    return (
      '<span class="' + clase + ' avatar-iniciales" style="background:' +
      color + '">' + escapar(ini) + "</span>"
    );
  }

  // Si la foto no existe en disco → se cambia por las iniciales
  document.addEventListener(
    "error",
    function (ev) {
      var img = ev.target;
      if (!img || img.tagName !== "IMG") {
        return;
      }
      if (!img.classList.contains("avatar-mini") && !img.classList.contains("avatar-ver")) {
        return;
      }
      var span = document.createElement("span");
      span.className = img.className.replace("con-foto", "").trim() + " avatar-iniciales";
      span.style.background = img.getAttribute("data-color") || "#d9232d";
      span.textContent = img.getAttribute("data-ini") || "?";
      img.parentNode.replaceChild(span, img);
    },
    true // fase de captura: los errores de imagen no burbujean
  );

  function chipEstado(estado) {
    var mapa = {
      0: ["badge-gris", "De baja"],
      1: ["badge-verde", "Activo"],
      2: ["badge-rojo", "Bloqueado"],
    };
    var info = mapa[Number(estado)] || ["badge-gris", "—"];

    return '<span class="badge-mod ' + info[0] + '">' + info[1] + "</span>";
  }

  // =========================================================
  // CARGA DE DATOS
  // =========================================================
  function mostrarCarga(activar) {
    var caja = $s("#tablaCarga");
    if (caja) {
      caja.classList.toggle("visible", Boolean(activar));
    }
  }

  function cargar() {
    if (!window.UsuariosAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.UsuariosAPI.listar().then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso(
          "No se pudo cargar",
          rpta.message || "Error al leer usuarios",
          "error",
          5000,
        );
        pintar([]);
        return;
      }

      filasActuales = rpta.data || [];

      var marcador = $s("#contadorFilas");
      if (marcador) {
        marcador.textContent =
          filasActuales.length +
          (filasActuales.length === 1 ? " registro" : " registros");
      }

      pintar(filasActuales);
    });
  }

  // =========================================================
  // ACCIONES DE FILA
  // =========================================================
  function acciones(fila) {
    var acc = window.RM_ACCIONES || {};

    var botones =
      '<button type="button" class="btn-tabla ver" data-accion="ver" data-id="' +
      fila.id +
      '" title="Ver detalle" aria-label="Ver detalle"><i class="fa-solid fa-eye"></i></button>';

    if (acc.modificar) {
      botones +=
        '<button type="button" class="btn-tabla editar" data-accion="editar" data-id="' +
        fila.id +
        '" title="Modificar" aria-label="Modificar"><i class="fa-solid fa-pen"></i></button>';

      if (Number(fila.estado) === 1) {
        botones +=
          '<button type="button" class="btn-tabla baja" data-accion="baja" data-id="' +
          fila.id +
          '" title="Dar de baja" aria-label="Dar de baja"><i class="fa-solid fa-user-slash"></i></button>';
      } else {
        botones +=
          '<button type="button" class="btn-tabla alta" data-accion="alta" data-id="' +
          fila.id +
          '" title="Dar de alta" aria-label="Dar de alta"><i class="fa-solid fa-user-check"></i></button>';
      }
    }

    if (acc.eliminar) {
      botones +=
        '<button type="button" class="btn-tabla borrar" data-accion="eliminar" data-id="' +
        fila.id +
        '" title="Eliminar definitivamente" aria-label="Eliminar definitivamente"><i class="fa-solid fa-trash-can"></i></button>';
    }

    if (acc.modificar) {
      botones +=
        '<button type="button" class="btn-tabla permiso" data-accion="permisos" data-id="' +
        fila.id +
        '" title="Privilegios / permisos" aria-label="Privilegios"><i class="fa-solid fa-user-shield"></i></button>';
    }

    return '<span class="acciones-fila">' + botones + "</span>";
  }

  // =========================================================
  // TABLA
  // =========================================================
  function pintar(filas) {
    if (!window.jQuery || !jQuery.fn.DataTable) {
      if (reintentosPintado++ < 50) {
        setTimeout(function () {
          pintar(filas);
        }, 60);
        return;
      }
      aviso(
        "Sin DataTables",
        "No se cargó la librería de tablas (¿conexión a internet?)",
        "error",
        6000,
      );
      return;
    }

    reintentosPintado = 0;

    if (tabla) {
      tabla.clear().rows.add(filas).draw(false);
      return;
    }

    tabla = jQuery("#tablaUsuarios").DataTable({
      data: filas,
      deferRender: true,
      responsive: {
        details: {
          type: "inline", // ← expandir en la misma fila, más elegante
          target: "tr",
        },
      },
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [
        [8, 10, 25, 50, -1],
        [8, 10, 25, 50, "Todos"],
      ],
      order: [[1, "asc"]],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json",
      },
// ESTA ES LA CLAVE: agrupamos el buscador (f) y el selector (l) en un solo div
  dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "48px" }, // el # no crece
        { targets: -1, width: "190px" }, // acciones (Ver / Editar / Baja-Alta / Eliminar / Permisos)
      ],

      columns: [
        {
          data: "id",
          title: "#",
          className: "col-id", // ← clase propia, no dt-num
          responsivePriority: 8,
          searchable: false,
        },
           
        {
          data: "nombre",
          name: "nombre",
          className: "col-nombre", // ← NADA de dt-texto en el td
          responsivePriority: 1,
          defaultContent: "",
          render: function (texto, tipo, fila) {
            var limpio = escapar(texto).trim();
            return (
              '<span class="avatar-fila">' +
              avatarHTML(fila, "avatar-mini") +
              '<span class="dt-texto">' +
              (limpio ? limpio : '<span class="vacio">Sin nombre</span>') +
              "</span></span>"
            );
          },
        },
              {
          data: "cedula",
          responsivePriority: 5,
          render: function (texto, tipo, fila) {
            var u = escapar(texto);
            if (tipo === "filter" || tipo === "sort") {
              return u + " " + escapar(fila.email);
            }
            return u;
          },
        },
         {
          data: "telefono",
          responsivePriority: 5,
          render: function (texto, tipo, fila) {
            var u = escapar(texto);
            if (tipo === "filter" || tipo === "sort") {
              return u + " " + escapar(fila.email);
            }
            return u;
          },
        },
        {
          data: "username",
          responsivePriority: 5,
          render: function (texto, tipo, fila) {
            var u = escapar(texto);
            if (tipo === "filter" || tipo === "sort") {
              return u + " " + escapar(fila.email);
            }
            return u;
          },
        },
        {
          data: "email",
          responsivePriority: 6,
          className: "col-email",
          render: function (texto) {
            var mail = escapar(texto);
            return '<span class="dt-subtexto">' + mail + "</span>";
          },
        },
        {
          data: "rol",
          responsivePriority: 3,
          render: function (texto) {
            return (
              '<span class="badge-mod badge-azul">' + escapar(texto) + "</span>"
            );
          },
        },
        {
          data: "sucursal",
          responsivePriority: 7,
          defaultContent: "",
          render: function (texto) {
            var s = escapar(texto).trim();
            return s && s !== "—" ? s : '<span class="vacio">Global</span>';
          },
        },
        {
          data: "estado",
          responsivePriority: 2,
          className: "col-estado",
          orderable: false,
          render: function (estado) {
            return chipEstado(estado);
          },
        },
        {
          data: null,
          name: "acciones",
          orderable: false,
          searchable: false,
          className: "col-acciones",
          responsivePriority: 4,
          render: function (fila) {
            return acciones(fila);
          },
        },
      ],

      drawCallback: function () {
        if (tabla) {
          var info = tabla.page.info();
          var mar = $s("#contadorFilas");
          if (mar) {
            mar.textContent =
              info.recordsTotal +
              " registros" +
              (info.recordsFiltered !== info.recordsTotal
                ? " (mostrando " + info.recordsFiltered + ")"
                : "");
          }
        }
      },
    });
  }

 function abrirModal() {
    modal = $s("#modalUsuario");
    limpiarErrores();
    // ❌ QUITAR: resetFotoPreview(null);
    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
        var primero = $s("#f_nombres");
        if (primero) primero.focus();
    }, 60);
}

  function cerrarModal() {
    modal = modal || $s("#modalUsuario");
    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";

    var form = document.getElementById("formNuevoUsuario");
    if (form) {
      form.reset();
      form.dataset.accion = "crear";
      form.dataset.id = "";
    }

    var selEstado = $s("#f_estado");
    if (selEstado) selEstado.value = "1";

    // Volver etiquetas al modo alta (asteriscos visibles)
    document
      .querySelectorAll(
        ".campo[data-campo='password'] label .req, .campo[data-campo='password2'] label .req",
      )
      .forEach(function (x) {
        x.style.display = "";
      });

    var titulo = $s("#modalTexto");
    var icono = $s("#modalIcono");
    if (titulo) {
      titulo.textContent = "Nuevo usuario";
    }
    if (icono) {
      icono.innerHTML = '<i class="fa-solid fa-user-plus"></i>';
    }

    var ayuda = $s("#ayudaPassword");
    if (ayuda) {
      ayuda.textContent = "Mínimo 8 caracteres";
      ayuda.classList.remove("error");
    }

    var etiqueta = $s("#etiquetaPassword");
    if (etiqueta) {
      etiqueta.textContent = "Contraseña";
    }

    // Contraseñas obligatorias en alta
    var pw = $s("#f_password");
    var pw2 = $s("#f_password2");
    if (pw) {
      pw.required = true;
    }
    if (pw2) {
      pw2.required = true;
    }
       var pie = $s("#pieNota");
    if (pie) {
      pie.textContent = "";
    }

    // Al cerrar, dejar el preview limpio para la próxima apertura
    resetFotoPreview(null);
  }
  var estaAbierto = function () {
    return modal && modal.classList.contains("abierto");
  };

  // =========================================================
  // VER DETALLE
  // =========================================================
  function verDetalle(id) {
    var fila = filasActuales.find(function (f) {
      return Number(f.id) === Number(id);
    });

    if (!fila) {
      aviso("Sin datos", "No se encontró el registro en pantalla", "warning");
      return;
    }

    var campo = function (etiqueta, valor) {
      return (
        "<dt>" + etiqueta + "</dt><dd>" + (escapar(valor) || "—") + "</dd>"
      );
    };

    Swal.fire({
      icon: "info",
      title: fila.nombre || "Usuario",
      html:
        '<div class="detalle-foto">' + avatarHTML(fila, "avatar-ver") + "</div>" +
        '<dl class="dl-datos">' +
        campo("Usuario", fila.username) +
        campo("Correo", fila.email) +
        campo("Cédula", fila.cedula) +
        campo("Teléfono", fila.telefono) +
        campo("Rol", fila.rol) +
        campo("Sucursal", fila.sucursal) +
        campo("Último ingreso", fila.ultimo_login) +
        "</dl>" +
        '<div style="margin-top:10px">' +
        chipEstado(fila.estado) +
        "</div>",
      confirmButtonText: "Cerrar",
      confirmButtonColor: "#d9232d",
    });
  }

  // =========================================================
  // EDITAR
  // =========================================================
  function editar(id) {
    if (!window.UsuariosAPI) {
      return;
    }
    window.UsuariosAPI.obtener(id).then(function (rpta) {
      if (!rpta.success || !rpta.data || !rpta.data.usuario) {
        aviso(
          "No se pudo cargar",
          rpta.message || "Usuario no encontrado",
          "error",
        );
        return;
      }
      cargarEnModal(rpta.data.usuario);
      abrirModal();
    });
  }

  function cargarEnModal(u) {
    var mapa = {
      f_id: u.id,
      f_nombres: u.nombres,
      f_apellidos: u.apellidos,
      f_cedula: u.cedula,
      f_telefono: u.telefono,
      f_email: u.email,
      f_username: u.username,
      f_rol: u.id_rol,
      f_sucursal: u.id_sucursal,
      f_estado: Number(u.estado) === 0 ? 0 : 1,
    };

    Object.keys(mapa).forEach(function (id) {
      var campo = document.getElementById(id);
      if (campo) {
        campo.value = mapa[id] == null ? "" : mapa[id];
      }
    });

    var form = document.getElementById("formNuevoUsuario");
    form.dataset.accion = "actualizar";
    form.dataset.id = u.id;

    var titulo = $s("#modalTexto");
    var icono = $s("#modalIcono");
    if (titulo) {
      titulo.textContent = "Modificar usuario";
    }
    if (icono) {
      icono.innerHTML = '<i class="fa-solid fa-user-pen"></i>';
    }

    // La contraseña es opcional al editar
    var pw = $s("#f_password");
    var pw2 = $s("#f_password2");
    if (pw) {
      pw.required = false;
      pw.value = "";
      pw.placeholder = "Dejar vacío para no cambiar";
    }
    if (pw2) {
      pw2.required = false;
      pw2.value = "";
      pw2.placeholder = "Sólo si cambias la contraseña";
    }

    var etiqueta = $s("#etiquetaPassword");
    if (etiqueta) {
      etiqueta.textContent = "Nueva contraseña (opcional)";
    }

    // Ocultar asteriscos de contraseña (ahora es opcional)
    document
      .querySelectorAll(
        ".campo[data-campo='password'] label .req, .campo[data-campo='password2'] label .req",
      )
      .forEach(function (x) {
        x.style.display = "none";
      });

    var ayuda = $s("#ayudaPassword");
    if (ayuda) {
      ayuda.textContent = "Vacío = se conserva la actual";
    }

    var pie = $s("#pieNota");
    if (pie) {
      pie.textContent = "Editando a: " + (u.username || "");
    }
    resetFotoPreview(u.foto ? rutaFoto(u.foto) : null);
  }

  // =========================================================
  // ALTA / BAJA (sin borrar historial)
  // =========================================================
  function cambiarEstado(id, nuevoEstado) {
    var fila = filasActuales.find(function (f) {
      return Number(f.id) === Number(id);
    });

    var esBaja = Number(nuevoEstado) === 0;
    var nombre = fila ? escapar(fila.nombre || fila.username) : "#" + id;

    Swal.fire({
      icon: esBaja ? "warning" : "question",
      title: esBaja ? "¿Dar de baja al usuario?" : "¿Dar de alta al usuario?",
      html:
        (esBaja
          ? "Se dará de baja a <strong>" + nombre + "</strong>." +
            "<br><span style='font-size:13.5px;color:#8a8a8f'>No podrá iniciar sesión, pero conservará todo su historial (ventas, pedidos, caja, etc.).</span>"
          : "Se dará de alta a <strong>" + nombre + "</strong>." +
            "<br><span style='font-size:13.5px;color:#8a8a8f'>Podrá iniciar sesión nuevamente con sus mismos privilegios.</span>"),
      showCancelButton: true,
      confirmButtonText: esBaja ? "Sí, dar de baja" : "Sí, dar de alta",
      cancelButtonText: "Cancelar",
      confirmButtonColor: esBaja ? "#b57a00" : "#14803d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (respuesta) {
      if (!respuesta.isConfirmed) {
        return;
      }

      window.UsuariosAPI.cambiarEstado(id, nuevoEstado).then(function (rpta) {
        if (rpta.success) {
          avisoGrande(
            esBaja ? "Usuario de baja" : "Usuario activo",
            rpta.message || (esBaja ? "Usuario dado de baja" : "Usuario dado de alta"),
            "success",
          );
          cargar();
        } else {
          aviso(
            "No se pudo cambiar el estado",
            rpta.message || "Intenta de nuevo",
            "error",
            5500,
          );
        }
      });
    });
  }

  // =========================================================
  // ELIMINAR (definitivo, sólo si no tiene historial)
  // =========================================================
  function eliminar(id) {
    var fila = filasActuales.find(function (f) {
      return Number(f.id) === Number(id);
    });

    Swal.fire({
      icon: "warning",
      title: "¿Eliminar definitivamente?",
      html:
        "Se eliminará de forma permanente a <strong>" +
        (fila ? escapar(fila.nombre || fila.username) : "#" + id) +
        "</strong>." +
        "<br><span style='font-size:13.5px;color:#8a8a8f'>Sólo se permite si no tiene ventas, pedidos, compras, arqueos ni otros registros. Si tiene historial, la operación se bloqueará: en ese caso usa <b>Dar de baja</b>.</span>",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
      cancelButtonColor: "#9aa0a6",
      reverseButtons: true,
    }).then(function (respuesta) {
      if (!respuesta.isConfirmed) {
        return;
      }


      window.UsuariosAPI.eliminar(id).then(function (rpta) {

        if (rpta.success) {
          avisoGrande(
            "Eliminado",
            rpta.message || "Usuario eliminado definitivamente",
            "success",
          );
          cargar();
        } else {
          aviso(
            "No se pudo eliminar",
            rpta.message || "Intenta de nuevo",
            "error",
            7000,
          );
        }
      });
    });
  }

  // =========================================================
  // GUARDAR (alta o edición según el modal)
  // =========================================================
  function leerFormulario() {
    return {
      id: Number($s("#f_id").value || "0") || 0,
      nombres: ($s("#f_nombres").value || "").trim(),
      apellidos: ($s("#f_apellidos").value || "").trim(),
      cedula: ($s("#f_cedula").value || "").trim(),
      telefono: ($s("#f_telefono").value || "").trim(),
      email: ($s("#f_email").value || "").trim(),
      username: ($s("#f_username").value || "").trim(),
      id_rol: $s("#f_rol").value || "",
      id_sucursal: Number($s("#f_sucursal").value || 0),
      estado: Number($s("#f_estado").value === "" ? 1 : $s("#f_estado").value),
      password: $s("#f_password").value || "",
      password_confirmacion: $s("#f_password2").value || "",
    };
  }

  function validar(datos, modoEdicion) {
    limpiarErrores();
    var fallos = [];

    if (datos.nombres.length < 2) {
      marcarError("nombres", "Escribe el nombre completo");
      fallos.push("nombres");
    }

    if (datos.apellidos.length < 2) {
      marcarError("apellidos", "Escribe los apellidos");
      fallos.push("apellidos");
    }

    if (!/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(datos.email)) {
      marcarError("correo", "El correo no es válido");
      fallos.push("correo");
    }

    if (
      datos.username.length < 3 ||
      datos.username.length > 50 ||
      /\s/.test(datos.username)
    ) {
      marcarError("username", "Entre 3 y 50 caracteres y sin espacios");
      fallos.push("usuario");
    }

    if (!datos.id_rol) {
      marcarError("rol", "Selecciona un rol");
      fallos.push("rol");
    }

    // La contraseña sólo es obligatoria en ALTA
    if (!modoEdicion) {
      if (datos.password.length < 8) {
        marcarError("password", "Mínimo 8 caracteres");
        fallos.push("contraseña");
      }

      if (datos.password !== datos.password_confirmacion) {
        marcarError("password2", "Las contraseñas no coinciden");
        fallos.push("confirmación");
      }
    } else if (datos.password !== "" || datos.password_confirmacion !== "") {
      // Si se quiere cambiar, tiene que ser completa y válida
      if (datos.password.length < 8) {
        marcarError("password", "Mínimo 8 caracteres");
        fallos.push("contraseña");
      }

      if (datos.password !== datos.password_confirmacion) {
        marcarError("password2", "Las contraseñas no coinciden");
        fallos.push("confirmación");
      }
    }

    return fallos;
  }

function enviar(evento) {
    evento.preventDefault();

    var form = document.getElementById("formNuevoUsuario");
    var modoEdicion = form.dataset.accion === "actualizar";
    var datos = leerFormulario();
    var fallos = validar(datos, modoEdicion);

    if (fallos.length) {
        aviso("Revisa el formulario", "Faltan datos: " + fallos.join(", "), "warning", 4000);
        var primerError = document.querySelector(".campo.error input, .campo.error select");
        if (primerError) primerError.focus();
        return;
    }

    if (!window.UsuariosAPI) {
        aviso("Error", "Módulo de red no disponible", "error");
        return;
    }

    // Construimos FormData para poder adjuntar la imagen
    var fd = new FormData();
    Object.keys(datos).forEach(function (k) {
        fd.append(k, datos[k]);
    });

    if (archivoFoto) {
        fd.append("foto", archivoFoto);
    }
    if (fotoQuitada) {
        fd.append("quitar_foto", "1");
    }

    var promesa = modoEdicion
        ? window.UsuariosAPI.actualizar(fd)
        : window.UsuariosAPI.crear(fd);

    promesa.then(function (rpta) {
        if (rpta.success) {
            cerrarModal();
            avisoGrande(
                modoEdicion ? "Actualizado" : "Usuario creado",
                rpta.message || "Los datos se guardaron correctamente",
                "success"
            );
            cargar();
            return;
        }
        aviso("No se pudo guardar", rpta.message || "Revisa los datos", "error", 5500);
    });
}

// Estado local de la foto seleccionada
var archivoFoto = null;
var fotoQuitada = false;

var previewFoto   = document.getElementById("fotoPreview");
var inputFoto     = document.getElementById("f_foto");
var btnElegirFoto = document.getElementById("btnElegirFoto");
var btnQuitarFoto = document.getElementById("btnQuitarFoto");

btnElegirFoto.addEventListener("click", function () {
    inputFoto.click();
});

inputFoto.addEventListener("change", function () {
    var file = inputFoto.files && inputFoto.files[0];
    if (!file) return;

    // Validación básica en cliente
    var tiposOk = ["image/jpeg", "image/png", "image/webp"];
    if (tiposOk.indexOf(file.type) === -1) {
        aviso("Formato no válido", "Usa JPG, PNG o WEBP", "warning");
        inputFoto.value = "";
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        aviso("Imagen muy grande", "El máximo es 2 MB", "warning");
        inputFoto.value = "";
        return;
    }

    archivoFoto = file;
    fotoQuitada = false;

    var url = URL.createObjectURL(file);
    previewFoto.innerHTML = '<img src="' + url + '" alt="Foto de perfil">';
    previewFoto.classList.add("con-foto");
    btnQuitarFoto.style.display = "inline-flex";
});

btnQuitarFoto.addEventListener("click", function () {
    archivoFoto = null;
    fotoQuitada = true;
    inputFoto.value = "";
    previewFoto.innerHTML = '<i class="fa-solid fa-user"></i>';
    previewFoto.classList.remove("con-foto");
    btnQuitarFoto.style.display = "none";
});
function resetFotoPreview(fotoUrl) {
    archivoFoto = null;
    fotoQuitada = false;
    inputFoto.value = "";

    if (fotoUrl) {
        previewFoto.innerHTML = '<img src="' + fotoUrl + '" alt="Foto de perfil">';
        previewFoto.classList.add("con-foto");
        btnQuitarFoto.style.display = "inline-flex";
    } else {
        previewFoto.innerHTML = '<i class="fa-solid fa-user"></i>';
        previewFoto.classList.remove("con-foto");
        btnQuitarFoto.style.display = "none";
    }
}
  // =========================================================
  // VALIDACIÓN EN LÍNEA DEL MODAL
  // =========================================================
  function marcarError(campo, mensaje) {
    var contenedor = document.querySelector('[data-campo="' + campo + '"]');
    if (!contenedor) {
      return;
    }

    contenedor.classList.toggle("error", Boolean(mensaje));

    var ayuda = contenedor.querySelector(".ayuda");
    if (ayuda) {
      if (mensaje) {
        if (!ayuda.dataset.original) {
          ayuda.dataset.original = ayuda.textContent;
        }
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
  // PERMISOS / PRIVILEGIOS (modal propio)
  // =========================================================
  var permInicial = { id: 0, catalogo: null, super: false, tablaOk: true };

  function abrirOverlay(el) {
    if (!el) {
      return;
    }
    el.classList.add("abierto");
    el.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function cerrarOverlay(el) {
    if (!el) {
      return;
    }
    el.classList.remove("abierto");
    el.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function abrirPermisos(id) {
    var m = $s("#modalPermisos");
    if (!m || !window.UsuariosAPI) {
      aviso("Error", "El modal de permisos no está disponible", "error");
      return;
    }

    permInicial.id = Number(id) || 0;
    permInicial.catalogo = null;

    $s("#permResumen").innerHTML =
      '<span class="mini-spin" aria-hidden="true"></span> Cargando permisos…';
    $s("#permArbol").innerHTML = "";
    $s("#permContador").textContent = "0 seleccionados";
    $s("#permNota").textContent = "";
    $s("#permGuardar").disabled = true;

    abrirOverlay(m);

    window.UsuariosAPI.permisos(permInicial.id).then(function (rpta) {
      if (!rpta.success || !rpta.data || !rpta.data.catalogo) {
        cerrarOverlay(m);
        aviso("No se pudo cargar", rpta.message || "Error al leer permisos", "error");
        return;
      }
      pintarPermisos(rpta.data);
    });
  }

  function cerrarPermisos() {
    cerrarOverlay($s("#modalPermisos"));
  }

  function pintarPermisos(d) {
    permInicial = {
      id: d.usuario.id,
      catalogo: d.catalogo,
      super: !!d.es_superadmin,
      tablaOk: !!d.tabla_ok,
    };

    var nombre = d.usuario.nombre || d.usuario.username || "";
    $s("#permTexto").textContent = "Permisos de " + nombre;

    if (permInicial.super) {
      $s("#permResumen").innerHTML =
        '<span class="badge-mod badge-morado">SuperAdministrador</span> ' +
        '<span class="txt-tenue">tiene acceso total: no necesita permisos</span>';
      $s("#permGuardar").disabled = true;
      $s("#permArbol").innerHTML = "";
      $s("#permContador").textContent = "—";
      return;
    }

    if (!permInicial.tablaOk) {
      $s("#permResumen").innerHTML =
        '<span class="badge-mod badge-rojo">Falta la tabla</span> ' +
        '<span class="txt-tenue">ejecuta <b>sql_permisos_v2.sql</b> en la base de datos</span>';
      $s("#permGuardar").disabled = true;
      
      return;
    }

    $s("#permResumen").innerHTML =
      '<span class="badge-mod badge-azul">' + escapar(d.usuario.rol) + "</span> " +
      '<span class="txt-tenue">marca lo que este usuario podrá ver y hacer</span>';
    $s("#permGuardar").disabled = false;

    armarArbol(d.catalogo, d.permisos || []);
  }

  // Checkbox pequeño de una acción (registrar / modificar / eliminar)
  function accionCheck(accion, letra, marcado) {
    return (
      '<label class="perm-accion" title="' + accion + '">' +
      '<input type="checkbox" class="chk-accion" data-acc="' + accion + '"' +
      (marcado ? " checked" : "") + ">" +
      "<span>" + letra + "</span>" +
      "</label>"
    );
  }

  // Construye el árbol Módulo ▸ Submenú ▸ Ítem con los estados guardados
  function armarArbol(catalogo, lista) {
    var setMod = {};
    var setSub = {};
    var setItem = {};
    var setAcc = {};

    lista.forEach(function (f) {
      if (f.item === null && f.sub === null) {
        setMod[f.perm] = true;
      } else if (f.item === null) {
        setSub[f.perm + "-" + f.sub] = true;
      } else {
        var keyItem = f.perm + "-" + f.sub + "-" + f.item;

        if (!f.accion || f.accion === "ver") {
          setItem[keyItem] = true;
        } else {
          if (!setAcc[keyItem]) setAcc[keyItem] = {};
          setAcc[keyItem][f.accion] = true;
        }
      }
    });

    var html = "";

    Object.keys(catalogo).forEach(function (pStr) {
      var perms = Number(pStr);
      var mod = catalogo[pStr];
      var subs = mod.submenus || {};
      var clavesSub = Object.keys(subs);

      html +=
        '<div class="perm-grupo" data-p="' + perms + '">' +
        '<label class="perm-mod">' +
        '<input type="checkbox" class="chk-mod" data-p="' + perms + '">' +
        '<i class="fa-solid ' + (mod.icono || "fa-cube") + '"></i>' +
        '<span class="perm-nombre">' + escapar(mod.nombre) + "</span>" +
        '<span class="perm-cuenta" data-cuenta="' + perms + '"></span>' +
        "</label>";

      if (clavesSub.length) {
        html += '<div class="perm-subs">';

        clavesSub.forEach(function (sStr) {
          var subId = Number(sStr);
          var sub = subs[sStr];
          var items = sub.items || {};
          var clavesItem = Object.keys(items);
          var marcado = setMod[perms] || setSub[perms + "-" + subId];

          html +=
            '<div class="perm-sub" data-p="' + perms + '" data-s="' + subId + '">' +
            '<label class="perm-sub-t">' +
            '<input type="checkbox" class="chk-sub" data-p="' + perms + '" data-s="' + subId + '"' +
            (marcado ? " checked" : "") + ">" +
            '<i class="fa-solid ' + (sub.icono || "fa-folder") + '"></i>' +
            '<span class="perm-nombre">' + escapar(sub.nombre) + "</span>" +
            '<span class="perm-cuenta-sub" data-cuenta-sub="' + perms + "-" + subId + '"></span>' +
            "</label>";

          if (clavesItem.length) {
            html += '<div class="perm-items">';

            clavesItem.forEach(function (iStr) {
              var itemId = Number(iStr);
              var keyItem = perms + "-" + subId + "-" + itemId;
              var todoMarcado =
                setMod[perms] ||
                setSub[perms + "-" + subId] ||
                setItem[keyItem];
              var acc = setAcc[keyItem] || {};

              // Una acción concedida implica "ver"
              if (Object.keys(acc).length > 0) todoMarcado = true;

              html +=
                '<div class="perm-item">' +
                '<label class="perm-item-ver">' +
                '<input type="checkbox" class="chk-item" data-p="' + perms +
                '" data-s="' + subId + '" data-i="' + itemId + '"' +
                (todoMarcado ? " checked" : "") + ">" +
                "<span>" + escapar(items[iStr]) + "</span>" +
                "</label>" +
                '<span class="perm-item-acciones">' +
                accionCheck("registrar", "R", acc.registrar) +
                accionCheck("modificar", "M", acc.modificar) +
                accionCheck("eliminar", "E", acc.eliminar) +
                "</span>" +
                "</div>";
            });

            html += "</div>";
          }

          html += "</div>";
        });

        html += "</div>";
      }

      html += "</div>";
    });

    $s("#permArbol").innerHTML = html;
    refrescarArbol();
  }

  function subCompleto(contSub) {
    var items = contSub.querySelectorAll(".chk-item");
    var chk = contSub.querySelector(".chk-sub");

    if (!items.length) {
      return chk.checked;
    }

    var ok = 0;
    items.forEach(function (i) {
      if (i.checked) ok++;
    });

    return ok === items.length && items.length > 0;
  }

  // Marca los estados de los padres (tri-estado) y actualiza contadores
  function refrescarArbol() {
    var arbol = $s("#permArbol");
    if (!arbol) {
      return;
    }

    arbol.querySelectorAll(".perm-sub").forEach(function (contSub) {
      var items = contSub.querySelectorAll(".chk-item");
      var chk = contSub.querySelector(".chk-sub");
      if (!chk) return;

      if (items.length) {
        var ok = 0;
        items.forEach(function (i) {
          if (i.checked) ok++;
        });

        chk.checked = ok === items.length && ok > 0;
        chk.indeterminate = ok > 0 && ok < items.length;

        var cuenta = contSub.querySelector(".perm-cuenta-sub");
        if (cuenta) {
          cuenta.textContent = ok + "/" + items.length;
        }
      }
    });

    arbol.querySelectorAll(".perm-grupo").forEach(function (g) {
      var chkMod = g.querySelector(".chk-mod");
      var subs = g.querySelectorAll(".perm-sub");

      if (!subs.length) {
        // Módulo sin menú todavía: el propio módulo es la hoja
        return;
      }

      var todos = 0;
      var ninguno = 0;

      subs.forEach(function (sub) {
        if (subCompleto(sub)) todos++;
        else if (!sub.querySelector(".chk-sub").checked) ninguno++;
      });

      chkMod.checked = todos === subs.length;
      chkMod.indeterminate = todos > 0 && todos < subs.length;

      var cu = g.querySelector(".perm-cuenta");
      if (cu) {
        cu.textContent = todos + "/" + subs.length;
      }
    });

    var total = recolectar().length;
    $s("#permContador").textContent =
      total + (total === 1 ? " regla seleccionada" : " reglas seleccionadas");
  }

  // Convierte lo marcado en la UI en filas {perm, sub, item, accion}:
  //   · una fila "ver" por cada ítem visible
  //   · una fila por cada acción marcada (registrar / modificar / eliminar)
  function recolectar() {
    var arbol = $s("#permArbol");
    var lista = [];
    if (!arbol) return lista;

    arbol.querySelectorAll(".perm-item").forEach(function (cont) {
      var ver = cont.querySelector(".chk-item");
      if (!ver || !ver.checked) return;

      var p = Number(ver.getAttribute("data-p"));
      var s = Number(ver.getAttribute("data-s"));
      var i = Number(ver.getAttribute("data-i"));

      lista.push({ perm: p, sub: s, item: i, accion: "ver" });

      cont.querySelectorAll(".chk-accion").forEach(function (a) {
        if (a.checked) {
          lista.push({
            perm: p,
            sub: s,
            item: i,
            accion: a.getAttribute("data-acc"),
          });
        }
      });
    });

    return lista;
  }

  function iniciarPermisos() {
    var arbol = $s("#permArbol");
    var m = $s("#modalPermisos");

    if (arbol) {
      // Jerarquía en cascada: módulo ▸ submenú ▸ ítem
      arbol.addEventListener("change", function (ev) {
        var el = ev.target;
        if (!el.classList) return;

        if (el.classList.contains("chk-item")) {
          // Si se quita "ver", se quitan sus acciones
          if (!el.checked) {
            var contItem = el.closest(".perm-item");
            if (contItem) {
              contItem.querySelectorAll(".chk-accion").forEach(function (a) {
                a.checked = false;
              });
            }
          }
        } else if (el.classList.contains("chk-accion")) {
          // Una acción siempre implica "ver"
          if (el.checked) {
            var contItem2 = el.closest(".perm-item");
            var ver2 = contItem2 ? contItem2.querySelector(".chk-item") : null;
            if (ver2) ver2.checked = true;
          }
        } else if (el.classList.contains("chk-sub")) {
          var contSub = el.closest(".perm-sub");
          if (contSub) {
            contSub.querySelectorAll(".chk-item").forEach(function (i) {
              i.checked = el.checked;
            });
            if (!el.checked) {
              contSub.querySelectorAll(".chk-accion").forEach(function (a) {
                a.checked = false;
              });
            }
            el.indeterminate = false;
          }
        } else if (el.classList.contains("chk-mod")) {
          var grupo = el.closest(".perm-grupo");
          if (grupo) {
            grupo.querySelectorAll(".chk-sub").forEach(function (s) {
              if (s.checked !== el.checked) {
                s.checked = el.checked;
                s.indeterminate = false;
              }
              s.dispatchEvent(new Event("change", { bubbles: true }));
            });
            if (!el.checked) {
              grupo.querySelectorAll(".chk-accion").forEach(function (a) {
                a.checked = false;
              });
            }
          }
        }

        refrescarArbol();
      });
    }

    var btnTodo = $s("#permTodo");
    if (btnTodo) {
      btnTodo.addEventListener("click", function () {
        var m2 = $s("#permArbol");
        m2.querySelectorAll(".chk-mod").forEach(function (g) {
          g.checked = true;
          g.dispatchEvent(new Event("change", { bubbles: true }));
        });
        m2.querySelectorAll(".chk-accion").forEach(function (a) {
          a.checked = true;
        });
        refrescarArbol();
      });
    }

    var btnNada = $s("#permNada");
    if (btnNada) {
      btnNada.addEventListener("click", function () {
        $s("#permArbol").querySelectorAll("input[type=checkbox]").forEach(function (c) {
          c.checked = false;
          c.indeterminate = false;
        });
        refrescarArbol();
      });
    }

    var btnPermCerrar = $s("#permCerrar");
    if (btnPermCerrar) btnPermCerrar.addEventListener("click", cerrarPermisos);

    var btnPermCancelar = $s("#permCancelar");
    if (btnPermCancelar) btnPermCancelar.addEventListener("click", cerrarPermisos);

    if (m) {
      m.addEventListener("click", function (ev) {
        if (ev.target === m) cerrarPermisos();
      });
    }

    var btnPermGuardar = $s("#permGuardar");
    if (btnPermGuardar) {
      btnPermGuardar.addEventListener("click", function () {
        if (permInicial.super || !permInicial.tablaOk) return;

        var lista = recolectar();
        btnPermGuardar.disabled = true;
        if (!btnPermGuardar.dataset.html) {
          btnPermGuardar.dataset.html = btnPermGuardar.innerHTML;
        }
        btnPermGuardar.innerHTML =
          '<span class="mini-spin"></span> <span>Guardando…</span>';

        window.UsuariosAPI.guardarPermisos(permInicial.id, lista).then(function (rpta) {
          btnPermGuardar.disabled = false;
          btnPermGuardar.innerHTML = btnPermGuardar.dataset.html;

          if (rpta.success) {
            cerrarPermisos();
            avisoGrande(
              "Permisos guardados",
              rpta.message || "Se actualizó el acceso del usuario",
              "success"
            );
          } else {
            aviso("No se pudieron guardar", rpta.message || "Intenta de nuevo", "error", 5500);
          }
        });
      });
    }
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    modal = $s("#modalUsuario");
    iniciarPermisos();

    cargar();

    var btnNuevo = $s("#btnNuevo");
    if (btnNuevo) {
      btnNuevo.addEventListener("click", abrirModal);
    }

    var btnCancelar = $s("#btnCancelar");
    if (btnCancelar) {
      btnCancelar.addEventListener("click", cerrarModal);
    }

    var btnCerrar = $s("#modalCerrar");
    if (btnCerrar) {
      btnCerrar.addEventListener("click", cerrarModal);
    }

    if (modal) {
      modal.addEventListener("click", function (ev) {
        if (ev.target === modal) {
          cerrarModal();
        }
      });
    }

    var form = document.getElementById("formNuevoUsuario");
    if (form) {
      form.addEventListener("submit", enviar);
      form.addEventListener("input", function (ev) {
        var caja = ev.target.closest(".campo");
        if (caja && caja.classList.contains("error")) {
          marcarError(caja.getAttribute("data-campo"), "");
        }
      });
    }

    document.querySelectorAll(".ojo").forEach(function (boton) {
      boton.addEventListener("click", function () {
        var input = document.getElementById(boton.getAttribute("data-ojo"));
        if (!input) {
          return;
        }
        var visible = input.type === "text";
        input.type = visible ? "password" : "text";
        boton.innerHTML = visible
          ? '<i class="fa-solid fa-eye"></i>'
          : '<i class="fa-solid fa-eye-slash"></i>';
      });
    });

    // Acciones Ver / Modificar / Eliminar (delegación: sobrevive a draw() de DataTables)
    var tbody = document.querySelector("#tablaUsuarios tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton) {
          return;
        }

        var id = boton.getAttribute("data-id");
        var accionBoton = boton.getAttribute("data-accion");

        if (accionBoton === "ver") {
          verDetalle(id);
        } else if (accionBoton === "editar") {
          editar(id);
        } else if (accionBoton === "baja") {
          cambiarEstado(id, 0);
        } else if (accionBoton === "alta") {
          cambiarEstado(id, 1);
        } else if (accionBoton === "eliminar") {
          eliminar(id);
        } else if (accionBoton === "permisos") {
          abrirPermisos(id);
        }
      });
    }

    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;

      var abierto = document.querySelector(".modal-overlay.abierto");
      if (!abierto) return;

      if (abierto.id === "modalPermisos") {
        cerrarPermisos();
      } else {
        cerrarModal();
      }
    });
  });
})();
