-- =====================================================
-- ROLES: MESERO Y COCINERO  ·  Restaurante Moderno
-- -----------------------------------------------------
-- Agrega los dos roles operativos que faltaban.
--
-- IMPORTANTE: los roles YA NO conceden privilegios por
-- sí mismos (no se usa roles.nivel_permiso). Los permisos
-- se asignan por usuario desde Usuarios → Privilegios.
-- Estos roles sólo sirven para clasificar al personal.
--
-- Ejecutar en phpMyAdmin sobre restauratemoderno.
-- Es idempotente: se puede ejecutar varias veces.
-- =====================================================

USE restauratemoderno;

INSERT INTO roles (nombre_rol, descripcion, nivel_permiso, estado)
VALUES
    ('Mesero',   'Toma pedidos, atiende mesas y coordina el salón', 3, 1),
    ('Cocinero', 'Prepara los pedidos en cocina y controla insumos', 4, 1)
ON DUPLICATE KEY UPDATE
    descripcion = VALUES(descripcion),
    estado      = VALUES(estado);
