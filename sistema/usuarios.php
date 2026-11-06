<?php

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Usuarios';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Usuarios (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_SEGURIDAD_USUARIOS);
$rmAcciones = rm_acciones(Permisos::ITEM_SEGURIDAD_USUARIOS);

// Catálogos (los pinta PHP: 0 viajes extra)
$roles      = [];
$sucursales = [];
try {
    $pdo = Conexion::getInstance()->getConnection();

    $roles = $pdo
        ->query("SELECT id_rol, nombre_rol FROM roles WHERE estado = 1 ORDER BY id_rol ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    // Alcance: sólo el SuperAdministrador ve todas las sucursales.
    $esSuperAdmin = rm_es_superadmin();
    $idSucursalSesion = (int) ($usuario['sucursal']['id'] ?? 0);

    if ($esSuperAdmin) {
        $sucursales = $pdo
            ->query("SELECT id_sucursal, nombre_sucursal FROM sucursales WHERE estado = 1 ORDER BY nombre_sucursal ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($idSucursalSesion > 0) {
        $stmtSuc = $pdo->prepare(
            "SELECT id_sucursal, nombre_sucursal FROM sucursales
              WHERE estado = 1 AND id_sucursal = :id
              ORDER BY nombre_sucursal ASC"
        );
        $stmtSuc->execute([':id' => $idSucursalSesion]);
        $sucursales = $stmtSuc->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('usuarios.php catálogos: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <!-- Token CSRF: sin este meta TODAS las llamadas de usuarios_api.js devuelven 403 -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <!-- Card única: header con título + acción, body con tabla -->
        <section class="content-card">

            <!-- Cabecera: título a la izquierda, acción a la derecha -->
            <div class="card-header">
                <h3>
                    <i class="fas fa-users" aria-hidden="true"></i>
                    Usuarios del sistema
                </h3>
                <?php if ($rmAcciones['registrar']): ?>
                    <button type="button" class="btn-mod btn-mod-primario" id="btnNuevo">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>Nuevo usuario</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Cuerpo: loader + tabla -->
            <div class="card-body">

                <div class="tabla-carga" id="tablaCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando usuarios…</span>
                </div>

                <div class="table-responsive">
                    <table id="tablaUsuarios" class="display nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre completo</th>
                                <th>Nro Doc.</th>
                                <th>WhatsApp</th>
                                <th>Usuario</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Sucursal</th>
                                <th>Estado</th>
                                <th title="Acciones" aria-label="Acciones">…</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </section>

    </main>

    <!-- ============================================
         MODAL: NUEVO / MODIFICAR USUARIO
         ============================================ -->
    <?php
    include '../modelos/usuarios/modalNuevoUsuario.php';   // alta / edición
    include '../modelos/usuarios/modalPermisos.php';      // privilegios 
    ?>
    <!-- Scripts con defer: el HTML pinta primero, después cargan -->
    <script defer src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script defer src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script defer src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script defer src="../modelos/usuarios/usuarios_api.js"></script>
    <script defer src="../modelos/usuarios/usuarios.js"></script>
    <!-- Utilidad de sistema + pie con menú -->
    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>