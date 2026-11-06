/**
 * =====================================================
 * modelos/cajas/arqueo.js
 * =====================================================
 * UI del arqueo de caja:
 *   · Mi caja (aperturar / cerrar)
 *   · Historial de arqueos de mi sucursal
 *   · Filtros por estado, sucursal (SuperAdmin) y fechas
 * =====================================================
 */
(function () {
  "use strict";

  var $s = function (sel, ctxo) {
    return (ctxo || document).querySelector(sel);
  };

  var tabla = null;
  var reintentos = 0;
  var datos = {
    miCaja: null,
    abierto: null,
    arqueos: [],
    alcance: "sucursal",
    acciones: window.RM_ACCIONES || {},
    permisos: {},
    movimientos: [],
    resumenCierre: null,
    esSuperadmin: false,
    esperadoCierre: 0,
    usuario: null,
    usuarioId: 0,
    cierre: null,
    cierreResumen: null,
    movArqueo: null,
    movsActual: null,
    movsData: null,
  };

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
    var abierto = estado === "abierto";
    return (
      '<span class="badge-mod ' +
      (abierto ? "badge-verde" : "badge-gris") +
      '">' +
      '<i class="fa-solid fa-' + (abierto ? "lock-open" : "lock") + '"></i> ' +
      (abierto ? "Abierta" : "Cerrada") +
      "</span>"
    );
  }

  function chipDiferencia(valor) {
    if (valor === null || valor === undefined) {
      return '<span class="vacio">—</span>';
    }

    var d = Number(valor);

    if (Math.abs(d) < 0.005) {
      return '<span class="badge-mod badge-verde">Cuadra</span>';
    }

    return d > 0
      ? '<span class="badge-mod badge-ambar">Sobra ' + formatoBs(d) + "</span>"
      : '<span class="badge-mod badge-rojo">Falta ' + formatoBs(Math.abs(d)) + "</span>";
  }

  // =========================================================
  // PERMISOS (acción del ítem Arqueo de caja)
  // =========================================================
  function puede(accion) {
    var permisos = datos.permisos || {};
    var arqueo = permisos.arqueo || datos.acciones || {};

    return Boolean(arqueo[accion]);
  }

  function puedeCajas(accion) {
    var permisos = datos.permisos || {};
    var cajas = permisos.cajas || {};

    return Boolean(cajas[accion]);
  }

  function chipTipoMovimiento(tipo) {
    var gasto = tipo === "gasto";

    return (
      '<span class="badge-mod ' +
      (gasto ? "badge-ambar" : "badge-rojo") +
      '">' +
      (gasto ? "Gasto" : "Egreso") +
      "</span>"
    );
  }

  // =========================================================
  // CARGA
  // =========================================================
  function filtros() {
    return {
      estado: ($s("#filtroEstado") && $s("#filtroEstado").value) || "",
      id_sucursal: ($s("#filtroSucursal") && $s("#filtroSucursal").value) || "",
      desde: ($s("#filtroDesde") && $s("#filtroDesde").value) || "",
      hasta: ($s("#filtroHasta") && $s("#filtroHasta").value) || "",
    };
  }

  function mostrarCarga(activar) {
    var caja = $s("#tablaCarga");
    if (caja) caja.classList.toggle("visible", Boolean(activar));
  }

  function cargar() {
    if (!window.ArqueoCajaAPI) {
      aviso("Error", "No se cargó el módulo de red", "error");
      return;
    }

    mostrarCarga(true);

    window.ArqueoCajaAPI.listar(filtros()).then(function (rpta) {
      mostrarCarga(false);

      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error al leer los arqueos", "error", 5000);
        return;
      }

      datos.miCaja = rpta.mi_caja || null;
      datos.abierto = rpta.arqueo_abierto || null;
      datos.arqueos = rpta.arqueos || [];
      datos.alcance = rpta.alcance || "sucursal";
      datos.permisos = rpta.permisos || {};
      datos.movimientos = rpta.movimientos || [];
      datos.resumenCierre = rpta.resumen_cierre || null;
      datos.esSuperadmin = Boolean(rpta.es_superadmin);
      datos.usuario = rpta.usuario || null;
      datos.usuarioId = Number((rpta.usuario && rpta.usuario.id) || 0);

      ajustarFiltroSucursal(rpta);
      pintarResumen(datos.arqueos);
      pintarMiCaja(rpta.usuario || {});
      pintar();
    });
  }

  function ajustarFiltroSucursal(rpta) {
    var cajaFiltro = $s("#filtroSucursalWrap");

    if (!cajaFiltro) return;

    if (rpta.alcance !== "global") {
      cajaFiltro.style.display = "none";
      return;
    }

    cajaFiltro.style.display = "";

    var sel = $s("#filtroSucursal");
    if (!sel || sel.dataset.cargado === "1") return;

    (rpta.sucursales || []).forEach(function (suc) {
      var op = document.createElement("option");
      op.value = String(suc.id_sucursal);
      op.textContent = suc.nombre_sucursal + (suc.ciudad ? " · " + suc.ciudad : "");
      sel.appendChild(op);
    });

    sel.dataset.cargado = "1";
  }

  function pintarResumen(arqueos) {
    var filas = arqueos || [];
    var abiertos = 0;
    var cerrados = 0;
    var hoy = 0;
    var ventas = 0;
    var gastos = 0;
    var hoyIso = new Date();
    var hoyTxt =
      hoyIso.getFullYear() +
      "-" +
      String(hoyIso.getMonth() + 1).padStart(2, "0") +
      "-" +
      String(hoyIso.getDate()).padStart(2, "0");

    filas.forEach(function (a) {
      if (a.estado === "abierto") abiertos++;
      else if (a.estado === "cerrado") cerrados++;

      if (String(a.fecha_apertura_iso || "").indexOf(hoyTxt) === 0) hoy++;

      ventas += Number(a.total_ventas) || 0;
      gastos += Number(a.total_egresos) || 0;
    });

    var setTexto = function (id, valor) {
      var el = document.getElementById(id);
      if (el) el.textContent = valor;
    };

    setTexto("arqAbiertos", abiertos);
    setTexto("arqCerrados", cerrados);
    setTexto("arqHoy", hoy);
    setTexto("arqVentas", formatoBs(ventas));
    setTexto("arqGastos", formatoBs(gastos));

    var alcance = document.getElementById("arqAlcanceTexto");
    if (alcance) {
      alcance.textContent =
        datos.esSuperadmin
          ? "Vista global de todas las sucursales"
          : "Vista de tu sucursal";
    }
  }

  // =========================================================
  // MI CAJA
  // =========================================================
  function pintarMiCaja(usuario) {
    var panel = $s("#miCajaPanel");
    if (!panel) return;

    if (!datos.miCaja) {
      var extra = usuario.es_superadmin
        ? ""
        : "<br><span style='font-size:12px'>Pide a un administrador que te asigne una caja desde el módulo <strong>Cajas</strong>.</span>";

      panel.innerHTML =
        '<div class="arq-vacio">' +
        '<i class="fa-solid fa-user-slash"></i>' +
        "<div><strong>No tienes una caja activa asignada.</strong>" +
        "<span>Sin caja no puedes aperturar turno ni vender.</span>" +
        extra +
        "</div></div>";
      return;
    }

    var c = datos.miCaja;
    var abierto = datos.abierto;

    var encabezado =
      '<div class="arq-mi-cabecera">' +
      '<span class="amc-icono"><i class="fa-solid fa-cash-register"></i></span>' +
      '<span class="amc-texto">' +
      '<span class="amc-titulo">' + escapar(c.codigo + " · " + c.nombre) + "</span>" +
      '<span class="amc-sub"><i class="fa-solid fa-store"></i> ' +
      escapar(c.sucursal) + (c.ciudad ? " · " + escapar(c.ciudad) : "") +
      "</span></span>" +
      (abierto
        ? '<span class="badge-mod badge-verde amc-estado"><i class="fa-solid fa-lock-open"></i> Caja abierta</span>'
        : '<span class="badge-mod badge-gris amc-estado"><i class="fa-solid fa-lock"></i> Caja cerrada</span>') +
      "</div>";

    var cuerpo;

    if (abierto) {
      var resumen = datos.resumenCierre || {};
      var egresos = Number(
        resumen.total_egresos != null ? resumen.total_egresos : abierto.total_egresos || 0
      );
      var esperado = Number(
        resumen.efectivo_esperado != null
          ? resumen.efectivo_esperado
          : Number(abierto.monto_apertura) + Number(abierto.total_efectivo) - egresos
      );

      cuerpo =
        '<div class="arq-mi-datos">' +
        dato("Monto inicial", formatoBs(abierto.monto_apertura)) +
        dato("Ventas en efectivo", formatoBs(abierto.total_efectivo)) +
        dato("Egresos / gastos", formatoBs(egresos)) +
        dato("Esperado en caja", formatoBs(esperado)) +
        dato("Aperturada", abierto.fecha_apertura) +
        "</div>" +
        '<div class="arq-mi-pie">' +
        '<span class="arq-mi-nota"><i class="fa-solid fa-circle-info"></i> Turno abierto por <strong>' +
        escapar(abierto.usuario_apertura) +
        "</strong></span>" +
        '<button type="button" class="btn-mod btn-mod-claro" data-accion="movimientos" data-id="' +
        abierto.id + '"><i class="fa-solid fa-list-ul"></i> Ver movimientos</button>' +
        (puede("registrar")
          ? '<button type="button" class="btn-mod btn-mod-claro" data-accion="movimiento" data-id="' +
            abierto.id + '"><i class="fa-solid fa-receipt"></i> Registrar gasto</button>'
          : "") +
        '<button type="button" class="btn-mod btn-mod-peligro" data-accion="cerrar" data-id="' +
        abierto.id + '"><i class="fa-solid fa-lock"></i> Cerrar caja</button>' +
        "</div>";
    } else {
      var ultimo = ultimoCierreDeCaja(c.id);

      cuerpo =
        '<div class="arq-mi-datos">' +
        dato("Monto sugerido", formatoBs(c.monto_inicial_sugerido)) +
        dato("Último cierre", ultimo ? ultimo.fecha_cierre : "Sin cierres") +
        dato("Último contado", ultimo && ultimo.monto_cierre !== null ? formatoBs(ultimo.monto_cierre) : "—") +
        dato("Último gasto", ultimo ? formatoBs(ultimo.total_egresos) : "—") +
        "</div>" +
        '<div class="arq-mi-pie">' +
        '<span class="arq-mi-nota"><i class="fa-solid fa-circle-info"></i> Apertura un turno para empezar a vender</span>' +
        (ultimo
          ? '<button type="button" class="btn-mod btn-mod-claro" data-accion="movimientos" data-id="' +
            ultimo.id + '"><i class="fa-solid fa-list-ul"></i> Ver último turno</button>'
          : "") +
        '<button type="button" class="btn-mod btn-mod-primario" data-accion="aperturar">' +
        '<i class="fa-solid fa-lock-open"></i> Aperturar caja</button>' +
        "</div>";
    }

    panel.innerHTML = '<div class="arq-mi">' + encabezado + cuerpo + "</div>";
  }

  function dato(et, val) {
    return (
      '<div class="amd-item"><span class="amd-et">' + et + '</span><span class="amd-val">' +
      escapar(val) +
      "</span></div>"
    );
  }

  function ultimoCierreDeCaja(idCaja) {
    var cerrados = datos.arqueos.filter(function (a) {
      return Number(a.id_caja) === Number(idCaja) && a.estado === "cerrado";
    });

    return cerrados.length ? cerrados[0] : null;
  }

  // =========================================================
  // TABLA
  // =========================================================
  function responsableDeArqueo(fila) {
    if (datos.esSuperadmin) return true;

    var id = Number(datos.usuarioId || 0);
    if (id <= 0) return false;

    return (
      Number(fila.id_usuario_apertura) === id ||
      Number(fila.id_usuario_asignado) === id
    );
  }

  function puedeCerrarArqueo(fila) {
    return fila.estado === "abierto" && puede("registrar") && responsableDeArqueo(fila);
  }

  function puedeReabrirArqueo(fila) {
    return fila.estado === "cerrado" && datos.esSuperadmin && puede("modificar");
  }

  function accionesArqueo(fila) {
    var abierto = fila.estado === "abierto";
    var html =
      '<span class="acciones-fila arq-acciones">' +
      '<button type="button" class="btn-tabla movs" data-accion="movimientos" data-id="' +
      fila.id +
      '" title="Ver movimientos de caja (ventas y gastos)" aria-label="Ver movimientos de caja">' +
      '<i class="fa-solid fa-list-ul"></i></button>';

    if (abierto && puede("registrar")) {
      html +=
        '<button type="button" class="btn-tabla gasto" data-accion="gasto" data-id="' +
        fila.id +
        '" title="Registrar egreso o gasto" aria-label="Registrar egreso o gasto">' +
        '<i class="fa-solid fa-receipt"></i></button>';
    }

    if (puedeCerrarArqueo(fila)) {
      html +=
        '<button type="button" class="btn-tabla cerrar" data-accion="cerrar" data-id="' +
        fila.id +
        '" title="Cerrar caja" aria-label="Cerrar caja">' +
        '<i class="fa-solid fa-lock"></i></button>';
    }

    if (!abierto) {
      html +=
        '<button type="button" class="btn-tabla ticket" data-accion="ticket" data-id="' +
        fila.id +
        '" title="Reimprimir ticket de cierre" aria-label="Reimprimir ticket de cierre">' +
        '<i class="fa-solid fa-print"></i></button>';

      if (puedeReabrirArqueo(fila)) {
        html +=
          '<button type="button" class="btn-tabla reabrir" data-accion="reabrir" data-id="' +
          fila.id +
          '" title="Reabrir caja para corregir" aria-label="Reabrir caja para corregir">' +
          '<i class="fa-solid fa-lock-open"></i></button>';
      }
    }

    return html + "</span>";
  }

  function pintar() {
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
      tabla.clear().rows.add(datos.arqueos).draw(false);
      return;
    }

    tabla = jQuery("#tablaArqueos").DataTable({
      data: datos.arqueos,
      deferRender: true,
      responsive: { details: { type: "inline", target: "tr" } },
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [
        [8, 10, 25, 50, -1],
        [8, 10, 25, 50, "Todos"],
      ],
      order: [
        [0, "desc"],
      ],
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json",
      },
      dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
      columnDefs: [
        { targets: 0, width: "52px" },
        { targets: -1, width: "130px" },
      ],
      columns: [
        { data: "id", title: "#", className: "dt-num", responsivePriority: 12, searchable: false },

        {
          data: "caja_codigo",
          responsivePriority: 2,
          render: function (t, type, fila) {
            return (
              '<span class="dt-texto"><strong>' + escapar(t) + "</strong> · " + escapar(fila.caja_nombre) + "</span>" +
              '<span class="dt-subtexto"><i class="fa-solid fa-store"></i> ' +
              (escapar(fila.sucursal) || "—") +
              (fila.ciudad ? " · " + escapar(fila.ciudad) : "") +
              "</span>"
            );
          },
        },

        {
          data: "fecha_apertura",
          responsivePriority: 3,
          render: function (t, type, fila) {
            return (
              '<span class="dt-texto">' +
              escapar(fila.usuario_apertura) +
              "</span>" +
              '<span class="dt-subtexto"><i class="fa-regular fa-clock"></i> ' +
              escapar(t) +
              "</span>"
            );
          },
        },

        {
          data: "fecha_cierre",
          responsivePriority: 8,
          render: function (t, type, fila) {
            if (!t) {
              return '<span class="badge-mod badge-verde">En curso</span>';
            }

            return (
              '<span class="dt-texto">' + escapar(fila.usuario_cierre || "—") + "</span>" +
              '<span class="dt-subtexto"><i class="fa-regular fa-clock"></i> ' + escapar(t) + "</span>"
            );
          },
        },

        {
          data: "monto_apertura",
          className: "dt-num",
          responsivePriority: 6,
          render: function (t) {
            return '<span class="dt-texto">' + formatoBs(t) + "</span>";
          },
        },

        {
          data: "total_ventas",
          className: "dt-num",
          responsivePriority: 10,
          render: function (t) {
            return '<span class="dt-texto dt-ventas">' + formatoBs(t) + "</span>";
          },
        },

        {
          data: "total_egresos",
          className: "dt-num",
          responsivePriority: 5,
          render: function (t) {
            return '<span class="dt-texto dt-gastos">' + formatoBs(t) + "</span>";
          },
        },

        {
          data: "monto_cierre",
          className: "dt-num",
          responsivePriority: 9,
          render: function (t, type, fila) {
            if (t === null) {
              return '<span class="vacio">—</span>';
            }

            return (
              '<span class="dt-texto">' + formatoBs(t) + "</span>" +
              '<span class="dt-subtexto">Esperado: ' + formatoBs(fila.monto_esperado || 0) + "</span>"
            );
          },
        },

        {
          data: "diferencia",
          className: "dt-num",
          orderable: false,
          responsivePriority: 4,
          render: function (t) {
            return chipDiferencia(t);
          },
        },

        {
          data: "estado",
          orderable: false,
          responsivePriority: 1,
          render: function (t) {
            return chipEstado(t);
          },
        },

        {
          data: null,
          name: "acciones",
          orderable: false,
          searchable: false,
          responsivePriority: 1,
          render: function (fila) {
            return accionesArqueo(fila);
          },
        },
      ],
    });
  }

  // =========================================================
  // APERTURA
  // =========================================================
  function abrirModalApertura() {
    var modal = $s("#modalApertura");
    if (!modal || !datos.miCaja) return;

    limpiarErrores();

    var c = datos.miCaja;
    $s("#apeCajaTitulo").textContent = c.codigo + " · " + c.nombre;
    $s("#apeCajaSucursal").textContent = c.sucursal + (c.ciudad ? " · " + c.ciudad : "");
    $s("#apeSugerido").textContent = formatoBs(c.monto_inicial_sugerido);
    $s("#ape_monto").value = Number(c.monto_inicial_sugerido || 0).toFixed(2);
    $s("#ape_observacion").value = "";
    $s("#mApeNota").textContent = "";

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var monto = $s("#ape_monto");
      if (monto) {
        monto.focus();
        monto.select();
      }
    }, 60);
  }

  function cerrarModalApertura() {
    var modal = $s("#modalApertura");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    $s("#formApertura").reset();
  }

  function guardarApertura(ev) {
    ev.preventDefault();

    limpiarErrores();

    var monto = parseFloat($s("#ape_monto").value);

    if (isNaN(monto) || monto < 0) {
      marcarError("monto", "Indica un monto inicial válido");
      $s("#ape_monto").focus();
      return;
    }

    var boton = $s("#mApeGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Aperturando…</span>';

    window.ArqueoCajaAPI
      .abrir(monto, ($s("#ape_observacion").value || "").trim())
      .then(function (rpta) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.html;

        if (rpta.success) {
          cerrarModalApertura();
          avisoGrande("Caja aperturada", rpta.message || "Buen turno", "success");
          cargar();
          return;
        }

        aviso("No se pudo aperturar", rpta.message || "Revisa los datos", "error", 5500);
      });
  }

  // =========================================================
  // CIERRE
  // =========================================================
  function abrirModalCierre(idArqueo) {
    var modal = $s("#modalCierre");
    var a = resolverArqueo(idArqueo);

    if (!modal || !a) return;

    if (a.estado !== "abierto") {
      aviso("Turno cerrado", "Ese arqueo ya está cerrado", "warning");
      return;
    }

    limpiarErrores();

    datos.cierre = a;
    datos.cierreResumen =
      datos.abierto && Number(datos.abierto.id) === Number(a.id)
        ? datos.resumenCierre || null
        : null;

    $s("#cieCajaTitulo").textContent = a.caja_codigo + " · " + a.caja_nombre;
    $s("#cieCajaSucursal").textContent = a.sucursal + (a.ciudad ? " · " + a.ciudad : "");
    $s("#cieFecha").textContent = a.fecha_apertura;

    // Totales del cierre: ventas por medio de pago, egresos y esperado.
    pintarResumenCierreModal();

    $s("#cie_monto").value = Number(datos.esperadoCierre || 0).toFixed(2);
    $s("#cie_observacion").value = "";
    $s("#mCieNota").textContent = "";

    actualizarDiferencia();

    var btnGuardar = $s("#mCieGuardar");
    var teniaResumen = Boolean(datos.cierreResumen);
    if (btnGuardar) btnGuardar.disabled = !teniaResumen;

    // Refresca los totales desde el servidor (ventas/gastos recientes).
    if (window.ArqueoCajaAPI && window.ArqueoCajaAPI.resumenCierre) {
      window.ArqueoCajaAPI.resumenCierre(a.id).then(function (rpta) {
        if (
          rpta &&
          rpta.success &&
          rpta.data &&
          rpta.data.resumen &&
          datos.cierre &&
          Number(datos.cierre.id) === Number(a.id)
        ) {
          datos.cierreResumen = rpta.data.resumen;
          pintarResumenCierreModal();
          $s("#cie_monto").value = Number(datos.esperadoCierre || 0).toFixed(2);
          actualizarDiferencia();
          if (btnGuardar) btnGuardar.disabled = false;
        } else if (btnGuardar && !teniaResumen) {
          btnGuardar.disabled = false;
        }
      });
    }

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var monto = $s("#cie_monto");
      if (monto) {
        monto.focus();
        monto.select();
      }
    }, 60);
  }

  function cerrarModalCierre() {
    var modal = $s("#modalCierre");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    datos.cierre = null;
    datos.cierreResumen = null;
    $s("#formCierre").reset();
  }

  function actualizarDiferencia() {
    var caja = $s("#cieDiferenciaCaja");
    var valor = $s("#cieDiferencia");
    if (!caja || !valor || !datos.cierre) return;

    var monto = parseFloat($s("#cie_monto").value);
    if (isNaN(monto)) monto = 0;

    var esperado = Number(datos.esperadoCierre || 0);
    var dif = Math.round((monto - esperado) * 100) / 100;

    valor.textContent = formatoBs(dif);

    caja.classList.remove("cuadra", "sobra", "falta");

    if (Math.abs(dif) < 0.005) caja.classList.add("cuadra");
    else if (dif > 0) caja.classList.add("sobra");
    else caja.classList.add("falta");
  }

  function guardarCierre(ev) {
    ev.preventDefault();

    limpiarErrores();

    var a = datos.cierre;
    if (!a) return;

    var monto = parseFloat($s("#cie_monto").value);

    if (isNaN(monto) || monto < 0) {
      marcarError("monto", "Indica el monto contado");
      $s("#cie_monto").focus();
      return;
    }

    var boton = $s("#mCieGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Cerrando…</span>';

    window.ArqueoCajaAPI
      .cerrar(a.id, monto, ($s("#cie_observacion").value || "").trim())
      .then(function (rpta) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.html;

        if (rpta.success) {
          cerrarModalCierre();

          var arqueo = rpta.data && rpta.data.arqueo ? rpta.data.arqueo : null;
          var tipo = "success";

          if (arqueo && arqueo.diferencia !== null && Math.abs(arqueo.diferencia) >= 0.005) {
            tipo = "warning";
          }

          var arqueoId =
            rpta.data && rpta.data.id ? Number(rpta.data.id) : Number(a.id);

          if (window.Swal) {
            Swal.fire({
              icon: tipo,
              title: "Caja cerrada",
              html:
                "<div style='font-size:13px'>" +
                escapar(rpta.message || "Arqueo cerrado") +
                "</div><div style='font-size:12px;color:#6b6165;margin-top:6px'>" +
                "Puedes reimprimir el ticket de cierre cuando lo necesites.</div>",
              showCancelButton: true,
              confirmButtonText: '<i class="fa-solid fa-print"></i> Imprimir ticket',
              cancelButtonText: "Cerrar",
              confirmButtonColor: "#d9232d",
            }).then(function (res) {
              if (res.isConfirmed) abrirTicketCierre(arqueoId);
              cargar();
            });
            return;
          }

          abrirTicketCierre(arqueoId);
          cargar();
          return;
        }

        aviso("No se pudo cerrar", rpta.message || "Revisa los datos", "error", 5500);
      });
  }

  // =========================================================
  // DETALLE
  // =========================================================
  function verDetalle(id) {
    var a = datos.arqueos.find(function (x) {
      return Number(x.id) === Number(id);
    });

    if (!a) {
      aviso("Sin datos", "No se encontró el arqueo", "warning");
      return;
    }

    var campo = function (et, val) {
      return "<dt>" + et + "</dt><dd>" + (escapar(val) || "—") + "</dd>";
    };

    var diferencia = a.diferencia === null ? "—" : formatoBs(a.diferencia);
    var diferenciaEt = "Diferencia";

    if (a.diferencia !== null && Math.abs(Number(a.diferencia)) < 0.005) {
      diferenciaEt = "Diferencia (cuadra)";
    } else if (a.diferencia !== null && Number(a.diferencia) > 0) {
      diferenciaEt = "Diferencia (sobrante)";
    } else if (a.diferencia !== null && Number(a.diferencia) < 0) {
      diferenciaEt = "Diferencia (faltante)";
    }

    Swal.fire({
      icon: "info",
      title: a.caja_codigo + " · " + a.caja_nombre,
      html:
        '<div style="text-align:center;margin-bottom:6px">' + chipEstado(a.estado) + "</div>" +
        '<dl class="dl-datos">' +
        campo("Sucursal", a.sucursal + (a.ciudad ? " · " + a.ciudad : "")) +
        campo("Apertura", a.fecha_apertura + " · " + a.usuario_apertura) +
        campo("Cierre", a.fecha_cierre ? a.fecha_cierre + " · " + a.usuario_cierre : "En curso") +
        campo("Monto inicial", formatoBs(a.monto_apertura)) +
        campo("Ventas efectivo", formatoBs(a.total_efectivo)) +
        campo("Ventas transferencia", formatoBs(a.total_transferencia)) +
        campo("Ventas tarjeta", formatoBs(a.total_tarjeta)) +
        campo("Ventas QR", formatoBs(a.total_qr)) +
        campo("Ventas otros", formatoBs(a.total_otros)) +
        campo("Total ventas", formatoBs(a.total_ventas)) +
        campo("Egresos / gastos", formatoBs(a.total_egresos)) +
        campo("Efectivo esperado", formatoBs(a.efectivo_esperado || 0)) +
        campo("Esperado guardado", a.monto_esperado !== null ? formatoBs(a.monto_esperado) : "—") +
        campo("Monto contado", a.monto_cierre !== null ? formatoBs(a.monto_cierre) : "—") +
        campo(diferenciaEt, diferencia) +
        campo("Observación apertura", a.observacion_apertura) +
        campo("Observación cierre", a.observacion_cierre) +
        "</dl>",
      width: 560,
      confirmButtonText: "Cerrar",
      confirmButtonColor: "#d9232d",
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
  // MOVIMIENTOS DE CAJA: VENTAS + GASTOS
  // =========================================================
  function buscarArqueo(id) {
    return (
      datos.arqueos.find(function (x) {
        return Number(x.id) === Number(id);
      }) || null
    );
  }

  function resolverArqueo(id) {
    if (!id) return datos.abierto;

    var a = buscarArqueo(id);
    if (a) return a;

    if (datos.abierto && Number(datos.abierto.id) === Number(id)) {
      return datos.abierto;
    }

    return null;
  }

  function setTexto(id, valor) {
    var el = document.getElementById(id);
    if (el) el.textContent = valor;
  }

  function pintarResumenCierreModal() {
    var res = datos.cierreResumen || {};
    var a = datos.cierre || {};

    var apertura = Number(res.monto_apertura != null ? res.monto_apertura : a.monto_apertura || 0);
    var efectivo = Number(res.total_efectivo != null ? res.total_efectivo : a.total_efectivo || 0);
    var transferencia = Number(
      res.total_transferencia != null ? res.total_transferencia : a.total_transferencia || 0
    );
    var qr = Number(res.total_qr != null ? res.total_qr : a.total_qr || 0);
    var tarjeta = Number(res.total_tarjeta != null ? res.total_tarjeta : a.total_tarjeta || 0);
    var otros = Number(res.total_otros != null ? res.total_otros : a.total_otros || 0);
    var totalVentas = Number(res.total_ventas != null ? res.total_ventas : a.total_ventas || 0);
    var egresos = Number(res.total_egresos || 0);
    var egresosEfectivo = Number(res.total_egresos_efectivo || 0);
    var esperado = Number(
      res.efectivo_esperado != null ? res.efectivo_esperado : apertura + efectivo - egresosEfectivo
    );

    datos.esperadoCierre = esperado;

    setTexto("cieMontoApertura", formatoBs(apertura));
    setTexto("cieVentasTotal", formatoBs(totalVentas));
    setTexto("cieVentas", formatoBs(efectivo));
    setTexto("cieTransferencia", formatoBs(transferencia));
    setTexto("cieQr", formatoBs(qr));
    setTexto("cieTarjeta", formatoBs(tarjeta));
    setTexto("cieOtros", formatoBs(otros));
    setTexto("cieEgresos", formatoBs(egresos));
    setTexto("cieEsperado", formatoBs(esperado));
  }

  function abrirModalMovimiento(idArqueo) {
    var modal = $s("#modalMovimiento");
    var a = resolverArqueo(idArqueo);

    if (!modal || !a) return;

    if (a.estado !== "abierto") {
      aviso("Turno cerrado", "Sólo puedes registrar gastos con la caja abierta", "warning");
      return;
    }

    datos.movArqueo = a;
    limpiarErrores();

    $s("#movCajaTitulo").textContent = a.caja_codigo + " · " + a.caja_nombre;
    $s("#movCajaSucursal").textContent = a.sucursal + (a.ciudad ? " · " + a.ciudad : "");
    $s("#movTipo").value = "egreso";
    $s("#movConcepto").value = "";
    $s("#movMonto").value = "";
    $s("#movMetodo").value = "efectivo";
    $s("#movReferencia").value = "";
    $s("#movObservacion").value = "";
    $s("#mMovNota").textContent = "";

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    setTimeout(function () {
      var campo = $s("#movConcepto");
      if (campo) campo.focus();
    }, 60);
  }

  function cerrarModalMovimiento() {
    var modal = $s("#modalMovimiento");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }

    datos.movArqueo = null;
    var form = $s("#formMovimiento");
    if (form) form.reset();
  }

  function guardarMovimiento(ev) {
    ev.preventDefault();

    var arqueo = datos.movArqueo;
    if (!arqueo) return;

    limpiarErrores();

    var concepto = ($s("#movConcepto").value || "").trim();
    var monto = parseFloat($s("#movMonto").value);

    if (!concepto) {
      marcarError("mov_concepto", "Escribe el concepto del egreso o gasto");
      $s("#movConcepto").focus();
      return;
    }

    if (isNaN(monto) || monto <= 0) {
      marcarError("mov_monto", "Indica un monto mayor que cero");
      $s("#movMonto").focus();
      return;
    }

    var boton = $s("#mMovGuardar");
    boton.disabled = true;
    if (!boton.dataset.html) boton.dataset.html = boton.innerHTML;
    boton.innerHTML = '<span class="mini-spin"></span> <span>Guardando…</span>';

    window.ArqueoCajaAPI
      .movimientoRegistrar(arqueo.id, {
        tipo: $s("#movTipo").value,
        concepto: concepto,
        monto: monto,
        metodo_pago: $s("#movMetodo").value,
        referencia: ($s("#movReferencia").value || "").trim(),
        observacion: ($s("#movObservacion").value || "").trim(),
      })
      .then(function (rpta) {
        boton.disabled = false;
        boton.innerHTML = boton.dataset.html;

        if (rpta.success) {
          cerrarModalMovimiento();
          aviso("Movimiento registrado", rpta.message || "Egreso registrado", "success");
          cargar();
          return;
        }

        aviso("No se pudo registrar", rpta.message || "Revisa los datos", "error", 5500);
      });
  }

  function confirmarEliminarMovimiento(id) {
    var data = datos.movsData;
    if (!data || !data.arqueo || !window.ArqueoCajaAPI) return;

    if (data.arqueo.estado !== "abierto") {
      aviso("Turno cerrado", "Reabre la caja para eliminar movimientos", "warning");
      return;
    }

    var mov = (data.movimientos || []).find(function (m) {
      return Number(m.id) === Number(id);
    });

    var detalle = mov
      ? mov.tipo_label + " · " + mov.concepto + " · " + formatoBs(mov.monto)
      : "Movimiento #" + id;

    var eliminar = function () {
      window.ArqueoCajaAPI.movimientoEliminar(data.arqueo.id, id).then(function (rpta) {
        if (rpta.success) {
          aviso("Movimiento eliminado", rpta.message || "Eliminado", "success");
          recargarMovimientos(data.arqueo.id);
          cargar();
          return;
        }

        aviso("No se pudo eliminar", rpta.message || "Error", "error", 5500);
      });
    };

    if (!window.Swal) {
      if (window.confirm("¿Eliminar este movimiento?\n" + detalle)) eliminar();
      return;
    }

    Swal.fire({
      icon: "warning",
      title: "Eliminar movimiento",
      html: "<div style='font-size:13px'>" + escapar(detalle) + "</div>",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
    }).then(function (res) {
      if (res.isConfirmed) eliminar();
    });
  }

  // =========================================================
  // MODAL: MOVIMIENTOS DE CAJA (ventas + gastos)
  // =========================================================
  function chipMetodoVenta(metodo) {
    var mapa = {
      efectivo: { icono: "fa-money-bill-wave", label: "Efectivo" },
      qr: { icono: "fa-qrcode", label: "QR" },
      tarjeta: { icono: "fa-credit-card", label: "Tarjeta" },
      mixto: { icono: "fa-shuffle", label: "Mixto" },
      otro: { icono: "fa-wallet", label: "Otro" },
    };
    var m = mapa[metodo] || mapa.otro;

    return '<span class="badge-mod badge-gris"><i class="fa-solid ' + m.icono + '"></i> ' + m.label + "</span>";
  }

  function cerrarModalMovimientos() {
    var modal = $s("#modalMovimientosCaja");
    if (!modal) return;

    modal.classList.remove("abierto");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal-overlay.abierto")) {
      document.body.style.overflow = "";
    }
  }

  function abrirModalMovimientos(id) {
    var modal = $s("#modalMovimientosCaja");
    var a = resolverArqueo(id);

    if (!modal || !a) {
      aviso("Sin datos", "No se encontró el turno de caja", "warning");
      return;
    }

    datos.movsActual = a;
    datos.movsData = null;

    $s("#movsCajaNombre").textContent = a.caja_codigo + " · " + a.caja_nombre;
    $s("#movsCajaMeta").textContent = a.sucursal + " · Turno #" + a.id + " · " + a.fecha_apertura;
    $s("#movsEstado").innerHTML = chipEstado(a.estado);
    $s("#movsContenido").style.display = "none";
    $s("#movsCarga").classList.add("visible");
    $s("#movsNota").textContent = "";

    modal.classList.add("abierto");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    recargarMovimientos(a.id);
  }

  function recargarMovimientos(id) {
    if (!window.ArqueoCajaAPI) return;

    var carga = $s("#movsCarga");
    if (carga) carga.classList.add("visible");

    window.ArqueoCajaAPI.movimientos(id).then(function (rpta) {
      if (carga) carga.classList.remove("visible");

      if (!rpta.success) {
        aviso("No se pudo cargar", rpta.message || "Error al leer los movimientos", "error", 5000);
        return;
      }

      datos.movsData = rpta.data || null;
      datos.movsActual = (rpta.data && rpta.data.arqueo) || datos.movsActual;
      pintarMovimientosModal();
    });
  }

  function pintarMovimientosModal() {
    var data = datos.movsData;
    if (!data || !data.arqueo) return;

    var a = data.arqueo;
    var res = data.resumen || {};
    var ventas = data.ventas || [];
    var movs = data.movimientos || [];

    $s("#movsCajaNombre").textContent = a.caja_codigo + " · " + a.caja_nombre;
    $s("#movsCajaMeta").textContent = a.sucursal + " · Turno #" + a.id + " · " + a.fecha_apertura;
    $s("#movsEstado").innerHTML = chipEstado(a.estado);

    setTexto("movsInicial", formatoBs(res.monto_apertura != null ? res.monto_apertura : a.monto_apertura));
    setTexto("movsVentas", formatoBs(res.total_ventas != null ? res.total_ventas : a.total_ventas));
    setTexto("movsGastos", formatoBs(res.total_egresos != null ? res.total_egresos : a.total_egresos));
    setTexto(
      "movsEsperado",
      formatoBs(res.efectivo_esperado != null ? res.efectivo_esperado : a.efectivo_esperado)
    );
    setTexto(
      "movsContado",
      a.monto_cierre !== null && a.monto_cierre !== undefined ? formatoBs(a.monto_cierre) : "—"
    );

    var dif = a.diferencia;
    var difWrap = $s("#movsDiferenciaWrap");
    var difVal = $s("#movsDiferencia");

    if (dif === null || dif === undefined) {
      if (difVal) difVal.textContent = "—";
      if (difWrap) difWrap.classList.remove("ok", "mal");
    } else {
      if (difVal) difVal.textContent = formatoBs(dif);
      if (difWrap) {
        difWrap.classList.remove("ok", "mal");
        difWrap.classList.add(Math.abs(Number(dif)) < 0.005 ? "ok" : "mal");
      }
    }

    var metodos = [
      { icono: "fa-money-bill-wave", et: "Efectivo", val: res.total_efectivo },
      { icono: "fa-qrcode", et: "QR", val: res.total_qr },
      { icono: "fa-credit-card", et: "Tarjeta", val: res.total_tarjeta },
      { icono: "fa-right-left", et: "Transfer.", val: res.total_transferencia },
      { icono: "fa-wallet", et: "Otros", val: res.total_otros },
    ];

    var htmlMetodos = metodos
      .map(function (m) {
        return (
          '<span class="arq-metodo-chip"><i class="fa-solid ' +
          m.icono +
          '"></i>' +
          m.et +
          " <b>" +
          formatoBs(m.val || 0) +
          "</b></span>"
        );
      })
      .join("");

    if ($s("#movsMetodosPago")) $s("#movsMetodosPago").innerHTML = htmlMetodos;

    setTexto("movsVentasCount", ventas.length);
    setTexto("movsGastosCount", movs.length);

    pintarVentasModal(ventas);
    pintarGastosModal(movs, a);

    actualizarBotonesMovs(a);

    if ($s("#movsContenido")) $s("#movsContenido").style.display = "";
  }

  function pintarVentasModal(ventas) {
    var body = $s("#movsVentasBody");
    var vacio = $s("#movsVentasVacio");
    if (!body) return;

    body.innerHTML = ventas
      .map(function (v) {
        var tipo = escapar(v.tipo_pedido_label || "");
        if (v.delivery_nombre) tipo += " · " + escapar(v.delivery_nombre);

        var pago = chipMetodoVenta(v.metodo_pago);
        if (v.pago_detalle) {
          pago += '<span class="arq-pago-linea">' + escapar(v.pago_detalle) + "</span>";
        }
        if (v.referencia) {
          pago += '<span class="arq-pago-linea">Ref.: ' + escapar(v.referencia) + "</span>";
        }
        if (v.metodo_pago === "efectivo" && Number(v.recibido) > 0) {
          pago +=
            '<span class="arq-pago-linea">Recibido ' +
            formatoBs(v.recibido) +
            " · Cambio " +
            formatoBs(v.cambio) +
            "</span>";
        }

        return (
          "<tr>" +
          "<td>" + escapar(v.hora) + "</td>" +
          '<td><span class="arq-venta-codigo">' + escapar(v.codigo) + "</span>" +
          (v.anulada ? '<span class="arq-venta-anulada">Anulada</span>' : "") +
          (v.usuario ? '<span class="dt-subtexto">' + escapar(v.usuario) + "</span>" : "") +
          "</td>" +
          "<td>" + tipo + "</td>" +
          "<td>" + pago + "</td>" +
          '<td class="r">' + formatoBs(v.total) + "</td>" +
          "</tr>"
        );
      })
      .join("");

    if (vacio) vacio.style.display = ventas.length ? "none" : "flex";
  }

  function pintarGastosModal(movs, arqueo) {
    var body = $s("#movsGastosBody");
    var vacio = $s("#movsGastosVacio");
    if (!body) return;

    var puedeBorrar = puede("eliminar") && arqueo && arqueo.estado === "abierto";

    body.innerHTML = movs
      .map(function (m) {
        return (
          "<tr>" +
          "<td>" + escapar(m.fecha) + "</td>" +
          "<td>" + chipTipoMovimiento(m.tipo) + "</td>" +
          "<td>" + escapar(m.concepto) +
          (m.referencia ? '<span class="dt-subtexto">Ref.: ' + escapar(m.referencia) + "</span>" : "") +
          (m.observacion ? '<span class="dt-subtexto">' + escapar(m.observacion) + "</span>" : "") +
          (m.usuario ? '<span class="dt-subtexto">' + escapar(m.usuario) + "</span>" : "") +
          "</td>" +
          "<td>" + escapar(m.metodo_pago_label || m.metodo_pago) + "</td>" +
          '<td class="r">' + formatoBs(m.monto) + "</td>" +
          '<td class="c">' +
          (puedeBorrar
            ? '<button type="button" class="btn-tabla borrar" data-accion="movimiento_eliminar" data-id="' +
              m.id + '" title="Eliminar movimiento" aria-label="Eliminar movimiento">' +
              '<i class="fa-solid fa-trash"></i></button>'
            : "") +
          "</td>" +
          "</tr>"
        );
      })
      .join("");

    if (vacio) vacio.style.display = movs.length ? "none" : "flex";
  }

  function actualizarBotonesMovs(a) {
    var editar = $s("#movsEditar");
    var eliminar = $s("#movsEliminar");
    var reabrir = $s("#movsReabrir");
    var nota = $s("#movsNota");

    var puedeEliminar =
      puede("eliminar") && (a.estado === "cerrado" || datos.esSuperadmin);

    if (editar) editar.style.display = a.estado === "abierto" && puede("modificar") ? "" : "none";
    if (eliminar) eliminar.style.display = puedeEliminar ? "" : "none";
    if (reabrir) reabrir.style.display = puedeReabrirArqueo(a) ? "" : "none";

    if (nota) {
      nota.textContent =
        a.estado === "cerrado"
          ? "Ticket de cierre disponible para reimprimir."
          : "Caja abierta: las ventas y gastos se actualizan al registrar movimientos.";
    }
  }

  function reabrirArqueo(id) {
    var a = resolverArqueo(id);

    if (!a) {
      aviso("Sin datos", "No se encontró el arqueo", "warning");
      return;
    }

    if (!datos.esSuperadmin) {
      aviso("Sin permiso", "Sólo el SuperAdministrador puede reabrir una caja", "warning");
      return;
    }

    if (a.estado !== "cerrado") {
      aviso("Caja abierta", "Ese turno ya está abierto", "warning");
      return;
    }

    var ejecutar = function () {
      window.ArqueoCajaAPI.reabrirArqueo(a.id).then(function (rpta) {
        if (!rpta.success) {
          aviso("No se pudo reabrir", rpta.message || "Revisa los datos", "error", 6000);
          return;
        }

        cerrarModalMovimientos();
        avisoGrande(
          "Caja reabierta",
          "Corrige los gastos o la apertura y vuelve a cerrar la caja para generar un nuevo ticket.",
          "success"
        );
        cargar();
      });
    };

    if (!window.Swal) {
      if (window.confirm("¿Reabrir la caja " + a.caja_codigo + " para corregirla?")) ejecutar();
      return;
    }

    Swal.fire({
      icon: "warning",
      title: "Reabrir caja",
      html:
        "<div style='font-size:13px; text-align:left'>" +
        "Se volverá a abrir el turno <b>#" + escapar(String(a.id)) + "</b> de " +
        "<b>" + escapar(a.caja_codigo + " · " + a.caja_nombre) + "</b>." +
        "<div style='margin-top:7px; color:#9a6b00'>" +
        "Podrás corregir gastos o la apertura y deberás cerrar la caja nuevamente. " +
        "El ticket anterior dejará de estar disponible hasta el nuevo cierre." +
        "</div></div>",
      showCancelButton: true,
      confirmButtonText: "Sí, reabrir",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#14803d",
    }).then(function (res) {
      if (res.isConfirmed) ejecutar();
    });
  }

  // =========================================================
  // MODIFICAR APERTURA / ELIMINAR ARQUEO / TICKET
  // =========================================================
  function abrirModalAperturaEditar(id) {
    var a = resolverArqueo(id);

    if (!a) {
      aviso("Sin datos", "No se encontró el arqueo", "warning");
      return;
    }

    if (a.estado !== "abierto") {
      aviso("Sólo arqueos abiertos", "La apertura sólo se puede modificar mientras el arqueo está abierto", "warning", 5500);
      return;
    }

    if (!window.Swal) {
      return;
    }

    Swal.fire({
      icon: "question",
      title: "Modificar apertura",
      html:
        '<div style="text-align:left;font-size:13px">' +
        '<label style="display:block;font-weight:700;margin-bottom:4px">Monto inicial (Bs)</label>' +
        '<input id="editApeMonto" type="number" step="0.01" min="0" class="swal2-input" style="width:100%;margin:0 0 10px" value="' +
        Number(a.monto_apertura || 0).toFixed(2) + '">' +
        '<label style="display:block;font-weight:700;margin-bottom:4px">Observación</label>' +
        '<textarea id="editApeObs" class="swal2-textarea" style="width:100%;margin:0;display:block">' +
        escapar(a.observacion_apertura || "") + "</textarea></div>",
      showCancelButton: true,
      confirmButtonText: "Guardar cambios",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
      focusConfirm: false,
      preConfirm: function () {
        var monto = parseFloat(document.getElementById("editApeMonto").value);

        if (isNaN(monto) || monto < 0) {
          Swal.showValidationMessage("Indica un monto inicial válido");
          return false;
        }

        return {
          monto: monto,
          observacion: (document.getElementById("editApeObs").value || "").trim(),
        };
      },
    }).then(function (res) {
      if (!res.isConfirmed || !res.value) return;

      window.ArqueoCajaAPI
        .actualizarApertura(a.id, res.value.monto, res.value.observacion)
        .then(function (rpta) {
          if (rpta.success) {
            aviso("Apertura actualizada", rpta.message || "Cambios guardados", "success");
            cargar();
            return;
          }

          aviso("No se pudo modificar", rpta.message || "Revisa los datos", "error", 5500);
        });
    });
  }

  function confirmarEliminarArqueo(id) {
    var a = resolverArqueo(id);

    if (!a) {
      aviso("Sin datos", "No se encontró el arqueo", "warning");
      return;
    }

    var detalle =
      "Arqueo #" + a.id + " · " + a.caja_codigo + " · " + a.sucursal +
      " · " + (a.fecha_apertura || "");

    var eliminar = function () {
      window.ArqueoCajaAPI.eliminarArqueo(a.id).then(function (rpta) {
        if (rpta.success) {
          aviso("Arqueo eliminado", rpta.message || "Eliminado", "success");
          cargar();
          return;
        }

        aviso("No se pudo eliminar", rpta.message || "Error", "error", 6000);
      });
    };

    if (!window.Swal) {
      if (window.confirm("¿Eliminar este arqueo?\n" + detalle)) eliminar();
      return;
    }

    Swal.fire({
      icon: "warning",
      title: "Eliminar arqueo",
      html:
        "<div style='font-size:13px'>" + escapar(detalle) + "</div>" +
        "<div style='font-size:12px;color:#9a6b00;margin-top:6px'>" +
        "Se borran también sus egresos / gastos. No se puede deshacer.</div>",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "#d9232d",
    }).then(function (res) {
      if (res.isConfirmed) eliminar();
    });
  }

  function abrirTicketCierre(id) {
    if (!window.ArqueoCajaAPI || !window.ArqueoCajaAPI.ticketCierreUrl) {
      aviso("Sin PDF", "No se cargó el módulo de red", "error");
      return;
    }

    window.open(window.ArqueoCajaAPI.ticketCierreUrl(id), "_blank", "noopener");
  }

  // =========================================================
  // ARRANQUE
  // =========================================================
  document.addEventListener("DOMContentLoaded", function () {
    cargar();

    // Acciones del panel "Mi caja"
    var panel = $s("#miCajaPanel");
    if (panel) {
      panel.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton) return;

        var accion = boton.getAttribute("data-accion");
        var id = boton.getAttribute("data-id");

        if (accion === "aperturar") abrirModalApertura();
        else if (accion === "cerrar") abrirModalCierre(id);
        else if (accion === "movimiento") abrirModalMovimiento(id);
        else if (accion === "movimientos") abrirModalMovimientos(id);
        else if (accion === "movimiento_eliminar") confirmarEliminarMovimiento(id);
      });
    }

    var recargar = $s("#btnRecargarArqueos");
    if (recargar) recargar.addEventListener("click", cargar);

    // Modal apertura
    var fApe = document.getElementById("formApertura");
    if (fApe) fApe.addEventListener("submit", guardarApertura);

    var apeCerrar = $s("#mApeCerrar");
    if (apeCerrar) apeCerrar.addEventListener("click", cerrarModalApertura);

    var apeCancelar = $s("#mApeCancelar");
    if (apeCancelar) apeCancelar.addEventListener("click", cerrarModalApertura);

    var mApe = $s("#modalApertura");
    if (mApe) {
      mApe.addEventListener("click", function (ev) {
        if (ev.target === mApe) cerrarModalApertura();
      });
    }

    // Modal cierre
    var fCie = document.getElementById("formCierre");
    if (fCie) fCie.addEventListener("submit", guardarCierre);

    var cieCerrar = $s("#mCieCerrar");
    if (cieCerrar) cieCerrar.addEventListener("click", cerrarModalCierre);

    var cieCancelar = $s("#mCieCancelar");
    if (cieCancelar) cieCancelar.addEventListener("click", cerrarModalCierre);

    var mCie = $s("#modalCierre");
    if (mCie) {
      mCie.addEventListener("click", function (ev) {
        if (ev.target === mCie) cerrarModalCierre();
      });
    }

    var cieMonto = $s("#cie_monto");
    if (cieMonto) {
      cieMonto.addEventListener("input", actualizarDiferencia);
      cieMonto.addEventListener("change", actualizarDiferencia);
    }

    // Modal egreso / gasto
    var fMov = document.getElementById("formMovimiento");
    if (fMov) fMov.addEventListener("submit", guardarMovimiento);

    var movCerrar = $s("#mMovCerrar");
    if (movCerrar) movCerrar.addEventListener("click", cerrarModalMovimiento);

    var movCancelar = $s("#mMovCancelar");
    if (movCancelar) movCancelar.addEventListener("click", cerrarModalMovimiento);

    var mMov = $s("#modalMovimiento");
    if (mMov) {
      mMov.addEventListener("click", function (ev) {
        if (ev.target === mMov) cerrarModalMovimiento();
      });
    }

    // Modal movimientos de caja (ventas + gastos)
    var movsCerrarX = $s("#movsCerrarX");
    if (movsCerrarX) movsCerrarX.addEventListener("click", cerrarModalMovimientos);

    var movsCerrarPie = $s("#movsCerrarPie");
    if (movsCerrarPie) movsCerrarPie.addEventListener("click", cerrarModalMovimientos);

    var movsPdf = $s("#movsPdf");
    if (movsPdf) {
      movsPdf.addEventListener("click", function () {
        if (!datos.movsActual || !window.ArqueoCajaAPI) return;
        window.open(window.ArqueoCajaAPI.movimientosPdfUrl(datos.movsActual.id), "_blank", "noopener");
      });
    }

    var mMovs = $s("#modalMovimientosCaja");
    if (mMovs) {
      mMovs.addEventListener("click", function (ev) {
        if (ev.target === mMovs) cerrarModalMovimientos();
      });
    }

    var movsTabs = $s("#movsTabs");
    if (movsTabs) {
      movsTabs.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-tab]");
        if (!boton) return;

        var tab = boton.getAttribute("data-tab");

        movsTabs.querySelectorAll("[data-tab]").forEach(function (b) {
          var activa = b === boton;
          b.classList.toggle("activa", activa);
          b.setAttribute("aria-selected", activa ? "true" : "false");
        });

        ["ventas", "gastos"].forEach(function (t) {
          var panel = document.getElementById("movsPanel" + t.charAt(0).toUpperCase() + t.slice(1));
          if (panel) panel.classList.toggle("activo", t === tab);
        });
      });
    }

    var movsReabrir = $s("#movsReabrir");
    if (movsReabrir) {
      movsReabrir.addEventListener("click", function () {
        if (datos.movsActual) reabrirArqueo(datos.movsActual.id);
      });
    }

    var movsEditar = $s("#movsEditar");
    if (movsEditar) {
      movsEditar.addEventListener("click", function () {
        if (!datos.movsActual) return;
        var id = datos.movsActual.id;
        cerrarModalMovimientos();
        abrirModalAperturaEditar(id);
      });
    }

    var movsEliminar = $s("#movsEliminar");
    if (movsEliminar) {
      movsEliminar.addEventListener("click", function () {
        if (!datos.movsActual) return;
        var id = datos.movsActual.id;
        cerrarModalMovimientos();
        confirmarEliminarArqueo(id);
      });
    }

    var movsGastosBody = $s("#movsGastosBody");
    if (movsGastosBody) {
      movsGastosBody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton) return;

        if (boton.getAttribute("data-accion") === "movimiento_eliminar") {
          confirmarEliminarMovimiento(boton.getAttribute("data-id"));
        }
      });
    }

    // Filtros
    ["filtroEstado", "filtroSucursal", "filtroDesde", "filtroHasta"].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener("change", cargar);
    });

    var limpiar = $s("#btnLimpiarFiltros");
    if (limpiar) {
      limpiar.addEventListener("click", function () {
        ["filtroEstado", "filtroSucursal", "filtroDesde", "filtroHasta"].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.value = "";
        });
        cargar();
      });
    }

    // Acciones de la tabla
    var tbody = document.querySelector("#tablaArqueos tbody");
    if (tbody) {
      tbody.addEventListener("click", function (ev) {
        var boton = ev.target.closest("[data-accion]");
        if (!boton || boton.disabled) return;

        var accion = boton.getAttribute("data-accion");
        var id = boton.getAttribute("data-id");

        if (accion === "ver") {
          verDetalle(id);
        } else if (accion === "movimientos") {
          abrirModalMovimientos(id);
        } else if (accion === "gasto") {
          abrirModalMovimiento(id);
        } else if (accion === "cerrar") {
          abrirModalCierre(id);
        } else if (accion === "reabrir") {
          reabrirArqueo(id);
        } else if (accion === "modificar_apertura") {
          abrirModalAperturaEditar(id);
        } else if (accion === "eliminar_arqueo") {
          confirmarEliminarArqueo(id);
        } else if (accion === "ticket") {
          abrirTicketCierre(id);
        } else {
          /* sin acción */
        }
      });
    }

    // Escape
    document.addEventListener("keydown", function (ev) {
      if (ev.key !== "Escape") return;
      var abierto = document.querySelector(".modal-overlay.abierto");
      if (!abierto) return;
      if (abierto.id === "modalApertura") cerrarModalApertura();
      else if (abierto.id === "modalCierre") cerrarModalCierre();
      else if (abierto.id === "modalMovimiento") cerrarModalMovimiento();
      else if (abierto.id === "modalMovimientosCaja") cerrarModalMovimientos();
    });
  });
})();
