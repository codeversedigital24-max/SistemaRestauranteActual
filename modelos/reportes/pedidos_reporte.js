(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };

    var estado = {
        datos: null,
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

    function chipTipo(p) {
        var clase = p.grupo_tipo === "mesa" ? "mesa" : "online";
        var icono = p.tipo_pedido === "mesa" ? "fa-chair"
            : (p.tipo_pedido === "delivery" ? "fa-truck" : "fa-bag-shopping");
        return '<span class="rep-chip ' + clase + '"><i class="fa-solid ' + icono + '"></i> ' +
            escapar(p.tipo_label) + '</span>';
    }

    function chipEstado(e) {
        return '<span class="rep-chip ' + escapar(e.estado) + '">' + escapar(e.estado_label) + '</span>';
    }

    // =========================================================
    // FILTROS / CARGA
    // =========================================================
    function filtros() {
        return {
            id_sucursal: ($s("#rfSucursal") && $s("#rfSucursal").value) || "",
            tipo:        ($s("#rfTipo")     && $s("#rfTipo").value)     || "",
            estado:      ($s("#rfEstado")   && $s("#rfEstado").value)   || "",
            desde:       ($s("#rfDesde")    && $s("#rfDesde").value)    || "",
            hasta:       ($s("#rfHasta")    && $s("#rfHasta").value)    || "",
        };
    }

    function cargar() {
        if (!window.PedidosReporteAPI) {
            aviso("Error", "No se cargó el módulo de red", "error");
            return;
        }

        mostrarCarga(true);
        window.PedidosReporteAPI.cargar(filtros()).then(function (rpta) {
            mostrarCarga(false);
            if (!rpta.success) {
                aviso("No se pudo cargar", rpta.message || "Error", "error", 5000);
                return;
            }

            estado.datos = rpta.data || {};
            llenarSucursales(estado.datos.sucursales || []);
            pintarResumen(estado.datos.resumen || {});
            pintarTabla(estado.datos.pedidos || []);
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

    function pintarResumen(r) {
        var set = function (sel, v) { var el = $s(sel); if (el) el.textContent = v; };
        set("#rcPedidos", String(r.pedidos || 0));
        set("#rcVigentes", String(r.vigentes || 0) + " vigentes");
        set("#rcMesas", String(r.mesas || 0));
        set("#rcOnline", String(r.online || 0));
        set("#rcCancelados", String(r.cancelados || 0));
        set("#rcTotal", bs(r.total));
        set("#rcPromedio", bs(r.promedio));
    }

    function pintarTabla(pedidos) {
        var body = $s("#repTablaBody");
        var foot = $s("#repTablaFoot");
        var wrap = $s("#repTablaScroll");
        if (!body) return;

        if (!pedidos.length) {
            body.innerHTML = '<tr><td colspan="10" class="rep-vacio">Sin pedidos en el rango seleccionado.</td></tr>';
            if (foot) foot.style.display = "none";
            if (wrap) wrap.style.display = "";
            return;
        }

        var total = 0;

        body.innerHTML = pedidos.map(function (p) {
            if (p.estado !== "cancelado") total += Number(p.total);

            return (
                "<tr>" +
                "<td><span class='cod'>#" + Number(p.id) + "</span></td>" +
                "<td>" + escapar(p.fecha) + "</td>" +
                "<td><b>" + escapar(p.codigo) + "</b></td>" +
                "<td>" + chipTipo(p) + "</td>" +
                "<td>" + escapar(p.cliente) +
                    (p.mesa ? "<span style='display:block;font-size:10.5px;color:#a79ea2'>Mesa " + escapar(p.mesa) + "</span>" : "") +
                "</td>" +
                "<td>" + escapar(p.sucursal) +
                    (p.ciudad ? "<span style='display:block;font-size:10.5px;color:#a79ea2'>" + escapar(p.ciudad) + "</span>" : "") +
                "</td>" +
                "<td class='num'>" + Number(p.items || 0) + "</td>" +
                "<td class='num'><b>" + bs(p.total) + "</b></td>" +
                "<td>" + chipEstado(p) + "</td>" +
                "<td>" + escapar(p.usuario || "—") + "</td>" +
                "</tr>"
            );
        }).join("");

        if (wrap) wrap.style.display = "";
        var footTotal = $s("#footTotal");
        if (footTotal) footTotal.textContent = bs(total);
        if (foot) foot.style.display = "";
    }

    // =========================================================
    // PDF
    // =========================================================
    function exportarPDF() {
        if (!window.PedidosReporteAPI) return;
        var url = window.PedidosReporteAPI.pdfUrl(filtros());
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
            ["rfSucursal", "rfTipo", "rfEstado", "rfDesde", "rfHasta"].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = "";
            });
            cargar();
        });

        var btnPdf = $s("#btnPdf");
        if (btnPdf) btnPdf.addEventListener("click", exportarPDF);
    });
})();
