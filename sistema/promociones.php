<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Promociones';

include 'validacionSesion.php';
include 'validacion_permisos.php';

rm_exigir_permiso('ver', Permisos::ITEM_VENTAS_PROMOCIONES);
$rmAcciones = rm_acciones(Permisos::ITEM_VENTAS_PROMOCIONES);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>
        window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;
    </script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        /* ---------- Tarjetas resumen ---------- */
        .pr-tarjetas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }

        .pr-card {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 12px;
            background: var(--mod-claro, #f8f7f7);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            font-size: 12.5px;
            color: var(--mod-texto, #6b6165);
        }

        .pr-card i {
            font-size: 16px;
            color: var(--mod-primaria, #d9232d);
        }

        .pr-card b {
            display: block;
            color: var(--mod-tinta, #1c1416);
            font-size: 17px;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .pr-card.verde i { color: #14803d; }
        .pr-card.azul i { color: #1e40af; }
        .pr-card.ambar i { color: #92400e; }
        .pr-card.rojo i { color: #b91c1c; }
        .pr-card.gris i { color: #6b7280; }

        /* ---------- Filtros ---------- */
        .pr-filtros {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .pr-filtros .pr-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 150px;
            min-width: 130px;
        }

        .pr-filtros label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0;
        }

        .pr-filtros input,
        .pr-filtros select {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .pr-filtros .pr-acciones {
            display: flex;
            gap: 6px;
            flex: 0 0 auto;
        }

        .pr-filtros .pr-acciones .btn-mod {
            height: 34px;
            padding: 0 13px;
            font-size: 12.5px;
        }

        #prContador {
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, .85);
            white-space: nowrap;
        }

        /* ---------- Tabla ---------- */
        .pr-tabla-wrap {
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 10px;
            padding: 10px 12px;
            overflow-x: auto;
        }

        .pr-tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            min-width: 850px;
        }

        .pr-tabla th {
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #fff;
            padding: 8px 9px;
            background: linear-gradient(135deg, var(--mod-primaria, #d9232d), var(--mod-primaria-osc, #ad1621));
            position: sticky;
            top: 0;
            z-index: 1;
            white-space: nowrap;
        }

        .pr-tabla td {
            padding: 8px 9px;
            border-bottom: 1px solid var(--mod-linea, #ececef);
            vertical-align: middle;
        }

        .pr-tabla tr:hover td {
            background: #fffafa;
        }

        .pr-tabla .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .pr-tabla .pr-acciones {
            text-align: right;
            white-space: nowrap;
        }

        .pr-prod b {
            display: block;
            color: var(--mod-tinta, #1c1416);
            font-size: 12.5px;
        }

        .pr-prod small {
            display: block;
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 11px;
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pr-objetivo {
            display: block;
            margin-top: 3px;
            color: var(--mod-texto, #6b6165);
            font-size: 11.5px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pr-descuento {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #b91c1c;
        }

        .pr-tabla small {
            font-size: 10.5px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .pr-vigencia {
            font-size: 11px;
            color: var(--mod-texto, #6b6165);
            white-space: nowrap;
        }

        .pr-flecha {
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0 4px;
        }

        /* ---------- Chips ---------- */
        .promo-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            white-space: nowrap;
        }

        .promo-chip.vigente {
            background: #e6f4ea;
            color: #14803d;
        }

        .promo-chip.programada {
            background: #dbeafe;
            color: #1e40af;
        }

        .promo-chip.vencida {
            background: #fee2e2;
            color: #b91c1c;
        }

        .promo-chip.inactiva {
            background: #f3f4f6;
            color: #6b7280;
        }

        .promo-chip.alcance {
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #d9232d);
            font-size: 10px;
        }

        .pr-vacio {
            padding: 36px 12px;
            text-align: center;
            color: var(--mod-texto-tenue, #a79ea2);
            font-size: 13px;
        }

        .pr-vacio i {
            margin-right: 6px;
        }

        @media (max-width: 640px) {
            .pr-tarjetas {
                grid-template-columns: repeat(2, 1fr);
            }

            .pr-card b {
                font-size: 15px;
            }

            .pr-filtros {
                flex-direction: column;
                align-items: stretch;
            }

            .pr-filtros .pr-campo {
                flex: 1 1 100%;
            }

            .pr-filtros .pr-acciones {
                justify-content: flex-end;
            }
        }
    </style>

    <script defer src="../modelos/pos/promociones_api.js"></script>
    <script defer src="../modelos/pos/promociones.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    Promociones
                </h3>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span id="prContador">0 promociones</span>
                    <?php if ($rmAcciones['registrar']): ?>
                        <button type="button" class="btn-mod btn-mod-primario" id="btnNuevaPromocion">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <span>Nueva promoción</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">

                <!-- Resumen -->
                <div class="pr-tarjetas">
                    <div class="pr-card">
                        <i class="fa-solid fa-tags"></i>
                        <span><b id="prTotal">0</b> total</span>
                    </div>
                    <div class="pr-card verde">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><b id="prVigentes">0</b> vigentes</span>
                    </div>
                    <div class="pr-card azul">
                        <i class="fa-solid fa-clock"></i>
                        <span><b id="prProgramadas">0</b> programadas</span>
                    </div>
                    <div class="pr-card rojo">
                        <i class="fa-solid fa-circle-minus"></i>
                        <span><b id="prVencidas">0</b> vencidas</span>
                    </div>
                    <div class="pr-card ambar">
                        <i class="fa-solid fa-toggle-off"></i>
                        <span><b id="prInactivas">0</b> inactivas</span>
                    </div>
                    <div class="pr-card gris">
                        <i class="fa-solid fa-toggle-on"></i>
                        <span><b id="prActivas">0</b> activas</span>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="pr-filtros">
                    <div class="pr-campo">
                        <label for="prBuscar">Buscar</label>
                        <input type="search" id="prBuscar" placeholder="Nombre, descripción o producto…" autocomplete="off" />
                    </div>
                    <div class="pr-campo">
                        <label for="prFiltroEstado">Situación</label>
                        <select id="prFiltroEstado">
                            <option value="">Todas</option>
                            <option value="vigente">Vigentes</option>
                            <option value="programada">Programadas</option>
                            <option value="vencida">Vencidas</option>
                            <option value="inactiva">Inactivas</option>
                        </select>
                    </div>
                    <div class="pr-campo">
                        <label for="prFiltroAlcance">Alcance</label>
                        <select id="prFiltroAlcance">
                            <option value="">Todos</option>
                            <option value="producto">Producto</option>
                            <option value="categoria">Categoría</option>
                        </select>
                    </div>
                    <div class="pr-acciones">
                        <button type="button" class="btn-mod btn-mod-primario" id="prFiltrar">
                            <i class="fa-solid fa-filter"></i> Filtrar
                        </button>
                        <button type="button" class="btn-mod btn-mod-claro" id="prLimpiar">
                            <i class="fa-solid fa-rotate-left"></i> Limpiar
                        </button>
                    </div>
                </div>

                <!-- Carga + tabla -->
                <div class="tabla-carga visible" id="prCarga" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando promociones…</span>
                </div>

                <div class="pr-tabla-wrap">
                    <table class="pr-tabla">
                        <thead>
                            <tr>
                                <th>Promoción</th>
                                <th>Alcance</th>
                                <th>Descuento</th>
                                <th class="num">Prioridad</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th style="text-align:right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="prBody">
                            <tr>
                                <td colspan="7" class="pr-vacio">
                                    <i class="fa-solid fa-tags"></i> Cargando promociones…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </section>
    </main>

    <?php include '../modelos/pos/modalPromocion.php'; ?>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>
