(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };

    var estado = {
        datos: null,
        charts: { metodo: null, dia: null },
    };

    // =========================================================
    // UTILS
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
    function aviso(t, m, tipo, time) {
        if (!window.Swal) return console.warn(t + ": " + m);
        Swal.fire({
            toast: true, position: "top-end", icon: tipo || "info",
            title: t, text: m, timer: time || 3000,
            timerProgressBar: true, showConfirmButton: false,
        });
    }
    function mostrarCarga(a) {
        var c = $s("#tablaCarga");
        if (c) c.classList.toggle("visible", !!a);
    }

    function chipMetodo(m) {
        var mapa = {
            efectivo: ["efectivo", "Efectivo", "fa-money-bill-wave"],
            qr:       ["qr", "QR", "fa-qrcode"],
            tarjeta:  ["tarjeta", "Tarjeta", "fa-credit-card"],
            mixto:    ["mixto", "Mixto", "fa-shuffle"],
            otro:     ["otro", "Otro", "fa-ellipsis"],
        };
        var i = mapa[m] || mapa.otro;
        return '<span class="rep-chip ' + i[0] + '"><i class="fa-solid ' + i[2] + '"></i> ' + i[1] + '</span>';
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
            id_arqueo:   ($s("#rfCaja")     && $s("#rfCaja").value)     || "",
            id_usuario:  ($s("#rfUsuario")  && $s("#rfUsuario").value)  || "",
            desde:       ($s("#rfDesde")    && $s("#rfDesde").value)    || "",
            hasta:       ($s("#rfHasta")    && $s("#rfHasta").value)    || "",
            metodo_pago: ($s("#rfMetodo")   && $s("#rfMetodo").value)   || "",
            estado:      ($s("#rfEstado")   && $s("#rfEstado").value)   || "",
        };
    }

    function cargar() {
        if (!window.VentasReporteAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);
        window.VentasReporteAPI.cargar(filtros()).then(function (rpta) {
            mostrarCarga(false);
            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.datos = rpta.data || {};
            llenarSucursales(estado.datos.sucursales || []);
            llenarCajas(estado.datos.cajas || []);
            llenarUsuarios(estado.datos.usuarios || []);
            pintarResumen(estado.datos.resumen || {});
            pintarTabla(estado.datos.ventas || []);
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

    function llenarCajas(cajas) {
        var sel = $s("#rfCaja");
        if (!sel) return;

        var actual = sel.value;
        var opciones = ['<option value="">Todas las cajas</option>'];
        cajas.forEach(function (c) {
            opciones.push('<option value="' + c.id + '">' +
                escapar(c.codigo + " · " + c.nombre + " · " + c.fecha) +
                "</option>");
        });
        sel.innerHTML = opciones.join("");
        if (actual && sel.querySelector('option[value="' + actual + '"]')) sel.value = actual;
    }

    function llenarUsuarios(usuarios) {
        var sel = $s("#rfUsuario");
        if (!sel) return;

        var actual = sel.value;
        var opciones = ['<option value="">Todos los vendedores</option>'];
        usuarios.forEach(function (u) {
            opciones.push('<option value="' + u.id + '">' +
                escapar(u.nombre) + " (" + escapar(u.username) + ")" +
                "</option>");
        });
        sel.innerHTML = opciones.join("");
        if (actual && sel.querySelector('option[value="' + actual + '"]')) sel.value = actual;
    }

    function pintarResumen(r) {
        var set = function (sel, v) { var el = $s(sel); if (el) el.textContent = v; };
        set("#rcVentas", String(r.ventas || 0));
        set("#rcAnuladas", String(r.anuladas || 0) + " anuladas");
        set("#rcTotal", bs(r.total));
        set("#rcDescuento", "Descuentos: " + bs(r.descuentos));
        set("#rcEfectivo", bs(r.efectivo));
        set("#rcQr", bs(r.qr));
        set("#rcTarjeta", bs(r.tarjeta));
        set("#rcPromedio", bs(r.ticket_promedio));
        set("#rcVendedores", String(r.vendedores || 0) + " vendedores");
    }

    function pintarTabla(ventas) {
        var body = $s("#repTablaBody");
        var foot = $s("#repTablaFoot");
        var wrap = $s("#repTablaScroll");
        if (!body) return;

        if (!ventas.length) {
            body.innerHTML = '<tr><td colspan="11" class="rep-vacio">Sin ventas en el rango seleccionado.</td></tr>';
            if (foot) foot.style.display = "none";
            if (wrap) wrap.style.display = "";
            return;
        }

        var total = 0;

        body.innerHTML = ventas.map(function (v) {
            // Las ventas anuladas se listan pero NO suman en el pie.
            if (String(v.estado) === "registrada") total += Number(v.total);

            return (
                "<tr>" +
                "<td><span class='cod'>#" + v.id + "</span></td>" +
                "<td>" + escapar(v.fecha_venta) + "</td>" +
                "<td>" + escapar(v.codigo) + "</td>" +
                "<td>" + escapar(v.usuario) + "</td>" +
                "<td>" + (v.caja_codigo ? escapar(v.caja_codigo) + " · " + escapar(v.caja_nombre) : "—") + "</td>" +
                "<td>" + escapar(v.sucursal) + (v.ciudad ? "<span style='display:block;font-size:10.5px;color:#a79ea2'>" + escapar(v.ciudad) + "</span>" : "") + "</td>" +
                "<td class='num'>" + Number(v.items || 0) + "</td>" +
                "<td class='num'><b>" + bs(v.total) + "</b></td>" +
                "<td>" + chipMetodo(v.metodo_pago) + "</td>" +
                "<td>" + chipEstado(v.estado) + "</td>" +
                "<td class='rep-acciones'><button type='button' data-ver='" + v.id + "'><i class='fa-solid fa-eye'></i></button></td>" +
                "</tr>"
            );
        }).join("");

        if (wrap) wrap.style.display = "";
        var footTotal = $s("#footTotal");
        if (footTotal) footTotal.textContent = bs(total);
        if (foot) foot.style.display = "";
    }

    // =========================================================
    // GRÁFICAS (canvas nativo)
    // =========================================================
    function pintarGraficas(d) {
        dibujarDona(document.getElementById("chartMetodo"), d.por_metodo || []);
        dibujarBarras(document.getElementById("chartDia"), d.por_dia || []);
    }

    function dibujarDona(canvas, datos) {
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) { vacio(ctx, W, H); return; }

        var colores = {
            efectivo: "#14803d", qr: "#1e40af",
            tarjeta: "#7a2ea3", mixto: "#d97706", otro: "#6b7280",
        };

        var total = datos.reduce(function (a, x) { return a + x.total; }, 0);
        var cx = W * 0.32, cy = H / 2, radio = Math.min(H, W * 0.6) / 2.4;

        var inicio = -Math.PI / 2;

        datos.forEach(function (d) {
            var frac = total > 0 ? d.total / total : 0;
            var fin = inicio + frac * Math.PI * 2;

            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radio, inicio, fin);
            ctx.closePath();
            ctx.fillStyle = colores[d.metodo] || "#6b7280";
            ctx.fill();

            inicio = fin;
        });

        // hueco
        ctx.beginPath();
        ctx.arc(cx, cy, radio * 0.6, 0, Math.PI * 2);
        ctx.fillStyle = "#fff";
        ctx.fill();

        ctx.fillStyle = "#1c1416";
        ctx.font = "bold 11px system-ui";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("Total", cx, cy - 7);
        ctx.font = "bold 10px system-ui";
        ctx.fillStyle = "#d9232d";
        ctx.fillText("Bs " + Math.round(total), cx, cy + 8);

        // leyenda
        var lx = W * 0.62, ly = H / 2 - (datos.length * 15) / 2;
        datos.forEach(function (d, i) {
            var y = ly + i * 22;
            ctx.fillStyle = colores[d.metodo] || "#6b7280";
            ctx.fillRect(lx, y, 10, 10);

            ctx.fillStyle = "#1c1416";
            ctx.font = "bold 11px system-ui";
            ctx.textAlign = "left";
            ctx.textBaseline = "middle";
            ctx.fillText((d.metodo || "").toUpperCase() + " · " + d.ventas, lx + 16, y + 5);

            ctx.fillStyle = "#a79ea2";
            ctx.font = "10px system-ui";
            ctx.fillText(bs(d.total), lx + 16, y + 18);
        });
    }

    function dibujarBarras(canvas, datos) {
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) { vacio(ctx, W, H); return; }

        var pad = { top: 20, right: 12, bottom: 32, left: 48 };
        var areaW = W - pad.left - pad.right;
        var areaH = H - pad.top - pad.bottom;

        var max = Math.max.apply(null, datos.map(function (d) { return d.total; }));
        if (max <= 0) max = 1;

        // ejes
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
            var v = max * (1 - i / 4);
            ctx.fillText(v >= 1000 ? (v / 1000).toFixed(1) + "k" : v.toFixed(0), pad.left - 5, y);
        }

        var gap = areaW / datos.length;
        var barW = Math.min(38, gap * 0.7);

        datos.forEach(function (d, i) {
            var alto = (d.total / max) * areaH;
            var x = pad.left + gap * i + (gap - barW) / 2;
            var y = pad.top + areaH - alto;

            var grad = ctx.createLinearGradient(0, y, 0, pad.top + areaH);
            grad.addColorStop(0, "#d9232d");
            grad.addColorStop(1, "#f59095");

            ctx.fillStyle = grad;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, y, barW, alto, [4, 4, 0, 0]);
            else ctx.rect(x, y, barW, alto);
            ctx.fill();

            var partes = (d.fecha || "").split("-");
            ctx.fillStyle = "#a79ea2";
            ctx.font = "9px system-ui";
            ctx.textAlign = "center";
            ctx.textBaseline = "top";
            ctx.fillText(partes[2] + "/" + partes[1], x + barW / 2, H - pad.bottom + 6);
        });
    }

    function vacio(ctx, W, H) {
        ctx.fillStyle = "#a79ea2";
        ctx.font = "12px system-ui";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("Sin datos", W / 2, H / 2);
    }

    // =========================================================
    // VER DETALLE (Swal)
    // =========================================================
    function verDetalle(id) {
        var v = (estado.datos.ventas || []).find(function (x) { return Number(x.id) === Number(id); });
        if (!v) return;

        var fila = function (et, val) {
            return "<dt>" + et + "</dt><dd>" + (escapar(val) || "—") + "</dd>";
        };

        Swal.fire({
            icon: "info",
            title: "Venta " + v.codigo,
            width: 620,
            html:
                '<dl class="dl-datos">' +
                fila("Fecha", v.fecha_venta) +
                fila("Sucursal", v.sucursal) +
                fila("Caja", v.caja_codigo ? v.caja_codigo + " · " + v.caja_nombre : "—") +
                fila("Vendedor", v.usuario) +
                fila("Método", v.metodo_pago) +
                fila("Subtotal", bs(v.subtotal)) +
                fila("Descuento", bs(v.descuento)) +
                fila("Impuesto", bs(v.impuesto)) +
                fila("Total", bs(v.total)) +
                (v.metodo_pago === "efectivo" ?
                    fila("Recibido", bs(v.recibido)) + fila("Cambio", bs(v.cambio))
                    : "") +
                fila("Estado", v.estado) +
                "</dl>",
            confirmButtonText: "Cerrar",
            confirmButtonColor: "#d9232d",
        });
    }

    // =========================================================
    // PDF
    // =========================================================
    function exportarPDF() {
        if (!window.VentasReporteAPI) return;
        var url = window.VentasReporteAPI.pdfUrl(filtros());
        var w = window.open(url, "_blank");
        if (!w) aviso("Ventana bloqueada", "Permite las ventanas emergentes para ver el PDF", "warning", 5000);
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        var hoy = new Date();
        var desde = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);
        var iso = function (d) { return d.toISOString().slice(0, 10); };
        if ($s("#rfDesde")) $s("#rfDesde").value = iso(desde);
        if ($s("#rfHasta")) $s("#rfHasta").value = iso(hoy);

        cargar();

        var btnFiltrar = $s("#rfFiltrar");
        if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

        var btnLimpiar = $s("#rfLimpiar");
        if (btnLimpiar) btnLimpiar.addEventListener("click", function () {
            ["rfSucursal", "rfCaja", "rfUsuario", "rfMetodo", "rfEstado", "rfDesde", "rfHasta"]
                .forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = "";
                });
            cargar();
        });

        var btnPdf = $s("#btnPdf");
        if (btnPdf) btnPdf.addEventListener("click", exportarPDF);

        var body = $s("#repTablaBody");
        if (body) body.addEventListener("click", function (e) {
            var b = e.target.closest("[data-ver]");
            if (!b) return;
            verDetalle(b.getAttribute("data-ver"));
        });
    });
})();