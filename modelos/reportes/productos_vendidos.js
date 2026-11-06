(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $todos = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    var estado = { datos: null, verUtilidad: false };

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
    function cant(n) {
        n = Number(n) || 0;
        return n.toLocaleString("es-BO", { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    }
    function pct(n) {
        n = Number(n) || 0;
        return n.toFixed(1) + " %";
    }
    function aviso(t, m, tipo, time) {
        if (!window.Swal) return console.warn(t + ": " + m);
        Swal.fire({
            toast: true, position: "top-end", icon: tipo || "info",
            title: t, text: m, timer: time || 3200,
            timerProgressBar: true, showConfirmButton: false,
        });
    }
    function mostrarCarga(a) {
        var c = $s("#pvCarga");
        if (c) c.classList.toggle("visible", !!a);
    }

    // =========================================================
    // CARGA
    // =========================================================
    function filtros() {
        return {
            id_sucursal:  ($s("#pvSucursal")  && $s("#pvSucursal").value)  || "",
            id_categoria: ($s("#pvCategoria") && $s("#pvCategoria").value) || "",
            desde:        ($s("#pvDesde")     && $s("#pvDesde").value)     || "",
            hasta:        ($s("#pvHasta")     && $s("#pvHasta").value)     || "",
        };
    }

    function cargar() {
        if (!window.ProductosVendidosAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);
        window.ProductosVendidosAPI.cargar(filtros()).then(function (rpta) {
            mostrarCarga(false);
            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.datos = rpta.data || {};
            estado.verUtilidad = !!estado.datos.ver_utilidad;

            llenarSucursales(estado.datos.sucursales || []);
            llenarCategorias(estado.datos.categorias || []);
            pintarResumen(estado.datos.resumen || {});
            pintarTabla(estado.datos.ranking || []);
            pintarGraficas(estado.datos);
        });
    }

    function llenarSucursales(suc) {
        var sel = $s("#pvSucursal");
        if (!sel) return;

        var esSuper = estado.datos && estado.datos.usuario && estado.datos.usuario.es_superadmin;
        if (!esSuper) {
            var wrap = $s("#pvFiltroSucursalWrap");
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

    function llenarCategorias(cats) {
        var sel = $s("#pvCategoria");
        if (!sel) return;

        if (sel.dataset.cargado === "1") return;
        var opciones = ['<option value="">Todas las categorías</option>'];
        cats.forEach(function (c) {
            opciones.push('<option value="' + Number(c.id_categoria) + '">' +
                escapar(c.nombre_categoria) + "</option>");
        });
        sel.innerHTML = opciones.join("");
        sel.dataset.cargado = "1";
    }

    function pintarResumen(r) {
        var set = function (sel, v) { var el = $s(sel); if (el) el.textContent = v; };
        set("#pvProductos", String(r.productos || 0));
        set("#pvVentas", String(r.ventas || 0) + " ventas");
        set("#pvUnidades", cant(r.unidades));
        set("#pvVendedores", String(r.vendedores || 0) + " vendedores");
        set("#pvVenta", bs(r.venta));
        set("#pvDescuento", "Desc.: " + bs(r.descuento));

        if (estado.verUtilidad) {
            $s("#pvCardCosto").style.display = "";
            $s("#pvCardUtilidad").style.display = "";
            set("#pvCosto", bs(r.costo));
            set("#pvUtilidad", bs(r.utilidad));
            set("#pvMargen", "Margen: " + pct(r.margen));
        }
    }

    function pintarTabla(ranking) {
        var body = $s("#pvBody");
        var foot = $s("#pvFoot");
        var wrap = $s("#pvTablaScroll");
        if (!body) return;

        // Columnas condicionales según permiso
        var verUtilidad = estado.verUtilidad;
        $todos(".pv-col-costo, .pv-col-util, .pv-col-marg").forEach(function (el) {
            el.style.display = verUtilidad ? "" : "none";
        });

        if (!ranking.length) {
            body.innerHTML = '<tr><td colspan="12" class="pv-vacio">Sin productos vendidos en el rango.</td></tr>';
            if (foot) foot.style.display = "none";
            if (wrap) wrap.style.display = "";
            return;
        }

        var totCant = 0, totVeces = 0, totVenta = 0, totDesc = 0, totCosto = 0, totUtil = 0;

        body.innerHTML = ranking.map(function (p, i) {
            totCant  += Number(p.cantidad);
            totVeces += Number(p.veces_vendido);
            totVenta += Number(p.venta);
            totDesc  += Number(p.descuento);
            if (verUtilidad) {
                totCosto += Number(p.costo || 0);
                totUtil  += Number(p.utilidad || 0);
            }

            var claseRank = "";
            if (i === 0) claseRank = "gold";
            else if (i === 1) claseRank = "silver";
            else if (i === 2) claseRank = "bronze";

            var foto = p.imagen_url
                ? '<img src="' + escapar(p.imagen_url) + '" alt="">'
                : '<i class="fas fa-box"></i>';

            var part = Number(p.participacion) || 0;
            var barW = Math.min(100, part);

            var colsUtil = "";
            if (verUtilidad) {
                colsUtil =
                    '<td class="num">' + bs(p.costo) + '</td>' +
                    '<td class="num verde">' + bs(p.utilidad) + '</td>' +
                    '<td class="num">' + pct(p.margen) + '</td>';
            }

            return (
                '<tr>' +
                '<td><span class="pv-rank ' + claseRank + '">' + (i + 1) + '</span></td>' +
                '<td><div class="pv-prod">' +
                    '<div class="foto">' + foto + '</div>' +
                    '<div class="info">' +
                        '<b title="' + escapar(p.nombre) + '">' + escapar(p.nombre) + '</b>' +
                        '<span>' + escapar(p.codigo) + '</span>' +
                    '</div>' +
                '</div></td>' +
                '<td>' + (p.categoria ? escapar(p.categoria) : '<span style="color:#a79ea2">—</span>') + '</td>' +
                '<td class="num"><b>' + cant(p.cantidad) + '</b></td>' +
                '<td class="num">' + p.veces_vendido + '</td>' +
                '<td class="num">' + bs(p.precio_prom) + '</td>' +
                '<td class="num"><b>' + bs(p.venta) + '</b></td>' +
                '<td class="num" style="color:#b91c1c">' + (p.descuento > 0 ? "− " + bs(p.descuento) : "—") + '</td>' +
                '<td class="num">' +
                    '<span class="pv-part">' +
                        '<span class="bar"><span style="width:' + barW + '%"></span></span>' +
                        pct(part) +
                    '</span>' +
                '</td>' +
                colsUtil +
                '</tr>'
            );
        }).join("");

        $s("#pvFootCant").textContent = cant(totCant);
        $s("#pvFootVeces").textContent = String(totVeces);
        $s("#pvFootVenta").textContent = bs(totVenta);
        $s("#pvFootDesc").textContent = "− " + bs(totDesc);

        if (verUtilidad) {
            $s("#pvFootCosto").textContent = bs(totCosto);
            $s("#pvFootUtil").textContent = bs(totUtil);
            $s("#pvFootMarg").textContent = pct(totVenta > 0 ? (totUtil / totVenta) * 100 : 0);
        }

        if (foot) foot.style.display = "";
        if (wrap) wrap.style.display = "";
    }

    // =========================================================
    // GRÁFICAS
    // =========================================================
    function pintarGraficas(d) {
        dibujarTop(document.getElementById("pvChartTop"), d.top_cantidad || []);
        dibujarDona(document.getElementById("pvChartCat"), d.por_categoria || []);
    }

    function dibujarTop(canvas, datos) {
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) {
            ctx.fillStyle = "#a79ea2"; ctx.font = "12px system-ui";
            ctx.textAlign = "center"; ctx.textBaseline = "middle";
            ctx.fillText("Sin datos", W / 2, H / 2);
            return;
        }

        var pad = { top: 20, right: 12, bottom: 52, left: 46 };
        var areaW = W - pad.left - pad.right;
        var areaH = H - pad.top - pad.bottom;

        var max = Math.max.apply(null, datos.map(function (d) { return d.cantidad; }));
        if (max <= 0) max = 1;

        ctx.strokeStyle = "#ececef"; ctx.lineWidth = 1;
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
            ctx.fillText(v.toFixed(0), pad.left - 5, y);
        }

        var gap = areaW / datos.length;
        var barW = Math.min(40, gap * 0.65);

        datos.forEach(function (d, i) {
            var alto = (d.cantidad / max) * areaH;
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

            // Etiqueta del nombre girada
            ctx.save();
            ctx.translate(x + barW / 2, H - pad.bottom + 6);
            ctx.rotate(-Math.PI / 4);
            ctx.fillStyle = "#6b6165";
            ctx.font = "9px system-ui";
            ctx.textAlign = "right";
            ctx.textBaseline = "middle";
            var nombre = String(d.nombre || "").substring(0, 14);
            ctx.fillText(nombre, 0, 0);
            ctx.restore();
        });
    }

    function dibujarDona(canvas, datos) {
        if (!canvas) return;
        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) {
            ctx.fillStyle = "#a79ea2"; ctx.font = "12px system-ui";
            ctx.textAlign = "center"; ctx.textBaseline = "middle";
            ctx.fillText("Sin datos", W / 2, H / 2);
            return;
        }

        var colores = ["#d9232d", "#1e40af", "#14803d", "#92400e", "#7a2ea3", "#0f8a8a", "#b91c1c", "#6b7280"];
        var total = datos.reduce(function (a, x) { return a + x.venta; }, 0);
        var cx = W * 0.34, cy = H / 2, radio = Math.min(H, W * 0.55) / 2.4;
        var inicio = -Math.PI / 2;

        datos.forEach(function (d, i) {
            var frac = total > 0 ? d.venta / total : 0;
            var fin = inicio + frac * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radio, inicio, fin);
            ctx.closePath();
            ctx.fillStyle = colores[i % colores.length];
            ctx.fill();
            inicio = fin;
        });

        ctx.beginPath();
        ctx.arc(cx, cy, radio * 0.6, 0, Math.PI * 2);
        ctx.fillStyle = "#fff";
        ctx.fill();

        ctx.fillStyle = "#1c1416";
        ctx.font = "bold 10px system-ui";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("Venta", cx, cy - 6);
        ctx.font = "bold 10px system-ui";
        ctx.fillStyle = "#d9232d";
        ctx.fillText("Bs " + Math.round(total), cx, cy + 8);

        // Leyenda (máx 6 entradas)
        var lx = W * 0.64, ly = H / 2 - (Math.min(6, datos.length) * 22) / 2;
        datos.slice(0, 6).forEach(function (d, i) {
            var y = ly + i * 22;
            ctx.fillStyle = colores[i % colores.length];
            ctx.fillRect(lx, y, 10, 10);
            ctx.fillStyle = "#1c1416";
            ctx.font = "bold 10px system-ui";
            ctx.textAlign = "left";
            ctx.textBaseline = "middle";
            ctx.fillText(String(d.categoria || "").substring(0, 14), lx + 16, y + 5);

            ctx.fillStyle = "#a79ea2";
            ctx.font = "9px system-ui";
            ctx.fillText(bs(d.venta), lx + 16, y + 16);
        });
    }

    // =========================================================
    // PDF
    // =========================================================
    function exportarPDF() {
        if (!window.ProductosVendidosAPI) return;
        var url = window.ProductosVendidosAPI.pdfUrl(filtros());
        var w = window.open(url, "_blank");
        if (!w) aviso("Ventana bloqueada", "Permite las ventanas emergentes", "warning", 5000);
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        var hoy = new Date();
        var desde = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);
        var iso = function (d) { return d.toISOString().slice(0, 10); };
        if ($s("#pvDesde")) $s("#pvDesde").value = iso(desde);
        if ($s("#pvHasta")) $s("#pvHasta").value = iso(hoy);

        cargar();

        var btnFiltro = $s("#pvFiltrar");
        if (btnFiltro) btnFiltro.addEventListener("click", cargar);

        var btnLimpiar = $s("#pvLimpiar");
        if (btnLimpiar) btnLimpiar.addEventListener("click", function () {
            ["pvSucursal", "pvCategoria", "pvDesde", "pvHasta"].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            cargar();
        });

        var btnPdf = $s("#pvPdf");
        if (btnPdf) btnPdf.addEventListener("click", exportarPDF);
    });
})();