(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var RUTA = "../modelos/dashboard/obtener_dashboard.php";

    var estado = {
        cargando: false,
        sucursalesCargadas: false,
        datos: null
    };

    function bs(n) {
        n = Number(n) || 0;
        return "Bs " + n.toLocaleString("es-BO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapar(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function csrf() { return window.DASH_CSRF || ""; }

    function mostrar(id, visible) {
        var el = document.getElementById(id);
        if (el) el.style.display = visible ? "" : "none";
    }

    function texto(id, valor) {
        var el = document.getElementById(id);
        if (el) el.textContent = valor;
    }

    function cargar() {
        if (estado.cargando) return;
        estado.cargando = true;

        var sel = $s("#dashSucursal");
        var idSucursal = sel && sel.value ? sel.value : "";
        var url = RUTA + (idSucursal ? "?id_sucursal=" + encodeURIComponent(idSucursal) : "");

        fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: { Accept: "application/json", "X-CSRF-Token": csrf() }
        })
            .then(function (r) { return r.json(); })
            .then(function (rpta) {
                if (!rpta || !rpta.success) {
                    console.error("Dashboard: ", rpta && rpta.message);
                    return;
                }

                estado.datos = rpta.data;
                render(rpta.data);
            })
            .catch(function (e) { console.error("Dashboard fetch:", e); })
            .finally(function () { estado.cargando = false; });
    }

    function perfilTexto(perfil) {
        if (perfil === "superadmin") return "SuperAdministrador";
        if (perfil === "sucursal") return "Administración de sucursal";
        if (perfil === "cajero") return "Cajero";
        return "Operación";
    }

    function render(d) {
        $s("#dashLoading").style.display = "none";
        $s("#dashContenido").style.display = "";

        var perfil = d.perfil || "operativo";
        var permite = d.permite || {};
        var filtro = d.filtro || {};
        var usuario = d.usuario || {};
        var sucursalTxt = "Todas las sucursales";

        if (!filtro.todas) {
            sucursalTxt = usuario.sucursal || "Mi sucursal";

            (d.sucursales || []).forEach(function (s) {
                if (String(s.id_sucursal) === String(filtro.id_sucursal)) {
                    sucursalTxt = s.nombre_sucursal;
                }
            });
        }

        texto("dashNombre", usuario.nombre || "Usuario");
        $s("#dashBadge").innerHTML =
            '<i class="fas fa-user-shield"></i> <span>' +
            escapar(usuario.rol || perfilTexto(perfil)) + " · " + escapar(sucursalTxt) + "</span>";

        prepararFiltroSucursal(d);
        pintarAviso(perfil);
        pintarKpis(d);
        prepararTarjetas(d);

        if (d.por_dia) pintarChartVentas(d.por_dia);
        if (d.por_hora) pintarChartHora(d.por_hora);
        if (d.por_metodo) pintarChartMetodos(d.por_metodo);
        if (d.recientes) pintarRecientes(d.recientes, perfil);
        if (d.top_productos) pintarTopProductos(d.top_productos, perfil);
        if (d.utilidades) pintarUtilidades(d.utilidades);
        if (d.top_vendedores) pintarVendedores(d.top_vendedores);
        if (d.stock_bajo) pintarStock(d.stock_bajo);
        if (d.compras) pintarCompras(d.compras);
        if (d.arqueos_abiertos || d.mi_caja !== undefined) pintarCajas(d);
        if (d.ventas_por_sucursal) pintarVentasSucursal(d.ventas_por_sucursal);

        pintarPedidosActivos(d.pedidos_activos || []);

        var linkVentas = $s("#dashLinkVentas");
        if (linkVentas) linkVentas.style.display = permite.historial ? "" : "none";

        var linkPedidos = $s("#dashLinkPedidos");
        if (linkPedidos) linkPedidos.style.display = permite.pedidos ? "" : "none";

        var linkCajas = $s("#dashLinkCajas");
        if (linkCajas) linkCajas.style.display = permite.arqueo ? "" : "none";

        var linkProductos = $s("#dashLinkProductos");
        if (linkProductos) linkProductos.style.display = permite.productos ? "" : "none";

        var grid = $s("#dashGrid");
        if (grid) grid.classList.toggle("solo-main", perfil === "operativo");
    }

    function prepararFiltroSucursal(d) {
        var wrap = $s("#dashFiltroSucursalWrap");
        var sel = $s("#dashSucursal");
        if (!wrap || !sel) return;

        wrap.style.display = d.perfil === "superadmin" ? "" : "none";

        if (d.perfil === "superadmin" && !estado.sucursalesCargadas) {
            (d.sucursales || []).forEach(function (s) {
                var op = document.createElement("option");
                op.value = String(s.id_sucursal);
                op.textContent = s.nombre_sucursal + (s.ciudad ? " · " + s.ciudad : "");
                sel.appendChild(op);
            });
            estado.sucursalesCargadas = true;
        }

        if (d.filtro && d.filtro.id_sucursal) {
            sel.value = String(d.filtro.id_sucursal);
        } else if (d.perfil === "superadmin") {
            sel.value = "";
        }
    }

    function pintarAviso(perfil) {
        var aviso = $s("#dashAvisoRol");
        var txt = $s("#dashAvisoTexto");
        if (!aviso || !txt) return;

        if (perfil === "superadmin") {
            aviso.style.display = "none";
            return;
        }

        aviso.style.display = "flex";

        if (perfil === "sucursal") {
            txt.textContent =
                "Estás viendo la operación y las ventas de tu sucursal. " +
                "Utilidades, ganancias y compras están reservadas al SuperAdministrador.";
        } else if (perfil === "cajero") {
            txt.textContent =
                "Estás viendo únicamente tus ventas de hoy. " +
                "No se muestran utilidades, ganancias ni información de otras cajas.";
        } else {
            txt.textContent =
                "Estás viendo la información operativa de tu sucursal. " +
                "No se muestran ventas, utilidades ni ganancias.";
        }
    }

    function prepararTarjetas(d) {
        var perfil = d.perfil || "operativo";
        var permite = d.permite || {};

        var verVentas = permite.ventas && !!d.ventas;

        mostrar("dashCardVentas7d", verVentas && perfil !== "cajero");
        mostrar("dashCardVentasHora", verVentas && perfil === "cajero");
        mostrar("dashCardRecientes", verVentas);
        mostrar("dashCardMetodos", verVentas && !!d.por_metodo);
        mostrar("dashCardTopProductos", verVentas && !!d.top_productos);
        mostrar("dashCardUtilidades", !!d.utilidades);
        mostrar("dashCardVendedores", !!d.top_vendedores);
        mostrar("dashCardStock", permite.stock && !!d.stock_bajo);
        mostrar("dashCardCompras", !!d.compras);
        mostrar("dashCardVentasSucursal", !!d.ventas_por_sucursal);
        mostrar("dashCardCajas", !!(d.arqueos_abiertos || d.mi_caja !== undefined));
        mostrar("dashCardPedidosActivos", (d.pedidos_activos || []).length > 0 || perfil === "operativo");

        if (perfil === "cajero") {
            texto("dashTituloVentas7d", "Mis ventas · últimos 7 días");
            texto("dashTituloRecientes", "Mis ventas de hoy");
            texto("dashTituloMetodos", "Mis métodos de pago · hoy");
            texto("dashTituloTopProductos", "Mis productos más vendidos · hoy");
        } else {
            texto("dashTituloVentas7d", d.filtro && d.filtro.todas ? "Ventas · últimos 7 días (todas)" : "Ventas · últimos 7 días");
            texto("dashTituloRecientes", "Ventas recientes");
            texto("dashTituloMetodos", "Métodos de pago · mes");
            texto("dashTituloTopProductos", "Top productos · mes");
        }

        if (perfil === "operativo") {
            mostrar("dashCardMetodos", false);
            mostrar("dashCardTopProductos", false);
            mostrar("dashCardRecientes", false);
            mostrar("dashCardVentas7d", false);
            mostrar("dashCardVentasHora", false);
        }
    }

    function kpi(et, val, sub, color, icono) {
        return (
            '<div class="dash-kpi ' + color + '">' +
                '<span class="kpi-ico"><i class="fas ' + icono + '"></i></span>' +
                '<span class="kpi-et">' + et + '</span>' +
                '<span class="kpi-val">' + val + '</span>' +
                '<span class="kpi-sub">' + sub + '</span>' +
            '</div>'
        );
    }

    function pintarKpis(d) {
        var perfil = d.perfil || "operativo";
        var v = d.ventas || {};
        var m = d.mesas || {};
        var p = d.pedidos || {};
        var html = "";

        if (perfil === "cajero") {
            html += kpi("Mis ventas hoy", bs(v.hoy_total),
                v.hoy_cant + " tickets registrados",
                "azul", "fa-sun");
            html += kpi("Mis tickets hoy", String(v.hoy_cant || 0), "ventas registradas", "verde", "fa-receipt");
            html += kpi("Ticket promedio", bs(v.ticket_prom), "de mis ventas de hoy", "ambar", "fa-calculator");

            if (d.mi_caja) {
                html += kpi("Mi caja", escapar(d.mi_caja.caja), "abierta · " + escapar(d.mi_caja.apertura), "morado", "fa-cash-register");
            }

            html += kpi("Mesas", (m.ocupadas || 0) + " ocupadas",
                m.libres + " libres · " + m.pendientes + " por cobrar", "gris", "fa-chair");
            html += kpi("Pedidos activos", String(p.pendientes || 0),
                p.pendientes + " en cocina · " + p.despachados + " despachados", "rojo", "fa-fire");

            $s("#dashKpis").innerHTML = html;
            return;
        }

        if (perfil === "operativo") {
            html += kpi("En cocina", String(p.en_cocina || 0), "pendientes o preparando", "rojo", "fa-fire-burner");
            html += kpi("Pedidos activos", String((p.en_cocina || 0) + (p.despachados || 0) + (p.por_cobrar || 0)),
                p.despachados + " listos · " + p.por_cobrar + " por cobrar", "azul", "fa-clipboard-list");
            html += kpi("Delivery activos", String(p.delivery_activos || 0), "pedidos en curso", "morado", "fa-motorcycle");
            html += kpi("Mesas", (m.ocupadas || 0) + " ocupadas",
                m.libres + " libres · " + m.pendientes + " por cobrar", "gris", "fa-chair");

            $s("#dashKpis").innerHTML = html;
            return;
        }

        // SuperAdmin y Administrador/Supervisor de sucursal.
        var delta2 = Number(v.delta_hoy_pct || 0);
        var deltaClase2 = delta2 >= 0 ? "up" : "down";
        var deltaTxt2 = (delta2 >= 0 ? "↑ " : "↓ ") + Math.abs(delta2).toFixed(1) + "% vs ayer";

        html += kpi("Hoy", bs(v.hoy_total),
            v.hoy_cant + " ventas · <span class='" + deltaClase2 + "'>" + deltaTxt2 + "</span>",
            "azul", "fa-sun");
        html += kpi("Semana", bs(v.semana_total), v.semana_cant + " ventas", "verde", "fa-calendar-week");
        html += kpi("Mes", bs(v.mes_total), v.mes_cant + " ventas", "morado", "fa-calendar-alt");
        html += kpi("Ticket promedio", bs(v.ticket_prom), "por venta", "ambar", "fa-receipt");
        html += kpi("Mesas", (m.ocupadas || 0) + " ocupadas",
            m.libres + " libres · " + m.pendientes + " por cobrar", "gris", "fa-chair");
        html += kpi("Pedidos activos", String((p.pendientes || 0) + (p.despachados || 0)),
            p.pendientes + " preparando · " + p.despachados + " despachados", "rojo", "fa-fire");

        $s("#dashKpis").innerHTML = html;
    }

    function pintarChartVentas(dias) {
        var canvas = document.getElementById("chartVentas7d");
        if (!canvas) return;

        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!dias.length) {
            ctx.fillStyle = "#a79ea2";
            ctx.font = "12px system-ui";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("Sin ventas en los últimos 7 días", W / 2, H / 2);
            return;
        }

        var pad = { top: 20, right: 16, bottom: 34, left: 52 };
        var areaW = W - pad.left - pad.right;
        var areaH = H - pad.top - pad.bottom;
        var max = Math.max.apply(null, dias.map(function (d) { return d.total; }));
        if (max <= 0) max = 1;

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
            ctx.fillText(v >= 1000 ? (v / 1000).toFixed(1) + "k" : v.toFixed(0), pad.left - 6, y);
        }

        var gap = areaW / dias.length;
        var barW = Math.min(46, gap * 0.6);

        dias.forEach(function (d, i) {
            var alto = (d.total / max) * areaH;
            var x = pad.left + gap * i + (gap - barW) / 2;
            var y = pad.top + areaH - alto;

            var grad = ctx.createLinearGradient(0, y, 0, pad.top + areaH);
            grad.addColorStop(0, "#d9232d");
            grad.addColorStop(1, "#f59095");

            ctx.fillStyle = grad;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, y, barW, alto, [5, 5, 0, 0]);
            else ctx.rect(x, y, barW, alto);
            ctx.fill();

            var partes = (d.fecha || "").split("-");
            var et = partes.length === 3 ? partes[2] + "/" + partes[1] : d.fecha;
            ctx.fillStyle = "#a79ea2";
            ctx.font = "9.5px system-ui";
            ctx.textAlign = "center";
            ctx.textBaseline = "top";
            ctx.fillText(et, x + barW / 2, H - pad.bottom + 6);

            ctx.fillStyle = "#1c1416";
            ctx.font = "bold 9.5px system-ui";
            ctx.textBaseline = "bottom";
            var etv = d.total >= 1000 ? (d.total / 1000).toFixed(1) + "k" : d.total.toFixed(0);
            ctx.fillText(etv, x + barW / 2, y - 3);
        });
    }

    function pintarChartHora(datos) {
        var canvas = document.getElementById("chartVentasHora");
        if (!canvas) return;

        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        var porHora = {};
        (datos || []).forEach(function (d) { porHora[d.hora] = d; });

        var horas = [];
        for (var h = 6; h <= 23; h++) horas.push(h);

        var max = 1;
        horas.forEach(function (h) {
            if (porHora[h] && porHora[h].total > max) max = porHora[h].total;
        });

        if (!datos || !datos.length) {
            ctx.fillStyle = "#a79ea2";
            ctx.font = "12px system-ui";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("Aún no tienes ventas hoy", W / 2, H / 2);
            return;
        }

        var pad = { top: 20, right: 12, bottom: 34, left: 46 };
        var areaW = W - pad.left - pad.right;
        var areaH = H - pad.top - pad.bottom;
        var gap = areaW / horas.length;
        var barW = Math.max(8, Math.min(28, gap * 0.58));

        ctx.strokeStyle = "#ececef";
        ctx.lineWidth = 1;
        for (var i = 0; i <= 3; i++) {
            var y = pad.top + (areaH / 3) * i;
            ctx.beginPath();
            ctx.moveTo(pad.left, y);
            ctx.lineTo(W - pad.right, y);
            ctx.stroke();

            ctx.fillStyle = "#a79ea2";
            ctx.font = "9px system-ui";
            ctx.textAlign = "right";
            ctx.textBaseline = "middle";
            var v = max * (1 - i / 3);
            ctx.fillText(v >= 1000 ? (v / 1000).toFixed(1) + "k" : v.toFixed(0), pad.left - 6, y);
        }

        horas.forEach(function (h, idx) {
            var d = porHora[h] || { total: 0, ventas: 0 };
            var alto = (d.total / max) * areaH;
            var x = pad.left + gap * idx + (gap - barW) / 2;
            var y = pad.top + areaH - alto;

            var grad = ctx.createLinearGradient(0, y, 0, pad.top + areaH);
            grad.addColorStop(0, "#d9232d");
            grad.addColorStop(1, "#f59095");

            ctx.fillStyle = grad;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, y, barW, alto, [4, 4, 0, 0]);
            else ctx.rect(x, y, barW, alto);
            ctx.fill();

            if (h % 2 === 0) {
                ctx.fillStyle = "#a79ea2";
                ctx.font = "9px system-ui";
                ctx.textAlign = "center";
                ctx.textBaseline = "top";
                ctx.fillText(String(h).padStart(2, "0") + ":00", x + barW / 2, H - pad.bottom + 6);
            }
        });
    }

    function pintarChartMetodos(datos) {
        var canvas = document.getElementById("chartMetodos");
        if (!canvas) return;

        var ctx = canvas.getContext("2d");
        var W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);

        if (!datos.length) {
            ctx.fillStyle = "#a79ea2";
            ctx.font = "12px system-ui";
            ctx.textAlign = "center";
            ctx.textBaseline = "middle";
            ctx.fillText("Sin datos del período", W / 2, H / 2);
            return;
        }

        var colores = { efectivo: "#14803d", qr: "#1e40af", tarjeta: "#7a2ea3", mixto: "#d97706", otro: "#6b7280" };
        var total = datos.reduce(function (a, x) { return a + x.total; }, 0);
        var cx = W * 0.32, cy = H / 2, radio = Math.min(H, W * 0.55) / 2.4;
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

        ctx.beginPath();
        ctx.arc(cx, cy, radio * 0.62, 0, Math.PI * 2);
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

        var lx = W * 0.62, ly = H / 2 - (datos.length * 15) / 2;
        datos.forEach(function (d, i) {
            var y = ly + i * 22;
            ctx.fillStyle = colores[d.metodo] || "#6b7280";
            ctx.fillRect(lx, y, 10, 10);

            ctx.fillStyle = "#1c1416";
            ctx.font = "bold 10.5px system-ui";
            ctx.textAlign = "left";
            ctx.textBaseline = "middle";
            ctx.fillText((d.metodo || "").toUpperCase(), lx + 16, y + 3);

            ctx.fillStyle = "#a79ea2";
            ctx.font = "9.5px system-ui";
            ctx.fillText(d.ventas + " · " + bs(d.total), lx + 16, y + 15);
        });
    }

    function pintarRecientes(ventas, perfil) {
        var cont = $s("#dashRecientes");
        if (!cont) return;

        if (!ventas.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-receipt"></i>' +
                (perfil === "cajero" ? "Aún no registraste ventas hoy" : "Sin ventas registradas") + "</div>";
            return;
        }

        cont.innerHTML = ventas.map(function (v) {
            var chipClase = v.estado === "anulada" ? "anulada" : v.metodo;
            var chipTxt = v.estado === "anulada" ? "ANULADA" : v.metodo;

            return (
                '<div class="dash-item">' +
                    '<div class="dt">' +
                        '<b>' + escapar(v.codigo) + '</b>' +
                        '<span>' + escapar(v.fecha) + ' · ' + escapar(v.usuario) + ' · ' + escapar(v.sucursal) + '</span>' +
                    '</div>' +
                    '<span class="badge-chip ' + chipClase + '">' + chipTxt + '</span>' +
                    '<span class="val">' + bs(v.total) + '</span>' +
                '</div>'
            );
        }).join("");
    }

    function pintarTopProductos(productos, perfil) {
        var cont = $s("#dashTopProductos");
        if (!cont) return;

        if (!productos.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-fire"></i>' +
                (perfil === "cajero" ? "Aún no vendiste productos hoy" : "Sin ventas este mes") + "</div>";
            return;
        }

        cont.innerHTML = productos.map(function (p, i) {
            return (
                '<div class="dash-vend-item">' +
                    '<span class="num">' + (i + 1) + '</span>' +
                    '<div class="nm">' +
                        '<b>' + escapar(p.nombre) + '</b>' +
                        '<span>' + escapar(p.codigo) + ' · ' + p.cantidad + ' vendidos</span>' +
                    '</div>' +
                    '<span class="val">' + bs(p.venta) + '</span>' +
                '</div>'
            );
        }).join("");
    }

    function pintarUtilidades(u) {
        mostrar("dashCardUtilidades", true);
        texto("uHoy", bs(u.hoy));
        texto("uSemana", bs(u.semana));
        texto("uMes", bs(u.mes));
        texto("uMargen", (u.margen_mes || 0).toFixed(1) + " %");
    }

    function pintarVendedores(vendedores) {
        var cont = $s("#dashVendedores");
        if (!cont) return;

        mostrar("dashCardVendedores", true);

        if (!vendedores.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-ranking-star"></i>Sin ventas este mes</div>';
            return;
        }

        cont.innerHTML = vendedores.map(function (v, i) {
            return (
                '<div class="dash-vend-item">' +
                    '<span class="num">' + (i + 1) + '</span>' +
                    '<div class="nm">' +
                        '<b>' + escapar(v.nombre) + '</b>' +
                        '<span>' + v.ventas + ' ventas</span>' +
                    '</div>' +
                    '<span class="val">' + bs(v.total) + '</span>' +
                '</div>'
            );
        }).join("");
    }

    function pintarStock(stock) {
        var cont = $s("#dashStockBajo");
        if (!cont) return;

        mostrar("dashCardStock", true);

        if (!stock.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-check-circle"></i>Sin productos en alerta</div>';
            return;
        }

        cont.innerHTML = stock.map(function (s) {
            return (
                '<div class="dash-stock-item">' +
                    '<div class="st">' +
                        '<b>' + escapar(s.nombre) + '</b>' +
                        '<span>' + escapar(s.sucursal) + ' · mín. ' + s.minimo + '</span>' +
                    '</div>' +
                    '<span class="stk">' + s.stock + '</span>' +
                '</div>'
            );
        }).join("");
    }

    function pintarCompras(c) {
        mostrar("dashCardCompras", true);
        texto("cTotal", bs(c.mes));
        texto("cCant", (c.cant || 0) + " compras");
        texto("cPorPagar", bs(c.por_pagar));
    }

    function pintarCajas(d) {
        var cont = $s("#dashCajas");
        if (!cont) return;

        mostrar("dashCardCajas", true);

        if (d.mi_caja !== undefined) {
            texto("dashTituloCajas", "Mi caja");
            var c = d.mi_caja;

            if (!c) {
                cont.innerHTML =
                    '<div class="dash-empty"><i class="fas fa-cash-register"></i>' +
                    "No tienes una caja abierta.<br><span style='font-size:11px'>Abre tu turno desde Arqueo de caja.</span></div>";
                return;
            }

            cont.innerHTML =
                '<div class="dash-caja-item">' +
                    '<div class="dc-info">' +
                        '<b>' + escapar(c.caja) + '</b>' +
                        '<span>' + escapar(c.sucursal) + ' · ' + escapar(c.apertura) + '</span>' +
                    '</div>' +
                    '<div class="dc-val">' +
                        '<b>' + bs(c.ventas) + '</b>' +
                        '<span>ventas</span>' +
                    '</div>' +
                '</div>' +
                '<div class="dash-caja-item">' +
                    '<div class="dc-info"><span>Gastos</span></div>' +
                    '<div class="dc-val"><b>' + bs(c.gastos) + '</b></div>' +
                '</div>' +
                '<div class="dash-caja-item">' +
                    '<div class="dc-info"><span>Esperado en caja</span></div>' +
                    '<div class="dc-val"><b>' + bs(c.esperado) + '</b></div>' +
                '</div>';
            return;
        }

        texto("dashTituloCajas", "Cajas abiertas");
        var cajas = d.arqueos_abiertos || [];

        if (!cajas.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-lock"></i>No hay cajas abiertas</div>';
            return;
        }

        cont.innerHTML = cajas.map(function (c) {
            return (
                '<div class="dash-caja-item">' +
                    '<div class="dc-info">' +
                        '<b>' + escapar(c.caja) + '</b>' +
                        '<span>' + escapar(c.usuario) + ' · ' + escapar(c.sucursal) + '</span>' +
                    '</div>' +
                    '<div class="dc-val">' +
                        '<b>' + bs(c.ventas) + '</b>' +
                        '<span>' + escapar(c.apertura) + '</span>' +
                    '</div>' +
                '</div>'
            );
        }).join("");
    }

    function pintarVentasSucursal(rows) {
        var cont = $s("#dashVentasSucursal");
        if (!cont) return;

        mostrar("dashCardVentasSucursal", true);

        if (!rows.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-store"></i>Sin sucursales activas</div>';
            return;
        }

        var max = 1;
        rows.forEach(function (r) {
            if (r.total > max) max = r.total;
        });

        cont.innerHTML = rows.map(function (r) {
            var pct = Math.max(2, Math.round((r.total / max) * 100));
            return (
                '<div class="dash-branch-item" data-sucursal="' + r.id + '" title="Ver solo ' + escapar(r.nombre) + '" style="cursor:pointer">' +
                    '<div class="db-nombre">' +
                        '<b>' + escapar(r.nombre) + '</b>' +
                        '<span>' + (r.ciudad ? escapar(r.ciudad) + ' · ' : '') + r.ventas + ' ventas</span>' +
                    '</div>' +
                    '<div class="db-barra"><i style="width:' + pct + '%"></i></div>' +
                    '<div class="db-val">' + bs(r.total) + '</div>' +
                '</div>'
            );
        }).join("");
    }

    function pintarPedidosActivos(pedidos) {
        var cont = $s("#dashPedidosActivos");
        if (!cont) return;

        mostrar("dashCardPedidosActivos", true);

        if (!pedidos.length) {
            cont.innerHTML = '<div class="dash-empty"><i class="fas fa-mug-hot"></i>Sin pedidos activos</div>';
            return;
        }

        var estadoTxt = {
            pendiente: "Pendiente",
            preparando: "Preparando",
            despachado: "Despachado",
            pendiente_pago: "Por cobrar"
        };

        cont.innerHTML = pedidos.map(function (p) {
            return (
                '<div class="dash-item">' +
                    '<div class="dt">' +
                        '<b>' + escapar(p.codigo) + ' · ' + escapar(p.referencia || p.tipo_et) + '</b>' +
                        '<span>' + escapar(p.tipo_et) + ' · ' + escapar(p.usuario) + ' · ' + escapar(p.fecha) + '</span>' +
                    '</div>' +
                    '<span class="badge-chip ' + escapar(p.estado) + '">' + escapar(estadoTxt[p.estado] || p.estado) + '</span>' +
                    '<span class="val">' + bs(p.total) + '</span>' +
                '</div>'
            );
        }).join("");
    }

    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        var refrescar = $s("#dashRefrescar");
        if (refrescar) refrescar.addEventListener("click", cargar);

        var sel = $s("#dashSucursal");
        if (sel) sel.addEventListener("change", cargar);

        var ventasSucursal = $s("#dashVentasSucursal");
        if (ventasSucursal) {
            ventasSucursal.addEventListener("click", function (ev) {
                var item = ev.target.closest("[data-sucursal]");
                if (!item) return;
                var id = item.getAttribute("data-sucursal");
                if (!sel || !id) return;
                sel.value = id;
                cargar();
            });
        }
    });
})();
