-- =====================================================
-- IDEMPOTENCIA DE VENTAS  ·  RESTAURANTE MODERNO
-- -----------------------------------------------------
-- Evita que un doble clic / reintento de red registre
-- la misma venta dos veces.
--
-- El POS envía una clave única por cobro; si la clave
-- ya existe, el servidor devuelve la venta existente en
-- lugar de insertar otra.
--
-- Es idempotente: se puede ejecutar más de una vez.
-- =====================================================

USE restauratemoderno;

-- 1) Columna nueva
SET @sql_col = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE ventas ADD COLUMN clave_idempotencia VARCHAR(64) NULL AFTER observaciones',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ventas'
      AND COLUMN_NAME = 'clave_idempotencia'
);
PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- 2) Índice único (los NULL no chocan entre sí)
SET @sql_idx = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE ventas ADD UNIQUE KEY uq_ventas_idempotencia (clave_idempotencia)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ventas'
      AND INDEX_NAME = 'uq_ventas_idempotencia'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
