-- =====================================================
-- MÓDULO DE PRODUCTOS  ·  Restaurante Moderno
-- -----------------------------------------------------
-- Crea el catálogo de categorías y productos.
-- Es idempotente: se puede ejecutar varias veces.
--
-- Ejecutar:
--   mysql -u root restauratemoderno < sql_productos.sql
-- =====================================================

-- =====================================================
-- 1. CATEGORÍAS
-- =====================================================
CREATE TABLE IF NOT EXISTS categorias_productos (
    id_categoria       INT PRIMARY KEY AUTO_INCREMENT,
    nombre_categoria   VARCHAR(80) NOT NULL UNIQUE,
    descripcion        VARCHAR(255) NULL,
    icono              VARCHAR(50) DEFAULT 'fa-utensils',
    orden              INT DEFAULT 0,
    estado             TINYINT DEFAULT 1 COMMENT '1=activa, 0=inactiva',
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. PRODUCTOS
-- =====================================================
CREATE TABLE IF NOT EXISTS productos (
    id_producto          INT PRIMARY KEY AUTO_INCREMENT,
    id_categoria         INT NOT NULL,

    codigo               VARCHAR(20) NOT NULL UNIQUE,
    nombre               VARCHAR(120) NOT NULL,
    descripcion          TEXT NULL,
    imagen               VARCHAR(255) NULL,

    -- Precio y oferta
    precio               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    precio_oferta        DECIMAL(10,2) NULL,
    oferta_activa        TINYINT(1) NOT NULL DEFAULT 0,
    oferta_desde         DATETIME NULL,
    oferta_hasta         DATETIME NULL,

    -- Costos e impuestos
    costo                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    impuesto_porcentaje  DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    -- Operación de restaurante
    tiempo_preparacion   INT NOT NULL DEFAULT 0 COMMENT 'minutos',
    control_stock        TINYINT(1) NOT NULL DEFAULT 0,
    stock                INT NOT NULL DEFAULT 0,
    stock_minimo         INT NOT NULL DEFAULT 0,

    -- Visibilidad
    disponible           TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=se puede vender',
    destacado            TINYINT(1) NOT NULL DEFAULT 0,
    estado               TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',

    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_producto_categoria
        FOREIGN KEY (id_categoria) REFERENCES categorias_productos(id_categoria),

    INDEX idx_producto_categoria (id_categoria),
    INDEX idx_producto_estado (estado),
    INDEX idx_producto_oferta (oferta_activa),
    INDEX idx_producto_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. CATEGORÍAS INICIALES
-- =====================================================
INSERT INTO categorias_productos (nombre_categoria, descripcion, icono, orden, estado) VALUES
('Entradas',        'Papas, aros de cebolla y bocaditos',            'fa-bowl-food',   1, 1),
('Hamburguesas',    'Hamburguesas simples, dobles y especiales',     'fa-burger',      2, 1),
('Pizzas',          'Pizzas personales y familiares',                'fa-pizza-slice', 3, 1),
('Alitas y Broaster','Alitas, pollo broaster y combos de pollo',     'fa-drumstick-bite', 4, 1),
('Salchipapas',     'Salchipapas clásicas y especiales',             'fa-bowl-rice',   5, 1),
('Bebidas',         'Gaseosas, jugos y aguas',                       'fa-glass-water', 6, 1),
('Postres',         'Brownies, helados y dulces',                    'fa-ice-cream',   7, 1),
('Combos',          'Combos personales, dúos y familiares',          'fa-box-open',    8, 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    icono       = VALUES(icono),
    orden       = VALUES(orden),
    estado      = VALUES(estado);

-- =====================================================
-- 4. PRODUCTOS DE EJEMPLO (precios en Bs)
-- =====================================================
INSERT INTO productos
    (id_categoria, codigo, nombre, descripcion, precio, descuento_porcentaje, precio_oferta,
     oferta_activa, oferta_desde, oferta_hasta, costo, impuesto_porcentaje,
     tiempo_preparacion, control_stock, stock, stock_minimo, disponible, destacado, estado)
VALUES
-- Entradas
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Entradas'),
 'PAPA-001', 'Papas Fritas Clásicas', 'Porción de papas fritas crocantes con sal de mar.', 15.00, 0.00, NULL, 0, NULL, NULL, 5.50, 13.00, 8, 1, 80, 10, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Entradas'),
 'PAPA-002', 'Papas con Queso y Tocino', 'Papas fritas cubiertas con queso cheddar y tocino.', 22.00, 10.00, 19.80, 1, NULL, NULL, 8.00, 13.00, 10, 1, 45, 8, 1, 0, 1),

-- Hamburguesas
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Hamburguesas'),
 'HAMB-001', 'Hamburguesa Clásica', 'Carne 150g, lechuga, tomate, cebolla y salsa de la casa.', 25.00, 0.00, NULL, 0, NULL, NULL, 9.50, 13.00, 12, 1, 60, 10, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Hamburguesas'),
 'HAMB-002', 'Hamburguesa Doble Queso', 'Doble carne 300g con doble queso cheddar.', 35.00, 15.00, 29.75, 1, NULL, NULL, 14.00, 13.00, 15, 1, 40, 8, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Hamburguesas'),
 'HAMB-003', 'Hamburguesa BBQ Bacon', 'Carne 180g, bacon, queso ahumado y salsa BBQ.', 38.00, 0.00, 32.00, 1, NULL, NULL, 15.50, 13.00, 15, 1, 35, 8, 1, 0, 1),

-- Pizzas
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Pizzas'),
 'PIZZA-001', 'Pizza Pepperoni Personal', 'Masa artesanal, salsa de tomate y pepperoni.', 45.00, 0.00, NULL, 0, NULL, NULL, 16.00, 13.00, 18, 1, 25, 5, 1, 0, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Pizzas'),
 'PIZZA-002', 'Pizza Familiar Suprema', 'Pizza grande con pepperoni, jamón, champiñones y aceitunas.', 85.00, 20.00, 68.00, 1, NULL, NULL, 32.00, 13.00, 22, 1, 15, 4, 1, 1, 1),

-- Alitas y Broaster
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Alitas y Broaster'),
 'ALITA-001', 'Alitas BBQ x6', 'Seis alitas bañadas en salsa BBQ.', 30.00, 0.00, NULL, 0, NULL, NULL, 12.00, 13.00, 14, 1, 30, 6, 1, 0, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Alitas y Broaster'),
 'ALITA-002', 'Alitas Picantes x12', 'Doce alitas con salsa picante de la casa.', 55.00, 10.00, NULL, 1, NULL, NULL, 22.00, 13.00, 18, 1, 20, 5, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Alitas y Broaster'),
 'BROAS-001', 'Pollo Broaster con Papas', 'Presa de pollo broaster, papas fritas y ensalada.', 32.00, 0.00, NULL, 0, NULL, NULL, 13.00, 13.00, 16, 1, 25, 5, 1, 0, 1),

-- Salchipapas
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Salchipapas'),
 'SALCHI-001', 'Salchipapa Clásica', 'Papas fritas con salchicha y salsas.', 20.00, 0.00, NULL, 0, NULL, NULL, 7.50, 13.00, 10, 1, 50, 10, 1, 0, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Salchipapas'),
 'SALCHI-002', 'Salchipapa Especial', 'Papas, salchicha, queso, tocino y huevo de codorniz.', 28.00, 15.00, NULL, 1, NULL, NULL, 11.00, 13.00, 12, 1, 35, 8, 1, 1, 1),

-- Bebidas
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Bebidas'),
 'BEB-001', 'Coca-Cola 500 ml', 'Gaseosa fría 500 ml.', 8.00, 0.00, NULL, 0, NULL, NULL, 4.00, 13.00, 1, 1, 120, 24, 1, 0, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Bebidas'),
 'BEB-002', 'Limonada Frozen', 'Limonada natural batida con hielo.', 15.00, 0.00, NULL, 0, NULL, NULL, 4.50, 13.00, 5, 0, 0, 0, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Bebidas'),
 'BEB-003', 'Agua Mineral 600 ml', 'Agua mineral sin gas.', 6.00, 0.00, NULL, 0, NULL, NULL, 2.50, 13.00, 1, 1, 100, 20, 1, 0, 1),

-- Postres
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Postres'),
 'POST-001', 'Brownie con Helado', 'Brownie de chocolate caliente con helado de vainilla.', 18.00, 0.00, NULL, 0, NULL, NULL, 6.50, 13.00, 5, 1, 20, 5, 1, 0, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Postres'),
 'POST-002', 'Sundae de Chocolate', 'Helado de vainilla con salsa de chocolate y topping.', 12.00, 0.00, NULL, 0, NULL, NULL, 4.00, 13.00, 3, 1, 30, 8, 1, 0, 1),

-- Combos
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Combos'),
 'COMBO-001', 'Combo Personal', 'Hamburguesa clásica + papas + bebida.', 45.00, 10.00, 40.50, 1, NULL, NULL, 18.00, 13.00, 15, 0, 0, 0, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Combos'),
 'COMBO-002', 'Combo Familiar', 'Pizza familiar + 6 alitas + 2 bebidas.', 120.00, 20.00, 96.00, 1, NULL, NULL, 48.00, 13.00, 25, 0, 0, 0, 1, 1, 1),
((SELECT id_categoria FROM categorias_productos WHERE nombre_categoria='Combos'),
 'COMBO-003', 'Combo Dúo', '2 hamburguesas + 2 papas + 2 bebidas.', 80.00, 12.50, 70.00, 1, NULL, NULL, 32.00, 13.00, 18, 0, 0, 0, 1, 0, 1)
ON DUPLICATE KEY UPDATE
    nombre               = VALUES(nombre),
    descripcion          = VALUES(descripcion),
    id_categoria         = VALUES(id_categoria),
    precio               = VALUES(precio),
    descuento_porcentaje = VALUES(descuento_porcentaje),
    precio_oferta        = VALUES(precio_oferta),
    oferta_activa        = VALUES(oferta_activa),
    costo                = VALUES(costo),
    impuesto_porcentaje  = VALUES(impuesto_porcentaje),
    tiempo_preparacion   = VALUES(tiempo_preparacion),
    control_stock        = VALUES(control_stock),
    stock                = VALUES(stock),
    stock_minimo         = VALUES(stock_minimo),
    disponible           = VALUES(disponible),
    destacado            = VALUES(destacado),
    estado               = VALUES(estado);
