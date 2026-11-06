<?php
/**
 * =====================================================
 * PRECIO DE VENTA DEL POS  (modelos/pos/PrecioPos.php)
 * -----------------------------------------------------
 * Única fuente de verdad para el precio de una línea:
 *
 *   1. Precio base (sucursal o producto).
 *   2. Oferta propia del producto (porcentaje o precio fijo).
 *   3. Mejor promoción vigente (producto o categoría).
 *   4. IVA del producto.
 *
 * El POS y el servidor usan exactamente este cálculo.
 * =====================================================
 */

require_once __DIR__ . '/Promocion.php';

class PrecioPos
{
    /**
     * @param float $precioBase Precio de la sucursal (o del producto).
     * @param array $producto   Fila con descuento_porcentaje, precio_oferta,
     *                          oferta_vigente / oferta_activa, impuesto_porcentaje.
     * @param array $promociones Lista de promociones vigentes.
     */
    public static function calcular(float $precioBase, array $producto, array $promociones = []): array
    {
        $base = max(0, round($precioBase, 2));

        $idProducto  = (int) ($producto['id_producto'] ?? $producto['id'] ?? 0);
        $idCategoria = (int) ($producto['id_categoria'] ?? 0);

        // ---- 1. Oferta propia del producto ----
        $ofertaVigente = ((int) (
            $producto['oferta_vigente']
            ?? $producto['oferta_activa']
            ?? 0
        )) === 1;

        $pctProducto = (float) (
            $producto['descuento_porcentaje']
            ?? $producto['descuento']
            ?? 0
        );

        $precioOferta = $producto['precio_oferta'] ?? null;
        $precioOferta = ($precioOferta === null || $precioOferta === '')
            ? null
            : (float) $precioOferta;

        $precioProducto = $base;

        if ($ofertaVigente) {
            $candidatos = [];

            if ($pctProducto > 0) {
                $candidatos[] = $base * (1 - (min(100.0, $pctProducto) / 100));
            }

            if ($precioOferta !== null && $precioOferta > 0 && $precioOferta < $base) {
                $candidatos[] = $precioOferta;
            }

            if ($candidatos !== []) {
                $precioProducto = min($candidatos);
            }
        }

        $precioProducto = round(max(0, min($base, $precioProducto)), 2);
        $descuentoProducto = round($base - $precioProducto, 2);

        // ---- 2. Promoción vigente ----
        $promo = Promocion::mejor($promociones, $idProducto, $idCategoria, $precioProducto);
        $precioPromo = $precioProducto;

        if ($promo !== null) {
            $precioPromo = round(max(0, $precioProducto - (float) $promo['descuento_unitario']), 2);
        }

        $descuentoPromocion = round($precioProducto - $precioPromo, 2);
        $descuentoUnitario  = round($base - $precioPromo, 2);
        $descuentoPct       = $base > 0 ? round(($descuentoUnitario / $base) * 100, 2) : 0;

        // ---- 3. IVA ----
        $ivaPct = (float) (
            $producto['impuesto_porcentaje']
            ?? $producto['impuesto']
            ?? 0
        );
        $ivaPct = max(0, min(100, round($ivaPct, 2)));
        $ivaUnitario = round($precioPromo * ($ivaPct / 100), 2);

        return [
            'precio_base'          => $base,
            'precio_producto'      => $precioProducto,
            'descuento_producto'   => $descuentoProducto,
            'oferta_aplicada'      => $descuentoProducto > 0.004 ? 1 : 0,
            'descuento_porcentaje_producto' => $base > 0
                ? round(($descuentoProducto / $base) * 100, 2)
                : 0,

            'promocion_id'         => $promo['id'] ?? 0,
            'promocion'            => $promo['nombre'] ?? '',
            'promocion_etiqueta'   => $promo['etiqueta'] ?? '',
            'descuento_promocion'  => $descuentoPromocion,

            'descuento_porcentaje' => $descuentoPct,
            'descuento_unitario'   => $descuentoUnitario,

            'precio_unitario'      => $precioPromo,
            'impuesto_porcentaje'  => $ivaPct,
            'impuesto_unitario'    => $ivaUnitario,
            'total_unitario'       => round($precioPromo + $ivaUnitario, 2),
        ];
    }
}
