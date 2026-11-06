<?php

/**
 * =====================================================
 * SUCURSALES  (sistema/sucursales.php)
 * -----------------------------------------------------
 *  Tabla DataTable + modal (alta / edición) + acciones.
 *  Estilo: assets/css/modulos.css (vía cabecera)
 *
 *  Datos  : ../modelos/sucursales/obtener_sucursales.php (1 fetch)
 *  API    : ../modelos/sucursales/sucursales_api.php (obtener|crear|actualizar|eliminar)
 *  Red    : ../modelos/sucursales/sucursales_api.js
 *  UI     : ../modelos/sucursales/sucursales.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Sucursales';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Sucursales (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_CONFIG_SUCURSALES);
$rmAcciones = rm_acciones(Permisos::ITEM_CONFIG_SUCURSALES);

// Contadores de cabecera (un viaje, se pintan sin JS)
$activas = 0;
$ciudades = 0;

// Alcance: sólo el SuperAdministrador ve todas las sucursales.
$esSuperAdmin = rm_es_superadmin();
$idSucursalSesion = (int) ($usuario['sucursal']['id'] ?? 0);

try {
    $pdo = Conexion::getInstance()->getConnection();

    if ($esSuperAdmin) {
        $activas = (int) $pdo->query('SELECT COUNT(*) FROM sucursales WHERE estado = 1')->fetchColumn();
        $ciudades = (int) $pdo->query('SELECT COUNT(DISTINCT ciudad) FROM sucursales WHERE estado = 1 AND ciudad IS NOT NULL')->fetchColumn();
    } elseif ($idSucursalSesion > 0) {
        $stmtActivas = $pdo->prepare('SELECT COUNT(*) FROM sucursales WHERE estado = 1 AND id_sucursal = :id');
        $stmtActivas->execute([':id' => $idSucursalSesion]);
        $activas = (int) $stmtActivas->fetchColumn();

        $stmtCiudades = $pdo->prepare(
            'SELECT COUNT(DISTINCT ciudad) FROM sucursales
              WHERE estado = 1 AND ciudad IS NOT NULL AND id_sucursal = :id'
        );
        $stmtCiudades->execute([':id' => $idSucursalSesion]);
        $ciudades = (int) $stmtCiudades->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('sucursales.php contadores: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <!-- Token CSRF: sin este meta TODAS las llamadas de sucursales_api.js devuelven 403 -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        /* Particularidades de esta página (todo lo demás vive en modulos.css) */
        .content-card {
            border-top: 3px solid var(--mod-primaria, #d9232d);
        }

        .suc-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .suc-mini {
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

        .suc-mini i {
            color: var(--mod-primaria, #d9232d);
            font-size: 15px;
        }

        .suc-mini b {
            color: var(--mod-tinta, #1c1416);
            font-size: 15px;
            font-variant-numeric: tabular-nums;
        }
    </style>

    <!-- Scripts con defer: el HTML pinta primero, después cargan -->
    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/sucursales/sucursales_api.js"></script>
    <script defer src="../modelos/sucursales/sucursales.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <!-- Encabezado con acción -->
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-store" aria-hidden="true"></i>
                    Sucursales
                </h3>
                <?php if ($rmAcciones['registrar'] && $esSuperAdmin): ?>
                    <button type="button" class="btn-mod btn-mod-primario" id="btnNueva">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>Nueva sucursal</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body">

                <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando sucursales...</span>
                </div>

                <div class="table-responsive">
                    <table id="tablaSucursales" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Dirección</th>
                                <th>Ciudad</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Usuarios</th>
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
         MODAL: NUEVA / MODIFICAR SUCURSAL
         ============================================ -->
    <?php
    include '../modelos/sucursales/modalSucursal.php';
    ?>

    <!-- Utilidad de sistema + pie con menú -->
    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>