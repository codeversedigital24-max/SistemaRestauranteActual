-- =====================================================
-- CUENTAS POR PAGAR  ·  Restaurante Moderno
-- -----------------------------------------------------
-- Agrega:
--   · compras.abono       → pago inicial de compras al crédito
--   · sucursales.logo     → logo propio para el recibo PDF
--   · compras_pagos       → abonos posteriores por compra
--
-- Es idempotente en MariaDB/MySQL 8 (IF NOT EXISTS).
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_cuentas_pagar.sql
-- =====================================================

ALTER TABLE compras
    ADD COLUMN IF NOT EXISTS abono DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total;

ALTER TABLE sucursales
    ADD COLUMN IF NOT EXISTS logo VARCHAR(255) NULL AFTER email;

-- N° de guía del recibo emitido por la empresa proveedora.
-- Obligatorio al registrar cada abono (ver CuentaPagar).
ALTER TABLE compras_pagos
    ADD COLUMN IF NOT EXISTS numero_guia VARCHAR(100) NULL AFTER metodo_pago,
    ADD KEY IF NOT EXISTS idx_pago_guia (numero_guia);

CREATE TABLE IF NOT EXISTS compras_pagos (
    id_pago       INT PRIMARY KEY AUTO_INCREMENT,
    id_compra     INT NOT NULL,
    id_sucursal   INT NOT NULL,
    id_proveedor  INT NOT NULL,
    id_usuario    INT NOT NULL,

    monto         DECIMAL(12,2) NOT NULL,
    metodo_pago   ENUM('efectivo', 'transferencia', 'qr', 'otro') NOT NULL DEFAULT 'efectivo',
    numero_guia   VARCHAR(100) NULL COMMENT 'N° de guía del recibo del proveedor',
    referencia    VARCHAR(100) NULL,
    observaciones VARCHAR(255) NULL,
    fecha_pago    DATETIME NOT NULL,

    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    KEY idx_pago_compra (id_compra),
    KEY idx_pago_sucursal (id_sucursal, fecha_pago),
    KEY idx_pago_proveedor (id_proveedor),
    KEY idx_pago_guia (numero_guia),

    CONSTRAINT fk_pago_compra
        FOREIGN KEY (id_compra) REFERENCES compras(id_compra)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pago_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
        ON UPDATE CASCADE,
    CONSTRAINT fk_pago_proveedor
        FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor)
        ON UPDATE CASCADE,
    CONSTRAINT fk_pago_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
