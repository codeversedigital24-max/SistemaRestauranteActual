(function () {
    "use strict";

    var $s = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $todos = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    var estado = {
        usuario: null,
        sucursales: [],
        idSucursal: 0,
        mesas: [],
        online: [],
        cocina: [],
        productos: [],
        categorias: [],
        filtro: "",
        filtroCategoria: "",
        carrito: [],
        mesaSeleccionada: null,
        pedidoActual: null,
        modoEdicion: false,
        metodoPago: "efectivo",
        pedidoCobrando: null,
        permisos: window.RM_ACCIONES || {},
    };

    // =========================================================
    // UTILIDADES
    // =========================================================
    function escapar(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }
    function numero(v) {
        var n = Number(v);
        return isFinite(n) ? n : 0;
    }
    function n2(v) { return Math.round((numero(v) + Number.EPSILON) * 100) / 100; }
    function bs(v) {
        var n = numero(v);
        return "Bs " + n.toLocaleString("es-BO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function bsNeg(v) { return "− " + bs(Math.abs(numero(v))); }
    function normalizar(texto) {
        var s = String(texto == null ? "" : texto).toLowerCase();
        if (s.normalize) s = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        return s.trim();
    }
    function plural(n, singular, plural_) {
        n = numero(n);
        return n === 1 ? singular : (plural_ || singular + "s");
    }

    function aviso(t, m, tipo, time) {
        if (!window.Swal) return console.warn(t + ": " + m);
        Swal.fire({
            toast: true, position: "top-end", icon: tipo || "info",
            title: t, text: m, timer: time || 3000, timerProgressBar: true,
            showConfirmButton: false,
        });
    }
    function confirmar(titulo, texto, tipo) {
        if (!window.Swal) return Promise.resolve(window.confirm(titulo + "\n" + texto));
        return Swal.fire({
            icon: tipo || "question", title: titulo, text: texto,
            showCancelButton: true, confirmButtonText: "Sí, continuar",
            cancelButtonText: "Cancelar", confirmButtonColor: "#d9232d",
            cancelButtonColor: "#9aa0a6", reverseButtons: true,
        }).then(function (r) { return r.isConfirmed; });
    }

    function mostrarCarga(a) {
        var c = $s("#pedCarga");
        if (c) c.classList.toggle("visible", !!a);
    }

    function abrirModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.add("abierto");
        m.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function cerrarModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.classList.remove("abierto");
        m.setAttribute("aria-hidden", "true");
        if (id === "modalPedido") cerrarCarritoMovil();
        if (!document.querySelector(".modal-overlay.abierto")) {
            document.body.style.overflow = "";
        }
    }

    // Carrito como bandeja inferior en móvil (igual que el POS).
    function abrirCarritoMovil() {
        var carrito = $s("#pedCarrito");
        var fondo = $s("#pedCarritoBackdrop");
        var contenido = $s(".ped-cat-contenido");
        if (carrito) carrito.classList.add("abierto");
        if (fondo) fondo.classList.add("visible");
        if (contenido) contenido.classList.add("carrito-abierto");
    }

    function cerrarCarritoMovil() {
        var carrito = $s("#pedCarrito");
        var fondo = $s("#pedCarritoBackdrop");
        var contenido = $s(".ped-cat-contenido");
        if (carrito) carrito.classList.remove("abierto");
        if (fondo) fondo.classList.remove("visible");
        if (contenido) contenido.classList.remove("carrito-abierto");
    }

    // Privilegios del ítem 7 (ver / registrar / modificar / eliminar).
    function puede(accion) {
        return !!(estado.permisos && estado.permisos[accion]);
    }
    function mostrarBoton(sel, visible) {
        var el = $s(sel);
        if (!el) return;
        el.style.display = visible ? "" : "none";
    }

    function iconoCategoria(id) {
        for (var i = 0; i < estado.categorias.length; i++) {
            if (Number(estado.categorias[i].id) === Number(id)) {
                return estado.categorias[i].icono || "fa-utensils";
            }
        }
        return "fa-utensils";
    }

    /** Stock con el que opera el pedido: global del producto (productos.stock). */
    function stockProducto(p) {
        if (!p) return 0;
        return numero(p.stock_pos != null ? p.stock_pos : p.stock_sucursal);
    }

    function productoPorId(id) {
        for (var i = 0; i < estado.productos.length; i++) {
            if (Number(estado.productos[i].id) === Number(id)) return estado.productos[i];
        }
        return null;
    }

    // El backend entrega estos campos pos-normalizados; los fallbacks
    // protegen la vista si algún dato faltara.
    function precioPos(p) { return numero(p.precio_pos != null ? p.precio_pos : p.precio); }
    function precioBasePos(p) { return numero(p.precio_base != null ? p.precio_base : p.precio); }
    function descuentoPos(p) { return numero(p.descuento_pos); }
    function impuestoPos(p) { return numero(p.impuesto_pos); }

    // =========================================================
    // CARGA
    // =========================================================
    function cargar() {
        if (!window.PedidosAPI) { aviso("Error", "No se cargó el módulo de red", "error"); return; }

        mostrarCarga(true);
        window.PedidosAPI.cargar(estado.idSucursal).then(function (rpta) {
            mostrarCarga(false);
            if (!rpta.success) { aviso("No se pudo cargar", rpta.message || "Error", "error", 5000); return; }

            var d = rpta.data || {};
            estado.usuario = d.usuario || {};
            estado.sucursales = d.sucursales || [];
            estado.idSucursal = Number(d.id_sucursal || 0);
            estado.mesas = d.mesas || [];
            estado.online = d.online || [];
            estado.cocina = d.cocina || [];
            estado.productos = d.productos || [];
            estado.categorias = d.categorias || [];

            pintarBarra();
            pintarSalon();
            pintarCocina();
            actualizarTabs();

            // Catálogo y carrito listos para cuando se abra el modal.
            pintarCategorias();
            pintarProductos();
            pintarCarrito();
        });
    }

    function pintarBarra() {
        var u = estado.usuario;
        var usrEl = $s("#pedUsuario");
        if (usrEl) usrEl.textContent = (u.nombre || "Cajero") + (u.es_superadmin ? " · SuperAdmin" : "");

        var sel = $s("#pedSucursal");
        if (!sel) return;
        if (!u.es_superadmin || estado.sucursales.length <= 1) {
            sel.style.display = "none";
            return;
        }
        sel.innerHTML = estado.sucursales.map(function (s) {
            return '<option value="' + s.id_sucursal + '"' +
                (Number(s.id_sucursal) === estado.idSucursal ? " selected" : "") + ">" +
                escapar(s.nombre_sucursal) + "</option>";
        }).join("");
        sel.style.display = "";
    }

    function actualizarTabs() {
        var libre = estado.mesas.filter(function (m) { return m.estado_mesa === "libre"; }).length;
        var salon = $s("#tabSalonCount");
        var cocina = $s("#tabCocinaCount");
        if (salon) salon.textContent = libre + " libres";
        if (cocina) cocina.textContent = estado.cocina.length;
    }

    function activarTab(tab) {
        $todos(".ped-tabs button").forEach(function (b) {
            b.classList.toggle("activa", b.getAttribute("data-tab") === tab);
        });
        $todos(".ped-panel").forEach(function (p) {
            p.classList.toggle("activo", p.id === "panel-" + tab);
        });
    }

    // =========================================================
    // SALÓN
    // =========================================================
    function pintarSalon() {
        var cont = $s("#pedMesas");
        var onlineCont = $s("#pedSalonOnline");
        var filtro = $s("#pedFiltroSalon");
        if (!cont) return;

        var verOnline = filtro && filtro.value === "online";

        cont.style.display = verOnline ? "none" : "";
        if (onlineCont) onlineCont.style.display = verOnline ? "" : "none";

        if (verOnline) {
            pintarOnline();
            return;
        }

        if (!estado.mesas.length) {
            cont.innerHTML = '<div class="cat-vacio" style="grid-column:1/-1;"><i class="fas fa-chair"></i>No hay mesas configuradas.</div>';
            return;
        }

        cont.innerHTML = estado.mesas.map(function (m) {
            var chip = "";
            if (m.estado_mesa === "libre") chip = "Libre";
            else if (m.estado_mesa === "ocupada") chip = "Ocupada";
            else if (m.estado_mesa === "despachado") chip = "Despachado";
            else if (m.estado_mesa === "pendiente_pago") chip = "Por cobrar";
            else chip = "Inactiva";

            var claseEstado = m.estado_mesa;
            var total = m.pedido_total > 0 ? bs(m.pedido_total) : "";
            var foto = m.imagen_url
                ? '<img src="' + escapar(m.imagen_url) + '" alt="">'
                : '<i class="fa-solid ' + escapar(m.icono || "fa-chair") + '"></i>';

            return (
                '<button type="button" class="mes-card-ped ' + escapar(claseEstado) + '" data-id="' + m.id_mesa + '" data-pedido="' + m.pedido_id + '">' +
                    '<div class="mc-foto">' + foto +
                        '<span class="mc-chip-estado">' + chip + '</span>' +
                        (total ? '<span class="mc-total">' + total + '</span>' : "") +
                    '</div>' +
                    '<div class="mc-body-ped">' +
                        '<span class="mc-codigo">' + escapar(m.codigo) + '</span>' +
                        '<span class="mc-nombre">' + escapar(m.nombre) + '</span>' +
                        '<span class="mc-meta">' +
                            '<span><i class="fas fa-user-group"></i> ' + m.capacidad + ' pers.</span>' +
                            (m.piso ? '<span><i class="fas fa-layer-group"></i> ' + escapar(m.piso) + '</span>' : "") +
                        '</span>' +
                        (m.pedido_id > 0 ? (
                            '<span class="mc-info">' +
                                '<span><i class="fas fa-clock"></i> ' + escapar(m.pedido_fecha) + '</span>' +
                                '<span><i class="fas fa-list"></i> ' + m.pedido_items + ' ítems</span>' +
                            '</span>'
                        ) : "") +
                    '</div>' +
                '</button>'
            );
        }).join("");
    }

    function pintarOnline() {
        var cont = $s("#pedSalonOnline");
        if (!cont) return;

        if (!estado.online || !estado.online.length) {
            cont.innerHTML = '<div class="cat-vacio" style="grid-column:1/-1;"><i class="fas fa-truck"></i>No hay pedidos online activos.</div>';
            return;
        }

        cont.innerHTML = estado.online.map(function (p) {
            return (
                '<button type="button" class="mes-card-ped ' + escapar(p.estado) + '" data-pedido="' + p.id_pedido + '">' +
                    '<div class="mc-foto">' +
                        '<i class="fas fa-truck"></i>' +
                        '<span class="mc-chip-estado">' + escapar(p.estado) + '</span>' +
                        '<span class="mc-total">' + bs(p.total) + '</span>' +
                    '</div>' +
                    '<div class="mc-body-ped">' +
                        '<span class="mc-codigo">' + escapar(p.codigo) + '</span>' +
                        '<span class="mc-nombre">' + (p.tipo_label || p.tipo_pedido) + '</span>' +
                        '<span class="mc-meta"><span><i class="fas fa-user"></i> ' + escapar(p.cliente || p.usuario) + '</span></span>' +
                        '<span class="mc-info"><span><i class="fas fa-list"></i> ' + (p.detalle || []).length + ' ítems</span></span>' +
                    '</div>' +
                '</button>'
            );
        }).join("");
    }

    // =========================================================
    // COCINA
    // =========================================================
    function pintarCocina() {
        var cont = $s("#pedCocina");
        if (!cont) return;

        if (!estado.cocina.length) {
            cont.innerHTML = '<div class="cat-vacio" style="grid-column:1/-1;"><i class="fas fa-fire"></i>No hay pedidos pendientes en cocina.</div>';
            return;
        }

        cont.innerHTML = estado.cocina.map(function (p) {
            var lineas = (p.detalle || []).map(function (l) {
                return (
                    '<div class="cc-linea">' +
                        '<span><span class="cc-cant">' + l.cantidad + '×</span> ' + escapar(l.producto) +
                            (l.observaciones ? ' <i style="color:#b57a00;font-size:10px;">(' + escapar(l.observaciones) + ')</i>' : "") +
                        '</span>' +
                    '</div>'
                );
            }).join("");

            var btnDespachar = puede("modificar")
                ? '<button type="button" class="primario" data-accion="despachar" data-id="' + p.id_pedido + '">' +
                      '<i class="fas fa-check"></i> Despachado' +
                  '</button>'
                : "";

            return (
                '<div class="cocina-card" data-id="' + p.id_pedido + '">' +
                    '<div class="cc-cab">' +
                        '<span class="cc-num">#' + p.id_pedido + '</span>' +
                        '<span class="cc-mesa">' + (p.mesa ? escapar(p.mesa) : (p.tipo_pedido === "delivery" ? "Delivery" : "Para llevar")) + '</span>' +
                        '<span class="cc-hora">' + escapar(p.fecha_apertura) + '</span>' +
                    '</div>' +
                    '<div class="cc-lista">' + lineas + '</div>' +
                    '<div class="cc-pie">' +
                        '<button type="button" data-accion="detalle" data-id="' + p.id_pedido + '">Ver</button>' +
                        btnDespachar +
                    '</div>' +
                '</div>'
            );
        }).join("");
    }

    // =========================================================
    // MODAL CATÁLOGO
    // =========================================================
    function abrirNuevoPedido(mesa) {
        if (!puede("modificar")) {
            return aviso("Sin permiso", "No tienes privilegios para tomar pedidos", "warning");
        }

        estado.mesaSeleccionada = mesa;
        estado.carrito = [];
        estado.modoEdicion = false;
        estado.pedidoActual = null;
        estado.filtro = "";
        estado.filtroCategoria = "";

        if ($s("#pedBuscar")) $s("#pedBuscar").value = "";
        if ($s("#pedTituloModal")) $s("#pedTituloModal").textContent = "Tomar pedido · " + mesa.nombre + " (" + mesa.codigo + ")";
        if ($s("#pedGuardarTexto")) $s("#pedGuardarTexto").textContent = "Guardar pedido";

        filtrar();
        pintarCategorias();
        pintarCarrito();
        pintarProductos();
        var grid = $s("#pedProductos");
        if (grid) grid.scrollTop = 0;
        cerrarCarritoMovil();
        abrirModal("modalPedido");
    }

    function abrirAgregarExtras(pedido) {
        if (!puede("modificar")) {
            return aviso("Sin permiso", "No tienes privilegios para modificar pedidos", "warning");
        }

        estado.mesaSeleccionada = null;
        estado.carrito = [];
        estado.modoEdicion = true;
        estado.pedidoActual = pedido;
        estado.filtro = "";
        estado.filtroCategoria = "";

        if ($s("#pedBuscar")) $s("#pedBuscar").value = "";
        if ($s("#pedTituloModal")) $s("#pedTituloModal").textContent = "Agregar a pedido #" + pedido.id_pedido + (pedido.mesa ? " · " + pedido.mesa : "");
        if ($s("#pedGuardarTexto")) $s("#pedGuardarTexto").textContent = "Agregar productos";

        filtrar();
        pintarCategorias();
        pintarCarrito();
        pintarProductos();
        var grid = $s("#pedProductos");
        if (grid) grid.scrollTop = 0;

        cerrarCarritoMovil();
        cerrarModal("modalDetalle");
        abrirModal("modalPedido");
    }

    function pintarCategorias() {
        var cont = $s("#pedCategorias");
        if (!cont) return;

        var html = '<button type="button" class="' +
            (estado.filtroCategoria === "" ? "activa" : "") +
            '" data-cat=""><i class="fas fa-layer-group"></i> Todas</button>';

        html += estado.categorias.map(function (c) {
            var activa = String(c.id) === String(estado.filtroCategoria);
            return '<button type="button" class="' + (activa ? "activa" : "") + '" data-cat="' + Number(c.id) + '">' +
                '<i class="fas ' + escapar(c.icono || "fa-utensils") + '"></i> ' + escapar(c.nombre) + '</button>';
        }).join("");

        cont.innerHTML = html;
    }

    function productosFiltrados() {
        var t = normalizar(estado.filtro);
        var c = estado.filtroCategoria;

        return estado.productos.filter(function (p) {
            if (c !== "" && String(p.id_categoria) !== String(c)) return false;
            if (t === "") return true;
            var bolsa = normalizar([p.nombre, p.codigo, p.categoria, p.descripcion].join(" "));
            return bolsa.indexOf(t) !== -1;
        });
    }

    function etiquetaStock(p) {
        var controla = Number(p.control_stock) === 1;
        var stock = stockProducto(p);

        if (!controla) {
            return '<span class="stock-ok"><i class="fas fa-circle-check"></i> Disponible</span>';
        }
        if (stock <= 0) {
            return '<span class="stock-cero"><i class="fas fa-circle-xmark"></i> Agotado</span>';
        }
        if (stock <= 5) {
            return '<span class="stock-bajo"><i class="fas fa-triangle-exclamation"></i> Quedan ' + stock + '</span>';
        }
        return '<span class="stock-ok"><i class="fas fa-box"></i> Stock ' + stock + '</span>';
    }

    function porcentajeDescuento(p) {
        var pct = numero(p.descuento_pct_pos);
        if (pct > 0.004) return Math.round(pct);
        var base = precioBasePos(p);
        var actual = precioPos(p);
        return base > 0.004 ? Math.round(((base - actual) / base) * 100) : 0;
    }

    function pintarProductos() {
        var cont = $s("#pedProductos");
        var vacio = $s("#pedVacio");
        var conteo = $s("#pedConteoProductos");
        if (!cont) return;

        var lista = productosFiltrados();

        if (conteo) conteo.textContent = lista.length + " " + plural(lista.length, "producto", "productos");
        if (vacio) vacio.classList.toggle("visible", lista.length === 0);
        cont.style.display = lista.length ? "" : "none";

        if (!lista.length) {
            cont.innerHTML = "";
            return;
        }

        cont.innerHTML = lista.map(function (p) {
            var controla = Number(p.control_stock) === 1;
            var stock = stockProducto(p);
            var agotado = controla && stock <= 0;
            var imagen = p.imagen_principal_url || p.imagen_url || "";
            var icono = iconoCategoria(p.id_categoria);
            var descuento = descuentoPos(p);
            var tienePromo = !!p.promocion;
            var iva = impuestoPos(p);
            var pct = porcentajeDescuento(p);

            var badges = "";
            if (tienePromo) {
                badges += '<span class="ped-badge promo" title="' + escapar(p.promocion) + '">' +
                    '<i class="fas fa-tag"></i> Promo</span>';
            }
            if (descuento > 0.004 && pct > 0) {
                badges += '<span class="ped-badge"><i class="fas fa-bolt"></i> −' + pct + '%</span>';
            } else if (!tienePromo && descuento > 0.004) {
                badges += '<span class="ped-badge"><i class="fas fa-bolt"></i> Oferta</span>';
            }

            return (
                '<button type="button" class="ped-prod' + (agotado ? " agotado" : "") + '" data-id="' + Number(p.id) + '" ' +
                    'aria-label="' + escapar(p.nombre) + (agotado ? " (agotado)" : "") + '"' +
                    (p.promocion ? ' title="' + escapar(p.promocion) + '"' : "") + '>' +

                    '<span class="ped-prod-media">' +
                        '<span class="sin-imagen"><i class="fas ' + escapar(icono) + '"></i></span>' +
                        (imagen ? '<img src="' + escapar(imagen) + '" alt="" loading="lazy" onerror="this.remove()">' : "") +
                        (badges ? '<span class="ped-prod-badges">' + badges + '</span>' : "") +
                    '</span>' +

                    '<span class="ped-prod-cuerpo">' +
                        '<span class="ped-prod-nombre">' + escapar(p.nombre) + '</span>' +
                        '<span class="ped-prod-codigo">' + escapar(p.codigo || "") + '</span>' +
                        '<span class="ped-prod-precios">' +
                            '<span class="ped-prod-precio">' + bs(precioPos(p)) + '</span>' +
                            (descuento > 0.004
                                ? '<span class="ped-prod-antes">' + bs(precioBasePos(p)) + '</span>'
                                : "") +
                        '</span>' +
                        '<span class="ped-prod-meta">' +
                            etiquetaStock(p) +
                            (iva > 0 ? '<span>IVA ' + iva + '%</span>' : "") +
                        '</span>' +
                    '</span>' +

                    '<span class="ped-prod-agregar"><i class="fas fa-plus"></i></span>' +
                '</button>'
            );
        }).join("");
    }

    function filtrar() {
        var limpiar = $s("#pedBuscarLimpiar");
        if (limpiar) limpiar.classList.toggle("visible", (estado.filtro || "").length > 0);
        pintarProductos();
    }

    // =========================================================
    // CARRITO
    // =========================================================
    function importeLinea(l) {
        var cantidad = numero(l.cantidad);
        var neto = n2(numero(l.precio_unitario) * cantidad);
        var bruto = n2(numero(l.precio_base) * cantidad);
        var descuento = n2(numero(l.descuento_unitario) * cantidad);
        var iva = n2(neto * (numero(l.impuesto_pct) / 100));

        return {
            bruto: bruto,
            neto: neto,
            descuento: descuento,
            iva: iva,
            total: n2(neto + iva),
        };
    }

    function calcularTotales() {
        var t = { bruto: 0, neto: 0, descuento: 0, iva: 0, total: 0, unidades: 0, lineas: estado.carrito.length };

        estado.carrito.forEach(function (l) {
            var i = importeLinea(l);
            t.bruto += i.bruto;
            t.neto += i.neto;
            t.descuento += i.descuento;
            t.iva += i.iva;
            t.total += i.total;
            t.unidades += numero(l.cantidad);
        });

        t.bruto = n2(t.bruto);
        t.neto = n2(t.neto);
        t.descuento = n2(t.descuento);
        t.iva = n2(t.iva);
        t.total = n2(t.total);
        t.unidades = n2(t.unidades);
        return t;
    }

    function agregarProducto(id) {
        var p = productoPorId(id);
        if (!p) return;

        var controla = Number(p.control_stock) === 1;
        var stock = stockProducto(p);
        var linea = null;

        for (var i = 0; i < estado.carrito.length; i++) {
            if (Number(estado.carrito[i].id_producto) === Number(id)) { linea = estado.carrito[i]; break; }
        }

        var actual = linea ? numero(linea.cantidad) : 0;

        if (controla && actual + 1 > stock + 0.0001) {
            return aviso(
                stock <= 0 ? "Agotado" : "Sin stock suficiente",
                stock <= 0
                    ? '"' + p.nombre + '" no tiene stock disponible.'
                    : '"' + p.nombre + '": sólo quedan ' + stock + ' unidades.',
                "warning"
            );
        }

        if (linea) {
            linea.cantidad = n2(actual + 1);
        } else {
            estado.carrito.push({
                id_producto: Number(p.id),
                nombre: p.nombre,
                codigo: p.codigo || "",
                precio_base: precioBasePos(p),
                precio_unitario: precioPos(p),
                descuento_unitario: descuentoPos(p),
                descuento_pct: numero(p.descuento_pct_pos),
                impuesto_pct: impuestoPos(p),
                promo: p.promocion || "",
                promo_etiqueta: p.promocion_etiqueta || "",
                icono: iconoCategoria(p.id_categoria),
                controla: controla,
                stock: stock,
                cantidad: 1,
            });
        }

        pintarCarrito();
    }

    function cambiarCantidad(id, delta) {
        var indice = -1;
        for (var i = 0; i < estado.carrito.length; i++) {
            if (Number(estado.carrito[i].id_producto) === Number(id)) { indice = i; break; }
        }
        if (indice === -1) return;

        var linea = estado.carrito[indice];
        var nueva = n2(numero(linea.cantidad) + delta);

        if (nueva <= 0) {
            estado.carrito.splice(indice, 1);
            pintarCarrito();
            return;
        }

        if (linea.controla && nueva > numero(linea.stock) + 0.0001) {
            return aviso("Sin stock suficiente", '"' + linea.nombre + '": sólo quedan ' + linea.stock + ' unidades.', "warning");
        }

        linea.cantidad = nueva;
        pintarCarrito();
    }

    function quitarLinea(id) {
        estado.carrito = estado.carrito.filter(function (l) {
            return Number(l.id_producto) !== Number(id);
        });
        pintarCarrito();
    }

    function pintarCarrito() {
        var t = calcularTotales();
        var lineas = $s("#pedCarritoLineas");
        var vacio = $s("#pedCarritoVacio");
        var count = $s("#pedCarritoCount");
        var resumen = $s("#pedCarritoResumen");
        var subtotal = $s("#pedSubtotal");
        var descuento = $s("#pedDescuento");
        var impuesto = $s("#pedImpuesto");
        var total = $s("#pedTotal");
        var fabTotal = $s("#pedFabTotal");
        var fabCount = $s("#pedFabCount");
        var guardar = $s("#pedGuardar");

        if (lineas) {
            lineas.style.display = t.lineas === 0 ? "none" : "";
            lineas.innerHTML = estado.carrito.map(function (l) {
                var imp = importeLinea(l);
                var chips = [];

                if (l.promo) {
                    chips.push(
                        '<span class="ped-linea-promo" title="' + escapar(l.promo) + '">' +
                        '<i class="fas fa-tag"></i><span>' + escapar(l.promo) + '</span></span>'
                    );
                } else if (numero(l.descuento_unitario) > 0.004) {
                    chips.push('<span class="ped-linea-promo"><i class="fas fa-bolt"></i><span>Oferta</span></span>');
                }

                return (
                    '<div class="ped-linea" data-id="' + Number(l.id_producto) + '">' +
                        '<div class="ped-linea-nombre">' + escapar(l.nombre) + '</div>' +
                        '<div class="ped-linea-precio">' + bs(imp.total) + '</div>' +
                        '<div class="ped-linea-meta">' +
                            '<span>' + bs(l.precio_unitario) + ' c/u</span>' +
                            (numero(l.impuesto_pct) > 0 ? '<span>· IVA ' + numero(l.impuesto_pct) + '%</span>' : "") +
                            chips.join("") +
                        '</div>' +
                        '<div class="ped-linea-controles">' +
                            '<div class="ped-stepper">' +
                                '<button type="button" data-accion="menos" aria-label="Quitar una unidad"><i class="fas fa-minus"></i></button>' +
                                '<b>' + l.cantidad + '</b>' +
                                '<button type="button" data-accion="mas" aria-label="Agregar una unidad"><i class="fas fa-plus"></i></button>' +
                            '</div>' +
                            '<button type="button" class="ped-linea-quitar" data-accion="quitar" aria-label="Quitar producto">' +
                                '<i class="fas fa-trash-can"></i>' +
                            '</button>' +
                        '</div>' +
                    '</div>'
                );
            }).join("");
        }

        var unidadesTxt = t.unidades === 0
            ? "0 artículos"
            : t.unidades + " " + plural(t.unidades, "artículo", "artículos");

        if (vacio) vacio.classList.toggle("visible", t.lineas === 0);
        if (count) count.textContent = t.unidades;
        if (resumen) resumen.textContent = unidadesTxt;
        if (subtotal) subtotal.textContent = bs(t.bruto);
        if (descuento) descuento.textContent = t.descuento > 0.004 ? bsNeg(t.descuento) : bs(0);
        if (impuesto) impuesto.textContent = bs(t.iva);
        if (total) total.textContent = bs(t.total);
        if (fabTotal) fabTotal.textContent = bs(t.total);
        if (fabCount) fabCount.textContent = t.unidades;
        if (guardar) guardar.disabled = t.lineas === 0;
    }

    // =========================================================
    // GUARDAR PEDIDO
    // =========================================================
    function guardarPedido() {
        if (!puede("modificar")) {
            return aviso("Sin permiso", "No tienes privilegios para modificar pedidos", "warning");
        }
        if (!estado.carrito.length) return;

        var items = estado.carrito.map(function (l) {
            return { id_producto: l.id_producto, cantidad: l.cantidad };
        });

        var btn = $s("#pedGuardar");
        if (btn) btn.disabled = true;
        var promesa;

        if (estado.modoEdicion && estado.pedidoActual) {
            promesa = window.PedidosAPI.agregarItems({
                id_pedido: estado.pedidoActual.id_pedido,
                items: items,
            });
        } else if (estado.mesaSeleccionada) {
            promesa = window.PedidosAPI.abrir({
                id_sucursal: estado.idSucursal,
                id_mesa: estado.mesaSeleccionada.id_mesa,
                tipo_pedido: "mesa",
            }).then(function (r) {
                if (!r.success) throw new Error(r.message || "No se pudo abrir el pedido");
                return window.PedidosAPI.agregarItems({ id_pedido: r.data.id, items: items });
            });
        } else {
            if (btn) btn.disabled = false;
            return aviso("Error", "No hay mesa seleccionada", "error");
        }

        promesa.then(function (r) {
            if (btn) btn.disabled = false;
            if (!r.success) {
                return aviso("No se pudo guardar", r.message || "Revisa los datos", "error", 5000);
            }
            cerrarModal("modalPedido");
            aviso("Pedido guardado", "Ya está en cocina", "success");
            estado.carrito = [];
            estado.mesaSeleccionada = null;
            estado.pedidoActual = null;
            estado.modoEdicion = false;
            cargar();
        }).catch(function (e) {
            if (btn) btn.disabled = false;
            aviso("No se pudo guardar", (e && e.message) || "Error", "error", 5500);
        });
    }

    // =========================================================
    // DETALLE
    // =========================================================
    function verDetalle(id) {
        var p = estado.cocina.find(function (x) { return Number(x.id_pedido) === Number(id); });

        if (!p) {
            var mesa = estado.mesas.find(function (m) { return Number(m.pedido_id) === Number(id); });
            if (mesa && mesa.pedido_id > 0 && window.PedidosAPI.detalle) {
                window.PedidosAPI.detalle(id).then(function (r) {
                    if (r.success && r.data.pedido) verDetalleDesde(r.data.pedido);
                    else aviso("No se pudo abrir", r.message || "Intenta de nuevo", "error");
                });
                return;
            }

            var online = estado.online.find(function (x) { return Number(x.id_pedido) === Number(id); });
            if (online) { verDetalleDesde(online); return; }

            aviso("No encontrado", "Recarga los datos", "warning");
            return;
        }

        verDetalleDesde(p);
    }

    function verDetalleDesde(p) {
        if ($s("#detTitulo")) $s("#detTitulo").textContent = "Pedido #" + p.id_pedido + (p.mesa ? " · " + p.mesa : "");

        if ($s("#detCabecera")) {
            var nombreMesa = p.mesa || (p.tipo_pedido === "delivery"
                ? "Delivery · " + (p.delivery_nombre || "")
                : "Para llevar");
            var items = (p.detalle || []).length;
            $s("#detCabecera").innerHTML =
                '<div class="dc-info">' +
                    '<b>' + escapar(nombreMesa) + '</b>' +
                    '<span>' + escapar(p.fecha_apertura || "") + ' · ' + items + ' ' +
                        plural(items, "ítem", "ítems") + '</span>' +
                '</div>' +
                '<span class="det-chip">' + escapar((p.estado || "").toUpperCase()) + '</span>';
        }

        if ($s("#detLineas")) {
            $s("#detLineas").innerHTML = (p.detalle || []).map(function (l) {
                return (
                    '<div class="det-linea">' +
                        '<span class="dl-cant">' + l.cantidad + '×</span>' +
                        '<span class="dl-nombre">' + escapar(l.producto) + '</span>' +
                        '<b>' + bs(l.total) + '</b>' +
                    '</div>'
                );
            }).join("");
        }

        if ($s("#detTotales")) {
            var brutoDet = n2(numero(p.subtotal) + numero(p.descuento));
            var descDet = numero(p.descuento);

            $s("#detTotales").innerHTML =
                '<div class="ped-total-fila"><span>Subtotal</span><b>' + bs(brutoDet) + '</b></div>' +
                (descDet > 0.004
                    ? '<div class="ped-total-fila descuento"><span>Descuento</span><b>' + bsNeg(descDet) + '</b></div>'
                    : "") +
                '<div class="ped-total-fila"><span>IVA</span><b>' + bs(p.impuesto) + '</b></div>' +
                '<div class="ped-total-fila total"><span>Total</span><b>' + bs(p.total) + '</b></div>';
        }

        // Botones según estado y privilegios del ítem 7.
        var mostrarCobrar = ["despachado", "pendiente_pago"].indexOf(p.estado) !== -1 && puede("registrar");
        var mostrarAgregar = ["pendiente", "preparando", "despachado"].indexOf(p.estado) !== -1 && puede("modificar");
        var mostrarCancelar = p.estado !== "pagado" && p.estado !== "cancelado" && puede("eliminar");

        mostrarBoton("#detCobrar", mostrarCobrar);
        mostrarBoton("#detAgregar", mostrarAgregar);
        mostrarBoton("#detCancelar", mostrarCancelar);

        var btnCobrar = $s("#detCobrar");
        if (btnCobrar) {
            btnCobrar.onclick = function () {
                cerrarModal("modalDetalle");
                abrirCobro(p.id_pedido, p);
            };
        }

        var btnAgregar = $s("#detAgregar");
        if (btnAgregar) btnAgregar.onclick = function () { abrirAgregarExtras(p); };

        var btnCancelar = $s("#detCancelar");
        if (btnCancelar) btnCancelar.onclick = function () { cancelarPedido(p.id_pedido); };

        abrirModal("modalDetalle");
    }

    // =========================================================
    // ACCIONES
    // =========================================================
    function cancelarPedido(id) {
        if (!puede("eliminar")) {
            return aviso("Sin permiso", "No tienes privilegios para cancelar pedidos", "warning");
        }

        confirmar("¿Cancelar pedido?", "Se cancelará el pedido, la mesa quedará libre y se devolverá el stock si ya se había descontado.", "warning").then(function (ok) {
            if (!ok) return;
            window.PedidosAPI.cancelar(id).then(function (r) {
                if (!r.success) return aviso("Error", r.message, "error");
                cerrarModal("modalDetalle");
                aviso("Pedido cancelado", r.message || "La mesa está libre", "success");
                cargar();
            });
        });
    }

    function despachar(id) {
        if (!puede("modificar")) {
            return aviso("Sin permiso", "No tienes privilegios para despachar pedidos", "warning");
        }

        confirmar("¿Marcar como despachado?", "El pedido saldrá de la lista de cocina y quedará listo para cobrar.").then(function (ok) {
            if (!ok) return;
            window.PedidosAPI.despachar(id).then(function (r) {
                if (!r.success) return aviso("Error", r.message, "error");
                aviso("Despachado", "El pedido salió de cocina", "success");
                cargar();
            });
        });
    }

    // =========================================================
    // COBRO
    // =========================================================
    function unidadesPedido(p) {
        var u = 0;
        (p.detalle || []).forEach(function (l) { u += numero(l.cantidad); });
        return n2(u);
    }

    function abrirCobro(id, pedido) {
        if (!puede("registrar")) {
            return aviso("Sin permiso", "No tienes privilegios para cobrar pedidos", "warning");
        }

        estado.pedidoCobrando = pedido;
        estado.metodoPago = "efectivo";

        var unidades = unidadesPedido(pedido);
        var desc = numero(pedido.descuento);
        var total = numero(pedido.total);
        var bruto = n2(numero(pedido.subtotal) + desc);

        var setTexto = function (sel, valor) {
            var el = $s(sel);
            if (el) el.textContent = valor;
        };

        setTexto("#cpTotal", bs(total));
        setTexto("#cpItems", unidades + " " + plural(unidades, "producto", "productos"));
        setTexto("#cpSubtotal", bs(bruto));
        setTexto("#cpDescuento", desc > 0.004 ? bsNeg(desc) : bs(0));
        setTexto("#cpImpuesto", bs(pedido.impuesto));
        setTexto("#cpResumenDescuento", desc > 0.004 ? "Ahorro " + bsNeg(desc) : "Sin descuentos");
        setTexto("#cpConfirmarMonto", bs(total));
        setTexto("#cpNota", "Total a pagar " + bs(total));
        setTexto("#cpMesa", pedido.mesa || (pedido.tipo_pedido === "delivery"
            ? "Delivery · " + (pedido.delivery_nombre || "")
            : "Para llevar"));

        var recibido = $s("#cpRecibido");
        if (recibido) recibido.value = total.toFixed(2);
        var ref = $s("#cpRef");
        if (ref) ref.value = "";
        var refMixto = $s("#cpMixtoRef");
        if (refMixto) refMixto.value = "";
        var obs = $s("#cpObs");
        if (obs) obs.value = pedido.observaciones || "";
        var mixtoEf = $s("#cpMixtoEf");
        if (mixtoEf) mixtoEf.value = total.toFixed(2);
        var mixtoQr = $s("#cpMixtoQr");
        if (mixtoQr) mixtoQr.value = "";
        var mixtoTj = $s("#cpMixtoTj");
        if (mixtoTj) mixtoTj.value = "";

        aplicarMetodo("efectivo");
        actualizarCambio();
        actualizarMixto();
        abrirModal("modalCobroPedido");
    }

    function aplicarMetodo(metodo) {
        estado.metodoPago = metodo;

        $todos("#cpMetodos button").forEach(function (b) {
            b.classList.toggle("activa", b.getAttribute("data-metodo") === metodo);
        });

        var ef = $s("#cpEfectivo");
        if (ef) ef.style.display = metodo === "efectivo" ? "" : "none";
        var ref = $s("#cpReferencia");
        if (ref) ref.style.display = (metodo === "qr" || metodo === "tarjeta") ? "" : "none";
        var mixto = $s("#cpMixto");
        if (mixto) mixto.style.display = metodo === "mixto" ? "" : "none";

        actualizarCambio();
        actualizarMixto();
    }

    function actualizarCambio() {
        if (!estado.pedidoCobrando) return;
        var total = numero(estado.pedidoCobrando.total);
        var input = $s("#cpRecibido");
        var rec = input ? numero(input.value) : 0;
        var dif = n2(rec - total);

        var val = $s("#cpCambio");
        if (!val) return;
        val.textContent = dif < 0 ? bsNeg(-dif) : bs(dif);

        var caja = $s("#cpCambioCaja");
        if (caja) caja.classList.toggle("negativo", dif < 0);
    }

    function actualizarMixto() {
        if (!estado.pedidoCobrando) return;
        var total = numero(estado.pedidoCobrando.total);
        var ef = numero(($s("#cpMixtoEf") || {}).value);
        var qr = numero(($s("#cpMixtoQr") || {}).value);
        var tj = numero(($s("#cpMixtoTj") || {}).value);
        var suma = n2(ef + qr + tj);
        var dif = n2(total - suma);

        var caja = $s("#cpMixtoEstado");
        if (!caja) return;

        var sumaEl = $s("#cpMixtoSuma");
        if (sumaEl) sumaEl.textContent = bs(suma);

        var txt = $s("#cpMixtoTexto") || caja.querySelector("span");

        caja.classList.remove("ok", "mal");
        if (Math.abs(dif) < 0.01) {
            caja.classList.add("ok");
            if (txt) txt.textContent = "Pago completo ✓";
        } else if (dif > 0) {
            caja.classList.add("mal");
            if (txt) txt.textContent = "Falta " + bs(dif);
        } else {
            caja.classList.add("mal");
            if (txt) txt.textContent = "Excede " + bs(-dif);
        }
    }

    function restarResto(medio) {
        if (!estado.pedidoCobrando) return;
        var total = numero(estado.pedidoCobrando.total);
        var ids = { efectivo: "#cpMixtoEf", qr: "#cpMixtoQr", tarjeta: "#cpMixtoTj" };
        var otros = 0;

        Object.keys(ids).forEach(function (k) {
            if (k === medio) return;
            otros += numero(($s(ids[k]) || {}).value);
        });

        var el = $s(ids[medio]);
        if (el) el.value = Math.max(0, n2(total - otros)).toFixed(2);
        actualizarMixto();
    }

    function cobrarPedido() {
        if (!puede("registrar")) {
            return aviso("Sin permiso", "No tienes privilegios para cobrar pedidos", "warning");
        }
        if (!estado.pedidoCobrando) return;

        var total = numero(estado.pedidoCobrando.total);
        var payload = {
            id_pedido: estado.pedidoCobrando.id_pedido,
            id_sucursal: estado.idSucursal,
        };

        var obs = $s("#cpObs");
        if (obs && obs.value.trim() !== "") payload.observaciones = obs.value.trim();

        if (estado.metodoPago === "efectivo") {
            var rec = numero(($s("#cpRecibido") || {}).value);
            if (rec + 0.005 < total) return aviso("Falta efectivo", "El recibido es menor al total", "warning");
            payload.metodo_pago = "efectivo";
            payload.recibido = rec;
        } else if (estado.metodoPago === "qr" || estado.metodoPago === "tarjeta") {
            payload.metodo_pago = estado.metodoPago;
            payload.referencia = (($s("#cpRef") || {}).value || "").trim();
        } else {
            var ef = numero(($s("#cpMixtoEf") || {}).value);
            var qr = numero(($s("#cpMixtoQr") || {}).value);
            var tj = numero(($s("#cpMixtoTj") || {}).value);
            var suma = n2(ef + qr + tj);

            if (Math.abs(suma - total) > 0.01) {
                return aviso("Suma incorrecta", "El pago mixto debe sumar el total", "warning");
            }

            var pagos = [];
            if (ef > 0) pagos.push({ metodo: "efectivo", monto: ef });
            if (qr > 0) pagos.push({ metodo: "qr", monto: qr });
            if (tj > 0) pagos.push({ metodo: "tarjeta", monto: tj });

            payload.metodo_pago = "mixto";
            payload.pagos = pagos;

            var refMixto = $s("#cpMixtoRef");
            if (refMixto && refMixto.value.trim() !== "") payload.referencia = refMixto.value.trim();
        }

        var btn = $s("#cpConfirmar");
        if (btn) btn.disabled = true;

        window.PedidosAPI.cobrar(payload).then(function (r) {
            if (btn) btn.disabled = false;
            if (!r.success) return aviso("No se pudo cobrar", r.message, "error", 5500);

            cerrarModal("modalCobroPedido");

            var venta = r.data && r.data.venta ? r.data.venta : null;
            var idVenta = (r.data && r.data.id_venta) || (venta ? venta.id : 0);

            Swal.fire({
                icon: "success",
                title: "Cobro registrado",
                html:
                    '<div style="text-align:center; font-size:14px;">' +
                    '<b style="font-size:22px; color:#d9232d;">' + bs(total) + '</b><br>' +
                    '<span style="font-size:12px; color:#6b6165;">' + escapar(estado.pedidoCobrando.mesa || "Pedido") + '</span>' +
                    '</div>' +
                    (venta && venta.cambio > 0
                        ? '<div style="text-align:center; margin-top:8px; padding:6px 10px; background:#e6f7ed; border-radius:8px; color:#1f6b3a; font-weight:700;">Cambio: ' + bs(venta.cambio) + '</div>'
                        : ""),
                showCancelButton: idVenta > 0,
                confirmButtonText: "Nueva venta",
                cancelButtonText: '<i class="fas fa-file-pdf"></i> Ver ticket',
                confirmButtonColor: "#d9232d",
                cancelButtonColor: "#1e40af",
                reverseButtons: true,
            }).then(function (resp) {
                if (resp.dismiss === Swal.DismissReason.cancel && idVenta > 0 && window.PedidosAPI.ticketUrl) {
                    window.open(window.PedidosAPI.ticketUrl(idVenta), "_blank");
                }
            });

            estado.pedidoCobrando = null;
            cargar();
        }).catch(function () {
            if (btn) btn.disabled = false;
        });
    }

    // =========================================================
    // ARRANQUE
    // =========================================================
    document.addEventListener("DOMContentLoaded", function () {
        cargar();

        // Tabs
        $todos(".ped-tabs button").forEach(function (b) {
            b.addEventListener("click", function () { activarTab(b.getAttribute("data-tab")); });
        });

        var r = $s("#pedRefrescar");
        if (r) r.addEventListener("click", cargar);

        var s = $s("#pedSucursal");
        if (s) s.addEventListener("change", function () {
            estado.idSucursal = Number(s.value) || 0;
            cargar();
        });

        var fSalon = $s("#pedFiltroSalon");
        if (fSalon) fSalon.addEventListener("change", pintarSalon);

        // Cerrar modales
        $todos("[data-cerrar]").forEach(function (b) {
            b.addEventListener("click", function () {
                var id = b.getAttribute("data-cerrar");
                var mapa = { pedido: "modalPedido", detalle: "modalDetalle", cobroPedido: "modalCobroPedido" };
                if (mapa[id]) cerrarModal(mapa[id]);
            });
        });

        // Clics en mesa salón
        var mesas = $s("#pedMesas");
        if (mesas) mesas.addEventListener("click", function (e) {
            var btn = e.target.closest(".mes-card-ped");
            if (!btn) return;
            var mesa = estado.mesas.find(function (m) { return Number(m.id_mesa) === Number(btn.getAttribute("data-id")); });
            if (!mesa) return;

            if (mesa.estado_mesa === "libre") abrirNuevoPedido(mesa);
            else if (mesa.pedido_id > 0) verDetalle(mesa.pedido_id);
        });

        // Clics en pedidos online
        var onlineCont = $s("#pedSalonOnline");
        if (onlineCont) onlineCont.addEventListener("click", function (e) {
            var btn = e.target.closest(".mes-card-ped");
            if (!btn) return;
            var p = estado.online.find(function (x) { return Number(x.id_pedido) === Number(btn.getAttribute("data-pedido")); });
            if (p) verDetalle(p.id_pedido);
        });

        // Cocina
        var cocina = $s("#pedCocina");
        if (cocina) cocina.addEventListener("click", function (e) {
            var b = e.target.closest("[data-accion]");
            if (!b) return;
            var acc = b.getAttribute("data-accion");
            var id = b.getAttribute("data-id");
            if (acc === "detalle") verDetalle(id);
            else if (acc === "despachar") despachar(id);
        });

        // Buscar y categorías
        var busca = $s("#pedBuscar");
        if (busca) busca.addEventListener("input", function () {
            estado.filtro = this.value;
            filtrar();
        });

        var limpiar = $s("#pedBuscarLimpiar");
        if (limpiar) limpiar.addEventListener("click", function () {
            estado.filtro = "";
            if (busca) { busca.value = ""; busca.focus(); }
            filtrar();
        });

        var cat = $s("#pedCategorias");
        if (cat) cat.addEventListener("click", function (e) {
            var b = e.target.closest("[data-cat]");
            if (!b) return;
            estado.filtroCategoria = b.getAttribute("data-cat") || "";
            pintarCategorias();
            pintarProductos();
        });

        var prods = $s("#pedProductos");
        if (prods) prods.addEventListener("click", function (e) {
            var b = e.target.closest(".ped-prod");
            if (!b || b.classList.contains("agotado")) return;
            agregarProducto(b.getAttribute("data-id"));
        });

        // Carrito
        var lineas = $s("#pedCarritoLineas");
        if (lineas) lineas.addEventListener("click", function (e) {
            var b = e.target.closest("[data-accion]");
            if (!b) return;
            var acc = b.getAttribute("data-accion");
            var id = b.getAttribute("data-id");
            if (acc === "mas") cambiarCantidad(id, 1);
            else if (acc === "menos") cambiarCantidad(id, -1);
            else if (acc === "quitar") quitarLinea(id);
        });

        // Carrito móvil (bandeja inferior)
        var fabCarrito = $s("#pedCarritoFab");
        if (fabCarrito) fabCarrito.addEventListener("click", function () {
            var c = $s("#pedCarrito");
            if (c && c.classList.contains("abierto")) cerrarCarritoMovil();
            else abrirCarritoMovil();
        });
        var cerrarCarrito = $s("#pedCarritoCerrar");
        if (cerrarCarrito) cerrarCarrito.addEventListener("click", cerrarCarritoMovil);
        var fondoCarrito = $s("#pedCarritoBackdrop");
        if (fondoCarrito) fondoCarrito.addEventListener("click", cerrarCarritoMovil);
        window.addEventListener("resize", function () {
            if (window.innerWidth > 980) cerrarCarritoMovil();
        });

        var guardar = $s("#pedGuardar");
        if (guardar) guardar.addEventListener("click", guardarPedido);

        var cancelarModal = $s("#pedCancelarModal");
        if (cancelarModal) cancelarModal.addEventListener("click", function () {
            cerrarCarritoMovil();
            cerrarModal("modalPedido");
            estado.carrito = [];
            estado.mesaSeleccionada = null;
            estado.pedidoActual = null;
            estado.modoEdicion = false;
        });

        // Cobro
        $todos("#cpMetodos button").forEach(function (b) {
            b.addEventListener("click", function () { aplicarMetodo(b.getAttribute("data-metodo")); });
        });

        var recibido = $s("#cpRecibido");
        if (recibido) recibido.addEventListener("input", actualizarCambio);

        $todos("[data-billete]").forEach(function (b) {
            b.addEventListener("click", function () {
                if (!estado.pedidoCobrando) return;
                var total = numero(estado.pedidoCobrando.total);
                var valor = b.getAttribute("data-billete");
                var monto = valor === "exacto" ? total : numero(valor);
                var input = $s("#cpRecibido");
                if (input) input.value = monto.toFixed(2);
                actualizarCambio();
            });
        });

        ["cpMixtoEf", "cpMixtoQr", "cpMixtoTj"].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener("input", actualizarMixto);
        });

        $todos("[data-resto]").forEach(function (b) {
            b.addEventListener("click", function () { restarResto(b.getAttribute("data-resto")); });
        });

        var confirmarCobro = $s("#cpConfirmar");
        if (confirmarCobro) confirmarCobro.addEventListener("click", cobrarPedido);

        // Escape
        document.addEventListener("keydown", function (e) {
            if (e.key !== "Escape") return;
            var carrito = $s("#pedCarrito");
            if (carrito && carrito.classList.contains("abierto")) {
                cerrarCarritoMovil();
                return;
            }
            var abierto = document.querySelector(".modal-overlay.abierto");
            if (abierto) cerrarModal(abierto.id);
        });
    });
})();
