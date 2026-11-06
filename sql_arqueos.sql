-- =====================================================
-- MÓDULO ARQUEO DE CAJA  ·  Restaurante Moderno
-- -----------------------------------------------------
-- Cada apertura / cierre de una caja deja un arqueo.
-- Reglas:
--   · Una caja sólo puede tener UN arqueo abierto.
--   · El arqueo guarda su sucursal (operación aislada
--     por sucursal, aunque la caja cambie de sucursal
--     después).
--   · total_* se reserva para cuando el módulo de ventas
--     empiece a registrar cobros.
--
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_arqueos.sql
-- =====================================================

CREATE TABLE IF NOT EXISTS arqueos_caja (
    id_arqueo              INT PRIMARY KEY AUTO_INCREMENT,
    id_caja                INT NOT NULL,
    id_sucursal            INT NOT NULL,

    id_usuario_apertura    INT NOT NULL,
    id_usuario_cierre      INT NULL,

    estado                 ENUM('abierto', 'cerrado') NOT NULL DEFAULT 'abierto',

    monto_apertura         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monto_cierre           DECIMAL(10,2) NULL,
    monto_esperado         DECIMAL(10,2) NULL,
    diferencia             DECIMAL(10,2) NULL,

    -- Totales que irá dejando el punto de venta
    total_ventas           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_efectivo         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_tarjeta          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_qr               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_otros            DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    observacion_apertura   VARCHAR(255) NULL,
    observacion_cierre     VARCHAR(255) NULL,

    fecha_apertura         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre           DATETIME NULL,

    -- Sólo las filas abiertas exponen el id de su caja:
    -- la clave única impide dos arqueos abiertos en la misma caja.
    caja_abierta           INT AS (CASE WHEN estado = 'abierto' THEN id_caja ELSE NULL END) STORED,

    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_arqueo_abierto (caja_abierta),
    KEY idx_arqueo_sucursal (id_sucursal, estado),
    KEY idx_arqueo_caja (id_caja),
    KEY idx_arqueo_usuario (id_usuario_apertura),
    KEY idx_arqueo_fechas (fecha_apertura),

    CONSTRAINT fk_arqueo_caja
        FOREIGN KEY (id_caja) REFERENCES cajas(id_caja)
        ON UPDATE CASCADE,

    CONSTRAINT fk_arqueo_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
        ON UPDATE CASCADE,

    CONSTRAINT fk_arqueo_usu_apertura
        FOREIGN KEY (id_usuario_apertura) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE,

    CONSTRAINT fk_arqueo_usu_cierre
        FOREIGN KEY (id_usuario_cierre) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
