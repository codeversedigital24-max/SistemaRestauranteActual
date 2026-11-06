-- =====================================================
-- MÓDULO DE COMPRAS + KARDEX  ·  Restaurante Moderno
-- -----------------------------------------------------
-- Cada sucursal mantiene su propio inventario:
--   · productos_sucursal → stock, costo promedio y
--     precio de venta POR SUCURSAL.
--   · compras / compra_detalle → documentos de compra.
--   · kardex → movimientos valorados con saldo.
--   · proveedores → catálogo de proveedores.
--
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_compras.sql
-- =====================================================

-- =====================================================
-- 1. PROVEEDORES
-- =====================================================
CREATE TABLE IF NOT EXISTS proveedores (
    id_proveedor   INT PRIMARY KEY AUTO_INCREMENT,
    nombre         VARCHAR(150) NOT NULL,
    razon_social   VARCHAR(150) NULL,
    nit            VARCHAR(30) NULL,
    contacto       VARCHAR(100) NULL,
    telefono       VARCHAR(30) NULL,
    email          VARCHAR(120) NULL,
    direccion      VARCHAR(255) NULL,
    ciudad         VARCHAR(80) NULL,
    notas          VARCHAR(255) NULL,
    estado         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_proveedor_nit (nit),
    KEY idx_proveedor_estado (estado),
    KEY idx_proveedor_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. INVENTARIO POR SUCURSAL
-- -----------------------------------------------------
-- El stock y el precio de venta viven aquí, no en el
-- producto global. Así una compra en Norte no altera
-- el inventario ni el precio de Central.
-- =====================================================

-- El stock global pasa a decimal para admitir granel (kg, g, L).
ALTER TABLE productos
    MODIFY stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    MODIFY stock_minimo DECIMAL(12,3) NOT NULL DEFAULT 0.000;

CREATE TABLE IF NOT EXISTS productos_sucursal (
    id_producto_sucursal INT PRIMARY KEY AUTO_INCREMENT,
    id_producto          INT NOT NULL,
    id_sucursal          INT NOT NULL,

    stock                DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    stock_minimo         DECIMAL(12,3) NOT NULL DEFAULT 0.000,

    costo_promedio       DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    valor_inventario     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    precio_venta         DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    control_stock        TINYINT(1) NOT NULL DEFAULT 1,
    estado               TINYINT(1) NOT NULL DEFAULT 1,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_producto_sucursal (id_producto, id_sucursal),
    KEY idx_ps_sucursal (id_sucursal),
    KEY idx_ps_stock (id_sucursal, stock),

    CONSTRAINT fk_ps_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ps_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. COMPRAS (cabecera)
-- =====================================================
CREATE TABLE IF NOT EXISTS compras (
    id_compra            INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal          INT NOT NULL,
    id_proveedor         INT NOT NULL,
    id_usuario           INT NOT NULL,

    tipo_documento       ENUM('factura', 'recibo', 'nota', 'otro') NOT NULL DEFAULT 'factura',
    numero_documento     VARCHAR(60) NULL,
    fecha_compra         DATETIME NOT NULL,
    forma_pago           ENUM('contado', 'credito', 'transferencia', 'qr', 'otro') NOT NULL DEFAULT 'contado',

    estado               ENUM('registrada', 'anulada') NOT NULL DEFAULT 'registrada',

    subtotal             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descuento            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    impuesto_porcentaje  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    impuesto             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    abono                DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Pago inicial de compras al crédito',

    observaciones        VARCHAR(255) NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_compra_sucursal (id_sucursal, fecha_compra),
    KEY idx_compra_proveedor (id_proveedor),
    KEY idx_compra_estado (estado),
    KEY idx_compra_documento (id_sucursal, id_proveedor, numero_documento),

    CONSTRAINT fk_compra_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
        ON UPDATE CASCADE,
    CONSTRAINT fk_compra_proveedor
        FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor)
        ON UPDATE CASCADE,
    CONSTRAINT fk_compra_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. DETALLE DE COMPRA
-- =====================================================
CREATE TABLE IF NOT EXISTS compra_detalle (
    id_detalle            INT PRIMARY KEY AUTO_INCREMENT,
    id_compra             INT NOT NULL,
    id_producto           INT NOT NULL,

    cantidad              DECIMAL(12,3) NOT NULL,
    costo_unitario        DECIMAL(12,4) NOT NULL,
    precio_venta_unitario DECIMAL(10,2) NOT NULL,
    subtotal              DECIMAL(12,2) NOT NULL,

    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    KEY idx_detalle_compra (id_compra),
    KEY idx_detalle_producto (id_producto),

    CONSTRAINT fk_detalle_compra
        FOREIGN KEY (id_compra) REFERENCES compras(id_compra)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detalle_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. PAGOS / ABONOS DE COMPRAS AL CRÉDITO
-- -----------------------------------------------------
-- El abono inicial vive en compras.abono; los pagos
-- posteriores se registran aquí, uno por compra.
-- =====================================================
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

-- =====================================================
-- 6. KARDEX VALORADO
-- -----------------------------------------------------
-- cantidad: + entrada / − salida
-- costo_promedio y saldo_valor quedan congelados en
-- cada movimiento para poder auditar la historia.
-- =====================================================
CREATE TABLE IF NOT EXISTS kardex (
    id_movimiento     BIGINT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal       INT NOT NULL,
    id_producto       INT NOT NULL,

    tipo              ENUM('entrada', 'salida', 'ajuste') NOT NULL,
    origen            ENUM('compra', 'venta', 'ajuste', 'inicial') NOT NULL DEFAULT 'ajuste',
    id_referencia     BIGINT NULL COMMENT 'id de compra, venta o ajuste',

    fecha             DATETIME NOT NULL,
    detalle           VARCHAR(200) NULL,

    cantidad          DECIMAL(12,3) NOT NULL COMMENT '+ entra / - sale',
    costo_unitario    DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    precio_venta      DECIMAL(10,2) NULL,
    valor_movimiento  DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    saldo_cantidad    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    costo_promedio    DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    saldo_valor       DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    id_usuario        INT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    KEY idx_kardex_sucursal (id_sucursal, fecha),
    KEY idx_kardex_producto (id_producto, fecha),
    KEY idx_kardex_tipo (id_sucursal, tipo, fecha),
    KEY idx_kardex_referencia (origen, id_referencia),

    CONSTRAINT fk_kardex_sucursal
        FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
        ON UPDATE CASCADE,
    CONSTRAINT fk_kardex_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON UPDATE CASCADE,
    CONSTRAINT fk_kardex_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. GALERÍA DE IMÁGENES DE PRODUCTO
-- -----------------------------------------------------
-- Una imagen principal por producto (clave generada).
-- =====================================================
CREATE TABLE IF NOT EXISTS productos_imagenes (
    id_imagen        INT PRIMARY KEY AUTO_INCREMENT,
    id_producto      INT NOT NULL,

    archivo          VARCHAR(255) NOT NULL COMMENT 'ruta relativa: assets/img/productos/...',
    nombre_original  VARCHAR(255) NULL,
    tipo_mime        VARCHAR(60) NULL,
    tamano           INT NULL COMMENT 'bytes',
    ancho            INT NULL,
    alto             INT NULL,

    orden            INT NOT NULL DEFAULT 0,
    principal        TINYINT(1) NOT NULL DEFAULT 0,

    principal_unico  INT AS (CASE WHEN principal = 1 THEN id_producto ELSE NULL END) STORED,

    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_imagen_principal (principal_unico),
    KEY idx_imagen_producto (id_producto, orden),

    CONSTRAINT fk_imagen_producto
        FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. PROVEEDORES DE EJEMPLO (sólo si la tabla está vacía)
-- =====================================================
INSERT IGNORE INTO proveedores
    (nombre, razon_social, nit, contacto, telefono, email, ciudad, notas)
VALUES
    ('Distribuidora Central', 'Distribuidora Central S.R.L.', '1020304050', 'María López', '+591 71234567', 'ventas@distcentral.bo', 'Santa Cruz', 'Proveedor de abarrotes y bebidas'),
    ('Carnes del Oriente', 'Carnes del Oriente S.A.', '3040506070', 'Jorge Rojas', '+591 72345678', 'pedidos@carnesoriente.bo', 'Santa Cruz', 'Carnes y embutidos'),
    ('Verdulería La Huerta', NULL, '5060708090', 'Ana Pérez', '+591 73456789', 'contacto@lahuerta.bo', 'Santa Cruz', 'Frutas y verduras frescas');
