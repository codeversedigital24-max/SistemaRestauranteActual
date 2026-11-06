-- =====================================================
-- MEJORAS DEL POS  ·  Restaurante Moderno
-- -----------------------------------------------------
--   1. promociones            → descuentos por producto o categoría
--   2. ventas                 → tipo de pedido, delivery y desglose mixto
--   3. venta_detalle          → descuento, promoción e IVA por línea
--
-- Es idempotente en MariaDB 10.4 / MySQL 8 (IF NOT EXISTS).
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_pos_mejoras.sql
-- =====================================================

-- =====================================================
-- 1. PROMOCIONES
-- -----------------------------------------------------
-- Una promoción aplica a UN producto o a UNA categoría.
-- Sólo se aplica la de mayor prioridad (no se acumulan).
-- Si `desde`/`hasta` son NULL la vigencia es abierta.
-- =====================================================
CREATE TABLE IF NOT EXISTS promociones (
    id_promocion   INT PRIMARY KEY AUTO_INCREMENT,
    nombre         VARCHAR(140) NOT NULL,
    descripcion    VARCHAR(255) NULL,

    alcance        ENUM('producto', 'categoria') NOT NULL DEFAULT 'producto',
    id_producto    INT NULL,
    id_categoria   INT NULL,

    tipo_descuento ENUM('porcentaje', 'monto') NOT NULL DEFAULT 'porcentaje',
    valor          DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    prioridad      INT NOT NULL DEFAULT 0,
    desde          DATETIME NULL,
    hasta          DATETIME NULL,
    activo         TINYINT(1) NOT NULL DEFAULT 1,

    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_promo_alcance (alcance, activo),
    KEY idx_promo_fechas (desde, hasta),
    KEY idx_promo_producto (id_producto),
    KEY idx_promo_categoria (id_categoria),
    CONSTRAINT fk_promo_producto
        FOREIGN KEY (id_producto) REFERENCES productos (id_producto)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_promo_categoria
        FOREIGN KEY (id_categoria) REFERENCES categorias_productos (id_categoria)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. VENTAS
-- =====================================================
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS descuento_productos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER descuento,
    ADD COLUMN IF NOT EXISTS tipo_pedido ENUM('mesa', 'llevar', 'delivery') NOT NULL DEFAULT 'mesa' AFTER metodo_pago,
    ADD COLUMN IF NOT EXISTS delivery_nombre VARCHAR(120) NULL AFTER tipo_pedido,
    ADD COLUMN IF NOT EXISTS mixto_efectivo DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER recibido,
    ADD COLUMN IF NOT EXISTS mixto_qr DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER mixto_efectivo,
    ADD COLUMN IF NOT EXISTS mixto_tarjeta DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER mixto_qr,
    ADD COLUMN IF NOT EXISTS referencia_pago VARCHAR(100) NULL AFTER mixto_tarjeta;

-- =====================================================
-- 3. VENTA_DETALLE
-- =====================================================
ALTER TABLE venta_detalle
    ADD COLUMN IF NOT EXISTS precio_base DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER precio_unitario,
    ADD COLUMN IF NOT EXISTS descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER precio_base,
    ADD COLUMN IF NOT EXISTS descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER descuento_porcentaje,
    ADD COLUMN IF NOT EXISTS promocion VARCHAR(140) NULL AFTER descuento,
    ADD COLUMN IF NOT EXISTS control_stock TINYINT(1) NULL DEFAULT NULL COMMENT 'Snapshot al momento de la venta' AFTER promocion,
    ADD COLUMN IF NOT EXISTS impuesto_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER control_stock,
    ADD COLUMN IF NOT EXISTS impuesto DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER impuesto_porcentaje,
    ADD COLUMN IF NOT EXISTS total DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER impuesto;
