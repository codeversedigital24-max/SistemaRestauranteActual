<?php

/**
 * =====================================================
 * MENÚ PRINCIPAL  ·  sistema/menu/menu_nav.php
 * -----------------------------------------------------
 * Se pinta SÓLO lo que el usuario tiene privilegio de ver.
 *
 * Niveles:
 *   módulo   (permisos)         → grupo principal
 *   submenú  (sub_permisos)     → desplegable anidado
 *   ítem     (detalle_permisos) → enlace a la página
 *
 * La visibilidad se resuelve con rm_puede('ver', ITEM).
 * Si no hay privilegios, el menú queda vacío (sólo Dashboard).
 * =====================================================
 */

// Grupos activos para resaltar el menú
$grupoCajas    = ['caja', 'arqueocaja'];                          // dropdown Cajas
$grupoVentas   = ['ventas', 'promociones'];                        // dropdown Ventas
$grupoCompras  = ['compras', 'nuevacompra', 'cuentas_pagar', 'kardex'];
$grupoConfig   = ['configuracion', 'mesas', 'sucursales', 'finanzas'];
$grupoReportes = [
    'ventasxfechas',
    'ventasutilidades',
    'comprasxfechas',
    'productosvendidos',
    'productosstock',
    'pedidosxfechas',
];
?>
<div class="navbar-menu" id="navbarMenu">

    <!-- ═══════════════════════════════════════════════════
         DASHBOARD (siempre disponible con sesión válida)
    ═══════════════════════════════════════════════════ -->
    <a href="dashboard.php" class="nav-item <?php echo ($pagina_actual == 'dashboard') ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i> Dashboard
    </a>

    <!-- ═══════════════════════════════════════════════════
         PUNTO DE VENTA
    ═══════════════════════════════════════════════════ -->
    <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_POS)): ?>
        <a href="pos.php" class="nav-item <?php echo ($pagina_actual == 'pos') ? 'active' : ''; ?>">
            <i class="fas fa-headset"></i> Punto de venta
        </a>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         PEDIDOS
    ═══════════════════════════════════════════════════ -->
    <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_PEDIDOS)): ?>
        <a href="pedidos.php" class="nav-item <?php echo ($pagina_actual == 'pedidos') ? 'active' : ''; ?>">
            <i class="fas fa-pencil-alt"></i> Pedidos
        </a>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         CAJAS (Cajas + Arqueo de caja)
    ═══════════════════════════════════════════════════ -->
    <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_CAJAS) || rm_puede('ver', Permisos::ITEM_VENTAS_ARQUEO)): ?>
        <div class="nav-dropdown">
            <button type="button"
                    class="nav-dropdown-trigger <?php echo in_array($pagina_actual, $grupoCajas, true) ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i> Cajas
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="nav-dropdown-menu">
                <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_CAJAS)): ?>
                    <a href="caja.php" class="dropdown-item">
                        <i class="fas fa-cash-register"></i> Cajas
                    </a>
                <?php endif; ?>
                <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_ARQUEO)): ?>
                    <a href="arqueocaja.php" class="dropdown-item">
                        <i class="fas fa-lock-open"></i> Arqueo de caja
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         VENTAS (solo historial)
    ═══════════════════════════════════════════════════ -->
    <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_HISTORIAL) || rm_puede('ver', Permisos::ITEM_VENTAS_PROMOCIONES)): ?>
        <div class="nav-dropdown">
            <button type="button"
                    class="nav-dropdown-trigger <?php echo in_array($pagina_actual, $grupoVentas, true) ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Ventas
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="nav-dropdown-menu">
                <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_HISTORIAL)): ?>
                    <a href="ventas.php" class="dropdown-item">
                        <i class="fas fa-receipt"></i> Lista de ventas
                    </a>
                <?php endif; ?>
                <?php if (rm_puede('ver', Permisos::ITEM_VENTAS_PROMOCIONES)): ?>
                    <a href="promociones.php" class="dropdown-item">
                        <i class="fas fa-tags"></i> Promociones
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         PRODUCTOS (ítem directo)
    ═══════════════════════════════════════════════════ -->
    <?php if (rm_puede('ver', Permisos::ITEM_INV_PRODUCTOS)): ?>
        <a href="productos.php" class="nav-item <?php echo ($pagina_actual == 'productos') ? 'active' : ''; ?>">
            <i class="fas fa-burger"></i> Productos
        </a>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         COMPRAS
    ═══════════════════════════════════════════════════ -->
    <?php if (
        rm_puede('ver', Permisos::ITEM_INV_COMPRAS)
        || rm_puede('ver', Permisos::ITEM_INV_NUEVA_COMPRA)
        || rm_puede('ver', Permisos::ITEM_INV_CUENTAS_PAGAR)
        || rm_puede('ver', Permisos::ITEM_INV_KARDEX)
    ): ?>
        <div class="nav-dropdown">
            <button type="button"
                    class="nav-dropdown-trigger <?php echo in_array($pagina_actual, $grupoCompras, true) ? 'active' : ''; ?>">
                <i class="fas fa-cart-shopping"></i> Compras
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="nav-dropdown-menu">
                <?php if (rm_puede('ver', Permisos::ITEM_INV_COMPRAS)): ?>
                    <a href="compras.php" class="dropdown-item">
                        <i class="fas fa-list"></i> Historial de compras
                    </a>
                <?php endif; ?>
                <?php if (rm_puede('ver', Permisos::ITEM_INV_NUEVA_COMPRA)): ?>
                    <a href="nuevacompra.php" class="dropdown-item">
                        <i class="fas fa-cart-plus"></i> Nueva compra
                    </a>
                <?php endif; ?>
                <?php if (rm_puede('ver', Permisos::ITEM_INV_CUENTAS_PAGAR)): ?>
                    <a href="cuentas_pagar.php" class="dropdown-item">
                        <i class="fas fa-file-invoice-dollar"></i> Cuentas por pagar
                    </a>
                <?php endif; ?>
                <?php if (rm_puede('ver', Permisos::ITEM_INV_KARDEX)): ?>
                    <div class="dropdown-divider"></div>
                    <a href="kardex.php" class="dropdown-item">
                        <i class="fas fa-clipboard-list"></i> Kardex
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         REPORTES
    ═══════════════════════════════════════════════════ -->
    <?php if (
        rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS)
        || rm_puede('ver', Permisos::ITEM_REP_UTILIDADES)
        || rm_puede('ver', Permisos::ITEM_REP_COMPRAS_FECHAS)
    ): ?>
        <div class="nav-dropdown">
            <button type="button"
                    class="nav-dropdown-trigger <?php echo in_array($pagina_actual, $grupoReportes, true) ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Reportes
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>

            <div class="nav-dropdown-menu">

                <!-- Reportes > Ventas -->
                <?php if (
                    rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS)
                    || rm_puede('ver', Permisos::ITEM_REP_UTILIDADES)
                ): ?>
                    <div class="dropdown-submenu">
                        <button type="button" class="dropdown-submenu-trigger">
                            <i class="fas fa-cash-register"></i> Ventas
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </button>
                        <div class="dropdown-submenu-menu">
                            <?php if (rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS)): ?>
                                <a href="ventasxfechas.php" class="dropdown-item">
                                    <i class="fas fa-calendar-days"></i> Ventas por fechas
                                </a>
                            <?php endif; ?>
                            <?php if (rm_puede('ver', Permisos::ITEM_REP_UTILIDADES)): ?>
                                <a href="ventasutilidades.php" class="dropdown-item">
                                    <i class="fas fa-hand-holding-dollar"></i> Utilidades
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reportes > Productos -->
                <?php if (rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS)): ?>
                    <div class="dropdown-submenu">
                        <button type="button" class="dropdown-submenu-trigger">
                            <i class="fas fa-boxes-stacked"></i> Productos
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </button>
                        <div class="dropdown-submenu-menu">
                            <a href="productosvendidos.php" class="dropdown-item">
                                <i class="fas fa-fire"></i> Productos más vendidos
                            </a>
                            <a href="productosstock.php" class="dropdown-item">
                                <i class="fas fa-boxes-stacked"></i> Productos en stock
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reportes > Pedidos -->
                <?php if (rm_puede('ver', Permisos::ITEM_REP_VENTAS_FECHAS)): ?>
                    <div class="dropdown-submenu">
                        <button type="button" class="dropdown-submenu-trigger">
                            <i class="fas fa-clipboard-list"></i> Pedidos
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </button>
                        <div class="dropdown-submenu-menu">
                            <a href="pedidosxfechas.php" class="dropdown-item">
                                <i class="fas fa-calendar-days"></i> Pedidos por fechas
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reportes > Compras -->
                <?php if (rm_puede('ver', Permisos::ITEM_REP_COMPRAS_FECHAS)): ?>
                    <div class="dropdown-divider"></div>
                    <a href="comprasxfechas.php" class="dropdown-item">
                        <i class="fas fa-calendar-days"></i> Compras por fechas
                    </a>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         USUARIOS
    ═══════════════════════════════════════════════════ -->
    <?php if (rm_puede('ver', Permisos::ITEM_SEGURIDAD_USUARIOS)): ?>
        <a href="usuarios.php" class="nav-item <?php echo ($pagina_actual == 'usuarios') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Usuarios
        </a>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════
         CONFIGURACIÓN
    ═══════════════════════════════════════════════════ -->
    <?php if (
        rm_puede('ver', Permisos::ITEM_CONFIG_EMPRESA)
        || rm_puede('ver', Permisos::ITEM_CONFIG_MESAS)
        || rm_puede('ver', Permisos::ITEM_CONFIG_SUCURSALES)
        || rm_puede('ver', Permisos::ITEM_CONFIG_FINANZAS)
    ): ?>
        <div class="nav-dropdown" id="configDropdown">
            <button type="button"
                    class="nav-dropdown-trigger <?php echo in_array($pagina_actual, $grupoConfig, true) ? 'active' : ''; ?>"
                    id="configTrigger">
                <i class="fas fa-cog"></i> Configuración
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>

            <div class="nav-dropdown-menu" id="configMenu">

                <!-- Configuración > General -->
                <?php if (
                    rm_puede('ver', Permisos::ITEM_CONFIG_EMPRESA)
                    || rm_puede('ver', Permisos::ITEM_CONFIG_MESAS)
                    || rm_puede('ver', Permisos::ITEM_CONFIG_SUCURSALES)
                ): ?>
                    <div class="dropdown-submenu">
                        <button type="button" class="dropdown-submenu-trigger">
                            <i class="fas fa-globe"></i> General
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </button>
                        <div class="dropdown-submenu-menu">
                            <?php if (rm_puede('ver', Permisos::ITEM_CONFIG_EMPRESA)): ?>
                                <a href="configuracion.php" class="dropdown-item">
                                    <i class="fas fa-building"></i> Empresa
                                </a>
                            <?php endif; ?>
                            <?php if (rm_puede('ver', Permisos::ITEM_CONFIG_MESAS)): ?>
                                <a href="mesas.php" class="dropdown-item">
                                    <i class="fas fa-chair"></i> Mesas
                                </a>
                            <?php endif; ?>
                            <?php if (rm_puede('ver', Permisos::ITEM_CONFIG_SUCURSALES)): ?>
                                <a href="sucursales.php" class="dropdown-item">
                                    <i class="fas fa-store"></i> Sucursales
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Configuración > Finanzas -->
                <?php if (rm_puede('ver', Permisos::ITEM_CONFIG_FINANZAS)): ?>
                    <div class="dropdown-submenu">
                        <button type="button" class="dropdown-submenu-trigger">
                            <i class="fas fa-coins"></i> Finanzas
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </button>
                        <div class="dropdown-submenu-menu">
                            <a href="finanzas.php" class="dropdown-item">
                                <i class="fas fa-money-bill-wave"></i> Datos financieros
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endif; ?>

</div>