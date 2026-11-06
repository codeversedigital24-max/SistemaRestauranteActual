/**
 * =====================================================
 * modelos/ventas/ventas.js
 * -----------------------------------------------------
 * UI del historial de ventas:
 *   · Filtros (sucursal, vendedor, método, estado, fechas, buscar)
 *   · DataTable con detalle y anulación
 * =====================================================
 */
(function () {
    "use strict";

    var $s = function (sel, ctxo) { return (ctxo || document).querySelector(sel); };

    var estado = {
        tabla: null,
        ventas: [],
        datos: null,
        reintentos: 0,
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
        return "Bs " + n.toLocaleString("es-BO", {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        });
    }

    function aviso(titulo, texto, tipo, tiempo) {
        if (!window.Swal) return console.warn(titulo + ": " + texto);
        Swal.fire({
            toast: true, position: "top-end", icon: tipo || "info",
            title: titulo, text: texto, timer: tiempo || 3200,
            timerProgressBar: true, showConfirmButton: false,
        });
    }

    function avisoGrande(titulo, texto, tipo) {
        if (!window.Swal) return;
        return Swal.fire({
            icon: tipo || "success", title: titulo, text: texto,
            confirmButtonText: "Entendido", confirmButtonColor: "#d9232d",
        });
    }

    function chipEstado(e) {
        return e === "anulada"
            ? '<span class="badge-mod badge-rojo">Anulada</span>'
            : '<span class="badge-mod badge-verde">Registrada</span>';
    }

    function chipMetodo(m) {
        var mapa = {
            efectivo: ["badge-verde", "Efectivo", "fa-money-bill-wave"],
            qr:       ["badge-azul",  "QR",       "fa-qrcode"],
            tarjeta:  ["badge-morado","Tarjeta",  "fa-credit-card"],
            mixto:    ["badge-ambar", "Mixto",    "fa-shuffle"],
            otro:     ["badge-gris",  "Otro",     "fa-ellipsis"],
        };
        var info = mapa[m] || mapa.otro;
        return '<span class="badge-mod ' + info[0] + '"><i class="fa-solid ' + info[2] + '"></i> ' + info[1] + '</span>';
    }

    // =========================================================
    // CARGA
    // =========================================================
    function filtros() {
        return {
            id_sucursal: ($s("#fSucursal") && $s("#fSucursal").value) || "",
            id_usuario:  ($s("#fVendedor") && $s("#fVendedor").value) || "",
            desde:       ($s("#fDesde")    && $s("#fDesde").value)    || "",
            hasta:       ($s("#fHasta")    && $s("#fHasta").value)    || "",
            metodo_pago: ($s("#fMetodo")   && $s("#fMetodo").value)   || "",
            estado:      ($s("#fEstado")   && $s("#fEstado").value)   || "",
            buscar:      ($s("#fBuscar")   && $s("#fBuscar").value)   || "",
        };
    }

    function mostrarCarga(activar) {
        var c = $s("#tablaCarga");
        if (c) c.classList.toggle("visible", Boolean(activar));
    }

    function cargar() {
        if (!window.VentasAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);

        window.VentasAPI.listar(filtros()).then(function (rpta) {
            mostrarCarga(false);

            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error al leer ventas", "error", 5000);
                pintarTabla([]);
                return;
            }

            estado.datos = rpta.data || {};
            estado.ventas = estado.datos.ventas || [];

            llenarSucursales(estado.datos.sucursales || []);
            llenarVendedores(estado.datos.vendedores || []);
            pintarResumen(estado.datos.resumen || {});
            pintarTabla(estado.ventas);
        });
    }

    function llenarSucursales(sucursales) {
        var sel = $s("#fSucursal");
        if (!sel) return;

        var esSuper = estado.datos && estado.datos.usuario && estado.datos.usuario.es_superadmin;
        if (!esSuper) {
            var wrap = $s("#fSucursalWrap");
            if (wrap) wrap.style.display = "none";
            return;
        }

        if (sel.dataset.cargado === "1") return;

        var opciones = ['<option value="">Todas las sucursales</option>'];
        sucursales.forEach(function (s) {
            opciones.push('<option value="' + Number(s.id_sucursal) + '">' +
                escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
                "</option>");
        });
        sel.innerHTML = opciones.join("");
        sel.dataset.cargado = "1";
    }

    function llenarVendedores(vendedores) {
        var sel = $s("#fVendedor");
        if (!sel) return;

        var actual = sel.value;
        var opciones = ['<option value="">Todos los vendedores</option>'];

        vendedores.forEach(function (v) {
            opciones.push(
                '<option value="' + v.id + '">' +
                escapar(v.nombre) + " (" + escapar(v.username) + ")" +
                "</option>"
            );
        });

        sel.innerHTML = opciones.join("");
        if (actual && sel.querySelector('option[value="' + actual + '"]')) sel.value = actual;
    }

    function pintarResumen(r) {
        var set = function (sel, v) { var el = $s(sel); if (el) el.textContent = v; };
        set("#rvVentas", String(r.ventas || 0));
        set("#rvTotal", bs(r.total));
        set("#rvEfectivo", bs(r.efectivo));
        set("#rvTarjeta", bs(r.tarjeta));
        set("#rvQr", bs(r.qr));
        set("#rvAnuladas", String(r.anuladas || 0));
    }

    // =========================================================
    // TABLA
    // =========================================================
    function pintarTabla(datos) {
        if (!window.jQuery || !jQuery.fn.DataTable) {
            if (estado.reintentos++ < 50) {
                setTimeout(function () { pintarTabla(datos); }, 60);
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

        estado.tabla = jQuery("#tablaVentas").DataTable({
            data: datos,
            deferRender: true,
            responsive: { details: { type: "inline", target: "tr" } },
            autoWidth: false,
            pageLength: 15,
            lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, "Todos"]],
            order: [[1, "desc"]],
            language: { url: "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
            dom: "<'dt-top'<'dt-buscador'f><'dt-selector'l>>" + "t" + "<'dt-bottom'ip>",
            columnDefs: [
                { targets: 0, width: "110px" },
                { targets: -1, width: "110px" },
            ],
            columns: [
                {
                    data: "codigo",
                    responsivePriority: 2,
                    render: function (t) {
                        return '<span class="ven-doc"><b>' + escapar(t) + '</b></span>';
                    },
                },
                { data: "fecha_venta", responsivePriority: 1 },
                {
                    data: "usuario",
                    responsivePriority: 3,
                    render: function (t, type, fila) {
                        return '<span class="dt-texto">' + escapar(t) + '</span>' +
                            '<span class="dt-subtexto"><i class="fa-solid fa-user"></i> ' + escapar(fila.username) + '</span>';
                    },
                },
                {
                    data: "sucursal",
                    responsivePriority: 6,
                    render: function (t, type, fila) {
                        return '<span class="dt-texto">' + escapar(t) + '</span>' +
                            (fila.ciudad ? '<span class="dt-subtexto">' + escapar(fila.ciudad) + '</span>' : '');
                    },
                },
                {
                    data: "items",
                    responsivePriority: 7,
                    className: "dt-num",
                    render: function (t) {
                        return '<b>' + Number(t || 0) + '</b>';
                    },
                },
                {
                    data: "total",
                    responsivePriority: 4,
                    className: "dt-num",
                    render: function (t) {
                        return '<b style="color:var(--mod-tinta)">' + bs(t) + '</b>';
                    },
                },
                {
                    data: "metodo_pago",
                    responsivePriority: 5,
                    orderable: false,
                    render: function (t) {
                        return chipMetodo(t);
                    },
                },
                {
                    data: "estado",
                    responsivePriority: 8,
                    orderable: false,
                    render: function (t) {
                        return chipEstado(t);
                    },
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    responsivePriority: 9,
                    render: function (fila) {
                        return '<span class="acciones-fila">' +
                            '<button type="button" class="btn-tabla ver" data-accion="ver" data-id="' + fila.id + '" title="Ver detalle"><i class="fa-solid fa-eye"></i></button>' +
                            (fila.estado === "registrada"
                                ? '<button type="button" class="btn-tabla borrar" data-accion="anular" data-id="' + fila.id + '" title="Anular venta"><i class="fa-solid fa-ban"></i></button>'
                                : '') +
                            '</span>';
                    },
                },
            ],
        });
    }

    // =========================================================
    // DETALLE
    // =========================================================
    function verDetalle(id) {
        if (!window.VentasAPI) return;

        window.VentasAPI.ventaDetalle(id).then(function (rpta) {
            if (!rpta.success || !rpta.data || !rpta.data.venta) {
                aviso("No se pudo abrir", rpta.message || "Venta no encontrada", "error");
                return;
            }

            var v = rpta.data.venta;

            var filas = (v.detalle || []).map(function (d) {
                return (
                    "<tr>" +
                    '<td style="padding:7px 8px;border-bottom:1px solid #ececef">' +
                    '<b style="display:block;font-size:12.5px">' + escapar(d.producto) + '</b>' +
                    '<span style="font-size:10.5px;color:#a79ea2">' + escapar(d.codigo) + '</span>' +
                    '</td>' +
                    '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right">' + d.cantidad + '</td>' +
                    '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right">' + bs(d.precio_unitario) + '</td>' +
                    '<td style="padding:7px 8px;border-bottom:1px solid #ececef;text-align:right"><b>' + bs(d.subtotal) + '</b></td>' +
                    "</tr>"
                );
            }).join("");

            var tarjeta = function (et, val) {
                return '<div style="padding:7px 10px;background:#f8f7f7;border:1px solid #ececef;border-radius:8px">' +
                    '<span style="display:block;font-size:9.5px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#a79ea2">' + et + '</span>' +
                    '<b style="display:block;font-size:12px;color:#1c1416;margin-top:1px">' + escapar(val || "—") + '</b>' +
                    '</div>';
            };

            Swal.fire({
                title: "Venta " + v.codigo,
                width: 780,
                html:
                    '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:10px;text-align:left">' +
                    tarjeta("Sucursal", v.sucursal) +
                    tarjeta("Vendedor", v.usuario) +
                    tarjeta("Fecha", v.fecha_venta) +
                    tarjeta("Método", v.metodo_pago) +
                    "</div>" +
                    '<div style="overflow-x:auto;border:1px solid #ececef;border-radius:10px">' +
                    '<table style="width:100%;border-collapse:collapse;font-size:12.5px">' +
                    "<thead><tr>" +
                    '<th style="padding:8px;background:#f8f7f7;text-align:left;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Producto</th>' +
                    '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Cant.</th>' +
                    '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Precio</th>' +
                    '<th style="padding:8px;background:#f8f7f7;text-align:right;font-size:10.5px;color:#a79ea2;text-transform:uppercase">Subtotal</th>' +
                    "</tr></thead><tbody>" + filas + "</tbody></table></div>" +
                    '<div style="display:flex;justify-content:flex-end;margin-top:10px">' +
                    '<div style="min-width:250px;font-size:13px">' +
                    '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#6b6165"><span>Subtotal</span><span>' + bs(v.subtotal) + '</span></div>' +
                    (v.descuento > 0 ? '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#6b6165"><span>Descuento</span><span>− ' + bs(v.descuento) + '</span></div>' : '') +
                    (v.impuesto > 0 ? '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#6b6165"><span>Impuesto</span><span>' + bs(v.impuesto) + '</span></div>' : '') +
                    '<div style="display:flex;justify-content:space-between;gap:20px;padding:8px 0 0;border-top:2px solid #1c1416;margin-top:5px"><b>Total</b><b style="font-size:16px;color:#d9232d">' + bs(v.total) + '</b></div>' +
                    (v.metodo_pago === 'efectivo'
                        ? '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#6b6165;font-size:12px"><span>Recibido</span><span>' + bs(v.recibido) + '</span></div>' +
                          '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#6b6165;font-size:12px"><span>Cambio</span><span>' + bs(v.cambio) + '</span></div>'
                        : '') +
                    "</div></div>",
                confirmButtonText: "Cerrar",
                confirmButtonColor: "#d9232d",
            });
        });
    }

    // =========================================================
    // ANULAR
    // =========================================================
    function anular(id) {
        var fila = estado.ventas.find(function (v) { return Number(v.id) === Number(id); });

        Swal.fire({
            icon: "warning",
            title: "¿Anular esta venta?",
            html:
                "Se anulará <strong>" + escapar(fila ? fila.codigo : "#" + id) + "</strong> por <b>" +
                bs(fila ? fila.total : 0) + "</b>.<br>" +
                "<span style='font-size:13px;color:#8a8a8f'>El stock se devuelve al inventario y los totales del arqueo se descuentan.</span>",
            showCancelButton: true,
            confirmButtonText: "Sí, anular",
            cancelButtonText: "Cancelar",
            confirmButtonColor: "#d9232d",
            cancelButtonColor: "#9aa0a6",
            reverseButtons: true,
        }).then(function (r) {
            if (!r.isConfirmed) return;

            window.VentasAPI.anularVenta(id).then(function (rpta) {
                if (!rpta.success) {
                    aviso("No se pudo anular", rpta.message || "Intenta de nuevo", "error", 5500);
                    return;
                }
                avisoGrande("Venta anulada", rpta.message || "El stock se actualizó", "success");
                cargar();
            });
        });
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        var btnFiltrar = $s("#btnFiltrar");
        if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

        var btnLimpiar = $s("#btnLimpiar");
        if (btnLimpiar) {
            btnLimpiar.addEventListener("click", function () {
                ["fSucursal", "fVendedor", "fMetodo", "fEstado", "fDesde", "fHasta", "fBuscar"].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = "";
                });
                cargar();
            });
        }

        ["fSucursal", "fVendedor", "fMetodo", "fEstado"].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener("change", function () { if (!el.disabled) cargar(); });
        });

        var buscar = $s("#fBuscar");
        if (buscar) {
            var timer;
            buscar.addEventListener("input", function () {
                clearTimeout(timer);
                timer = setTimeout(cargar, 400);
            });
        }

        var tbody = document.querySelector("#tablaVentas tbody");
        if (tbody) {
            tbody.addEventListener("click", function (ev) {
                var boton = ev.target.closest("[data-accion]");
                if (!boton || boton.disabled) return;
                var acc = boton.getAttribute("data-accion");
                var id  = boton.getAttribute("data-id");
                if (acc === "ver") verDetalle(id);
                else if (acc === "anular") anular(id);
            });
        }
    });
})();