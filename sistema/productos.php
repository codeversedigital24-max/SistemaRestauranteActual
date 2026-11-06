<?php

/**
 * =====================================================
 * PRODUCTOS  (sistema/productos.php)
 * -----------------------------------------------------
 *  Catálogo de productos del restaurante:
 *  precio, descuento, oferta, stock y disponibilidad.
 *
 *  Datos  : ../modelos/productos/obtener_productos.php (1 fetch)
 *  API    : ../modelos/productos/productos_api.php
 *  Red    : ../modelos/productos/productos_api.js
 *  UI     : ../modelos/productos/productos.js
 *  Modelo : ../modelos/productos/Producto.php
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Productos';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Productos (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_INV_PRODUCTOS);
$rmAcciones = rm_acciones(Permisos::ITEM_INV_PRODUCTOS);

// Catálogos y contadores (se pintan sin JS)
$categorias = [];
$total      = 0;
$ofertas    = 0;
$agotados   = 0;

try {
    $pdo = Conexion::getInstance()->getConnection();

    $categorias = $pdo
        ->query("SELECT id_categoria, nombre_categoria, icono
                 FROM categorias_productos
                 WHERE estado = 1
                 ORDER BY orden, nombre_categoria")
        ->fetchAll(PDO::FETCH_ASSOC);

    $total = (int) $pdo
        ->query("SELECT COUNT(*) FROM productos WHERE estado = 1")
        ->fetchColumn();

    $ofertas = (int) $pdo
        ->query("SELECT COUNT(*) FROM productos WHERE estado = 1 AND oferta_activa = 1")
        ->fetchColumn();

    $agotados = (int) $pdo
        ->query("SELECT COUNT(*) FROM productos
                 WHERE estado = 1 AND control_stock = 1 AND stock <= 0")
        ->fetchColumn();
} catch (Throwable $e) {
    error_log('productos.php catálogos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <!-- Token CSRF: sin este meta TODAS las llamadas de productos_api.js devuelven 403 -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        /* Precio con tachado cuando hay oferta vigente */
        .prod-precio {
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .prod-precio strong {
            color: var(--mod-tinta);
            font-weight: 800;
        }

        .prod-precio s {
            color: var(--mod-texto-tenue);
            font-size: 11.5px;
        }

        /* Botón de disponibilidad en la fila */
        .btn-tabla.disp {
            color: var(--mod-verde);
            border-color: #bfe6cd;
            background: var(--mod-verde-fondo);
        }

        .btn-tabla.disp:hover {
            background: var(--mod-verde);
            border-color: var(--mod-verde);
            color: #fff;
        }

        /* Mini tarjetas de resumen */
        .prod-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .prod-mini {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            font-size: 12.5px;
            color: var(--mod-texto, #6b6165);
        }

        .prod-mini i {
            color: var(--mod-primaria, #d9232d);
            font-size: 15px;
        }

        .prod-mini b {
            color: var(--mod-tinta, #1c1416);
            font-size: 15px;
            font-variant-numeric: tabular-nums;
        }

        .prod-fila {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prod-fila-txt {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .prod-thumb {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            object-fit: cover;
            background: #f4f4f6;
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #b5b5b8;
            font-size: 16px;
        }

        .prod-thumb-vacia {
            border: 1px dashed #d5d5d9;
        }

        .prod-detalle-gal {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .prod-detalle-gal img {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
        }
    </style>

    <!-- Scripts con defer: el HTML pinta primero, después cargan -->
    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/productos/productos_api.js"></script>
    <script defer src="../modelos/productos/productos.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">

            <div class="card-header">
                <h3>
                    <i class="fas fa-burger" aria-hidden="true"></i>
                    Productos del restaurante
                </h3>
                <?php if ($rmAcciones['registrar']): ?>
                    <button type="button" class="btn-mod btn-mod-primario" id="btnNuevoProducto">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>Nuevo producto</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body">

                <!-- Resumen rápido -->
                <div class="prod-tarjetas">
                    <div class="prod-mini">
                        <i class="fas fa-utensils"></i>
                        <span><b><?php echo $total; ?></b> activos</span>
                    </div>
                    <div class="prod-mini">
                        <i class="fas fa-fire"></i>
                        <span><b><?php echo $ofertas; ?></b> en oferta</span>
                    </div>
                    <div class="prod-mini">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><b><?php echo $agotados; ?></b> agotados</span>
                    </div>
                    <div class="prod-mini">
                        <i class="fas fa-layer-group"></i>
                        <span><b><?php echo count($categorias); ?></b> categorías</span>
                    </div>
                </div>

                <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando productos...</span>
                </div>

                <div class="table-responsive">
                    <table id="tablaProductos" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Oferta</th>
                                <th>Stock</th>
                                <th>Disponible</th>
                                <th>Estado</th>
                                <th title="Acciones" aria-label="Acciones">...</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </section>

    </main>

    <!-- ============================================
         MODAL: NUEVO / MODIFICAR PRODUCTO
         ============================================ -->
    <?php include '../modelos/productos/modalProducto.php'; ?>

    <!-- Utilidad de sistema + pie con menú -->
    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>