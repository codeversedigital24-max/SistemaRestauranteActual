<?php
/**
 * =====================================================
 * MODELO: CategoriaProducto
 * -----------------------------------------------------
 * Catálogo de categorías del menú (Entradas, Hamburguesas,
 * Pizzas, Bebidas, Combos, ...).
 *
 * El módulo de productos sólo necesita leerlas para el
 * combo del modal; se deja aquí el alta por si más adelante
 * se agrega un mantenedor de categorías.
 * =====================================================
 */

class CategoriaProducto
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    /** Categorías activas ordenadas. */
    public function listar(bool $soloActivas = true): array
    {
        $sql = 'SELECT id_categoria, nombre_categoria, descripcion, icono, orden, estado
                FROM categorias_productos';

        if ($soloActivas) {
            $sql .= ' WHERE estado = 1';
        }

        $sql .= ' ORDER BY orden, nombre_categoria';

        try {
            return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('CategoriaProducto::listar - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Alta rápida de categoría.
     *
     * @return array{exito: bool, mensaje: string, id: int}
     */
    public function crear(array $datos): array
    {
        $nombre = trim(strip_tags((string) ($datos['nombre_categoria'] ?? '')));

        if (mb_strlen($nombre) < 3) {
            return ['exito' => false, 'mensaje' => 'El nombre debe tener al menos 3 caracteres', 'id' => 0];
        }

        if (mb_strlen($nombre) > 80) {
            return ['exito' => false, 'mensaje' => 'El nombre no puede superar los 80 caracteres', 'id' => 0];
        }

        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO categorias_productos (nombre_categoria, descripcion, icono, orden, estado)
                 VALUES (:nombre, :descripcion, :icono, :orden, 1)'
            );

            $stmt->execute([
                ':nombre'      => $nombre,
                ':descripcion' => trim(strip_tags((string) ($datos['descripcion'] ?? ''))) ?: null,
                ':icono'       => trim(strip_tags((string) ($datos['icono'] ?? ''))) ?: 'fa-utensils',
                ':orden'       => (int) ($datos['orden'] ?? 0),
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Categoría creada correctamente',
                'id'      => (int) $this->conn->lastInsertId(),
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['exito' => false, 'mensaje' => 'Esa categoría ya existe', 'id' => 0];
            }

            error_log('CategoriaProducto::crear - ' . $e->getMessage());

            return ['exito' => false, 'mensaje' => 'No se pudo crear la categoría', 'id' => 0];
        }
    }
}
