-- =====================================================
-- PERMISOS v2  ·  RESTAURANTE MODERNO
-- -----------------------------------------------------
-- Modelo definitivo de privilegios (sin roles.nivel_permiso):
--
--   permisos          → NIVEL 1 · módulo  (menú)
--   sub_permisos      → NIVEL 2 · submenú (desplegable)
--   detalle_permisos  → NIVEL 3 · ítem    (página / recurso)
--   usuarios_permisos → concesión por usuario + ACCIÓN:
--                       ver · registrar · modificar · eliminar
--
-- QUÉ HACE ESTE SCRIPT
--   1) Añade la columna `accion` a usuarios_permisos.
--   2) Deja un índice único (usuario, ítem, acción) para que
--      un usuario pueda tener VARIAS acciones sobre el mismo
--      ítem (por eso se eliminan índices únicos antiguos).
--   3) Guarda copia de seguridad de las 4 tablas.
--   4) Reconstruye el catálogo (módulos ▸ submenús ▸ ítems)
--      con todos los recursos del sistema y sus rutas.
--   5) Reinicia las asignaciones y le concede TODAS las
--      acciones de TODOS los ítems a los SuperAdministradores.
--      El resto de usuarios arranca SIN privilegios (por
--      diseño: si no tiene privilegio, no puede hacer nada).
--
-- IMPORTANTE: este script RESETEA las concesiones existentes.
-- Ejecutar una sola vez en phpMyAdmin sobre la base
-- `restauratemoderno`. Las copias de seguridad quedan en:
--   permisos_bkp_v2, sub_permisos_bkp_v2,
--   detalle_permisos_bkp_v2, usuarios_permisos_bkp_v2
-- =====================================================

USE restauratemoderno;

-- -----------------------------------------------------
-- 1) Copias de seguridad (sólo la primera vez)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS permisos_bkp_v2           AS SELECT * FROM permisos;
CREATE TABLE IF NOT EXISTS sub_permisos_bkp_v2       AS SELECT * FROM sub_permisos;
CREATE TABLE IF NOT EXISTS detalle_permisos_bkp_v2   AS SELECT * FROM detalle_permisos;
CREATE TABLE IF NOT EXISTS usuarios_permisos_bkp_v2  AS SELECT * FROM usuarios_permisos;

-- -----------------------------------------------------
-- 2) Columna `accion` + índice único correcto
-- -----------------------------------------------------
DROP PROCEDURE IF EXISTS rm_permisos_v2;

DELIMITER $$

CREATE PROCEDURE rm_permisos_v2()
BEGIN
    DECLARE v_idx  VARCHAR(64);
    DECLARE v_done INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT INDEX_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'usuarios_permisos'
          AND NON_UNIQUE   = 0
          AND INDEX_NAME  <> 'PRIMARY'
          AND INDEX_NAME NOT LIKE '%accion%'
        GROUP BY INDEX_NAME;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET @rm_ignorar = 1;

    -- Columna nueva
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'usuarios_permisos'
          AND COLUMN_NAME  = 'accion'
    ) THEN
        ALTER TABLE usuarios_permisos
            ADD COLUMN accion VARCHAR(20) NOT NULL DEFAULT 'ver'
            AFTER id_detalle_permiso;
    END IF;

    -- Quitar índices únicos antiguos que impiden varias acciones
    OPEN cur;

    leer_indices: LOOP
        FETCH cur INTO v_idx;

        IF v_done = 1 THEN
            LEAVE leer_indices;
        END IF;

        SET @rm_sql = CONCAT('ALTER TABLE usuarios_permisos DROP INDEX `', v_idx, '`');
        PREPARE rm_stmt FROM @rm_sql;
        EXECUTE rm_stmt;
        DEALLOCATE PREPARE rm_stmt;
    END LOOP;

    CLOSE cur;

    -- Índice único por usuario + ítem + acción
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'usuarios_permisos'
          AND INDEX_NAME   = 'uq_usuario_item_accion'
    ) THEN
        ALTER TABLE usuarios_permisos
            ADD UNIQUE KEY uq_usuario_item_accion (id_usuario, id_detalle_permiso, accion);
    END IF;
END$$

DELIMITER ;

CALL rm_permisos_v2();
DROP PROCEDURE IF EXISTS rm_permisos_v2;

-- -----------------------------------------------------
-- 3) Reconstruir catálogo
-- -----------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM usuarios_permisos;
DELETE FROM detalle_permisos;
DELETE FROM sub_permisos;
DELETE FROM permisos;

-- ===== NIVEL 1 · MÓDULOS =====
INSERT INTO permisos (id_permiso, nombre_permiso, icono, orden, estado) VALUES
(1, 'Configuración', 'fa-cog',        1, 1),
(2, 'Seguridad',     'fa-shield-alt', 2, 1),
(3, 'Ventas',        'fa-cash-register', 3, 1),
(4, 'Inventario',    'fa-boxes-stacked', 4, 1),
(5, 'Reportes',      'fa-chart-pie',  5, 1);

-- ===== NIVEL 2 · SUBMENÚS =====
INSERT INTO sub_permisos (id_sub_permiso, id_permiso, nombre_sub_permiso, icono, orden, estado) VALUES
(1, 1, 'General',            'fa-globe',        1, 1),
(2, 1, 'Finanzas',           'fa-coins',        2, 1),
(3, 2, 'Usuarios',           'fa-users',        1, 1),
(4, 3, 'Operación',          'fa-cash-register',1, 1),
(5, 3, 'Historial',          'fa-clock',        2, 1),
(6, 4, 'Productos',          'fa-burger',       1, 1),
(7, 4, 'Compras',            'fa-cart-shopping',2, 1),
(8, 5, 'Ventas',             'fa-chart-line',   1, 1),
(9, 5, 'Compras',            'fa-truck',        2, 1);

-- ===== NIVEL 3 · ÍTEMS (páginas / recursos) =====
INSERT INTO detalle_permisos (id_detalle_permiso, id_sub_permiso, nombre_detalle, ruta, orden, estado) VALUES
(1,  1, 'Empresa',                'configuracion.php',      1, 1),
(2,  1, 'Mesas',                  'mesas.php',              2, 1),
(3,  1, 'Sucursales',             'sucursales.php',         3, 1),
(4,  2, 'Datos financieros',      'finanzas.php',           1, 1),
(5,  3, 'Gestión de usuarios',    'usuarios.php',           1, 1),
(6,  4, 'Punto de venta',         'pos.php',                1, 1),
(7,  4, 'Pedidos',                'pedidos.php',            2, 1),
(8,  4, 'Cajas',                  'caja.php',               3, 1),
(9,  4, 'Arqueo de caja',         'arqueocaja.php',         4, 1),
(10, 5, 'Historial de ventas',    'ventas.php',             1, 1),
(11, 6, 'Productos',              'productos.php',          1, 1),
(12, 7, 'Compras',                'compras.php',            1, 1),
(13, 7, 'Nueva compra',           'nuevacompra.php',        2, 1),
(14, 7, 'Cuentas por pagar',      'cuentas_pagar.php',      3, 1),
(15, 7, 'Kardex',                 'kardex.php',             4, 1),
(16, 8, 'Ventas por fechas',      'ventasxfechas.php',      1, 1),
(17, 8, 'Utilidades',             'ventasutilidades.php',   2, 1),
(18, 9, 'Compras por fechas',     'comprasxfechas.php',     1, 1),
(19, 4, 'Promociones',            'promociones.php',        5, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------
-- 4) Conceder TODO al SuperAdministrador
-- -----------------------------------------------------
INSERT INTO usuarios_permisos
    (id_usuario, id_permiso, id_sub_permiso, id_detalle_permiso, accion, permitir, estado)
SELECT
    u.id_usuario,
    s.id_permiso,
    d.id_sub_permiso,
    d.id_detalle_permiso,
    a.accion,
    1,
    1
FROM usuarios u
INNER JOIN roles  r ON r.id_rol = u.id_rol
INNER JOIN detalle_permisos d ON d.estado = 1
INNER JOIN sub_permisos     s ON s.id_sub_permiso = d.id_sub_permiso AND s.estado = 1
CROSS JOIN (
    SELECT 'ver'       AS accion UNION ALL
    SELECT 'registrar' UNION ALL
    SELECT 'modificar' UNION ALL
    SELECT 'eliminar'
) a
WHERE u.estado = 1
  AND (UPPER(TRIM(r.nombre_rol)) = 'SUPERADMINISTRADOR' OR u.id_usuario = 1);

-- -----------------------------------------------------
-- 5) Verificación rápida (opcional)
-- -----------------------------------------------------
-- SELECT COUNT(*) AS items FROM detalle_permisos;                 -- esperado: 19
-- SELECT COUNT(*) AS reglas FROM usuarios_permisos;               -- 19 × 4 × nº superadmins
-- SELECT p.nombre_permiso, s.nombre_sub_permiso, d.nombre_detalle, d.ruta
--   FROM permisos p
--   JOIN sub_permisos s       ON s.id_permiso = p.id_permiso
--   JOIN detalle_permisos d   ON d.id_sub_permiso = s.id_sub_permiso
--  ORDER BY p.orden, s.orden, d.orden;
