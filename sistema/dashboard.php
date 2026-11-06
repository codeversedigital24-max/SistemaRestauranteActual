<?php
require_once '../config/config.php';
$tituloPagina = 'Dashboard';
rm_iniciar_sesion();

include 'validacionSesion.php';
include 'validacion_permisos.php';

$avisoPermisos = $_SESSION['mensaje_error'] ?? '';
unset($_SESSION['mensaje_error']);

$diasSemana = ['Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'];
$mesesAno = ['January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril', 'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'];

$fechaHoy = $diasSemana[date('l')] . ', ' . (int) date('j') . ' de ' . $mesesAno[date('F')] . ' de ' . date('Y');
$horaAhora = date('H:i');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />
    <style>
        /* ============================================================
           DASHBOARD · Layout
           ============================================================ */
        .dash-hero {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 18px 20px;
            border-radius: 14px;
            color: #fff;
            background:
                radial-gradient(900px 180px at 100% -40%, var(--mod-primaria-osc), transparent 62%),
                linear-gradient(135deg, var(--mod-primaria-osc) 0%, var(--mod-primaria) 100%);
            margin-bottom: 14px;
        }

        .dash-hero h1 {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -.3px;
        }

        .dash-hero h1 span {
            color: white;
        }

        .dash-hero p {
            margin: 3px 0 0;
            font-size: 12.5px;
            opacity: .7;
        }

        .dash-hero-textos {
            min-width: 0;
        }

        .dash-hero-acciones {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dash-filtro-sucursal {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .dash-filtro-sucursal label {
            margin: 0;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .55);
        }

        .dash-filtro-sucursal select {
            height: 34px;
            min-width: 190px;
            padding: 0 10px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .12);
            color: #fff;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            outline: none;
        }

        .dash-filtro-sucursal select option {
            color: #1c1416;
            background: #fff;
        }

        .dash-hero-btn {
            height: 36px;
            padding: 0 13px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, .2);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dash-hero-btn:hover {
            background: rgba(255, 255, 255, .18);
        }

        .dash-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 13px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .18);
            font-size: 12px;
            font-weight: 700;
        }

        /* KPI cards */
        .dash-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .dash-kpi {
            position: relative;
            padding: 14px 16px 14px 62px;
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 12px;
            transition: transform .15s, box-shadow .15s;
        }

        .dash-kpi:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .06);
        }

        .dash-kpi .kpi-ico {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #d9232d);
            font-size: 16px;
        }

        .dash-kpi .kpi-et {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .dash-kpi .kpi-val {
            display: block;
            font-size: 21px;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
            letter-spacing: -.5px;
        }

        .dash-kpi .kpi-sub {
            display: block;
            font-size: 11px;
            color: #6b6165;
            margin-top: 3px;
            margin-top: 3px;
        }

        .dash-kpi .kpi-sub .up {
            color: #14803d;
            font-weight: 800;
        }

        .dash-kpi .kpi-sub .down {
            color: #b91c1c;
            font-weight: 800;
        }

        .dash-kpi.verde .kpi-ico {
            background: #e6f4ea;
            color: #14803d;
        }

        .dash-kpi.azul .kpi-ico {
            background: #dbeafe;
            color: #1e40af;
        }

        .dash-kpi.ambar .kpi-ico {
            background: #fef3c7;
            color: #92400e;
        }

        .dash-kpi.morado .kpi-ico {
            background: #f3e8ff;
            color: #7a2ea3;
        }

        .dash-kpi.gris .kpi-ico {
            background: #f3f4f6;
            color: #6b7280;
        }

        .dash-kpi.rojo .kpi-ico {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* Layout principal */
        .dash-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
            align-items: start;
        }

        .dash-grid.solo-main {
            grid-template-columns: 1fr;
        }

        @media (max-width: 900px) {
            .dash-grid {
                grid-template-columns: 1fr;
            }
        }

        .dash-card {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .dash-card .head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-bottom: 1px solid #ececef;
            background: linear-gradient(180deg, #fff, #fcfafa);
        }

        .dash-card .head h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #1c1416;
        }

        .dash-card .head i {
            color: var(--mod-primaria, #d9232d);
            font-size: 14px;
        }

        .dash-card .head a {
            margin-left: auto;
            font-size: 11px;
            color: var(--mod-primaria);
            text-decoration: none;
            font-weight: 700;
        }

        .dash-card .body {
            padding: 12px 14px;
        }

        /* Chart */
        .dash-chart {
            width: 100%;
            height: 220px;
        }

        .dash-chart-sm {
            width: 100%;
            height: 190px;
        }

        /* Lista de recientes */
        .dash-list {
            display: flex;
            flex-direction: column;
            max-height: 340px;
            overflow-y: auto;
        }

        .dash-list::-webkit-scrollbar {
            width: 6px;
        }

        .dash-list::-webkit-scrollbar-thumb {
            background: #e6dddf;
            border-radius: 3px;
        }

        .dash-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0eaeb;
            font-size: 12px;
        }

        .dash-item:last-child {
            border-bottom: none;
        }

        .dash-item .dt {
            flex: 1 1 auto;
            min-width: 0;
        }

        .dash-item .dt b {
            display: block;
            font-size: 12.5px;
            color: #1c1416;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-item .dt span {
            display: block;
            font-size: 10.5px;
            color: #a79ea2;
        }

        .dash-item .val {
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .dash-item .badge-chip {
            font-size: 9.5px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .dash-item .badge-chip.efectivo {
            background: #e6f4ea;
            color: #14803d;
        }

        .dash-item .badge-chip.qr {
            background: #dbeafe;
            color: #1e40af;
        }

        .dash-item .badge-chip.tarjeta {
            background: #f3e8ff;
            color: #7a2ea3;
        }

        .dash-item .badge-chip.mixto {
            background: #fef3c7;
            color: #92400e;
        }

        .dash-item .badge-chip.otro {
            background: #f3f4f6;
            color: #6b7280;
        }

        .dash-item .badge-chip.anulada {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dash-item .badge-chip.pendiente,
        .dash-item .badge-chip.preparando {
            background: #fef3c7;
            color: #92400e;
        }

        .dash-item .badge-chip.despachado,
        .dash-item .badge-chip.pendiente_pago {
            background: #dbeafe;
            color: #1e40af;
        }

        .dash-branch-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0eaeb;
            font-size: 12px;
        }

        .dash-branch-item:last-child {
            border-bottom: none;
        }

        .dash-branch-item .db-nombre {
            flex: 1 1 auto;
            min-width: 0;
        }

        .dash-branch-item .db-nombre b {
            display: block;
            color: #1c1416;
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-branch-item .db-nombre span {
            display: block;
            font-size: 10.5px;
            color: #a79ea2;
        }

        .dash-branch-item .db-barra {
            width: 92px;
            height: 6px;
            border-radius: 999px;
            background: #f1eff0;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .dash-branch-item .db-barra i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #d9232d, #f59095);
        }

        .dash-branch-item .db-val {
            width: 76px;
            text-align: right;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .dash-caja-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0eaeb;
            font-size: 12px;
        }

        .dash-caja-item:last-child {
            border-bottom: none;
        }

        .dash-caja-item .dc-info {
            flex: 1 1 auto;
            min-width: 0;
        }

        .dash-caja-item .dc-info b {
            display: block;
            color: #1c1416;
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-caja-item .dc-info span {
            display: block;
            font-size: 10.5px;
            color: #a79ea2;
        }

        .dash-caja-item .dc-val {
            text-align: right;
            white-space: nowrap;
        }

        .dash-caja-item .dc-val b {
            display: block;
            font-size: 12.5px;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
        }

        .dash-caja-item .dc-val span {
            display: block;
            font-size: 10px;
            color: #a79ea2;
        }

        /* Stock bajo */
        .dash-stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            border-bottom: 1px dashed #f0eaeb;
            font-size: 12px;
        }

        .dash-stock-item:last-child {
            border-bottom: none;
        }

        .dash-stock-item .st {
            min-width: 0;
        }

        .dash-stock-item .st b {
            display: block;
            font-weight: 700;
            color: #1c1416;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-stock-item .st span {
            font-size: 10.5px;
            color: #a79ea2;
        }

        .dash-stock-item .stk {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            background: #fee2e2;
            color: #b91c1c;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
        }

        /* Vendedores */
        .dash-vend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0eaeb;
            font-size: 12px;
        }

        .dash-vend-item:last-child {
            border-bottom: none;
        }

        .dash-vend-item .num {
            width: 22px;
            height: 22px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--mod-primaria);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .dash-vend-item .nm {
            flex: 1 1 auto;
            min-width: 0;
        }

        .dash-vend-item .nm b {
            display: block;
            font-size: 12px;
            color: #1c1416;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-vend-item .nm span {
            font-size: 10.5px;
            color: #a79ea2;
        }

        .dash-vend-item .val {
            font-weight: 800;
            color: #14803d;
            font-variant-numeric: tabular-nums;
        }

        /* Placeholder cuando no hay datos */
        .dash-empty {
            padding: 30px 14px;
            text-align: center;
            color: #a79ea2;
            font-size: 12px;
        }

        .dash-empty i {
            display: block;
            font-size: 26px;
            color: #ddc7c9;
            margin-bottom: 6px;
        }

        /* Aviso de rol */
        .dash-aviso-rol {
            background: #f8f6ff;
            border: 1px solid #e6e0f8;
            color: #5a3ea6;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12px;
            margin-bottom: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .dash-aviso-rol i {
            margin-top: 2px;
            font-size: 15px;
        }

        /* Loading */
        .dash-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 80px 20px;
            font-size: 13px;
            color: #6b6165;
        }
    </style>

    <script defer src="../modelos/dashboard/dashboard_api.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <!-- HERO -->
        <section class="dash-hero">
            <div class="dash-hero-textos">
                <h1>¡Hola, <span id="dashNombre">…</span>!</h1>
                <p>
                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars(ucfirst($fechaHoy)); ?>
                    &nbsp;·&nbsp;
                    <i class="fas fa-clock"></i> <?php echo $horaAhora; ?>
                </p>
            </div>
            <div class="dash-hero-acciones">
                <div class="dash-filtro-sucursal" id="dashFiltroSucursalWrap" style="display:none;">
                    <select id="dashSucursal">
                        <option value="">Todas las sucursales</option>
                    </select>
                </div>
                <button type="button" class="dash-hero-btn" id="dashRefrescar">
                    <i class="fas fa-rotate"></i> Actualizar
                </button>
                <div class="dash-hero-badge" id="dashBadge">
                    <i class="fas fa-user-shield"></i> <span>—</span>
                </div>
            </div>
        </section>

        <!-- Loading -->
        <div class="dash-loading" id="dashLoading">
            <span class="mini-spin"></span> Cargando tu panel…
        </div>

        <!-- Contenido dinámico -->
        <div id="dashContenido" style="display:none;">

            <div class="dash-aviso-rol" id="dashAvisoRol" style="display:none;">
                <i class="fas fa-circle-info"></i>
                <span id="dashAvisoTexto"></span>
            </div>

            <!-- KPIs -->
            <section class="dash-kpis" id="dashKpis"></section>

            <!-- Charts -->
            <div class="dash-grid" id="dashGrid">

                <!-- Columna izquierda -->
                <div>
                    <div class="dash-card" id="dashCardVentas7d">
                        <div class="head">
                            <i class="fas fa-chart-line"></i>
                            <h3 id="dashTituloVentas7d">Ventas · últimos 7 días</h3>
                        </div>
                        <div class="body">
                            <canvas class="dash-chart" id="chartVentas7d" width="800" height="220"></canvas>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardVentasHora" style="display:none;">
                        <div class="head">
                            <i class="fas fa-clock"></i>
                            <h3>Mis ventas por hora · hoy</h3>
                        </div>
                        <div class="body">
                            <canvas class="dash-chart" id="chartVentasHora" width="800" height="220"></canvas>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardRecientes">
                        <div class="head">
                            <i class="fas fa-receipt"></i>
                            <h3 id="dashTituloRecientes">Ventas recientes</h3>
                            <a href="ventas.php" id="dashLinkVentas">Ver todo</a>
                        </div>
                        <div class="body">
                            <div class="dash-list" id="dashRecientes"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardVentasSucursal" style="display:none;">
                        <div class="head">
                            <i class="fas fa-store"></i>
                            <h3>Ventas por sucursal · mes</h3>
                        </div>
                        <div class="body">
                            <div id="dashVentasSucursal"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardPedidosActivos" style="display:none;">
                        <div class="head">
                            <i class="fas fa-fire-burner"></i>
                            <h3>Pedidos activos</h3>
                            <a href="pedidos.php" id="dashLinkPedidos">Ver pedidos</a>
                        </div>
                        <div class="body">
                            <div class="dash-list" id="dashPedidosActivos"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardUtilidades" style="display:none;">
                        <div class="head">
                            <i class="fas fa-hand-holding-dollar"></i>
                            <h3>Utilidades · solo SuperAdmin</h3>
                            <a href="ventasutilidades.php">Ver detalle</a>
                        </div>
                        <div class="body">
                            <div class="dash-kpis" style="margin-bottom:0;">
                                <div class="dash-kpi verde" style="padding:12px 16px 12px 56px;">
                                    <span class="kpi-ico"><i class="fas fa-hand-holding-dollar"></i></span>
                                    <span class="kpi-et">Utilidad hoy</span>
                                    <span class="kpi-val" id="uHoy">Bs 0,00</span>
                                </div>
                                <div class="dash-kpi verde" style="padding:12px 16px 12px 56px;">
                                    <span class="kpi-ico"><i class="fas fa-calendar-week"></i></span>
                                    <span class="kpi-et">Utilidad semana</span>
                                    <span class="kpi-val" id="uSemana">Bs 0,00</span>
                                </div>
                                <div class="dash-kpi verde" style="padding:12px 16px 12px 56px;">
                                    <span class="kpi-ico"><i class="fas fa-calendar-alt"></i></span>
                                    <span class="kpi-et">Utilidad mes</span>
                                    <span class="kpi-val" id="uMes">Bs 0,00</span>
                                    <span class="kpi-sub">Margen <b id="uMargen">0 %</b></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div>
                    <div class="dash-card" id="dashCardMetodos">
                        <div class="head">
                            <i class="fas fa-chart-pie"></i>
                            <h3 id="dashTituloMetodos">Métodos de pago · mes</h3>
                        </div>
                        <div class="body">
                            <canvas class="dash-chart-sm" id="chartMetodos" width="400" height="190"></canvas>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardTopProductos">
                        <div class="head">
                            <i class="fas fa-fire"></i>
                            <h3 id="dashTituloTopProductos">Top productos · mes</h3>
                        </div>
                        <div class="body">
                            <div id="dashTopProductos"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardCajas" style="display:none;">
                        <div class="head">
                            <i class="fas fa-cash-register"></i>
                            <h3 id="dashTituloCajas">Cajas abiertas</h3>
                            <a href="arqueocaja.php" id="dashLinkCajas">Ver arqueo</a>
                        </div>
                        <div class="body">
                            <div id="dashCajas"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardVendedores" style="display:none;">
                        <div class="head">
                            <i class="fas fa-ranking-star"></i>
                            <h3>Top vendedores · mes</h3>
                        </div>
                        <div class="body">
                            <div id="dashVendedores"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardStock" style="display:none;">
                        <div class="head">
                            <i class="fas fa-triangle-exclamation"></i>
                            <h3>Stock bajo</h3>
                            <a href="productos.php" id="dashLinkProductos">Ver productos</a>
                        </div>
                        <div class="body">
                            <div id="dashStockBajo"></div>
                        </div>
                    </div>

                    <div class="dash-card" id="dashCardCompras" style="display:none;">
                        <div class="head">
                            <i class="fas fa-cart-shopping"></i>
                            <h3>Compras del mes</h3>
                        </div>
                        <div class="body">
                            <div class="dash-kpis" style="grid-template-columns:1fr 1fr;margin-bottom:0;">
                                <div class="dash-kpi ambar" style="padding:12px 16px 12px 56px;">
                                    <span class="kpi-ico"><i class="fas fa-cart-shopping"></i></span>
                                    <span class="kpi-et">Total comprado</span>
                                    <span class="kpi-val" id="cTotal">Bs 0,00</span>
                                    <span class="kpi-sub" id="cCant">0 compras</span>
                                </div>
                                <div class="dash-kpi rojo" style="padding:12px 16px 12px 56px;">
                                    <span class="kpi-ico"><i class="fas fa-file-invoice-dollar"></i></span>
                                    <span class="kpi-et">Por pagar</span>
                                    <span class="kpi-val" id="cPorPagar">Bs 0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <script>
        window.DASH_CSRF = <?php echo json_encode(rm_csrf_token()); ?>;
    </script>

    <?php if ($avisoPermisos !== ''): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-right',
                    icon: 'warning',
                    title: <?php echo json_encode($avisoPermisos, JSON_UNESCAPED_UNICODE); ?>,
                    showConfirmButton: false,
                    timer: 4500,
                    timerProgressBar: true
                });
            }
        });
    </script>
    <?php endif; ?>

    <?php include 'footer/footer.php'; ?>
</body>

</html>