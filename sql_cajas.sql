-- =====================================================
-- MÓDULO DE CAJAS  ·  Restaurante Moderno
-- -----------------------------------------------------
-- Una sucursal puede tener una o más cajas.
-- Cada caja puede asignarse a un usuario (cajero) y
-- guarda el historial de asignaciones para auditoría.
--
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_cajas.sql
-- =====================================================

-- =====================================================
-- 1. CAJAS
-- =====================================================
CREATE TABLE IF NOT EXISTS cajas (
    id_caja                 INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal             INT NOT NULL,

    codigo                  VARCHAR(20) NOT NULL,
    nombre                  VARCHAR(80) NOT NULL,
    descripcion             VARCHAR(255) NULL,

    id_usuario_asignado     INT NULL COMMENT 'Cajero responsable actual',
    monto_inicial_sugerido  DECIMAL(10,2) NOT NULL DEFAULT 0.00
                            COMMENT 'Monto base sugerido al abrir el arqueo',

    estado                  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva',

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_caja_sucursal_codigo (id_sucursal, codigo),
    UNIQUE KEY uq_caja_sucursal_nombre (id_sucursal, nombre),
    KEY idx_caja_usuario (id_usuario_asignado),
    KEY idx_caja_estado (estado),

    CONSTRAINT fk_caja_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
        ON UPDATE CASCADE,

    CONSTRAINT fk_caja_usuario
        FOREIGN KEY (id_usuario_asignado) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. HISTORIAL DE ASIGNACIONES
-- =====================================================
CREATE TABLE IF NOT EXISTS cajas_asignaciones (
    id_asignacion   INT PRIMARY KEY AUTO_INCREMENT,
    id_caja         INT NOT NULL,
    id_usuario      INT NOT NULL,
    id_asignado_por INT NULL COMMENT 'Usuario que hizo la asignación',

    observacion     VARCHAR(255) NULL,
    fecha_desde     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_hasta     DATETIME NULL,
    activa          TINYINT(1) NOT NULL DEFAULT 1,

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    KEY idx_asig_caja (id_caja, activa),
    KEY idx_asig_usuario (id_usuario, activa),

    CONSTRAINT fk_asig_caja
        FOREIGN KEY (id_caja) REFERENCES cajas(id_caja)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_asig_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_asig_por
        FOREIGN KEY (id_asignado_por) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. CAJAS DE EJEMPLO
-- =====================================================
INSERT INTO cajas
    (id_sucursal, codigo, nombre, descripcion, id_usuario_asignado, monto_inicial_sugerido, estado)
VALUES
((SELECT id_sucursal FROM sucursales WHERE codigo_sucursal = 'SUC-001'),
 'CAJA-01', 'Caja 1 · Principal', 'Mostrador principal y pedidos en salón',
 (SELECT id_usuario FROM usuarios WHERE username = 'cajero1'), 200.00, 1),

((SELECT id_sucursal FROM sucursales WHERE codigo_sucursal = 'SUC-001'),
 'CAJA-02', 'Caja 2 · Delivery', 'Pedidos para llevar y delivery',
 NULL, 150.00, 1),

((SELECT id_sucursal FROM sucursales WHERE codigo_sucursal = 'SUC-002'),
 'CAJA-01', 'Caja 1 · Norte', 'Caja única de la sucursal Norte',
 NULL, 150.00, 1),

((SELECT id_sucursal FROM sucursales WHERE codigo_sucursal = 'SUC-003'),
 'CAJA-01', 'Caja 1 · Este', 'Caja única de la sucursal Este',
 NULL, 150.00, 1),

((SELECT id_sucursal FROM sucursales WHERE codigo_sucursal = 'SUC-004'),
 'CAJA-01', 'Caja 1 · Oeste', 'Caja única de la sucursal Oeste',
 NULL, 150.00, 1)
ON DUPLICATE KEY UPDATE
    descripcion            = VALUES(descripcion),
    id_usuario_asignado    = VALUES(id_usuario_asignado),
    monto_inicial_sugerido = VALUES(monto_inicial_sugerido),
    estado                 = VALUES(estado);

-- =====================================================
-- 4. ASIGNACIÓN INICIAL EN EL HISTORIAL
-- =====================================================
INSERT INTO cajas_asignaciones
    (id_caja, id_usuario, id_asignado_por, observacion, fecha_desde, activa)
SELECT c.id_caja,
       c.id_usuario_asignado,
       NULL,
       'Asignación inicial de ejemplo',
       NOW(),
       1
FROM cajas c
WHERE c.id_usuario_asignado IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM cajas_asignaciones a
      WHERE a.id_caja = c.id_caja AND a.activa = 1
  );
