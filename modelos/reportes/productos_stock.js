(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $todos = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    var estado = {
        datos: null,
        filtroEstado: "",   // "", "sin", "bajo", "optimo", "sin_control"
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
    function cant(n) {
        n = Number(n) || 0;
        return n.toLocaleString("es-BO", { minimumFractionDigits: 0, maximumFractionDigits: 3 });
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
        var c = $s("#psCarga");
        if (c) c.classList.toggle("visible", !!a);
    }

    function chipEstado(estado) {
        var mapa = {
            sin:         ["sin",         "Sin stock",    "fa-circle-xmark"],
            bajo:        ["bajo",        "Bajo",         "fa-triangle-exclamation"],
            optimo:      ["optimo",      "Óptimo",       "fa-circle-check"],
            sin_control: ["sin_control", "Sin control",  "fa-infinity"],
        };
        var info = mapa[estado] || mapa.sin_control;
        return '<span class="ps-chip ' + info[0] + '"><i class="fa-solid ' + info[2] + '"></i> ' + info[1] + '</span>';
    }

    // =========================================================
    // CARGA
    // =========================================================
    function filtros() {
        return {
            id_sucursal:  ($s("#psSucursal")  && $s("#psSucursal").value)  || "",
            id_categoria: ($s("#psCategoria") && $s("#psCategoria").value) || "",
            buscar:       ($s("#psBuscar")    && $s("#psBuscar").value)    || "",
            estado:       estado.filtroEstado,
        };
    }

    function cargar() {
        if (!window.ProductosStockAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }
        mostrarCarga(true);
        window.ProductosStockAPI.cargar(filtros()).then(function (rpta) {
            mostrarCarga(false);
            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.datos = rpta.data || {};
            llenarSucursales(estado.datos.sucursales || []);
            llenarCategorias(estado.datos.categorias || []);
            pintarResumen(estado.datos.resumen || {});
            pintarTabla(estado.datos.productos || []);
            marcarTarjetasActivas();
        });
    }

    function llenarSucursales(suc) {
        var sel = $s("#psSucursal");
        if (!sel) return;

        var esSuper = estado.datos && estado.datos.usuario && estado.datos.usuario.es_superadmin;
        if (!esSuper) {
            var wrap = $s("#psFiltroSucursalWrap");
            if (wrap) wrap.style.display = "none";
            return;
        }

        if (sel.dataset.cargado === "1") return;
        var opciones = suc.map(function (s) {
            return '<option value="' + Number(s.id_sucursal) + '"' +
                (Number(s.id_sucursal) === Number(estado.datos.id_sucursal) ? " selected" : "") + '>' +
                escapar(s.nombre_sucursal) + (s.ciudad ? " · " + escapar(s.ciudad) : "") +
                "</option>";
        });
        sel.innerHTML = opciones.join("");
        sel.dataset.cargado = "1";
    }

    function llenarCategorias(cats) {
        var sel = $s("#psCategoria");
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
        set("#psTotal", String(r.total || 0));
        set("#psSin", String(r.sin || 0));
        set("#psBajo", String(r.bajo || 0));
        set("#psOptimo", String(r.optimo || 0));
        set("#psSinControl", String(r.sin_control || 0));
        set("#psValor", bs(r.valor));
    }

    function marcarTarjetasActivas() {
        $todos(".ps-card[data-estado]").forEach(function (c) {
            var activo = c.getAttribute("data-estado") === estado.filtroEstado;
            c.classList.toggle("activa", activo);
        });
        $todos(".ps-tabs button").forEach(function (b) {
            b.classList.toggle("activa", b.getAttribute("data-estado") === estado.filtroEstado);
        });
    }

    function pintarTabla(productos) {
        var body = $s("#psBody");
        var foot = $s("#psFoot");
        var wrap = $s("#psTablaScroll");
        if (!body) return;

        if (!productos.length) {
            body.innerHTML = '<tr><td colspan="8" class="ps-vacio">Sin productos con estos filtros.</td></tr>';
            if (foot) foot.style.display = "none";
            if (wrap) wrap.style.display = "";
            return;
        }

        var totStock = 0, totFalta = 0, totValor = 0;

        body.innerHTML = productos.map(function (p) {
            totStock += Number(p.stock);
            totFalta += Number(p.deficit);
            totValor += Number(p.valor_stock);

            var foto = p.imagen_url
                ? '<img src="' + escapar(p.imagen_url) + '" alt="">'
                : '<i class="fas ' + escapar(p.categoria_icono || 'fa-box') + '"></i>';

            return (
                '<tr>' +
                '<td><div class="ps-prod">' +
                    '<div class="foto">' + foto + '</div>' +
                    '<div class="info">' +
                        '<b title="' + escapar(p.nombre) + '">' + escapar(p.nombre) + '</b>' +
                        '<span>' + escapar(p.codigo) + ' · ' + escapar(p.sucursal) + '</span>' +
                    '</div>' +
                '</div></td>' +
                '<td>' + (p.categoria ? escapar(p.categoria) : '<span style="color:#a79ea2">—</span>') + '</td>' +
                '<td class="num"><span class="ps-stock-val ' + escapar(p.estado_stock) + '">' + cant(p.stock) + '</span></td>' +
                '<td class="num">' + (p.controla ? cant(p.stock_minimo) : '—') + '</td>' +
                '<td class="num" style="color:#b91c1c">' + (p.deficit > 0 ? cant(p.deficit) : '—') + '</td>' +
                '<td class="num">' + bs(p.precio) + '</td>' +
                '<td class="num"><b>' + bs(p.valor_stock) + '</b></td>' +
                '<td>' + chipEstado(p.estado_stock) + '</td>' +
                '</tr>'
            );
        }).join("");

        $s("#psFootStock").textContent = cant(totStock);
        $s("#psFootFalta").textContent = cant(totFalta);
        $s("#psFootValor").textContent = bs(totValor);

        if (foot) foot.style.display = "";
        if (wrap) wrap.style.display = "";
    }

    // =========================================================
    // FILTROS RÁPIDOS (tarjetas)
    // =========================================================
    function engancharTarjetas() {
        $todos(".ps-card[data-estado]").forEach(function (c) {
            c.addEventListener("click", function () {
                var nuevo = c.getAttribute("data-estado") || "";
                estado.filtroEstado = (nuevo === estado.filtroEstado && nuevo !== "") ? "" : nuevo;
                cargar();
            });
        });
    }

    // =========================================================
    // PDF
    // =========================================================
    function exportarPDF(completo) {
        if (!window.ProductosStockAPI) return;

        var f = filtros();
        if (completo) f.estado = "";

        var url = window.ProductosStockAPI.pdfUrl(f);
        var w = window.open(url, "_blank");
        if (!w) aviso("Ventana bloqueada", "Permite las ventanas emergentes para ver el PDF", "warning", 5000);
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();
        engancharTarjetas();

        var btnFiltrar = $s("#psFiltrar");
        if (btnFiltrar) btnFiltrar.addEventListener("click", cargar);

        var btnLimpiar = $s("#psLimpiar");
        if (btnLimpiar) btnLimpiar.addEventListener("click", function () {
            ["psCategoria", "psBuscar"].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            estado.filtroEstado = "";
            cargar();
        });

        var selSuc = $s("#psSucursal");
        if (selSuc) selSuc.addEventListener("change", cargar);

        var selCat = $s("#psCategoria");
        if (selCat) selCat.addEventListener("change", cargar);

        var buscar = $s("#psBuscar");
        if (buscar) {
            var timer = null;
            buscar.addEventListener("input", function () {
                clearTimeout(timer);
                timer = setTimeout(cargar, 350);
            });
        }

        var btnPdf = $s("#psPdf");
        if (btnPdf) btnPdf.addEventListener("click", function () { exportarPDF(false); });

        var btnPdfTodo = $s("#psPdfTodo");
        if (btnPdfTodo) btnPdfTodo.addEventListener("click", function () { exportarPDF(true); });
    });
})();