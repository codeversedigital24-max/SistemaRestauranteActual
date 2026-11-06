-- =====================================================
-- PERMISO · PROMOCIONES  ·  RESTAURANTE MODERNO
-- -----------------------------------------------------
-- Agrega el ítem "Promociones" al catálogo de permisos
-- (Ventas ▸ Operación) y concede todas sus acciones a
-- los SuperAdministradores.
--
-- Es idempotente: se puede ejecutar más de una vez.
-- =====================================================

USE restauratemoderno;

-- 1) Ítem del catálogo (sólo si todavía no existe)
INSERT INTO detalle_permisos
    (id_detalle_permiso, id_sub_permiso, nombre_detalle, ruta, orden, estado)
SELECT 19, 4, 'Promociones', 'promociones.php', 5, 1
WHERE NOT EXISTS (
        SELECT 1 FROM detalle_permisos WHERE ruta = 'promociones.php'
      )
  AND NOT EXISTS (
        SELECT 1 FROM detalle_permisos WHERE id_detalle_permiso = 19
      );

-- 2) Conceder todas las acciones a los SuperAdministradores
INSERT IGNORE INTO usuarios_permisos
    (id_usuario, id_permiso, id_sub_permiso, id_detalle_permiso, accion, permitir, estado)
SELECT
    u.id_usuario,
    3 AS id_permiso,
    4 AS id_sub_permiso,
    19 AS id_detalle_permiso,
    a.accion,
    1,
    1
FROM usuarios u
INNER JOIN roles r ON r.id_rol = u.id_rol
CROSS JOIN (
    SELECT 'ver'       AS accion UNION ALL
    SELECT 'registrar' UNION ALL
    SELECT 'modificar' UNION ALL
    SELECT 'eliminar'
) a
WHERE u.estado = 1
  AND (UPPER(TRIM(r.nombre_rol)) = 'SUPERADMINISTRADOR' OR u.id_usuario = 1);
