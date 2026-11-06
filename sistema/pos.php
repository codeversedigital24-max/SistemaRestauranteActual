<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Punto de venta';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Punto de venta (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_VENTAS_POS);
$rmAcciones = rm_acciones(Permisos::ITEM_VENTAS_POS);

// Promociones: ítem propio del catálogo de permisos.
$puedePromos = rm_puede('ver', Permisos::ITEM_VENTAS_PROMOCIONES);
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
        /* ==========================================================
           POS  ·  Interfaz de venta
           ========================================================== */
        :root {
            --pos-fondo: #f6f4f4;
            --pos-carta: #ffffff;
            --pos-borde: #ece8e8;
            --pos-tinta: #1b1416;
            --pos-texto: #6d6367;
            --pos-tenue: #a0969a;
            --pos-rojo: #d9232d;
            --pos-rojo-osc: #ac1620;
            --pos-verde: #12803c;
            --pos-verde-fondo: #e6f7ed;
            --pos-sombra: 0 18px 45px -30px rgba(30, 18, 22, .45);
        }

        .main-content {
            padding: 12px 14px 28px;
        }

        /* ---------- HERO ---------- */
        .pos-hero {
            position: relative;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 16px 18px;
            border-radius: 16px;
            color: #fff;
            background:
                radial-gradient(900px 180px at 100% -40%, var(--mod-primaria-osc), transparent 62%),
                linear-gradient(135deg, var(--mod-primaria-osc) 0%, var(--mod-primaria) 100%);
            box-shadow: 0 20px 45px -28px rgba(0, 0, 0, .7);
            overflow: hidden;
        }

        .pos-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image: linear-gradient(90deg, rgba(255, 255, 255, .035) 1px, transparent 1px);
            background-size: 26px 26px;
            pointer-events: none;
            opacity: .5;
        }

        .pos-hero-marca {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .pos-hero-icono {
            width: 44px;
            height: 44px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 13px;
            font-size: 19px;
            color: #fff;
            background: linear-gradient(135deg, var(--pos-rojo), #f04a52);
            box-shadow: 0 10px 24px -10px rgba(217, 35, 45, .9);
        }

        .pos-hero-textos h1 {
            margin: 0;
            font-size: 19px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -.3px;
        }

        .pos-hero-textos p {
            margin: 3px 0 0;
            font-size: 12.5px;
            color: rgba(255, 255, 255, .62);
        }

        .pos-hero-estado {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, .9);
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .14);
            backdrop-filter: blur(4px);
        }

        .pos-hero-estado .punto {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #f5b93b;
            box-shadow: 0 0 0 3px rgba(245, 185, 59, .22);
        }

        .pos-hero-estado.ok .punto {
            background: #35d07a;
            box-shadow: 0 0 0 3px rgba(53, 208, 122, .22);
        }

        .pos-hero-estado.mal .punto {
            background: #ff5c63;
            box-shadow: 0 0 0 3px rgba(255, 92, 99, .22);
        }

        .pos-hero-acciones {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            position: relative;
            z-index: 1;
        }

        .pos-hero-acciones select {
            height: 38px;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, .2);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .pos-hero-acciones select option {
            color: #1b1416;
        }

        .pos-boton-hero {
            height: 38px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: .15s ease;
        }

        .pos-boton-hero:hover {
            background: rgba(255, 255, 255, .18);
            color: #fff;
        }

        /* ---------- CONTENEDOR ---------- */
        .pos-panel {
            margin-top: 12px;
            background: var(--pos-carta);
            border: 1px solid var(--pos-borde);
            border-radius: 16px;
            box-shadow: var(--pos-sombra);
            overflow: hidden;
        }

        .pos-carga {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 60px 20px;
            color: var(--pos-texto);
            font-size: 13.5px;
        }

        .pos-carga.visible {
            display: flex;
        }

        .pos-spin {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #ead9da;
            border-top-color: var(--pos-rojo);
            animation: posGiro .7s linear infinite;
        }

        @keyframes posGiro {
            to {
                transform: rotate(360deg);
            }
        }

        .pos-contenido {
            display: none;
            padding: 14px;
        }

        .pos-contenido.visible {
            display: block;
        }

        /* ---------- BLOQUEO ---------- */
        .pos-bloqueo {
            display: none;
            align-items: center;
            gap: 14px;
            margin: 0 14px 14px;
            padding: 14px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fff6e8, #fffaf2);
            border: 1px solid #f2dfb6;
            color: #7a5a13;
        }

        .pos-bloqueo.visible {
            display: flex;
        }

        .pos-bloqueo>i {
            font-size: 22px;
            color: #c8931a;
        }

        .pos-bloqueo strong {
            display: block;
            font-size: 13.5px;
            color: #6d4e0b;
        }

        .pos-bloqueo span {
            display: block;
            font-size: 12.5px;
            margin-top: 2px;
        }

        .pos-bloqueo a {
            margin-left: auto;
            flex: 0 0 auto;
        }

        /* ---------- BARRA DE CATÁLOGO ---------- */
        .pos-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 372px;
            gap: 14px;
            align-items: start;
        }

        .pos-catalogo {
            min-width: 0;
        }

        .pos-filtros {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pos-buscar {
            position: relative;
            flex: 1 1 auto;
        }

        .pos-buscar i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--pos-tenue);
            font-size: 13px;
            pointer-events: none;
        }

        .pos-buscar input {
            width: 100%;
            height: 42px;
            border-radius: 11px;
            border: 1.5px solid var(--pos-borde);
            background: #fbfafa;
            padding: 0 38px 0 38px;
            font-family: inherit;
            font-size: 13.5px;
            color: var(--pos-tinta);
            outline: none;
            transition: .15s ease;
        }

        .pos-buscar input:focus {
            border-color: #f0b6b9;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(217, 35, 45, .08);
        }

        .pos-buscar button {
            position: absolute;
            right: 7px;
            top: 50%;
            transform: translateY(-50%);
            width: 27px;
            height: 27px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--pos-tenue);
            cursor: pointer;
            display: none;
        }

        .pos-buscar button.visible {
            display: block;
        }

        .pos-conteo {
            flex: 0 0 auto;
            height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px;
            border-radius: 11px;
            border: 1px solid var(--pos-borde);
            background: #fbfafa;
            color: var(--pos-texto);
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pos-conteo b {
            color: var(--pos-tinta);
        }

        .pos-categorias {
            display: flex;
            gap: 7px;
            overflow-x: auto;
            padding: 11px 1px 4px;
            scrollbar-width: none;
        }

        .pos-categorias::-webkit-scrollbar {
            display: none;
        }

        .pos-categorias button {
            flex: 0 0 auto;
            height: 32px;
            padding: 0 13px;
            border-radius: 999px;
            border: 1.5px solid var(--pos-borde);
            background: #fff;
            color: var(--pos-texto);
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: .14s ease;
        }

        .pos-categorias button i {
            font-size: 11px;
            opacity: .75;
        }

        .pos-categorias button:hover {
            border-color: #e3b4b7;
            color: var(--pos-rojo);
        }

        .pos-categorias button.activa {
            background: var(--pos-tinta);
            border-color: var(--pos-tinta);
            color: #fff;
            box-shadow: 0 7px 16px -9px rgba(27, 20, 22, .8);
        }

        .pos-categorias button.activa i {
            opacity: 1;
            color: #ff8d93;
        }

        /* ---------- GRID DE PRODUCTOS ---------- */
        .pos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(154px, 1fr));
            gap: 10px;
            padding-top: 8px;
        }

        .pos-producto {
            position: relative;
            display: flex;
            flex-direction: column;
            text-align: left;
            border-radius: 13px;
            border: 1px solid var(--pos-borde);
            background: #fff;
            padding: 0 0 10px;
            cursor: pointer;
            overflow: hidden;
            font-family: inherit;
            transition: transform .14s ease, box-shadow .16s ease, border-color .16s ease;
        }

        .pos-producto:hover {
            transform: translateY(-2px);
            border-color: #e7d3d4;
            box-shadow: 0 14px 28px -20px rgba(40, 20, 24, .55);
        }

        .pos-producto:active {
            transform: scale(.985);
        }

        .pos-producto.agotado {
            opacity: .55;
            cursor: not-allowed;
        }

        .pos-producto-media {
            position: relative;
            height: 96px;
            background:
                radial-gradient(120px 80px at 80% 0%, rgba(217, 35, 45, .16), transparent 70%),
                linear-gradient(145deg, #f7eef0, #f2e7e8);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .pos-producto-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            position: relative;
            z-index: 1;
        }

        .pos-producto-media .sin-imagen {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-size: 26px;
            color: #d4b3b7;
        }

        .pos-producto-badges {
            position: absolute;
            top: 7px;
            left: 7px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            z-index: 1;
        }

        .pos-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
            line-height: 1;
            color: #fff;
            background: linear-gradient(135deg, var(--pos-rojo), #ef4a52);
            box-shadow: 0 4px 10px -4px rgba(217, 35, 45, .8);
        }

        .pos-badge.promo {
            background: linear-gradient(135deg, #7a2ea3, #a544d6);
            box-shadow: 0 4px 10px -4px rgba(122, 46, 163, .7);
        }

        .pos-badge.iva {
            background: rgba(255, 255, 255, .9);
            color: #6b5b5f;
            font-weight: 700;
            text-transform: none;
            letter-spacing: 0;
            backdrop-filter: blur(3px);
        }

        .pos-producto-cuerpo {
            padding: 9px 10px 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 auto;
        }

        .pos-producto-nombre {
            font-size: 12.5px;
            font-weight: 800;
            color: var(--pos-tinta);
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 31px;
        }

        .pos-producto-codigo {
            font-size: 10px;
            font-weight: 600;
            color: var(--pos-tenue);
            letter-spacing: .3px;
        }

        .pos-producto-precios {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-top: 2px;
            flex-wrap: wrap;
        }

        .pos-producto-precio {
            font-size: 14.5px;
            font-weight: 900;
            color: var(--pos-tinta);
            letter-spacing: -.3px;
            font-variant-numeric: tabular-nums;
        }

        .pos-producto-antes {
            font-size: 10.5px;
            color: var(--pos-tenue);
            text-decoration: line-through;
            font-variant-numeric: tabular-nums;
        }

        .pos-producto-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-top: auto;
            padding-top: 5px;
            font-size: 9.5px;
            font-weight: 700;
            color: var(--pos-tenue);
        }

        .pos-producto-meta .stock-ok {
            color: var(--pos-verde);
        }

        .pos-producto-meta .stock-bajo {
            color: #b57a00;
        }

        .pos-producto-meta .stock-cero {
            color: var(--pos-rojo);
        }

        .pos-agregar {
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
            background: linear-gradient(135deg, var(--pos-rojo), #ee3c45);
            box-shadow: 0 8px 16px -7px rgba(217, 35, 45, .8);
        }

        .pos-vacio {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 54px 20px;
            color: var(--pos-tenue);
            text-align: center;
        }

        .pos-vacio.visible {
            display: flex;
        }

        .pos-vacio i {
            font-size: 32px;
            color: #ddc7c9;
        }

        .pos-vacio strong {
            color: var(--pos-texto);
            font-size: 14px;
        }

        .pos-vacio span {
            font-size: 12.5px;
        }

        /* ---------- CARRITO ---------- */
        .pos-carrito {
            position: sticky;
            top: var(--pos-top-offset);
            max-height: calc(100dvh - var(--pos-top-offset) - 16px);
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--pos-borde, #ece8e8);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 16px 38px -28px rgba(30, 18, 22, .5);
        }

        .pos-carrito-cabecera {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 12px 13px;
            border-bottom: 1px solid var(--pos-borde);
            background: linear-gradient(180deg, #fff, #fcfafa);
        }

        .pcc-titulo {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
        }

        .pcc-titulo>i {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--mod-primaria-suave);
            color: var(--pos-rojo);
            font-size: 13px;
        }

        .pcc-titulo b {
            display: block;
            font-size: 13.5px;
            color: var(--pos-tinta);
            line-height: 1.2;
        }

        .pcc-titulo span {
            display: block;
            font-size: 10.5px;
            color: var(--pos-tenue);
            margin-top: 1px;
        }

        .pcc-count {
            margin-left: auto;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--pos-tinta);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
        }

        .pcc-cerrar {
            display: none;
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 8px;
            background: #f4f0f0;
            color: var(--pos-texto);
            cursor: pointer;
            font-size: 12px;
        }

        .pos-pedido-pill {
            display: none;
            align-items: center;
            gap: 7px;
            margin: 9px 12px 0;
            padding: 7px 10px;
            border-radius: 9px;
            background: #f8f2f2;
            border: 1px dashed #e6d3d5;
            color: #7a5a5e;
            font-size: 11.5px;
            font-weight: 700;
        }

        .pos-pedido-pill i {
            color: var(--pos-rojo);
        }

        .pos-carrito-lineas {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding: 6px 10px;
            scrollbar-width: thin;
        }

        .pos-linea {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 3px 8px;
            padding: 9px 3px;
            border-bottom: 1px dashed #f0eaeb;
            animation: posLinea .18s ease;
        }

        @keyframes posLinea {
            from {
                opacity: 0;
                transform: translateX(6px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .pos-linea:last-child {
            border-bottom: 0;
        }

        .pos-linea-nombre {
            font-size: 12px;
            font-weight: 800;
            color: var(--pos-tinta);
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .pos-linea-precio {
            font-size: 12.5px;
            font-weight: 900;
            color: var(--pos-tinta);
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .pos-linea-meta {
            grid-column: 1 / 2;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
            font-size: 10px;
            color: var(--pos-tenue);
        }

        .pos-linea-promo {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 1px 5px;
            border-radius: 4px;
            background: #f6ecfb;
            color: #7a2ea3;
            font-weight: 700;
            max-width: 100%;
        }

        .pos-linea-promo span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pos-linea-controles {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4px;
        }

        .pos-stepper {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 2px;
            border-radius: 8px;
            background: #f7f3f3;
            border: 1px solid #efe8e9;
        }

        .pos-stepper button {
            width: 25px;
            height: 25px;
            border: 0;
            border-radius: 6px;
            background: #fff;
            color: var(--pos-tinta);
            font-size: 11px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
        }

        .pos-stepper button:hover {
            color: var(--pos-rojo);
        }

        .pos-stepper b {
            min-width: 26px;
            text-align: center;
            font-size: 11.5px;
            color: var(--pos-tinta);
            font-variant-numeric: tabular-nums;
        }

        .pos-linea-quitar {
            width: 26px;
            height: 26px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #c8b9bb;
            cursor: pointer;
            font-size: 11px;
        }

        .pos-linea-quitar:hover {
            background: var(--mod-rojo-fondo);
            color: var(--pos-rojo);
        }

        .pos-carrito-vacio {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex: 1 1 auto;
            min-height: 150px;
            padding: 22px 16px;
            text-align: center;
            color: var(--pos-tenue);
        }

        .pos-carrito-vacio.visible {
            display: flex;
        }

        .pos-carrito-vacio i {
            font-size: 30px;
            color: #e0cfd1;
            margin-bottom: 4px;
        }

        .pos-carrito-vacio strong {
            font-size: 13px;
            color: var(--pos-texto);
        }

        .pos-carrito-vacio span {
            font-size: 11.5px;
        }

        .pos-totales {
            flex: 0 0 auto;
            padding: 9px 13px 8px;
            background: linear-gradient(180deg, #fcfafa, #f8f4f4);
            border-top: 1px dashed #e7dcdd;
        }

        .pos-total-fila {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 2.5px 0;
            font-size: 12px;
            color: var(--pos-texto);
        }

        .pos-total-fila span:last-child {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: var(--pos-tinta);
        }

        .pos-total-fila.ahorro span:last-child {
            color: var(--pos-verde);
        }

        .pos-total-fila.total {
            margin-top: 4px;
            padding-top: 7px;
            border-top: 1px solid #e8ddde;
            font-size: 12.5px;
            color: var(--pos-tinta);
        }

        .pos-total-fila.total b {
            font-size: 21px;
            font-weight: 900;
            letter-spacing: -.6px;
            font-variant-numeric: tabular-nums;
        }

        .pos-carrito-pie {
            flex: 0 0 auto;
            display: flex;
            align-items: stretch;
            gap: 7px;
            padding: 9px 11px 11px;
        }

        .pos-btn-vaciar {
            width: 40px;
            flex: 0 0 auto;
            border-radius: 10px;
            border: 1.5px solid #eee2e3;
            background: #fff;
            color: #b9a5a7;
            cursor: pointer;
            font-size: 12px;
            transition: .15s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1px;
        }

        .pos-btn-vaciar:hover {
            color: var(--pos-rojo);
            border-color: #f0c2c5;
            background: #fff8f8;
        }

        /* Atajo de teclado visible en el botón Cobrar */
        .pos-kbd {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 18px;
            padding: 0 6px;
            border-radius: 5px;
            background: rgba(255, 255, 255, .18);
            border: 1px solid rgba(255, 255, 255, .38);
            font-family: "Consolas", monospace;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .3px;
            line-height: 1;
            color: #fff;
        }

        .pos-kbd-mini {
            font-family: "Consolas", monospace;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: .2px;
            line-height: 1;
            opacity: .8;
        }

        .pos-btn-cobrar {
            flex: 1 1 auto;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0 16px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--pos-rojo), var(--pos-rojo-osc));
            color: #fff;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 12px 24px -12px rgba(217, 35, 45, .85);
            transition: transform .13s ease, box-shadow .15s ease, filter .15s ease;
        }

        .pos-btn-cobrar:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 15px 28px -12px rgba(217, 35, 45, .95);
        }

        .pos-btn-cobrar:disabled {
            cursor: not-allowed;
            filter: grayscale(.55);
            opacity: .55;
            box-shadow: none;
        }

        .pbc-texto {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 800;
        }

        .pbc-total {
            font-size: 13.5px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }

        /* ---------- FAB / MÓVIL ---------- */
        .pos-fab {
            display: none;
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom));
            z-index: 500;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #241a1d, #171112);
            color: #fff;
            font-family: inherit;
            box-shadow: 0 18px 38px -14px rgba(0, 0, 0, .8);
            cursor: pointer;
        }

        .pfc-icono {
            position: relative;
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--pos-rojo), #ef4a52);
            font-size: 14px;
        }

        .pfc-texto {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.15;
            flex: 1 1 auto;
        }

        .pfc-texto b {
            font-size: 15px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }

        .pfc-texto small {
            font-size: 10.5px;
            color: rgba(255, 255, 255, .6);
            margin-top: 1px;
        }

        .pfc-flecha {
            color: rgba(255, 255, 255, .55);
            font-size: 12px;
        }

        .pos-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 400;
            background: rgba(17, 11, 13, .5);
            backdrop-filter: blur(2px);
        }

        .pos-backdrop.visible {
            display: block;
            animation: posFundido .16s ease;
        }

        @keyframes posFundido {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @media (max-width: 1024px) {
            .pos-layout {
                grid-template-columns: minmax(0, 1fr) 336px;
            }
        }

        @media (max-width: 900px) {
            .pos-layout {
                grid-template-columns: 1fr;
            }

            .pos-fab {
                display: flex;
            }

            .pos-carrito {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 450;
                max-height: 88dvh;
                border-radius: 18px 18px 0 0;
                border-bottom: 0;
                transform: translateY(112%);
                transition: transform .26s cubic-bezier(.32, .72, .28, 1);
                box-shadow: 0 -18px 50px -18px rgba(0, 0, 0, .45);
                padding-bottom: env(safe-area-inset-bottom);
            }

            .pos-carrito::before {
                content: "";
                width: 42px;
                height: 4px;
                border-radius: 999px;
                background: #e5dcdd;
                margin: 7px auto 0;
                flex: 0 0 auto;
            }

            .pos-carrito.abierto {
                transform: translateY(0);
            }

            .pcc-cerrar,
            .pos-pedido-pill {
                display: inline-flex;
            }

            .pos-carrito-lineas {
                padding: 4px 13px;
            }

            .pos-linea {
                padding: 10px 2px;
            }

            .pos-btn-cobrar {
                height: 48px;
            }

            .main-content {
                padding-bottom: 88px;
            }
        }

        @media (max-width: 700px) {
            .pos-hero {
                padding: 14px;
                gap: 12px;
                border-radius: 14px;
            }

            .pos-hero-textos h1 {
                font-size: 17px;
            }

            .pos-hero-textos p {
                font-size: 11.5px;
            }

            .pos-hero-estado {
                order: 3;
                width: 100%;
                justify-content: center;
                padding: 6px 10px;
                font-size: 11.5px;
            }

            .pos-hero-acciones {
                gap: 6px;
            }

            .pos-hero-acciones select {
                max-width: 128px;
                height: 36px;
                font-size: 12px;
            }

            .pos-boton-hero {
                width: 36px;
                height: 36px;
                padding: 0;
                justify-content: center;
                border-radius: 50%;
            }

            .pos-boton-hero span {
                display: none;
            }

            .pos-contenido {
                padding: 11px;
            }

            .pos-bloqueo {
                margin: 0 11px 11px;
                flex-wrap: wrap;
            }

            .pos-bloqueo a {
                width: 100%;
                margin-left: 0;
                justify-content: center;
            }

            .pos-filtros {
                gap: 7px;
            }

            .pos-buscar input {
                height: 40px;
                font-size: 13px;
            }

            .pos-conteo {
                height: 40px;
                padding: 0 10px;
                font-size: 11.5px;
            }

            .pos-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .pos-producto-media {
                height: 82px;
            }

            .pos-producto-cuerpo {
                padding: 8px 8px 0;
            }

            .pos-producto-nombre {
                font-size: 11.5px;
                min-height: 29px;
            }

            .pos-producto-precio {
                font-size: 13px;
            }

            .pos-producto-meta {
                font-size: 9px;
            }

            .pos-producto-badges {
                top: 5px;
                left: 5px;
            }

            .pos-badge {
                font-size: 8.5px;
                padding: 2px 5px;
            }

            .pos-agregar {
                width: 26px;
                height: 26px;
                right: 6px;
                bottom: 6px;
            }
        }

        /* ---------- MODAL DE COBRO ---------- */
        .pos-modal-cobro {
            width: min(660px, 100%);
        }

        .pos-modal-cobro .modal-cuerpo {
            display: block;
            padding: 14px 16px 6px;
        }

        .cobro-resumen {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 12px;
            background:
                radial-gradient(400px 120px at 100% 0%, rgba(217, 35, 45, .18), transparent 70%),
                linear-gradient(135deg, #251b1e, #171112);
            color: #fff;
            margin-bottom: 14px;
        }

        .cobro-resumen .cr-et {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .7px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .55);
        }

        .cobro-resumen .cr-val {
            font-size: 27px;
            font-weight: 900;
            letter-spacing: -.8px;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .cobro-resumen .cr-items {
            text-align: right;
            font-size: 11px;
            color: rgba(255, 255, 255, .6);
            line-height: 1.5;
        }

        .cobro-resumen .cr-items b {
            color: #fff;
        }

        .cobro-seccion {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0 0 7px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--pos-texto);
        }

        .cobro-seccion i {
            color: var(--pos-rojo);
            font-size: 11px;
        }

        .cobro-seccion.dividida {
            margin-top: 14px;
        }

        .cobro-tipos {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7px;
            margin-bottom: 10px;
        }

        .cobro-tipo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 10px 6px;
            border-radius: 11px;
            border: 1.5px solid var(--pos-borde);
            background: #fff;
            color: var(--pos-texto);
            font-family: inherit;
            cursor: pointer;
            transition: .14s ease;
        }

        .cobro-tipo i {
            font-size: 15px;
            color: #b9aaad;
        }

        .cobro-tipo b {
            font-size: 11.5px;
            font-weight: 800;
            color: var(--pos-tinta);
        }

        .cobro-tipo small {
            font-size: 9.5px;
            color: var(--pos-tenue);
        }

        .cobro-tipo:hover {
            border-color: #e3b4b7;
        }

        .cobro-tipo.activa {
            border-color: var(--pos-rojo);
            background: #fff6f6;
            box-shadow: 0 0 0 3px rgba(217, 35, 45, .08);
        }

        .cobro-tipo.activa i {
            color: var(--pos-rojo);
        }

        .cobro-tipo-extra {
            display: none;
            margin-bottom: 10px;
        }

        .cobro-tipo-extra.visible {
            display: block;
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
            padding: 9px 4px;
            border-radius: 10px;
            border: 1.5px solid var(--pos-borde);
            background: #fff;
            color: var(--pos-texto);
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
            background: linear-gradient(135deg, var(--pos-rojo), var(--pos-rojo-osc));
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
        }

        .cc-campo.ancho {
            grid-column: 1 / -1;
        }

        .cc-campo label {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
            color: var(--pos-texto);
        }

        .cc-campo input,
        .cc-campo textarea {
            width: 100%;
            height: 40px;
            padding: 0 11px;
            border-radius: 9px;
            border: 1.5px solid var(--pos-borde);
            background: #fcfbfb;
            color: var(--pos-tinta);
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

        .cc-campo input::placeholder,
        .cc-campo textarea::placeholder {
            color: #bfb3b6;
        }

        .cobro-billetes {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            grid-column: 1 / -1;
        }

        .cobro-billetes button {
            height: 29px;
            padding: 0 9px;
            border-radius: 7px;
            border: 1px solid var(--pos-borde);
            background: #fff;
            color: var(--pos-texto);
            font-family: inherit;
            font-size: 10.5px;
            font-weight: 800;
            cursor: pointer;
            transition: .13s ease;
        }

        .cobro-billetes button:hover {
            border-color: #e9b9bc;
            color: var(--pos-rojo);
        }

        .cobro-cambio {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 13px;
            margin: 2px 0 10px;
            border-radius: 9px;
            background: var(--pos-verde-fondo);
            border: 1px solid #c4e8d3;
            color: #1f6b3a;
            font-size: 12px;
            font-weight: 700;
        }

        .cobro-cambio strong {
            font-size: 17px;
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
            color: var(--pos-tinta);
        }

        .mixto-linea .ml-et i {
            width: 25px;
            height: 25px;
            display: grid;
            place-items: center;
            border-radius: 7px;
            background: #f7f2f2;
            color: var(--pos-texto);
            font-size: 10.5px;
        }

        .mixto-linea input {
            height: 36px;
            width: 100%;
            border-radius: 8px;
            border: 1.5px solid var(--pos-borde);
            background: #fcfbfb;
            padding: 0 9px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--pos-tinta);
            text-align: right;
            outline: none;
            font-variant-numeric: tabular-nums;
        }

        .mixto-linea input:focus {
            border-color: #efb4b8;
            background: #fff;
        }

        .mixto-linea .ml-resto {
            height: 28px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid var(--pos-borde);
            background: #fff;
            color: var(--pos-texto);
            font-family: inherit;
            font-size: 9.5px;
            font-weight: 800;
            cursor: pointer;
        }

        .mixto-linea .ml-resto:hover {
            color: var(--pos-rojo);
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
            color: var(--pos-texto);
            font-weight: 600;
        }

        .mixto-estado b {
            font-variant-numeric: tabular-nums;
            color: var(--pos-tinta);
        }

        .mixto-estado.ok {
            background: var(--pos-verde-fondo);
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
            padding: 8px 11px;
            border-radius: 8px;
            background: #f8f4f4;
            color: var(--pos-texto);
            font-size: 11px;
            line-height: 1.45;
        }

        .cobro-aviso i {
            color: var(--pos-tenue);
            margin-top: 1px;
        }

        .cobro-pie-nota {
            margin-right: auto;
            font-size: 11px;
            color: var(--pos-tenue);
        }

        /* ---------- MODAL DE ÉXITO ---------- */
        .pos-modal-exito {
            width: min(420px, 100%);
        }

        .exito-cuerpo {
            padding: 22px 20px 18px;
            text-align: center;
        }

        .exito-icono {
            width: 62px;
            height: 62px;
            margin: 0 auto 12px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #26a65b, #14803d);
            color: #fff;
            font-size: 26px;
            box-shadow: 0 14px 30px -14px rgba(20, 128, 61, .8);
            animation: exitoPop .35s cubic-bezier(.2, 1.4, .4, 1);
        }

        @keyframes exitoPop {
            from {
                transform: scale(.5);
                opacity: 0;
            }

            to {
                transform: none;
                opacity: 1;
            }
        }

        .exito-cuerpo h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
            color: var(--pos-tinta);
            letter-spacing: -.3px;
        }

        .exito-cuerpo>p {
            margin: 4px 0 14px;
            font-size: 12px;
            color: var(--pos-texto);
        }

        .exito-total {
            padding: 12px;
            border-radius: 12px;
            background: #faf6f6;
            border: 1px dashed #e6d9db;
            margin-bottom: 12px;
        }

        .exito-total span {
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
            text-transform: uppercase;
            color: var(--pos-tenue);
        }

        .exito-total b {
            display: block;
            margin-top: 2px;
            font-size: 27px;
            font-weight: 900;
            color: var(--pos-tinta);
            letter-spacing: -.7px;
            font-variant-numeric: tabular-nums;
        }

        .exito-datos {
            display: grid;
            gap: 5px;
            margin-bottom: 16px;
            font-size: 11.5px;
            color: var(--pos-texto);
        }

        .exito-datos div {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 5px 8px;
            border-radius: 7px;
            background: #fbfafa;
        }

        .exito-datos b {
            color: var(--pos-tinta);
            text-align: right;
        }

        .exito-acciones {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .exito-acciones .btn-mod {
            justify-content: center;
            height: 40px;
        }

        .exito-acciones .completo {
            grid-column: 1 / -1;
            justify-content: center;
            height: 42px;
        }

        @media (max-width: 640px) {
            .pos-modal-cobro .modal-cuerpo {
                padding: 12px 12px 4px;
            }

            .cobro-tipos {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 5px;
            }

            .cobro-tipo {
                padding: 8px 3px;
            }

            .cobro-tipo small {
                display: none;
            }

            .cobro-metodos {
                gap: 5px;
            }

            .cobro-metodo {
                font-size: 10px;
                padding: 8px 2px;
            }

            .mixto-linea {
                grid-template-columns: 1fr 100px auto;
                gap: 4px;
            }

            .mixto-linea .ml-et span {
                font-size: 11px;
            }

            .cobro-pie-nota {
                display: none;
            }

            .exito-cuerpo {
                padding: 20px 14px 16px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .pos-producto,
            .pos-btn-cobrar,
            .pos-carrito {
                transition: none;
            }

            .pos-linea,
            .exito-icono {
                animation: none;
            }
        }

        /* ---------- MODAL PRODUCTOS ---------- */
        .pos-modal-productos .modal-caja {
            width: min(1100px, 100%);
            height: min(85vh, 800px);
            display: flex;
            flex-direction: column;
        }

        .pos-modal-productos .modal-cabecera {
            flex: 0 0 auto;
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8f7f7, #f2e7e8);
            border-bottom: 1px solid var(--pos-borde);
        }

        .pos-modal-productos .modal-cuerpo {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: 0;
        }

        .pos-modal-carga {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 40px 20px;
            color: var(--pos-texto);
            font-size: 14px;
        }

        .pos-modal-carga.visible {
            display: flex;
        }

        .pos-modal-carga .pos-spin {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #ead9da;
            border-top-color: var(--pos-rojo);
            animation: posGiro .7s linear infinite;
        }

        .pos-modal-contenido {
            padding: 16px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .pos-modal-filtros {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .pos-modal-buscar {
            position: relative;
            flex: 1 1 auto;
            min-width: 200px;
        }

        .pos-modal-buscar i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--pos-tenue);
            font-size: 14px;
            pointer-events: none;
        }

        .pos-modal-buscar input {
            width: 100%;
            height: 42px;
            border-radius: 10px;
            border: 1.5px solid var(--pos-borde);
            background: #fbfafa;
            padding: 0 12px 0 36px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: .15s ease;
        }

        .pos-modal-buscar input:focus {
            border-color: #f0b6b9;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(217, 35, 45, .08);
        }

        .pos-modal-buscar button {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--pos-tenue);
            cursor: pointer;
            display: none;
        }

        .pos-modal-buscar button.visible {
            display: block;
        }

        .pos-modal-conteo {
            flex: 0 0 auto;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border-radius: 10px;
            border: 1px solid var(--pos-borde);
            background: #fbfafa;
            color: var(--pos-texto);
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pos-modal-categorias {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 8px 2px;
            margin-bottom: 12px;
            scrollbar-width: none;
        }

        .pos-modal-categorias::-webkit-scrollbar {
            display: none;
        }

        .pos-modal-categorias button {
            flex: 0 0 auto;
            height: 32px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1.5px solid var(--pos-borde);
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--pos-texto);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .pos-modal-categorias button:hover {
            border-color: #e3b4b7;
            color: var(--pos-rojo);
        }

        .pos-modal-categorias button.activa {
            background: var(--pos-tinta);
            border-color: var(--pos-tinta);
            color: #fff;
            box-shadow: 0 7px 16px -9px rgba(27, 20, 22, .8);
        }

        .pos-modal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            overflow-y: auto;
            padding-right: 4px;
            min-height: 0;
            align-content: start;
        }

        .pos-modal-prod {
            position: relative;
            display: flex;
            flex-direction: column;
            text-align: left;
            border-radius: 13px;
            border: 1px solid var(--pos-borde);
            background: #fff;
            padding: 0 0 10px;
            cursor: pointer;
            overflow: hidden;
            font-family: inherit;
            transition: transform .14s ease, box-shadow .16s ease, border-color .16s ease;
        }

        .pos-modal-prod:hover {
            transform: translateY(-2px);
            border-color: #e7d3d4;
            box-shadow: 0 14px 28px -20px rgba(40, 20, 24, .55);
        }

        .pos-modal-prod:active {
            transform: scale(.985);
        }

        .pos-modal-prod.agotado {
            opacity: .55;
            cursor: not-allowed;
        }

        .pos-modal-prod .pmp-media {
            position: relative;
            height: 96px;
            background:
                radial-gradient(120px 80px at 80% 0%, rgba(217, 35, 45, .16), transparent 70%),
                linear-gradient(145deg, #f7eef0, #f2e7e8);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .pos-modal-prod .pmp-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            position: relative;
            z-index: 1;
        }

        .pos-modal-prod .sin-imagen {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            font-size: 28px;
            color: #d4b3b7;
        }

        .pos-modal-prod .pmp-badges {
            position: absolute;
            top: 7px;
            left: 7px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            z-index: 1;
        }

        .pos-modal-prod .pmp-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .3px;
            text-transform: uppercase;
            line-height: 1;
            color: #fff;
            background: linear-gradient(135deg, var(--pos-rojo), #ef4a52);
            box-shadow: 0 4px 10px -4px rgba(217, 35, 45, .8);
        }

        .pos-modal-prod .pmp-badge.promo {
            background: linear-gradient(135deg, #7a2ea3, #a544d6);
            box-shadow: 0 4px 10px -4px rgba(122, 46, 163, .7);
        }

        .pos-modal-prod .pmp-cuerpo {
            padding: 9px 10px 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1 1 auto;
        }

        .pos-modal-prod .pmp-nombre {
            font-size: 12.5px;
            font-weight: 800;
            color: var(--pos-tinta);
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 31px;
        }

        .pos-modal-prod .pmp-precios {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-top: 2px;
            flex-wrap: wrap;
        }

        .pos-modal-prod .pmp-precio {
            font-size: 14.5px;
            font-weight: 900;
            color: var(--pos-tinta);
            letter-spacing: -.3px;
            font-variant-numeric: tabular-nums;
        }

        .pos-modal-prod .pmp-antes {
            font-size: 10.5px;
            color: var(--pos-tenue);
            text-decoration: line-through;
            font-variant-numeric: tabular-nums;
        }

        .pos-modal-prod .pmp-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            margin-top: auto;
            padding-top: 5px;
            font-size: 9.5px;
            font-weight: 700;
            color: var(--pos-tenue);
        }

        .pos-modal-prod .pmp-stock-ok {
            color: var(--pos-verde);
        }

        .pos-modal-prod .pmp-stock-bajo {
            color: #b57a00;
        }

        .pos-modal-prod .pmp-stock-cero {
            color: var(--pos-rojo);
        }

        .pos-modal-prod .pmp-agregar {
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
            background: linear-gradient(135deg, var(--pos-rojo), #ee3c45);
            box-shadow: 0 8px 16px -7px rgba(217, 35, 45, .8);
        }

        /* Botón flotante para abrir modal de productos */
        .pos-modal-flotante {
            display: none;
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 500;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pos-rojo), #ee3c45);
            box-shadow: 0 8px 24px -8px rgba(217, 35, 45, .9);
            cursor: pointer;
            transition: transform .14s ease, box-shadow .16s ease;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 4px;
            font-size: 14px;
            color: #fff;
        }

        .pos-modal-flotante:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px -10px rgba(217, 35, 45, .95);
        }

        .pos-modal-flotante:active {
            transform: scale(.95);
        }

        .pos-modal-flotante i {
            font-size: 20px;
        }

        .pos-modal-flotante span {
            font-size: 10px;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .pos-modal-flotante {
                display: flex;
            }
        }

        /* Responsive para el modal */
        @media (max-width: 900px) {
            .pos-modal-productos .modal-caja {
                height: 90vh;
            }

            .pos-modal-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }

        @media (max-width: 700px) {
            .pos-modal-productos .modal-caja {
                height: 96vh;
                border-radius: 18px 18px 0 0;
            }

            .pos-modal-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .pos-modal-prod .pmp-media {
                height: 82px;
            }

            .pos-modal-prod .pmp-cuerpo {
                padding: 8px 8px 0;
            }

            .pos-modal-prod .pmp-nombre {
                font-size: 11.5px;
                min-height: 29px;
            }

            .pos-modal-prod .pmp-precio {
                font-size: 13px;
            }

            .pos-modal-prod .pmp-agregar {
                width: 26px;
                height: 26px;
                right: 6px;
                bottom: 6px;
            }
        }
    </style>

    <script defer src="../modelos/pos/pos_api.js"></script>
    <script defer src="../modelos/pos/pos.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">

        <!-- HERO -->
        <section class="pos-hero">
            <div class="pos-hero-marca">
                <span class="pos-hero-icono"><i class="fas fa-cash-register"></i></span>
                <div class="pos-hero-textos">
                    <h1>Punto de venta</h1>
                    <p id="posUsuario">Cargando información…</p>
                </div>
            </div>

            <div class="pos-hero-estado" id="posEstadoCaja">
                <span class="punto"></span>
                <span id="posEstadoTexto">Verificando caja…</span>
            </div>

            <div class="pos-hero-acciones">
                <select id="posSucursal" style="display:none;" aria-label="Sucursal"></select>

                <?php if ($puedePromos): ?>
                    <a href="promociones.php" class="pos-boton-hero" title="Administrar promociones">
                        <i class="fas fa-tags"></i>
                        <span>Promociones</span>
                    </a>
                <?php endif; ?>

                <button type="button" class="pos-boton-hero" id="posProductosModal" title="Ver todos los productos" data-modal="productos">
                    <i class="fas fa-cube"></i>
                    <span>Todos los productos</span>
                </button>

                <button type="button" class="pos-boton-hero" id="posRefrescar" title="Actualizar catálogo y caja">
                    <i class="fas fa-rotate"></i>
                    <span>Actualizar</span>
                </button>
            </div>
        </section>

        <!-- PANEL -->
        <section class="pos-panel">
            <div class="pos-carga visible" id="posCarga" role="status" aria-live="polite">
                <span class="pos-spin" aria-hidden="true"></span>
                <span>Verificando caja y catálogo…</span>
            </div>

            <div class="pos-bloqueo" id="posBloqueo">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>No hay caja abierta</strong>
                    <span id="posBloqueoMsg">Abre tu arqueo de caja para poder registrar ventas.</span>
                </div>
                <a href="arqueocaja.php" class="btn-mod btn-mod-primario">
                    <i class="fas fa-lock-open"></i>
                    <span>Abrir caja</span>
                </a>
            </div>

            <div class="pos-contenido" id="posContenido">
                <div class="pos-layout">

                    <!-- CATÁLOGO -->
                    <section class="pos-catalogo" aria-label="Catálogo de productos">
                        <div class="pos-filtros">
                            <div class="pos-buscar">
                                <i class="fas fa-magnifying-glass"></i>
                                <input type="search" id="posBuscar" placeholder="Buscar producto, código o categoría…" autocomplete="off" />
                                <button type="button" id="posBuscarLimpiar" aria-label="Limpiar búsqueda"><i class="fas fa-xmark"></i></button>
                            </div>
                            <span class="pos-conteo"><b id="posConteo">0</b> productos</span>
                        </div>

                        <div class="pos-categorias" id="posCategorias" role="tablist" aria-label="Categorías"></div>

                        <div class="pos-grid" id="posProductos"></div>

                        <div class="pos-vacio" id="posVacio">
                            <i class="fas fa-magnifying-glass"></i>
                            <strong>Sin resultados</strong>
                            <span id="posVacioTexto">Prueba con otro nombre o categoría.</span>
                        </div>
                    </section>

                    <!-- CARRITO -->
                    <aside class="pos-carrito" id="posCarrito" aria-label="Carrito de venta">
                        <div class="pos-carrito-cabecera">
                            <div class="pcc-titulo">
                                <i class="fas fa-basket-shopping"></i>
                                <div>
                                    <b>Carrito</b>
                                    <span id="posCarritoResumen">0 artículos</span>
                                </div>
                            </div>
                            <span class="pcc-count" id="posCarritoCount">0</span>
                            <button type="button" class="pcc-cerrar" id="posCarritoCerrar" aria-label="Cerrar carrito">
                                <i class="fas fa-xmark"></i>
                            </button>
                        </div>

                        <div class="pos-pedido-pill" id="posPedidoPill">
                            <i class="fas fa-utensils"></i>
                            <span id="posTipoPedidoTexto">Para mesa</span>
                        </div>

                        <div class="pos-carrito-lineas" id="posCarritoLineas"></div>

                        <div class="pos-carrito-vacio" id="posCarritoVacio">
                            <i class="fas fa-cart-plus"></i>
                            <strong>Tu carrito está vacío</strong>
                            <span>Toca un producto para agregarlo.</span>
                        </div>

                        <div class="pos-totales" id="posTotales">
                            <div class="pos-total-fila">
                                <span>Subtotal</span>
                                <span id="posSubtotal">Bs 0,00</span>
                            </div>
                            <div class="pos-total-fila ahorro" id="posAhorroFila">
                                <span>Descuentos</span>
                                <span id="posDescuento">− Bs 0,00</span>
                            </div>
                            <div class="pos-total-fila">
                                <span>IVA</span>
                                <span id="posImpuesto">Bs 0,00</span>
                            </div>
                            <div class="pos-total-fila total">
                                <span>Total</span>
                                <b id="posTotal">Bs 0,00</b>
                            </div>
                        </div>

                        <div class="pos-carrito-pie">
                            <button type="button" class="pos-btn-vaciar" id="posLimpiar" aria-label="Vaciar carrito (F9)" aria-keyshortcuts="F9" title="Vaciar carrito (F9)">
                                <i class="fas fa-trash-can"></i>
                                <span class="pos-kbd-mini">F9</span>
                            </button>
                            <button type="button" class="pos-btn-cobrar" id="posCobrar" disabled aria-keyshortcuts="F2" title="Cobrar (F2)">
                                <span class="pbc-texto"><i class="fas fa-money-bill-wave"></i> Cobrar <kbd class="pos-kbd">F2</kbd></span>
                                <span class="pbc-total" id="posCobrarTotal">Bs 0,00</span>
                            </button>
                        </div>
                    </aside>

                </div>
            </div>
        </section>
    </main>

    <!-- FAB MÓVIL -->
    <button type="button" class="pos-fab" id="posFabCarrito" aria-label="Abrir carrito">
        <span class="pfc-icono"><i class="fas fa-basket-shopping"></i></span>
        <span class="pfc-texto">
            <b id="posFabTotal">Bs 0,00</b>
            <small id="posFabCount">0 artículos</small>
        </span>
        <i class="fas fa-chevron-up pfc-flecha"></i>
    </button>
    <div class="pos-backdrop" id="posFabBackdrop"></div>

    <!-- ==========================================================
         MODAL: COBRAR
         ========================================================== -->
    <div class="modal-overlay" id="modalCobro" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cobroTitulo">
        <div class="modal-caja pos-modal-cobro">

            <div class="modal-cabecera">
                <h2 id="cobroTitulo">
                    <span class="ico-titulo"><i class="fa-solid fa-money-bill-wave"></i></span>
                    <span style="color:#fff;">Cobrar venta</span>
                </h2>
                <button type="button" class="modal-cerrar" id="cobroCerrar" aria-label="Cerrar">&times;</button>
            </div>

            <form id="formCobro" novalidate autocomplete="off">
                <div class="modal-cuerpo">

                    <div class="cobro-resumen">
                        <div>
                            <div class="cr-et">Total a cobrar</div>
                            <div class="cr-val" id="cobroTotal">Bs 0,00</div>
                        </div>
                        <div class="cr-items">
                            <b id="cobroItems">0 productos</b><br>
                            <span id="cobroResumenDesc">Sin descuentos</span>
                        </div>
                    </div>

                    <div class="cobro-seccion"><i class="fas fa-utensils"></i> Tipo de pedido</div>
                    <div class="cobro-tipos" id="cobroTipos" role="group" aria-label="Tipo de pedido">
                        <button type="button" class="cobro-tipo activa" data-tipo="mesa">
                            <i class="fas fa-utensils"></i>
                            <b>Para mesa</b>
                            <small>Consumo aquí</small>
                        </button>
                        <button type="button" class="cobro-tipo" data-tipo="llevar">
                            <i class="fas fa-bag-shopping"></i>
                            <b>Para llevar</b>
                            <small>Empacado</small>
                        </button>
                        <button type="button" class="cobro-tipo" data-tipo="delivery">
                            <i class="fas fa-motorcycle"></i>
                            <b>Delivery</b>
                            <small>Envío</small>
                        </button>
                    </div>

                    <div class="cobro-tipo-extra" id="cobroDeliveryWrap">
                        <div class="cc-campo">
                            <label for="cobroDelivery">Nombre del delivery</label>
                            <input type="text" id="cobroDelivery" maxlength="120" placeholder="Ej. Juan Pérez" />
                        </div>
                    </div>

                    <div class="cobro-seccion dividida"><i class="fas fa-wallet"></i> Forma de pago</div>
                    <div class="cobro-metodos" id="cobroMetodos" role="group" aria-label="Forma de pago">
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
                    <div id="cobroEfectivo">
                        <div class="cobro-campos">
                            <div class="cc-campo ancho">
                                <label for="cobroRecibido">Efectivo recibido</label>
                                <input type="number" id="cobroRecibido" inputmode="decimal" step="0.01" min="0" placeholder="0.00" />
                            </div>
                            <div class="cobro-billetes" id="cobroBilletes">
                                <button type="button" data-billete="10">Bs 10</button>
                                <button type="button" data-billete="20">Bs 20</button>
                                <button type="button" data-billete="50">Bs 50</button>
                                <button type="button" data-billete="100">Bs 100</button>
                                <button type="button" data-billete="200">Bs 200</button>
                                <button type="button" data-billete="exacto">Exacto</button>
                            </div>
                        </div>
                        <div class="cobro-cambio" id="cobroCambioCaja">
                            <span>Cambio</span>
                            <strong id="cobroCambio">Bs 0,00</strong>
                        </div>
                    </div>

                    <!-- QR / TARJETA -->
                    <div id="cobroReferencia" style="display:none;">
                        <div class="cobro-campos">
                            <div class="cc-campo ancho">
                                <label for="cobroRef">Referencia / N° de operación</label>
                                <input type="text" id="cobroRef" maxlength="60" placeholder="Opcional" />
                            </div>
                        </div>
                    </div>

                    <!-- MIXTO -->
                    <div id="cobroMixto" style="display:none;">
                        <div class="mixto-linea">
                            <span class="ml-et"><i class="fas fa-money-bill-wave"></i><span>Efectivo</span></span>
                            <input type="number" id="mixtoEfectivo" inputmode="decimal" step="0.01" min="0" placeholder="0.00" aria-label="Monto en efectivo" />
                            <button type="button" class="ml-resto" data-resto="efectivo">Resto</button>
                        </div>
                        <div class="mixto-linea">
                            <span class="ml-et"><i class="fas fa-qrcode"></i><span>QR</span></span>
                            <input type="number" id="mixtoQr" inputmode="decimal" step="0.01" min="0" placeholder="0.00" aria-label="Monto por QR" />
                            <button type="button" class="ml-resto" data-resto="qr">Resto</button>
                        </div>
                        <div class="mixto-linea">
                            <span class="ml-et"><i class="fas fa-credit-card"></i><span>Tarjeta</span></span>
                            <input type="number" id="mixtoTarjeta" inputmode="decimal" step="0.01" min="0" placeholder="0.00" aria-label="Monto con tarjeta" />
                            <button type="button" class="ml-resto" data-resto="tarjeta">Resto</button>
                        </div>
                        <div class="mixto-estado" id="mixtoEstado">
                            <span id="mixtoTexto">Suma del pago</span>
                            <b id="mixtoSuma">Bs 0,00</b>
                        </div>
                        <div class="cobro-campos">
                            <div class="cc-campo ancho">
                                <label for="cobroRefMixto">Referencia (QR / tarjeta)</label>
                                <input type="text" id="cobroRefMixto" maxlength="60" placeholder="Opcional" />
                            </div>
                        </div>
                    </div>

                    <div class="cc-campo ancho">
                        <label for="cobroObs">Observaciones</label>
                        <textarea id="cobroObs" maxlength="255" placeholder="Opcional: indicaciones del pedido…"></textarea>
                    </div>

                    <div class="cobro-aviso" id="cobroAviso">
                        <i class="fas fa-circle-info"></i>
                        <span id="cobroAvisoTexto">Al confirmar, se registra la venta, se actualiza el stock de los productos que lo controlan y se suma a la caja abierta.</span>
                    </div>

                </div>

                <div class="modal-pie">
                    <span class="cobro-pie-nota" id="cobroNota"></span>
                    <button type="button" class="btn-mod btn-mod-claro" id="cobroCancelar">
                        <i class="fas fa-xmark"></i> Cancelar
                    </button>
                    <button type="submit" class="btn-mod btn-mod-primario" id="cobroConfirmar" aria-keyshortcuts="F8" title="Procesar pago (F8)">
                        <i class="fas fa-check"></i> <span id="cobroConfirmarTexto">Confirmar cobro</span> <kbd class="pos-kbd">F8</kbd>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- ==========================================================
         MODAL: PRODUCTOS (GALERÍA)
         ========================================================== -->
    <div class="modal-overlay" id="posModalProductos" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="posModalTituloProductos">
        <div class="modal-caja pos-modal-productos">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-cube"></i></span>
                    <span id="posModalTituloProductos">Seleccionar producto</span>
                </h2>
                <button type="button" class="modal-cerrar" id="posCerrarModalProductos" aria-label="Cerrar">&times;</button>
            </div>

            <div class="modal-cuerpo">
                <div class="pos-modal-carga" id="posModalCargaProductos">
                    <div class="pos-spin"></div>
                    <span>Cargando productos...</span>
                </div>

                <div class="pos-modal-contenido">
                    <div class="pos-modal-filtros">
                        <div class="pos-modal-buscar">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="search" id="posModalBuscar" placeholder="Buscar producto, código o categoría..." autocomplete="off" />
                            <button type="button" id="posModalBuscarLimpiar" aria-label="Limpiar búsqueda"><i class="fas fa-xmark"></i></button>
                        </div>
                        <div class="pos-modal-conteo" id="posModalConteoProductos">
                            0 productos
                        </div>
                    </div>

                    <div class="pos-modal-categorias" id="posModalCategorias"></div>

                    <div class="pos-modal-grid" id="posModalGridProductos">
                        <!-- Los productos se cargarán aquí dinámicamente -->
                    </div>

                    <div class="pos-modal-vacio" id="posModalVacioProductos">
                        <i class="fas fa-cube"></i>
                        <strong>No hay productos</strong>
                        <span>Prueba con otra búsqueda o categoría.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================================
         MODAL: VENTA REGISTRADA
         ========================================================== -->
    <div class="modal-overlay" id="modalExito" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="exitoTitulo">
        <div class="modal-caja pos-modal-exito">
            <div class="exito-cuerpo">
                <div class="exito-icono"><i class="fas fa-check"></i></div>
                <h3 id="exitoTitulo">Venta registrada</h3>
                <p id="exitoMensaje">El cobro se guardó correctamente.</p>

                <div class="exito-total">
                    <span>Total cobrado</span>
                    <b id="exitoTotal">Bs 0,00</b>
                </div>

                <div class="exito-datos">
                    <div><span>Ticket</span><b id="exitoCodigo">—</b></div>
                    <div><span>Pedido</span><b id="exitoPedido">—</b></div>
                    <div><span>Pago</span><b id="exitoPago">—</b></div>
                </div>

                <div class="exito-acciones">
                    <button type="button" class="btn-mod btn-mod-claro" id="exitoTicket">
                        <i class="fas fa-eye"></i> Ver ticket
                    </button>
                    <button type="button" class="btn-mod btn-mod-claro" id="exitoDescargar">
                        <i class="fas fa-download"></i> Descargar
                    </button>
                    <button type="button" class="btn-mod btn-mod-primario completo" id="exitoNueva">
                        <i class="fas fa-plus"></i> Nueva venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../sistema/pos.js"></script>
    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>