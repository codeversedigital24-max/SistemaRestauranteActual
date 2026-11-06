<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Pedidos';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Pedidos (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_VENTAS_PEDIDOS);
$rmAcciones = rm_acciones(Permisos::ITEM_VENTAS_PEDIDOS);
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
        /* Swal siempre por encima de modales */
        .swal2-container {
            z-index: 20000 !important;
        }

        /* ---------- HERO ---------- */
        .ped-hero {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            padding: 14px 18px;
            border-radius: 14px;
            color: #fff;
          background:
                radial-gradient(900px 180px at 100% -40%, var(--mod-primaria-osc), transparent 62%),
                linear-gradient(135deg, var(--mod-primaria-osc) 0%, var(--mod-primaria) 100%);
            margin-bottom: 12px;
        }

        .ped-hero h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .ped-hero p {
            margin: 2px 0 0;
            font-size: 12px;
            opacity: .65;
        }

        .ped-hero select {
            height: 36px;
            padding: 0 10px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 600;
        }

        .ped-hero select option {
            color: #1c1416;
        }

        .ped-hero .btn-refrescar {
            height: 36px;
            padding: 0 12px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, .2);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ped-hero .spacer {
            margin-left: auto;
        }

        /* ---------- TABS ---------- */
        .ped-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid #ececef;
            padding: 0 14px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .ped-tabs::-webkit-scrollbar {
            display: none;
        }

        .ped-tabs button {
            border: none;
            background: transparent;
            padding: 11px 16px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            color: #a79ea2;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .ped-tabs button.activa {
            color: var(--mod-primaria, #d9232d);
            border-bottom-color: var(--mod-primaria);
        }

        .ped-tabs button .badge-tab {
            background: var(--mod-primaria-suave);
            color: var(--mod-primaria);
            font-size: 10.5px;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 999px;
        }

        .ped-panel {
            display: none;
            padding: 14px;
        }

        .ped-panel.activo {
            display: block;
        }

        /* ---------- SALÓN ---------- */
        .ped-filtro-sal {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .ped-filtro-sal label {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .ped-filtro-sal select {
            height: 34px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1.5px solid #ececef;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
        }

        .mes-grid-ped {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }

        .mes-card-ped {
            border: 1px solid #ececef;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            text-align: left;
            display: flex;
            flex-direction: column;
            padding: 0;
            font-family: inherit;
            min-width: 0;
            transition: transform .12s, box-shadow .15s, border-color .15s;
        }

        .mes-card-ped:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, .08);
        }

        .mes-card-ped.ocupada {
            border-color: #f0c88a;
        }

        .mes-card-ped.pendiente_pago,
        .mes-card-ped.despachado {
            border-color: #7eb1f3;
            background: linear-gradient(180deg, #fff, #f4f9ff);
        }

        .mes-card-ped .mc-foto {
            height: 92px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f8f7f7, #f1eff0);
            color: #c7cdd8;
            font-size: 36px;
            overflow: hidden;
            position: relative;
        }

        .mes-card-ped .mc-foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mes-card-ped .mc-chip-estado {
            position: absolute;
            top: 6px;
            left: 6px;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 999px;
            font-weight: 800;
            text-transform: uppercase;
            color: #fff;
        }

        .mes-card-ped.libre .mc-chip-estado {
            background: #14803d;
        }

        .mes-card-ped.ocupada .mc-chip-estado {
            background: #b57a00;
        }

        .mes-card-ped.despachado .mc-chip-estado,
        .mes-card-ped.pendiente_pago .mc-chip-estado {
            background: #1e40af;
        }

        .mes-card-ped .mc-total {
            position: absolute;
            top: 6px;
            right: 6px;
            padding: 2px 8px;
            font-size: 10px;
            background: rgba(0, 0, 0, .65);
            border-radius: 999px;
            color: #fff;
            font-weight: 700;
        }

        .mes-card-ped .mc-body-ped {
            padding: 9px 11px 11px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .mes-card-ped .mc-codigo {
            font-family: monospace;
            font-size: 10.5px;
            color: #a79ea2;
            letter-spacing: .4px;
        }

        .mes-card-ped .mc-nombre {
            font-size: 13.5px;
            font-weight: 800;
            color: #1c1416;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .mes-card-ped .mc-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #6b6165;
            margin-top: 2px;
            flex-wrap: wrap;
        }

        .mes-card-ped .mc-info {
            display: flex;
            gap: 8px;
            font-size: 10.5px;
            color: #a79ea2;
            margin-top: 3px;
            flex-wrap: wrap;
        }

        .mes-card-ped .mc-info i {
            color: var(--mod-primaria);
        }

        /* ---------- COCINA ---------- */
        .cocina-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 10px;
        }

        .cocina-card {
            border: 1px solid #ececef;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .cocina-card .cc-cab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            background: linear-gradient(180deg, #fffaf0, #fff6e8);
            border-bottom: 1px solid #f0e0b4;
        }

        .cocina-card .cc-cab .cc-num {
            background: #b57a00;
            color: #fff;
            font-weight: 800;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .cocina-card .cc-cab .cc-mesa {
            font-size: 13px;
            font-weight: 800;
            color: #1c1416;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cocina-card .cc-cab .cc-hora {
            font-size: 10.5px;
            color: #a79ea2;
            margin-left: auto;
            font-family: monospace;
        }

        .cocina-card .cc-lista {
            padding: 8px 12px 10px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-height: 60px;
        }

        .cocina-card .cc-linea {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12.5px;
            color: #1c1416;
            padding-bottom: 3px;
            border-bottom: 1px dashed #f0eaeb;
        }

        .cocina-card .cc-linea:last-child {
            border-bottom: none;
        }

        .cocina-card .cc-linea .cc-cant {
            background: var(--mod-primaria-suave);
            color: var(--mod-primaria);
            font-weight: 800;
            font-size: 11px;
            padding: 1px 7px;
            border-radius: 5px;
        }

        .cocina-card .cc-pie {
            padding: 8px 12px 10px;
            border-top: 1px solid #ececef;
            display: flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .cocina-card .cc-pie button {
            height: 32px;
            padding: 0 12px;
            border-radius: 7px;
            border: 1px solid #ececef;
            background: #fff;
            font-family: inherit;
            font-size: 11.5px;
            font-weight: 700;
            color: #6b6165;
            cursor: pointer;
        }

        .cocina-card .cc-pie button.primario {
            background: #14803d;
            border-color: #14803d;
            color: #fff;
        }

        .cocina-card .cc-pie button.primario:hover {
            background: #0f6630;
        }

        .cat-vacio {
            padding: 30px 14px;
            text-align: center;
            color: #a79ea2;
            font-size: 12px;
        }

        .cat-vacio i {
            font-size: 26px;
            display: block;
            margin-bottom: 6px;
            color: #ddc7c9;
        }

        /* =========================================================
           MODAL DE PRODUCTOS (catálogo tipo POS + carrito)
           ========================================================= */
        .ped-modal-productos .modal-caja {
            width: min(1180px, 100%);
            max-width: min(1180px, 100%);
            height: min(90dvh, 900px);
            max-height: calc(100dvh - 28px);
            display: flex;
            flex-direction: column;
        }

        .ped-modal-productos .modal-cabecera {
            flex: 0 0 auto;
        }

        .ped-modal-productos .modal-cabecera h2 {
            min-width: 0;
        }

        .ped-modal-productos .modal-cabecera h2>span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ped-modal-productos .modal-cuerpo {
            display: block;
            /* anula el grid global de .modal-cuerpo */
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: 0;
        }

        .ped-cat-contenido {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
            padding: 14px 16px;
        }

        .ped-cat-filtros {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .ped-cat-buscar {
            position: relative;
            flex: 1 1 auto;
            min-width: 190px;
        }

        .ped-cat-buscar>i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a79ea2;
            font-size: 14px;
            pointer-events: none;
        }

        .ped-cat-buscar input {
            width: 100%;
            height: 42px;
            border-radius: 10px;
            border: 1.5px solid #ececef;
            background: #fbfafa;
            padding: 0 40px 0 36px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: .15s ease;
        }

        .ped-cat-buscar input:focus {
            border-color: #f0b6b9;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(217, 35, 45, .08);
        }

        .ped-cat-buscar button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #a79ea2;
            cursor: pointer;
            display: none;
            place-items: center;
        }

        .ped-cat-buscar button.visible {
            display: grid;
        }

        .ped-cat-buscar button:hover {
            background: #f7eeee;
            color: var(--mod-primaria);
        }

        .ped-cat-conteo {
            flex: 0 0 auto;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid #ececef;
            background: #fbfafa;
            color: #6b6165;
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .ped-cat-categorias {
            flex: 0 0 auto;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 2px 10px;
            margin-bottom: 2px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }

        .ped-cat-categorias::-webkit-scrollbar {
            display: none;
        }

        .ped-cat-categorias button {
            flex: 0 0 auto;
            height: 32px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1.5px solid #ececef;
            background: #fff;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            color: #6b6165;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: .14s ease;
        }

        .ped-cat-categorias button:hover {
            border-color: #e3b4b7;
            color: var(--mod-primaria);
        }

        .ped-cat-categorias button.activa {
            background: #1c1416;
            border-color: #1c1416;
            color: #fff;
            box-shadow: 0 7px 16px -9px rgba(27, 20, 22, .8);
        }

        .ped-cat-categorias button.activa i {
            color: #ff8d93;
        }

        .ped-cat-layout {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 372px;
            gap: 14px;
        }

        .ped-cat-col {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 0;
            min-width: 0;
        }

        .ped-cat-grid {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(178px, 1fr));
            grid-auto-rows: max-content;
            gap: 12px;
            overflow-y: auto;
            padding: 2px 6px 2px 2px;
            align-content: start;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .ped-cat-grid::-webkit-scrollbar {
            width: 8px;
        }

        .ped-cat-grid::-webkit-scrollbar-thumb {
            background: #e6dddf;
            border-radius: 4px;
        }

        .ped-cat-vacio {
            display: none;
            flex: 1 1 auto;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 50px 20px;
            text-align: center;
            color: #a79ea2;
            font-size: 12.5px;
        }

        .ped-cat-vacio.visible {
            display: flex;
        }

        .ped-cat-vacio i {
            font-size: 32px;
            color: #ddc7c9;
        }

        .ped-cat-vacio strong {
            color: #1c1416;
            font-size: 14px;
        }

        /* --- Tarjeta de producto (misma información que el POS) --- */
        .ped-prod {
            position: relative;
            display: flex;
            flex-direction: column;
            text-align: left;
            border-radius: 13px;
            border: 1px solid #ececef;
            background: #fff;
            padding: 0 0 10px;
            cursor: pointer;
            overflow: hidden;
            font-family: inherit;
            min-width: 0;
            transition: transform .14s ease, box-shadow .16s ease, border-color .16s ease;
        }

        .ped-prod:hover {
            transform: translateY(-2px);
            border-color: #e7d3d4;
            box-shadow: 0 14px 28px -20px rgba(40, 20, 24, .55);
        }

        .ped-prod:active {
            transform: scale(.985);
        }

        .ped-prod.agotado {
            opacity: .55;
            cursor: not-allowed;
        }

        .ped-prod-media {
            position: relative;
            height: 108px;
            flex: 0 0 auto;
            background:
                radial-gradient(120px 80px at 80% 0%, rgba(217, 35, 45, .16), transparent 70%),
                linear-gradient(145deg, #f7eef0, #f2e7e8);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .ped-prod-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            position: relative;
            z-index: 1;
        }

        .ped-prod-media .sin-imagen {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-size: 28px;
            color: #d4b3b7;
        }

        .ped-prod-badges {
            position: absolute;
            top: 7px;
            left: 7px;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .ped-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .3px;
            line-height: 1;
            text-transform: uppercase;
            color: #fff;
            background: linear-gradient(135deg, var(--mod-primaria), #ef4a52);
            box-shadow: 0 4px 10px -4px rgba(217, 35, 45, .8);
        }

        .ped-badge.promo {
            background: linear-gradient(135deg, #7a2ea3, #a544d6);
            box-shadow: 0 4px 10px -4px rgba(122, 46, 163, .7);
        }

        .ped-prod-cuerpo {
            padding: 9px 10px 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .ped-prod-nombre {
            font-size: 12.5px;
            font-weight: 800;
            color: #1c1416;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 31px;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .ped-prod-codigo {
            font-size: 10px;
            font-weight: 600;
            color: #a79ea2;
            letter-spacing: .3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ped-prod-precios {
            display: flex;
            align-items: baseline;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 2px;
            min-width: 0;
        }

        .ped-prod-precio {
            font-size: 15px;
            font-weight: 900;
            color: #1c1416;
            letter-spacing: -.3px;
            font-variant-numeric: tabular-nums;
        }

        .ped-prod-antes {
            font-size: 10.5px;
            color: #a79ea2;
            text-decoration: line-through;
            font-variant-numeric: tabular-nums;
        }

        .ped-prod-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-top: auto;
            padding-top: 6px;
            padding-right: 32px;
            font-size: 9.5px;
            font-weight: 700;
            color: #a79ea2;
            white-space: nowrap;
            min-width: 0;
        }

        .ped-prod-meta>span {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ped-prod-meta .stock-ok {
            color: #12803c;
        }

        .ped-prod-meta .stock-bajo {
            color: #b57a00;
        }

        .ped-prod-meta .stock-cero {
            color: var(--mod-primaria);
        }

        .ped-prod-agregar {
            position: absolute;
            right: 8px;
            bottom: 8px;
            width: 29px;
            height: 29px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 12px;
            background: linear-gradient(135deg, var(--mod-primaria), #ee3c45);
            box-shadow: 0 8px 16px -7px rgba(217, 35, 45, .8);
            z-index: 2;
        }

        /* --- Carrito --- */
        .ped-carrito {
            display: flex;
            flex-direction: column;
            min-height: 0;
            min-width: 0;
            border: 1px solid #ececef;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 16px 38px -28px rgba(30, 18, 22, .5);
        }

        .ped-carrito-cab {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px 12px;
            border-bottom: 1px solid #ececef;
            background: linear-gradient(180deg, #fff, #fcfafa);
        }

        .ped-carrito-cab>i {
            width: 32px;
            height: 32px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--mod-primaria-suave);
            color: var(--mod-primaria);
            font-size: 13px;
        }

        .pcc-titulo {
            min-width: 0;
        }

        .pcc-titulo b {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #1c1416;
            line-height: 1.2;
        }

        .pcc-titulo span {
            display: block;
            font-size: 10.5px;
            color: #a79ea2;
            margin-top: 1px;
        }

        .ped-carrito-cab .badge {
            margin-left: auto;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1c1416;
            color: #fff;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }

        .ped-carrito-cerrar {
            display: none;
            width: 28px;
            height: 28px;
            flex: 0 0 auto;
            border: 0;
            border-radius: 8px;
            background: #f4f0f0;
            color: #6b6165;
            cursor: pointer;
            font-size: 12px;
        }

        .ped-carrito-lineas {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 6px 12px;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .ped-carrito-lineas::-webkit-scrollbar {
            width: 6px;
        }

        .ped-carrito-lineas::-webkit-scrollbar-thumb {
            background: #e6dddf;
            border-radius: 3px;
        }

        .ped-linea {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 2px 8px;
            padding: 10px 1px;
            border-bottom: 1px dashed #f0eaeb;
        }

        .ped-linea:last-child {
            border-bottom: none;
        }

        .ped-linea-nombre {
            font-size: 12px;
            font-weight: 800;
            color: #1c1416;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .ped-linea-precio {
            font-size: 12.5px;
            font-weight: 900;
            color: #1c1416;
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .ped-linea-meta {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
            font-size: 10px;
            color: #a79ea2;
        }

        .ped-linea-promo {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            max-width: 100%;
            padding: 1px 5px;
            border-radius: 4px;
            background: #f6ecfb;
            color: #7a2ea3;
            font-weight: 700;
        }

        .ped-linea-promo span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ped-linea-controles {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-top: 4px;
        }

        .ped-stepper {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: #f7f3f3;
            border: 1px solid #efe8e9;
            border-radius: 8px;
            padding: 2px;
        }

        .ped-stepper button {
            width: 26px;
            height: 26px;
            border: 0;
            border-radius: 6px;
            background: #fff;
            color: #1c1416;
            font-size: 10px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
        }

        .ped-stepper button:hover {
            background: #fff4f4;
            color: var(--mod-primaria);
        }

        .ped-stepper b {
            min-width: 28px;
            text-align: center;
            font-size: 11.5px;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
        }

        .ped-linea-quitar {
            width: 27px;
            height: 27px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #c8b9bb;
            cursor: pointer;
        }

        .ped-linea-quitar:hover {
            background: #ffe8e8;
            color: var(--mod-primaria);
        }

        .ped-carrito-vacio {
            display: none;
            flex: 1 1 auto;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 26px 18px;
            text-align: center;
            color: #a79ea2;
            font-size: 12px;
        }

        .ped-carrito-vacio.visible {
            display: flex;
        }

        .ped-carrito-vacio i {
            font-size: 30px;
            color: #ddc7c9;
            margin-bottom: 3px;
        }

        .ped-carrito-vacio b {
            color: #6b6165;
            font-size: 13px;
        }

        .ped-carrito-totales {
            flex: 0 0 auto;
            padding: 10px 13px 9px;
            border-top: 1px dashed #e6d9db;
            background: #faf6f6;
        }

        .ped-total-fila {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 11.5px;
            color: #6b6165;
            padding: 2.5px 0;
        }

        .ped-total-fila b {
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-weight: 700;
        }

        .ped-total-fila.descuento b {
            color: #14803d;
        }

        .ped-total-fila.total {
            border-top: 1px solid #e6d9db;
            margin-top: 5px;
            padding-top: 7px;
            font-size: 13px;
            font-weight: 800;
            color: #1c1416;
        }

        .ped-total-fila.total b {
            font-size: 21px;
            color: var(--mod-primaria);
            font-weight: 900;
            letter-spacing: -.5px;
        }

        .ped-carrito-acciones {
            flex: 0 0 auto;
            display: flex;
            gap: 7px;
            padding: 10px 13px 13px;
        }

        .ped-carrito-acciones button {
            flex: 1 1 auto;
            height: 44px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .ped-btn-claro {
            background: #fff;
            border: 1px solid #ececef;
            color: #6b6165;
        }

        .ped-btn-claro:hover {
            background: #faf7f7;
        }

        .ped-btn-primario {
            background: linear-gradient(135deg, var(--mod-primaria), var(--mod-primaria-osc));
            border: 1px solid var(--mod-primaria);
            color: #fff;
            box-shadow: 0 9px 20px -10px rgba(217, 35, 45, .85);
        }

        .ped-btn-primario:disabled {
            opacity: .5;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Botón flotante del carrito en móvil */
        .ped-carrito-fab {
            display: none;
            flex: 0 0 auto;
            align-items: center;
            gap: 10px;
            height: 54px;
            margin-top: 9px;
            padding: 0 14px;
            border: 0;
            border-radius: 13px;
            background: linear-gradient(135deg, #241a1d, #171112);
            color: #fff;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 15px 32px -16px rgba(0, 0, 0, .9);
        }

        .ped-carrito-fab .pcf-icono {
            position: relative;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--mod-primaria), #ef4a52);
            font-size: 14px;
        }

        .ped-carrito-fab .pcf-icono b {
            position: absolute;
            top: -5px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: #fff;
            color: var(--mod-primaria);
            font-size: 10px;
            font-weight: 900;
        }

        .ped-carrito-fab .pcf-texto {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.15;
            flex: 1 1 auto;
            min-width: 0;
        }

        .ped-carrito-fab .pcf-texto b {
            font-size: 15px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }

        .ped-carrito-fab .pcf-texto small {
            font-size: 10.5px;
            color: rgba(255, 255, 255, .62);
            margin-top: 1px;
        }

        .ped-carrito-fab>i {
            color: rgba(255, 255, 255, .55);
            font-size: 12px;
        }

        .ped-carrito-backdrop {
            display: none;
            position: absolute;
            inset: 0;
            z-index: 7;
            background: rgba(17, 11, 13, .45);
            backdrop-filter: blur(2px);
        }

        .ped-carrito-backdrop.visible {
            display: block;
            animation: pedFundido .16s ease;
        }

        @keyframes pedFundido {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* =========================================================
           MODAL DE COBRO (estética POS)
           ========================================================= */
        .ped-modal-cobro .modal-caja {
            width: min(720px, 100%);
            max-width: min(720px, 100%);
            max-height: calc(100dvh - 28px);
        }

        .ped-modal-cobro .modal-cuerpo {
            display: block;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 16px 18px 6px;
            -webkit-overflow-scrolling: touch;
        }

        .ped-modal-cobro .modal-pie {
            gap: 8px;
        }

        .ped-modal-cobro #cpConfirmar {
            gap: 8px;
        }

        .ped-modal-cobro #cpConfirmarMonto {
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            padding-left: 8px;
            margin-left: 2px;
            border-left: 1px solid rgba(255, 255, 255, .3);
        }

        .ped-cobro-resumen {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 14px;
            background:
                radial-gradient(400px 140px at 100% 0%, rgba(217, 35, 45, .20), transparent 70%),
                linear-gradient(135deg, #251b1e, #171112);
            color: #fff;
            margin-bottom: 14px;
        }

        .ped-cobro-resumen .cr-et {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .7px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .55);
        }

        .ped-cobro-resumen .cr-val {
            font-size: 34px;
            font-weight: 900;
            letter-spacing: -.9px;
            line-height: 1.08;
            font-variant-numeric: tabular-nums;
        }

        .ped-cobro-resumen .cr-items {
            text-align: right;
            font-size: 11px;
            color: rgba(255, 255, 255, .62);
            line-height: 1.6;
            max-width: 44%;
        }

        .ped-cobro-resumen .cr-items b {
            color: #fff;
        }

        .ped-cobro-resumen .cr-mesa {
            display: block;
            margin-top: 3px;
            font-size: 10.5px;
            color: rgba(255, 255, 255, .55);
            overflow-wrap: anywhere;
        }

        .ped-cobro-desglose {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 14px;
        }

        .ped-desg {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #faf6f6;
            border: 1px solid #f0e6e7;
            min-width: 0;
        }

        .ped-desg span {
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #a79ea2;
        }

        .ped-desg b {
            font-size: 15px;
            font-weight: 800;
            color: #1c1416;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ped-desg.descuento b {
            color: #14803d;
        }

        .cobro-seccion {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #6b6165;
        }

        .cobro-seccion i {
            color: var(--mod-primaria);
            font-size: 11px;
        }

        .cobro-metodos {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 7px;
            margin-bottom: 12px;
        }

        .cobro-metodo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 10px 4px;
            border-radius: 11px;
            border: 1.5px solid #ececef;
            background: #fff;
            color: #6b6165;
            font-family: inherit;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            transition: .14s ease;
        }

        .cobro-metodo i {
            font-size: 15px;
            color: #b9aaad;
        }

        .cobro-metodo:hover {
            border-color: #e3b4b7;
        }

        .cobro-metodo.activa {
            background: linear-gradient(135deg, var(--mod-primaria), var(--mod-primaria-osc));
            border-color: transparent;
            color: #fff;
            box-shadow: 0 9px 20px -10px rgba(217, 35, 45, .85);
        }

        .cobro-metodo.activa i {
            color: #fff;
        }

        .cobro-campos {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }

        .cc-campo {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .cc-campo.ancho {
            grid-column: 1 / -1;
        }

        .cc-campo label {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
            color: #6b6165;
        }

        .cc-campo input,
        .cc-campo textarea {
            width: 100%;
            height: 42px;
            padding: 0 11px;
            border-radius: 9px;
            border: 1.5px solid #ececef;
            background: #fcfbfb;
            color: #1c1416;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: .14s ease;
        }

        .cc-campo textarea {
            height: 58px;
            padding: 9px 11px;
            resize: vertical;
            min-height: 58px;
        }

        .cc-campo input:focus,
        .cc-campo textarea:focus {
            border-color: #efb4b8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(217, 35, 45, .07);
        }

        .cobro-billetes {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            grid-column: 1 / -1;
        }

        .cobro-billetes button {
            height: 31px;
            padding: 0 10px;
            border-radius: 7px;
            border: 1px solid #ececef;
            background: #fff;
            color: #6b6165;
            font-family: inherit;
            font-size: 10.5px;
            font-weight: 800;
            cursor: pointer;
            transition: .13s ease;
        }

        .cobro-billetes button:hover {
            border-color: #e9b9bc;
            color: var(--mod-primaria);
        }

        .cobro-cambio {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 13px;
            margin: 2px 0 10px;
            border-radius: 9px;
            background: #e6f7ed;
            border: 1px solid #c4e8d3;
            color: #1f6b3a;
            font-size: 12px;
            font-weight: 700;
        }

        .cobro-cambio strong {
            font-size: 18px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }

        .cobro-cambio.negativo {
            background: var(--mod-rojo-fondo);
            border-color: #f4c4c6;
            color: #a31820;
        }

        .mixto-linea {
            display: grid;
            grid-template-columns: 1fr 118px auto;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .mixto-linea .ml-et {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 700;
            color: #1c1416;
        }

        .mixto-linea .ml-et i {
            width: 25px;
            height: 25px;
            display: grid;
            place-items: center;
            border-radius: 7px;
            background: #f7f2f2;
            color: #6b6165;
            font-size: 10.5px;
        }

        .mixto-linea input {
            height: 36px;
            width: 100%;
            border-radius: 8px;
            border: 1.5px solid #ececef;
            background: #fcfbfb;
            padding: 0 9px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: #1c1416;
            text-align: right;
            outline: none;
            font-variant-numeric: tabular-nums;
        }

        .mixto-linea input:focus {
            border-color: #efb4b8;
            background: #fff;
        }

        .mixto-linea .ml-resto {
            height: 29px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid #ececef;
            background: #fff;
            color: #6b6165;
            font-family: inherit;
            font-size: 9.5px;
            font-weight: 800;
            cursor: pointer;
        }

        .mixto-linea .ml-resto:hover {
            color: var(--mod-primaria);
            border-color: #e9b9bc;
        }

        .mixto-estado {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin: 7px 0 10px;
            padding: 8px 11px;
            border-radius: 8px;
            background: #f8f4f4;
            border: 1px dashed #e6d9db;
            font-size: 11.5px;
            color: #6b6165;
            font-weight: 600;
        }

        .mixto-estado b {
            font-variant-numeric: tabular-nums;
            color: #1c1416;
        }

        .mixto-estado.ok {
            background: #e6f7ed;
            border-color: #c4e8d3;
            color: #1f6b3a;
        }

        .mixto-estado.mal {
            background: var(--mod-rojo-fondo);
            border-color: #f4c4c6;
            color: #a31820;
        }

        .cobro-aviso {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 8px;
            padding: 9px 11px;
            border-radius: 8px;
            background: #f8f4f4;
            color: #6b6165;
            font-size: 11px;
            line-height: 1.45;
        }

        .cobro-aviso i {
            color: #a79ea2;
            margin-top: 1px;
        }

        .cobro-pie-nota {
            margin-right: auto;
            font-size: 11px;
            color: #a79ea2;
            font-variant-numeric: tabular-nums;
        }

        /* ---------- MODAL DETALLE ---------- */
        .ped-modal-detalle .modal-caja {
            width: min(640px, 100%);
            max-width: min(640px, 100%);
        }

        .ped-modal-detalle .modal-cuerpo {
            display: block;
            padding: 14px 16px;
        }

        .det-cabecera {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .det-cabecera .dc-info {
            font-size: 12px;
            color: #6b6165;
            line-height: 1.5;
        }

        .det-cabecera .dc-info b {
            display: block;
            font-size: 14px;
            color: #1c1416;
        }

        .det-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e6f7ed;
            color: #1f6b3a;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .det-linea {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px dashed #f0eaeb;
            font-size: 12.5px;
        }

        .det-linea:last-child {
            border-bottom: none;
        }

        .det-linea .dl-nombre {
            flex: 1;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .det-linea .dl-cant {
            background: var(--mod-primaria-suave);
            color: var(--mod-primaria);
            font-weight: 800;
            font-size: 10.5px;
            padding: 1px 6px;
            border-radius: 5px;
            align-self: flex-start;
            margin-right: 4px;
        }

        .det-linea b {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .det-totales {
            margin-top: 10px;
            padding: 11px 13px;
            border-radius: 10px;
            background: #faf6f6;
            border: 1px solid #f0e6e7;
        }

        .det-totales .ped-total-fila {
            font-size: 12px;
        }

        .det-totales .ped-total-fila.total {
            font-size: 13px;
        }

        .det-totales .ped-total-fila.total b {
            font-size: 19px;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 1180px) {
            .ped-cat-layout {
                grid-template-columns: minmax(0, 1fr) 348px;
                gap: 12px;
            }
        }

        /* Tablet / móvil: carrito tipo bandeja (POS) y catálogo a pantalla completa */
        @media (max-width: 980px) {
            .ped-cat-layout {
                position: relative;
                display: block;
                min-height: 0;
            }

            .ped-cat-col {
                height: 100%;
                min-height: 0;
            }

            .ped-cat-grid {
                height: 100%;
            }

            .ped-carrito {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 8;
                max-height: 84%;
                border-radius: 16px 16px 0 0;
                border-bottom: 0;
                transform: translateY(112%);
                opacity: 0;
                pointer-events: none;
                transition: transform .26s cubic-bezier(.32, .72, .28, 1), opacity .2s ease;
                box-shadow: 0 -18px 50px -18px rgba(0, 0, 0, .45);
            }

            .ped-carrito.abierto {
                transform: translateY(0);
                opacity: 1;
                pointer-events: auto;
            }

            .ped-carrito::before {
                content: "";
                width: 42px;
                height: 4px;
                border-radius: 999px;
                background: #e5dcdd;
                margin: 7px auto 0;
                flex: 0 0 auto;
            }

            .ped-carrito-cerrar {
                display: grid;
                place-items: center;
            }

            .ped-carrito-lineas {
                min-height: 42px;
                padding: 4px 13px;
            }

            .ped-carrito-fab {
                display: flex;
            }

            .ped-cat-contenido.carrito-abierto .ped-carrito-fab {
                display: none;
            }

            .ped-carrito-acciones button {
                height: 46px;
            }
        }

        @media (max-width: 900px) {
            .ped-cat-grid {
                grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
                gap: 10px;
            }
        }

        @media (max-width: 700px) {
            .ped-modal-productos .modal-caja {
                height: 100%;
                max-height: 100%;
                border-radius: 16px;
            }

            .ped-cat-contenido {
                padding: 10px 12px 12px;
            }

            .ped-cat-filtros {
                gap: 7px;
                margin-bottom: 6px;
            }

            .ped-cat-conteo {
                height: 34px;
                font-size: 11.5px;
                padding: 0 9px;
            }

            .ped-cat-buscar input {
                height: 38px;
                font-size: 13px;
            }

            .ped-cat-categorias {
                padding-bottom: 8px;
            }

            .ped-cat-categorias button {
                height: 30px;
                padding: 0 10px;
                font-size: 11px;
            }

            .ped-cat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .ped-prod-media {
                height: 96px;
            }

            .ped-prod-cuerpo {
                padding: 8px 8px 0;
            }

            .ped-prod-nombre {
                font-size: 11.5px;
                min-height: 29px;
            }

            .ped-prod-precio {
                font-size: 13.5px;
            }

            .ped-prod-antes {
                font-size: 9.5px;
            }

            .ped-prod-meta {
                padding-right: 27px;
                font-size: 9px;
            }

            .ped-prod-agregar {
                width: 26px;
                height: 26px;
                right: 6px;
                bottom: 6px;
            }

            .ped-carrito-fab {
                height: 50px;
            }

            .ped-carrito-fab .pcf-texto b {
                font-size: 14px;
            }

            .ped-modal-cobro .modal-caja {
                max-height: 100%;
                border-radius: 16px;
            }

            .ped-modal-cobro .modal-cuerpo {
                padding: 12px 12px 4px;
            }

            .ped-cobro-resumen {
                padding: 13px 14px;
            }

            .ped-cobro-resumen .cr-val {
                font-size: 28px;
            }

            .ped-cobro-desglose {
                gap: 6px;
            }

            .ped-desg {
                padding: 8px 9px;
            }

            .ped-desg b {
                font-size: 13px;
            }

            .cobro-metodos {
                gap: 5px;
            }

            .cobro-metodo {
                font-size: 10px;
                padding: 9px 2px;
            }

            .cobro-metodo i {
                font-size: 14px;
            }

            .mixto-linea {
                grid-template-columns: 1fr 100px auto;
                gap: 4px;
            }

            .cobro-pie-nota {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .ped-hero {
                padding: 12px 14px;
                gap: 10px;
            }

            .ped-hero h1 {
                font-size: 16px;
            }

            .ped-panel {
                padding: 10px;
            }

            .mes-grid-ped {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .mes-card-ped .mc-foto {
                height: 74px;
                font-size: 28px;
            }

            .mes-card-ped .mc-nombre {
                font-size: 12px;
            }

            .mes-card-ped .mc-info {
                display: none;
            }

            .cocina-grid {
                grid-template-columns: 1fr;
            }

            .ped-carrito-cab {
                padding: 9px 11px;
            }

            .ped-carrito-totales {
                padding: 8px 11px 7px;
            }

            .ped-total-fila.total b {
                font-size: 19px;
            }

            .ped-carrito-acciones {
                padding: 8px 11px 11px;
            }

            .cobro-metodos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .cobro-metodo {
                flex-direction: row;
                justify-content: center;
                gap: 6px;
                padding: 9px 4px;
            }

            .ped-cobro-desglose {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .ped-desg span {
                font-size: 8.5px;
            }

            .ped-desg b {
                font-size: 11.5px;
            }
        }

        @media (max-width: 400px) {
            .ped-cat-grid {
                gap: 6px;
            }

            .ped-prod-media {
                height: 86px;
            }

            .ped-prod-nombre {
                font-size: 11px;
            }

            .ped-prod-precio {
                font-size: 12.5px;
            }

            .ped-prod-antes {
                font-size: 9px;
            }

            .ped-prod-meta {
                font-size: 8.5px;
            }
        }

        /* Pantallas muy bajas (móvil horizontal): el carrito usa toda la altura */
        @media (max-width: 980px) and (max-height: 560px) {
            .ped-carrito {
                position: fixed;
                left: 6px;
                right: 6px;
                bottom: calc(6px + env(safe-area-inset-bottom));
                max-height: calc(100dvh - 12px);
                border-radius: 16px;
                border-bottom: 1px solid #ececef;
            }
        }
    </style>

    <script defer src="../modelos/pedidos/pedidos_api.js"></script>
    <script defer src="../modelos/pedidos/pedidos.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <!-- HERO -->
        <section class="ped-hero">
            <div>
                <h1><i class="fas fa-utensils"></i> Pedidos</h1>
                <p id="pedUsuario">Cargando…</p>
            </div>
            <select id="pedSucursal" style="display:none;" aria-label="Sucursal"></select>
            <div class="spacer"></div>
            <button type="button" class="btn-refrescar" id="pedRefrescar">
                <i class="fas fa-rotate"></i> Actualizar
            </button>
        </section>

        <!-- TABS (Salón + Cocina) -->
        <section class="content-card">
            <div class="ped-tabs" role="tablist">
                <button type="button" class="activa" data-tab="salon">
                    <i class="fas fa-chair"></i> Salón
                    <span class="badge-tab" id="tabSalonCount">0</span>
                </button>
                <button type="button" data-tab="cocina">
                    <i class="fas fa-fire"></i> Cocina
                    <span class="badge-tab" id="tabCocinaCount">0</span>
                </button>
            </div>

            <div id="pedCarga" class="tabla-carga visible" role="status" aria-live="polite">
                <span class="mini-spin"></span> <span>Cargando pedidos…</span>
            </div>

            <!-- SALÓN -->
            <div class="ped-panel activo" id="panel-salon" role="tabpanel">
                <div class="ped-filtro-sal">
                    <label for="pedFiltroSalon">Listado</label>
                    <select id="pedFiltroSalon">
                        <option value="mesas">Mesas · Salón</option>
                        <option value="online">Pedidos online</option>
                    </select>
                </div>
                <div class="mes-grid-ped" id="pedMesas"></div>
                <div class="mes-grid-ped" id="pedSalonOnline" style="display:none;"></div>
            </div>

            <!-- COCINA -->
            <div class="ped-panel" id="panel-cocina" role="tabpanel">
                <div class="cocina-grid" id="pedCocina"></div>
            </div>
        </section>
    </main>

    <!-- ==========================================================
         MODAL: TOMAR / AGREGAR PEDIDO  (catálogo tipo POS + carrito)
         ========================================================== -->
    <div class="modal-overlay ped-modal-productos" id="modalPedido" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pedTituloModal">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-clipboard-list"></i></span>
                    <span id="pedTituloModal">Tomar pedido</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="pedido" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-cuerpo">
                <div class="ped-cat-contenido">

                    <div class="ped-cat-filtros">
                        <div class="ped-cat-buscar">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="search" id="pedBuscar" placeholder="Buscar producto, código o categoría…" autocomplete="off" />
                            <button type="button" id="pedBuscarLimpiar" aria-label="Limpiar búsqueda">
                                <i class="fas fa-xmark"></i>
                            </button>
                        </div>
                        <div class="ped-cat-conteo" id="pedConteoProductos">0 productos</div>
                    </div>

                    <div class="ped-cat-categorias" id="pedCategorias"></div>

                    <div class="ped-cat-layout">
                        <div class="ped-cat-col">
                            <div class="ped-cat-grid" id="pedProductos"></div>
                            <div class="ped-cat-vacio" id="pedVacio">
                                <i class="fas fa-magnifying-glass"></i>
                                <strong>Sin resultados</strong>
                                <span>Prueba con otro nombre o categoría.</span>
                            </div>
                        </div>

                        <div class="ped-carrito-backdrop" id="pedCarritoBackdrop"></div>

                        <aside class="ped-carrito" id="pedCarrito" aria-label="Carrito del pedido">
                            <div class="ped-carrito-cab">
                                <i class="fas fa-basket-shopping"></i>
                                <div class="pcc-titulo">
                                    <b>Pedido</b>
                                    <span id="pedCarritoResumen">0 artículos</span>
                                </div>
                                <span class="badge" id="pedCarritoCount">0</span>
                                <button type="button" class="ped-carrito-cerrar" id="pedCarritoCerrar" aria-label="Cerrar carrito">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>

                            <div class="ped-carrito-lineas" id="pedCarritoLineas"></div>

                            <div class="ped-carrito-vacio" id="pedCarritoVacio">
                                <i class="fas fa-cart-plus"></i>
                                <b>Tu pedido está vacío</b>
                                <span>Toca un producto para agregarlo</span>
                            </div>

                            <div class="ped-carrito-totales">
                                <div class="ped-total-fila"><span>Subtotal</span><b id="pedSubtotal">Bs 0,00</b></div>
                                <div class="ped-total-fila descuento"><span>Descuento</span><b id="pedDescuento">Bs 0,00</b></div>
                                <div class="ped-total-fila"><span>IVA</span><b id="pedImpuesto">Bs 0,00</b></div>
                                <div class="ped-total-fila total"><span>Total</span><b id="pedTotal">Bs 0,00</b></div>
                            </div>

                            <div class="ped-carrito-acciones">
                                <button type="button" class="ped-btn-claro" id="pedCancelarModal">
                                    <i class="fas fa-xmark"></i> Cancelar
                                </button>
                                <?php if ($rmAcciones['modificar']): ?>
                                    <button type="button" class="ped-btn-primario" id="pedGuardar" disabled>
                                        <i class="fas fa-check"></i> <span id="pedGuardarTexto">Guardar pedido</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </aside>
                    </div>

                    <button type="button" class="ped-carrito-fab" id="pedCarritoFab" aria-label="Ver el pedido">
                        <span class="pcf-icono">
                            <i class="fas fa-basket-shopping"></i>
                            <b id="pedFabCount">0</b>
                        </span>
                        <span class="pcf-texto">
                            <b id="pedFabTotal">Bs 0,00</b>
                            <small>Ver pedido</small>
                        </span>
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         MODAL: DETALLE DEL PEDIDO
         ========================================================== -->
    <div class="modal-overlay ped-modal-detalle" id="modalDetalle" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="detTitulo">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-receipt"></i></span>
                    <span id="detTitulo">Pedido</span>
                </h2>
                <button type="button" class="modal-cerrar" data-cerrar="detalle" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-cuerpo">
                <div class="det-cabecera" id="detCabecera"></div>
                <div id="detLineas" style="margin-bottom:10px; max-height:46vh; overflow-y:auto;"></div>
                <div class="det-totales" id="detTotales"></div>
            </div>
            <div class="modal-pie" style="display:flex; flex-wrap:wrap; gap:6px;">
                <?php if ($rmAcciones['modificar']): ?>
                    <button type="button" class="btn-mod btn-mod-claro" id="detAgregar" style="margin-right:auto;">
                        <i class="fas fa-plus"></i> Agregar extras
                    </button>
                <?php endif; ?>
                <?php if ($rmAcciones['eliminar']): ?>
                    <button type="button" class="btn-mod btn-mod-peligro" id="detCancelar">
                        <i class="fas fa-xmark"></i> Cancelar
                    </button>
                <?php endif; ?>
                <?php if ($rmAcciones['registrar']): ?>
                    <button type="button" class="btn-mod btn-mod-primario" id="detCobrar">
                        <i class="fas fa-money-bill-wave"></i> Cobrar
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         MODAL: COBRAR PEDIDO (estético POS, ampliado)
         ========================================================== -->
    <div class="modal-overlay ped-modal-cobro" id="modalCobroPedido" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cpTitulo">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2 id="cpTitulo">
                    <span class="ico-titulo"><i class="fa-solid fa-money-bill-wave"></i></span>
                    <span style="color:#fff">Cobrar pedido</span>
                </h2>
                <button type="button" class="modal-cerrar" id="cpCancelar" data-cerrar="cobroPedido" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-cuerpo">

                <div class="ped-cobro-resumen">
                    <div>
                        <div class="cr-et">Total a pagar</div>
                        <div class="cr-val" id="cpTotal">Bs 0,00</div>
                    </div>
                    <div class="cr-items">
                        <b id="cpItems">0 productos</b><br>
                        <span id="cpResumenDescuento">Sin descuentos</span>
                        <span class="cr-mesa" id="cpMesa"></span>
                    </div>
                </div>

                <div class="ped-cobro-desglose">
                    <div class="ped-desg">
                        <span>Subtotal</span><b id="cpSubtotal">Bs 0,00</b>
                    </div>
                    <div class="ped-desg descuento">
                        <span>Descuento</span><b id="cpDescuento">Bs 0,00</b>
                    </div>
                    <div class="ped-desg">
                        <span>IVA</span><b id="cpImpuesto">Bs 0,00</b>
                    </div>
                </div>

                <div class="cobro-seccion"><i class="fas fa-wallet"></i> Forma de pago</div>
                <div class="cobro-metodos" id="cpMetodos" role="group" aria-label="Forma de pago">
                    <button type="button" class="cobro-metodo activa" data-metodo="efectivo">
                        <i class="fas fa-money-bill-wave"></i> Efectivo
                    </button>
                    <button type="button" class="cobro-metodo" data-metodo="qr">
                        <i class="fas fa-qrcode"></i> QR
                    </button>
                    <button type="button" class="cobro-metodo" data-metodo="tarjeta">
                        <i class="fas fa-credit-card"></i> Tarjeta
                    </button>
                    <button type="button" class="cobro-metodo" data-metodo="mixto">
                        <i class="fas fa-shuffle"></i> Mixto
                    </button>
                </div>

                <!-- EFECTIVO -->
                <div id="cpEfectivo">
                    <div class="cobro-campos">
                        <div class="cc-campo ancho">
                            <label for="cpRecibido">Efectivo recibido</label>
                            <input type="number" id="cpRecibido" inputmode="decimal" step="0.01" min="0" placeholder="0.00" />
                        </div>
                        <div class="cobro-billetes" id="cpBilletes">
                            <button type="button" data-billete="10">Bs 10</button>
                            <button type="button" data-billete="20">Bs 20</button>
                            <button type="button" data-billete="50">Bs 50</button>
                            <button type="button" data-billete="100">Bs 100</button>
                            <button type="button" data-billete="200">Bs 200</button>
                            <button type="button" data-billete="exacto">Exacto</button>
                        </div>
                    </div>
                    <div class="cobro-cambio" id="cpCambioCaja">
                        <span>Cambio</span>
                        <strong id="cpCambio">Bs 0,00</strong>
                    </div>
                </div>

                <!-- QR / TARJETA -->
                <div id="cpReferencia" style="display:none;">
                    <div class="cobro-campos">
                        <div class="cc-campo ancho">
                            <label for="cpRef">Referencia / N° de operación</label>
                            <input type="text" id="cpRef" maxlength="60" placeholder="Opcional" />
                        </div>
                    </div>
                </div>

                <!-- MIXTO -->
                <div id="cpMixto" style="display:none;">
                    <div class="mixto-linea">
                        <span class="ml-et"><i class="fas fa-money-bill-wave"></i><span>Efectivo</span></span>
                        <input type="number" id="cpMixtoEf" inputmode="decimal" step="0.01" min="0" placeholder="0.00" aria-label="Monto en efectivo" />
                        <button type="button" class="ml-resto" data-resto="efectivo">Resto</button>
                    </div>
                    <div class="mixto-linea">
                        <span class="ml-et"><i class="fas fa-qrcode"></i><span>QR</span></span>
                        <input type="number" id="cpMixtoQr" inputmode="decimal" step="0.01" min="0" placeholder="0.00" aria-label="Monto por QR" />
                        <button type="button" class="ml-resto" data-resto="qr">Resto</button>
                    </div>
                    <div class="mixto-linea">
                        <span class="ml-et"><i class="fas fa-credit-card"></i><span>Tarjeta</span></span>
                        <input type="number" id="cpMixtoTj" inputmode="decimal" step="0.01" min="0" placeholder="0.00" aria-label="Monto con tarjeta" />
                        <button type="button" class="ml-resto" data-resto="tarjeta">Resto</button>
                    </div>
                    <div class="mixto-estado" id="cpMixtoEstado">
                        <span id="cpMixtoTexto">Suma del pago</span>
                        <b id="cpMixtoSuma">Bs 0,00</b>
                    </div>
                    <div class="cobro-campos">
                        <div class="cc-campo ancho">
                            <label for="cpMixtoRef">Referencia (QR / tarjeta)</label>
                            <input type="text" id="cpMixtoRef" maxlength="60" placeholder="Opcional" />
                        </div>
                    </div>
                </div>

                <div class="cobro-campos">
                    <div class="cc-campo ancho">
                        <label for="cpObs">Observaciones</label>
                        <textarea id="cpObs" maxlength="255" placeholder="Opcional: indicaciones del cobro…"></textarea>
                    </div>
                </div>

                <div class="cobro-aviso" id="cpAviso">
                    <i class="fas fa-circle-info"></i>
                    <span>Al confirmar se registra la venta y se suma a la caja/arqueo abierto. El stock ya se descontó al tomar el pedido; la mesa queda libre.</span>
                </div>
            </div>

            <div class="modal-pie">
                <span class="cobro-pie-nota" id="cpNota"></span>
                <button type="button" class="btn-mod btn-mod-claro" data-cerrar="cobroPedido">
                    <i class="fas fa-xmark"></i> Cancelar
                </button>
                <button type="button" class="btn-mod btn-mod-primario" id="cpConfirmar">
                    <i class="fas fa-check"></i>
                    <span id="cpConfirmarTexto">Cobrar</span>
                    <span id="cpConfirmarMonto">Bs 0,00</span>
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>