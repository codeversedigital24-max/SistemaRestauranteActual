<?php

/**
 * =====================================================
 * CAJAS  (sistema/caja.php)
 * -----------------------------------------------------
 *  Paso 1 del flujo de caja:
 *   · Crear las cajas de cada sucursal (una o más).
 *   · Asignarlas a un personal (cajero) de esa sucursal.
 *
 *  Después, en arquecaja.php, cada usuario abre SU caja
 *  para empezar a vender.
 *
 *  Datos  : ../modelos/cajas/obtener_cajas.php (1 fetch)
 *  API    : ../modelos/cajas/cajas_api.php
 *  Red    : ../modelos/cajas/cajas_api.js
 *  UI     : ../modelos/cajas/cajas.js
 *  Modelo : ../modelos/cajas/Caja.php
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Cajas';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Cajas (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_VENTAS_CAJAS);
$rmAcciones = rm_acciones(Permisos::ITEM_VENTAS_CAJAS);

// Catálogos y contadores (se pintan sin JS)
$sucursales = [];
$resumen    = ['total' => 0, 'activas' => 0, 'asignadas' => 0, 'libres' => 0];

// Alcance: sólo el SuperAdministrador ve todas las sucursales.
$esSuperAdmin = rm_es_superadmin();
$idSucursalSesion = (int) ($usuario['sucursal']['id'] ?? 0);

try {
    $pdo = Conexion::getInstance()->getConnection();

    require_once '../modelos/cajas/Caja.php';

    $modelo = new Caja($pdo);

    // Sucursal del alcance: null = todas (SuperAdmin); 0 = ninguna.
    $idSucursalAlcance = $esSuperAdmin ? null : $idSucursalSesion;

    // Cajero sin privilegio `modificar` en cajas ni arqueos: sólo SU caja.
    $puedeModificarCajas = rm_puede('modificar', Permisos::ITEM_VENTAS_CAJAS)
        || rm_puede('modificar', Permisos::ITEM_VENTAS_ARQUEO);

    $idUsuarioAsignado = (!$esSuperAdmin && !$puedeModificarCajas)
        ? (int) $_SESSION['id_usuario']
        : null;

    $sucursales = $modelo->sucursales($idSucursalAlcance);

    $resumenCaja = $modelo->resumen($idSucursalAlcance, $idUsuarioAsignado);
    $resumen['total']     = $resumenCaja['total'];
    $resumen['activas']   = $resumenCaja['activas'];
    $resumen['asignadas'] = $resumenCaja['asignadas'];
    $resumen['libres']    = $resumenCaja['libres'];
} catch (Throwable $e) {
    error_log('caja.php catálogos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>
        window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;
    </script>
    <!-- Token CSRF: sin este meta TODAS las llamadas de cajas_api.js devuelven 403 -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .content-card {
            border-top: 3px solid var(--mod-primaria, #d9232d);
        }

        /* Tarjetas de resumen */
        .caja-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .caja-mini {
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

        .caja-mini i {
            color: var(--mod-primaria, #d9232d);
            font-size: 15px;
        }

        .caja-mini b {
            color: var(--mod-tinta, #1c1416);
            font-size: 15px;
            font-variant-numeric: tabular-nums;
        }

        /* Barra de filtro */
        .caja-barra {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding: 8px 11px;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
        }

        .caja-barra label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: var(--mod-texto, #6b6165);
            margin: 0;
        }

        .caja-barra select {
            height: 32px;
            min-width: 230px;
            padding: 0 30px 0 10px;
            border: 1px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        #cajaFiltroTexto {
            font-size: 12px;
            color: var(--mod-texto-tenue, #a79ea2);
            margin-left: auto;
        }

        /* Botón asignar de la fila */
        .btn-tabla.asignar {
            color: var(--mod-azul, #1f6fd0);
            border-color: #cfe3fb;
            background: var(--mod-azul-fondo, #e8f2fe);
        }

        .btn-tabla.asignar:hover {
            background: var(--mod-azul, #1f6fd0);
            border-color: var(--mod-azul, #1f6fd0);
            color: #fff;
        }

        /* Historial dentro del SweetAlert */
        .caja-hist {
            margin-top: 12px;
            text-align: left;
        }

        .caja-hist-titulo {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--mod-texto, #6b6165);
            margin-bottom: 6px;
        }

        .caja-hist-fila {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2px 10px;
            padding: 7px 10px;
            border-left: 3px solid var(--mod-linea-fuerte, #d9d9e0);
            background: #fafafa;
            border-radius: 0 7px 7px 0;
            margin-bottom: 5px;
        }

        .caja-hist-fila.activa {
            border-left-color: var(--mod-verde, #14803d);
            background: var(--mod-verde-fondo, #e5f7ec);
        }

        .caja-hist-fila .chf-quien {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--mod-tinta, #1c1416);
        }

        .caja-hist-fila .chf-fecha {
            font-size: 11px;
            color: var(--mod-texto, #6b6165);
            font-variant-numeric: tabular-nums;
        }

        .caja-hist-fila .chf-obs {
            grid-column: 1 / -1;
            font-size: 11.5px;
            color: var(--mod-texto-tenue, #a79ea2);
            font-style: italic;
        }

        .caja-hist-vacio {
            font-size: 12px;
            color: var(--mod-texto-tenue, #a79ea2);
            padding: 6px 0;
        }
    </style>

    <!-- Scripts con defer: el HTML pinta primero, después cargan -->
    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/cajas/cajas_api.js"></script>
    <script defer src="../modelos/cajas/cajas.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-cash-register" aria-hidden="true"></i>
                    Cajas
                </h3>
                <?php if ($rmAcciones['registrar']): ?>
                    <button type="button" class="btn-mod btn-mod-primario" id="btnNuevaCaja">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>Nueva caja</span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="card-body">

                <!-- Resumen rápido -->
                <div class="caja-tarjetas">
                    <div class="caja-mini">
                        <i class="fas fa-cash-register"></i>
                        <span><b id="cajasTotal"><?php echo $resumen['total']; ?></b> cajas</span>
                    </div>
                    <div class="caja-mini">
                        <i class="fas fa-circle-check"></i>
                        <span><b id="cajasActivas"><?php echo $resumen['activas']; ?></b> activas</span>
                    </div>
                    <div class="caja-mini">
                        <i class="fas fa-user-check"></i>
                        <span><b id="cajasAsignadas"><?php echo $resumen['asignadas']; ?></b> asignadas</span>
                    </div>
                    <div class="caja-mini">
                        <i class="fas fa-user-slash"></i>
                        <span><b id="cajasLibres"><?php echo $resumen['libres']; ?></b> libres</span>
                    </div>
                </div>

                <!-- Filtro por sucursal -->
                <div class="caja-barra">
                    <label for="filtroSucursal">
                        <i class="fa-solid fa-store"></i> Sucursal
                    </label>
                    <select id="filtroSucursal">
                        <?php if ($esSuperAdmin): ?>
                            <option value="">Todas las sucursales</option>
                        <?php endif; ?>
                        <?php foreach ($sucursales as $suc): ?>
                            <option value="<?php echo (int) $suc['id_sucursal']; ?>">
                                <?php echo htmlspecialchars($suc['nombre_sucursal']); ?>
                                <?php echo $suc['ciudad'] ? ' · ' . htmlspecialchars($suc['ciudad']) : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="cajaFiltroTexto">...</span>
                </div>

                <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando cajas...</span>
                </div>

                <div class="table-responsive">
                    <table id="tablaCajas" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Caja</th>
                                <th>Sucursal</th>
                                <th>Responsable</th>
                                <th>Monto sugerido</th>
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
         MODALES: CAJA + ASIGNACIÓN
         ============================================ -->
    <?php include '../modelos/cajas/modalCaja.php'; ?>
    <?php include '../modelos/cajas/modalAsignarCaja.php'; ?>

    <!-- Utilidad de sistema + pie con menú -->
    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>