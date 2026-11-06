(function () {
    "use strict";

    var $s = function (sel, ctxo) { return (ctxo || document).querySelector(sel); };

    var estado = {
        datos: null,
        charts: { forma: null, dia: null },
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

    function chipForma(f) {
        return f === "credito"
            ? '<span class="rep-chip credito"><i class="fa-solid fa-credit-card"></i> Crédito</span>'
            : '<span class="rep-chip contado"><i class="fa-solid fa-money-bill-wave"></i> Contado</span>';
    }

    function chipEstado(e) {
        return e === "anulada"
            ? '<span class="rep-chip anulada">Anulada</span>'
            : '<span class="rep-chip registrada">Registrada</span>';
    }

    // =========================================================
    // CARGA
    // =========================================================
    function filtros() {
        return {
            id_sucursal: ($s("#rfSucursal") && $s("#rfSucursal").value) || "",
            id_usuario:  ($s("#rfUsuario")  && $s("#rfUsuario").value)  || "",
            desde:       ($s("#rfDesde")    && $s("#rfDesde").value)    || "",
            hasta:       ($s("#rfHasta")    && $s("#rfHasta").value)    || "",
            forma_pago:  ($s("#rfForma")    && $s("#rfForma").value)    || "",
            estado:      ($s("#rfEstado")   && $s("#rfEstado").value)   || "",
        };
    }

    function mostrarCarga(a) {
        var c = $s("#tablaCarga");
        if (c) c.classList.toggle("visible", Boolean(a));
    }

    function cargar() {
        if (!window.ComprasReporteAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);

        window.ComprasReporteAPI.cargar(filtros()).then(function (rpta) {
            mostrarCarga(false);

            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.datos = rpta.data || {};

            llenarSucursales(estado.datos.sucursales || []);
            llenarUsuarios(estado.datos.usuarios || []);
            pintarResumen(estado.datos.resumen || {});
            pintarTabla(estado.datos.compras || []);
            pintarGraficas(estado.datos);
        });
    }

    function llenarSucursales(suc) {
        var sel = $s("#rfSucursal");
        if (!sel) return;

        var esSuper = estado.datos && estado.datos.usuario && estado.datos.usuario.es_superadmin;
        if (!esSuper) {
            var wrap = $s("#rfSucursalWrap");
            if (wrap) wrap.style.display = "none";
            return;
        }

        if (sel.dataset.cargado === "1") return;
        var opciones = ['<option value="">Todas las sucursales</option>'];
        suc.forEach(function (s) {
            opciones.push('<option value="' + Number(s.id_sucursal) + '">' +
                escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
                "</option>");
        });
        sel.innerHTML = opciones.join("");
        sel.dataset.cargado = "1";
    }

    function llenarUsuarios(usuarios) {
        var sel = $s("#rfUsuario");
        if (!sel) return;

        var actual = sel.value;
        var opciones = ['<option value="">Todos los usuarios</option>'];
        usuarios.forEach(function (u) {
            opciones.push('<option value="' + u.id + '">' +
                escapar(u.nombre) + " (" + escapar(u.username) + ")</option>");
        });
        sel.innerHTML = opciones.join("");
        if (actual && sel.querySelector('option[value="' + actual + '"]')) sel.value = actual;
    }

    function pintarResumen(r) {
        var set = function (sel, v) { var el = $s(sel); if (el) el.textContent = v; };
        set("#rcCompras", String(r.compras || 0));
        set("#rcTotal", bs(r.total));
        set("#rcContado", bs(r.contado));
        set("#rcContadoN", (r.compras_contado || 0) + " compras");
        set("#rcCredito", bs(r.credito));
        set("#rcCreditoN", (r.compras_credito || 0) + " compras");
        set("#rcSaldo", bs(r.saldo_pendiente));
        set("#rcPagado", "Pagado: " + bs((r.abonos || 0) + (r.pagado_posterior || 0)));
        set("#rcProv", String(r.proveedores || 0));
    }

    function pintarTabla(compras) {
        var body = $s("#repTablaBody");
        var foot = $s("#repTablaFoot");
        var wrap = $s("#repTablaScroll");
        if (!body) return;

        if (!compras.length) {
            body.innerHTML = '<tr><td colspan="11" class="rep-vacio">Sin compras en el rango seleccionado.</td></tr>';
            if (foot) foot.style.display = "none";
            if (wrap) wrap.style.display = "";
            return;
        }

        var totalSum = 0;
        var saldoSum = 0;

        body.innerHTML = compras.map(function (c) {
            totalSum += Number(c.total);
            saldoSum += Number(c.saldo);

            return (
                "<tr>" +
                "<td><span class='cod'>#" + c.id + "</span></td>" +
                "<td>" + escapar(c.fecha_compra) + "</td>" +
                "<td>" + escapar(c.tipo_documento) + (c.numero_documento ? " · " + escapar(c.numero_documento) : "") + "</td>" +
                "<td>" + escapar(c.proveedor) + (c.proveedor_nit ? "<span style='display:block;font-size:10.5px;color:#a79ea2'>NIT " + escapar(c.proveedor_nit) + "</span>" : "") + "</td>" +
                "<td>" + escapar(c.usuario) + "</td>" +
                "<td>" + escapar(c.sucursal) + (c.ciudad ? "<span style='display:block;font-size:10.5px;color:#a79ea2'>" + escapar(c.ciudad) + "</span>" : "") + "</td>" +
                "<td class='num'><b>" + bs(c.total) + "</b></td>" +
                "<td>" + chipForma(c.forma_pago) + "</td>" +
                "<td class='num'>" + (Number(c.saldo) > 0 ? "<b style='color:#b91c1c'>" + bs(c.saldo) + "</b>" : "—") + "</td>" +
                "<td>" + chipEstado(c.estado) + "</td>" +
                "<td class='rep-acciones'><button type='button' data-ver='" + c.id + "'><i class='fa-solid fa-eye'></i></button></td>" +
                "</tr>"
            );
        }).join("");

        if (wrap) wrap.style.display = "";

        var footTotal = $s("#footTotal");
        var footSaldo = $s("#footSaldo");
        if (footTotal) footTotal.textContent = bs(totalSum);
        if (footSaldo) footSaldo.textContent = bs(saldoSum);
        if (foot) foot.style.display = "";
    }

    // =========================================================
    // GRÁFICAS (canvas nativo, sin librerías)
    // =========================================================
    function pintarGraficas(d) {
        dibujarDona(document.getElementById("chartFormaPago"), d.por_forma || []);
        dibujarBarras(document.getElementById("chartPorDia"), d.por_dia || []);
    }

    function dibujarDona(canvas, datos) {
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) {
            dibujarMensajeVacio(ctx, W, H, "Sin datos");
            return;
        }

        var total = datos.reduce(function (a, x) { return a + x.total; }, 0);
        var colores = { contado: "#14803d", credito: "#d97706" };
        var centro = { x: W * 0.35, y: H / 2, r: Math.min(H, W * 0.7) / 2.4 };

        var inicio = -Math.PI / 2;

        datos.forEach(function (f) {
            var porcion = total > 0 ? f.total / total : 0;
            var fin = inicio + porcion * Math.PI * 2;

            ctx.beginPath();
            ctx.moveTo(centro.x, centro.y);
            ctx.arc(centro.x, centro.y, centro.r, inicio, fin);
            ctx.closePath();
            ctx.fillStyle = colores[f.forma] || "#6b7280";
            ctx.fill();

            inicio = fin;
        });

        // Círculo interior
        ctx.beginPath();
        ctx.arc(centro.x, centro.y, centro.r * 0.6, 0, Math.PI * 2);
        ctx.fillStyle = "#fff";
        ctx.fill();

        // Texto central
        ctx.fillStyle = "#1c1416";
        ctx.font = "bold 12px system-ui";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("Total", centro.x, centro.y - 8);
        ctx.font = "bold 11px system-ui";
        ctx.fillStyle = "#d9232d";
        ctx.fillText(bs(total).replace("Bs ", ""), centro.x, centro.y + 8);

        // Leyenda
        var lx = W * 0.68;
        var ly = H / 2 - (datos.length * 14) / 2;

        datos.forEach(function (f, i) {
            var y = ly + i * 22;
            ctx.fillStyle = colores[f.forma] || "#6b7280";
            ctx.fillRect(lx, y, 10, 10);

            ctx.fillStyle = "#1c1416";
            ctx.font = "bold 11px system-ui";
            ctx.textAlign = "left";
            ctx.textBaseline = "middle";
            ctx.fillText((f.forma === "contado" ? "Contado" : "Crédito") + "  " + f.compras, lx + 16, y + 5);

            ctx.fillStyle = "#a79ea2";
            ctx.font = "10.5px system-ui";
            ctx.fillText(bs(f.total), lx + 16, y + 18);
        });
    }

    function dibujarBarras(canvas, datos) {
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) {
            dibujarMensajeVacio(ctx, W, H, "Sin datos");
            return;
        }

        var pad = { top: 20, right: 12, bottom: 30, left: 44 };
        var areaW = W - pad.left - pad.right;
        var areaH = H - pad.top - pad.bottom;

        var maxTotal = Math.max.apply(null, datos.map(function (d) { return d.total; }));
        if (maxTotal <= 0) maxTotal = 1;

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
            var val = maxTotal * (1 - i / 4);
            ctx.fillText(val >= 1000 ? (val / 1000).toFixed(1) + "k" : val.toFixed(0), pad.left - 5, y);
        }

        var gap = areaW / datos.length;
        var barW = Math.min(38, gap * 0.7);

        datos.forEach(function (d, i) {
            var altoBarra = (d.total / maxTotal) * areaH;
            var x = pad.left + gap * i + (gap - barW) / 2;
            var y = pad.top + areaH - altoBarra;

            var grad = ctx.createLinearGradient(0, y, 0, pad.top + areaH);
            grad.addColorStop(0, "#d9232d");
            grad.addColorStop(1, "#f59095");

            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.roundRect ? ctx.roundRect(x, y, barW, altoBarra, [4, 4, 0, 0]) : ctx.rect(x, y, barW, altoBarra);
            ctx.fill();

            // Etiqueta fecha (dd/mm)
            ctx.fillStyle = "#a79ea2";
            ctx.font = "9px system-ui";
            ctx.textAlign = "center";
            ctx.textBaseline = "top";
            var partes = d.fecha.split("-");
            ctx.fillText(partes[2] + "/" + partes[1], x + barW / 2, H - pad.bottom + 6);
        });
    }

    function dibujarMensajeVacio(ctx, W, H, msg) {
        ctx.fillStyle = "#a79ea2";
        ctx.font = "12px system-ui";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(msg, W / 2, H / 2);
    }

    // =========================================================
    // DETALLE (Swal)
    // =========================================================
    function verDetalle(id) {
        var c = (estado.datos.compras || []).find(function (x) { return Number(x.id) === Number(id); });
        if (!c) return;

        // El detalle viene del mismo registro ya cargado. Si quisieras
        // las líneas, necesitarías otro endpoint (CompraReporte::detalle()).
        // Por ahora mostramos cabecera.
        var fila = function (et, val) {
            return "<dt>" + et + "</dt><dd>" + (escapar(val) || "—") + "</dd>";
        };

        Swal.fire({
            icon: "info",
            title: "Compra #" + c.id,
            html:
                '<dl class="dl-datos">' +
                fila("Fecha", c.fecha_compra) +
                fila("Proveedor", c.proveedor + (c.proveedor_nit ? " · NIT " + c.proveedor_nit : "")) +
                fila("Usuario", c.usuario) +
                fila("Sucursal", c.sucursal) +
                fila("Documento", c.tipo_documento + " " + (c.numero_documento || "")) +
                fila("Forma de pago", c.forma_pago) +
                fila("Subtotal", bs(c.subtotal)) +
                fila("Descuento", bs(c.descuento)) +
                fila("Impuesto", bs(c.impuesto)) +
                fila("Total", bs(c.total)) +
                fila("Abono", bs(c.abono)) +
                fila("Pagado después", bs(c.pagado)) +
                fila("Saldo", bs(c.saldo)) +
                "</dl>",
            confirmButtonText: "Cerrar",
            confirmButtonColor: "#d9232d",
        });
    }

    // =========================================================
    // PDF
    // =========================================================
    function exportarPDF() {
        var url = window.ComprasReporteAPI.detallePdf(filtros());
        var win = window.open(url, "_blank");
        if (!win) aviso("Ventana bloqueada", "Permite las ventanas emergentes para ver el PDF", "warning", 5000);
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        // Fechas por defecto: últimos 30 días
        var hoy = new Date();
        var desde = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);
        var iso = function (d) { return d.toISOString().slice(0, 10); };
        if ($s("#rfDesde")) $s("#rfDesde").value = iso(desde);
        if ($s("#rfHasta")) $s("#rfHasta").value = iso(hoy);

        cargar();

        var btn = $s("#rfFiltrar");
        if (btn) btn.addEventListener("click", cargar);

        var btnLimpiar = $s("#rfLimpiar");
        if (btnLimpiar) {
            btnLimpiar.addEventListener("click", function () {
                ["rfSucursal","rfUsuario","rfForma","rfEstado","rfDesde","rfHasta"].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = "";
                });
                cargar();
            });
        }

        var btnPdf = $s("#btnPdf");
        if (btnPdf) btnPdf.addEventListener("click", exportarPDF);

        var tbody = $s("#repTablaBody");
        if (tbody) {
            tbody.addEventListener("click", function (ev) {
                var b = ev.target.closest("[data-ver]");
                if (!b) return;
                verDetalle(b.getAttribute("data-ver"));
            });
        }
    });
})();