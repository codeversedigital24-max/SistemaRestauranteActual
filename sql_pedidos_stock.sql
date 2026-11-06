-- =====================================================
-- STOCK EN PEDIDOS  ·  Restaurante Moderno
-- -----------------------------------------------------
-- El pedido pasa a descontar stock AL TOMARSE (igual que
-- el POS, sobre el stock global de `productos.stock`) y
-- devuelve el stock si se cancela ANTES de cobrarse.
--
-- QUÉ HACE ESTE SCRIPT
--   1) pedido_detalle.control_stock  → snapshot de si la
--      línea controlaba stock al momento de pedir.
--      Se rellena desde productos.control_stock.
--   2) pedidos.stock_descontado      → 1 = el stock ya se
--      descontó al agregar los productos al pedido.
--
-- Es idempotente (MariaDB 10.4 / MySQL 8): se puede
-- ejecutar varias veces sin errores.
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_pedidos_stock.sql
-- =====================================================

USE restauratemoderno;

DROP PROCEDURE IF EXISTS rm_pedidos_stock;

DELIMITER $$

CREATE PROCEDURE rm_pedidos_stock()
BEGIN
    -- -------------------------------------------------
    -- 1) Snapshot de control de stock por línea
    -- -------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'pedido_detalle'
          AND COLUMN_NAME  = 'control_stock'
    ) THEN
        ALTER TABLE pedido_detalle
            ADD COLUMN control_stock TINYINT(1) NULL DEFAULT NULL
            COMMENT 'Snapshot al momento de tomar el pedido';
    END IF;

    -- Rellenar las líneas históricas con el control actual del producto.
    UPDATE pedido_detalle d
      INNER JOIN productos p ON p.id_producto = d.id_producto
       SET d.control_stock = p.control_stock
     WHERE d.control_stock IS NULL;

    -- -------------------------------------------------
    -- 2) Bandera en la cabecera del pedido
    -- -------------------------------------------------
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'pedidos'
          AND COLUMN_NAME  = 'stock_descontado'
    ) THEN
        ALTER TABLE pedidos
            ADD COLUMN stock_descontado TINYINT(1) NOT NULL DEFAULT 0
            COMMENT '1 = el stock ya se descontó al tomar el pedido';
    END IF;
END$$

DELIMITER ;

CALL rm_pedidos_stock();

DROP PROCEDURE IF EXISTS rm_pedidos_stock;

-- -----------------------------------------------------
-- Verificación (debe devolver las dos columnas)
-- -----------------------------------------------------
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND (
        (TABLE_NAME = 'pedido_detalle' AND COLUMN_NAME = 'control_stock')
     OR (TABLE_NAME = 'pedidos'        AND COLUMN_NAME = 'stock_descontado')
   )
 ORDER BY TABLE_NAME;
