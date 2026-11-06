-- =====================================================
-- MOVIMIENTOS DE CAJA (EGRESOS / GASTOS)  ·  Arqueo
-- Restaurante Moderno · MySQL 8 / MariaDB 10.4+
-- -----------------------------------------------------
-- 1) Crea la tabla `arqueo_movimientos`: cada egreso o
--    gasto que sale de la caja MIENTRAS el arqueo está
--    abierto queda registrado con su medio de pago.
--    Sólo los movimientos en EFECTIVO reducen el efectivo
--    esperado del arqueo; el resto se informa aparte.
--
-- 2) Agrega de forma IDEMPOTENTE a `arqueos_caja` las
--    columnas que necesita el cierre ampliado:
--      total_egresos          DECIMAL(12,2) NOT NULL DEFAULT 0
--      efectivo_esperado      DECIMAL(12,2) NULL
--      total_transferencia    DECIMAL(12,2) NOT NULL DEFAULT 0
--      ticket_cierre_generado TINYINT(1)    NOT NULL DEFAULT 0
--    (total_qr / total_tarjeta ya existen: se crean sólo
--     si una instalación vieja no las tuviera).
--
-- Es idempotente: se puede ejecutar varias veces.
-- Ejecutar sobre la base del sistema, por ejemplo:
--   mysql -u root restauratemoderno < sql_arqueo_movimientos.sql
-- o pegando el contenido en phpMyAdmin con la base
-- `restauratemoderno` seleccionada.
-- =====================================================

-- =====================================================
-- 1. MOVIMIENTOS DE CAJA
-- =====================================================
CREATE TABLE IF NOT EXISTS arqueo_movimientos (
    id_movimiento  INT PRIMARY KEY AUTO_INCREMENT,

    id_arqueo      INT NOT NULL COMMENT 'Arqueo (turno) al que pertenece',
    id_sucursal    INT NOT NULL COMMENT 'Sucursal de la caja al momento del movimiento',
    id_usuario     INT NOT NULL COMMENT 'Usuario que registró el movimiento',

    tipo           ENUM('egreso', 'gasto') NOT NULL DEFAULT 'egreso',
    concepto       VARCHAR(200) NOT NULL,
    monto          DECIMAL(12,2) NOT NULL,

    metodo_pago    ENUM('efectivo', 'transferencia', 'qr', 'tarjeta', 'otro')
                   NOT NULL DEFAULT 'efectivo',
    referencia     VARCHAR(100) NULL,
    observacion    VARCHAR(255) NULL,

    fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_mov_arqueo (id_arqueo),
    KEY idx_mov_sucursal (id_sucursal, fecha),
    KEY idx_mov_usuario (id_usuario),

    CONSTRAINT fk_mov_arqueo
        FOREIGN KEY (id_arqueo) REFERENCES arqueos_caja (id_arqueo)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_mov_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales (id_sucursal)
        ON UPDATE CASCADE,

    CONSTRAINT fk_mov_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. COLUMNAS NUEVAS EN arqueos_caja (idempotente)
-- =====================================================
DROP PROCEDURE IF EXISTS rm_arqueo_movimientos_columnas;

DELIMITER $$

CREATE PROCEDURE rm_arqueo_movimientos_columnas()
BEGIN
    -- Efectivo esperado = apertura + ventas efectivo - egresos efectivo
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'arqueos_caja'
          AND COLUMN_NAME  = 'efectivo_esperado'
    ) THEN
        ALTER TABLE arqueos_caja
            ADD COLUMN efectivo_esperado DECIMAL(12,2) NULL AFTER monto_esperado;
    END IF;

    -- Total de egresos / gastos del turno (todos los medios de pago)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'arqueos_caja'
          AND COLUMN_NAME  = 'total_egresos'
    ) THEN
        ALTER TABLE arqueos_caja
            ADD COLUMN total_egresos DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_otros;
    END IF;

    -- Ventas cobradas por transferencia (medio propio del arqueo)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'arqueos_caja'
          AND COLUMN_NAME  = 'total_transferencia'
    ) THEN
        ALTER TABLE arqueos_caja
            ADD COLUMN total_transferencia DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_efectivo;
    END IF;

    -- Compatibilidad: instalaciones viejas sin estas dos columnas
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'arqueos_caja'
          AND COLUMN_NAME  = 'total_tarjeta'
    ) THEN
        ALTER TABLE arqueos_caja
            ADD COLUMN total_tarjeta DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_efectivo;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'arqueos_caja'
          AND COLUMN_NAME  = 'total_qr'
    ) THEN
        ALTER TABLE arqueos_caja
            ADD COLUMN total_qr DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_tarjeta;
    END IF;

    -- Marca de ticket de cierre emitido (permite reimprimir)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'arqueos_caja'
          AND COLUMN_NAME  = 'ticket_cierre_generado'
    ) THEN
        ALTER TABLE arqueos_caja
            ADD COLUMN ticket_cierre_generado TINYINT(1) NOT NULL DEFAULT 0 AFTER efectivo_esperado;
    END IF;
END$$

DELIMITER ;

CALL rm_arqueo_movimientos_columnas();

DROP PROCEDURE IF EXISTS rm_arqueo_movimientos_columnas;

-- =====================================================
-- 3. RESPALDO DE VALORES (sin tocar arqueos cerrados)
-- -----------------------------------------------------
-- Deja calculado el efectivo esperado de los turnos que
-- quedaron abiertos antes de este cambio.
-- =====================================================
UPDATE arqueos_caja
SET efectivo_esperado = ROUND(
        monto_apertura + total_efectivo - COALESCE(total_egresos, 0),
        2
    )
WHERE estado = 'abierto'
  AND efectivo_esperado IS NULL;

UPDATE arqueos_caja
SET total_egresos = 0.00
WHERE total_egresos IS NULL;

-- =====================================================
-- 4. VERIFICACIÓN (opcional)
-- =====================================================
-- SHOW COLUMNS FROM arqueos_caja;
-- SHOW COLUMNS FROM arqueo_movimientos;
-- SELECT * FROM arqueos_caja WHERE estado = 'abierto';
-- SELECT tipo, metodo_pago, COUNT(*) AS cantidad, SUM(monto) AS total
--   FROM arqueo_movimientos
--  GROUP BY tipo, metodo_pago;
