<?php
/**
 * =====================================================
 * MODELO: Promocion  (modelos/pos/Promocion.php)
 * -----------------------------------------------------
 * Promociones especiales de venta.
 *
 * Reglas:
 *   · Cada promoción aplica a UN producto o a UNA categoría.
 *   · Sólo cuentan las activas dentro de su ventana de fechas.
 *   · Si varias aplican al mismo producto se usa la de mayor
 *     prioridad; si empatan, la que otorgue mayor descuento.
 *   · El descuento nunca deja el precio por debajo de cero.
 * =====================================================
 */

class Promocion
{
    private PDO $conn;

    public const ALCANCES = ['producto', 'categoria'];
    public const TIPOS = ['porcentaje', 'monto'];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    /** Todas las promociones con nombres y estado de vigencia. */
    public function listar(): array
    {
        $sql = "SELECT pr.*,
                       p.nombre AS producto_nombre,
                       p.codigo AS producto_codigo,
                       c.nombre_categoria
                  FROM promociones pr
                  LEFT JOIN productos p            ON p.id_producto = pr.id_producto
                  LEFT JOIN categorias_productos c ON c.id_categoria = pr.id_categoria
                 ORDER BY pr.activo DESC, pr.prioridad DESC, pr.id_promocion DESC";

        try {
            $filas = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Promocion::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT pr.*,
                    p.nombre AS producto_nombre,
                    p.codigo AS producto_codigo,
                    c.nombre_categoria
               FROM promociones pr
               LEFT JOIN productos p            ON p.id_producto = pr.id_producto
               LEFT JOIN categorias_productos c ON c.id_categoria = pr.id_categoria
              WHERE pr.id_promocion = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? $this->normalizar($fila) : null;
    }

    /**
     * Promociones activas y vigentes hoy.
     * Ordenadas por prioridad para resolver rápido.
     */
    public function vigentes(): array
    {
        $sql = "SELECT pr.*
                  FROM promociones pr
                 WHERE pr.activo = 1
                   AND (pr.desde IS NULL OR pr.desde <= NOW())
                   AND (pr.hasta IS NULL OR pr.hasta >= NOW())
                 ORDER BY pr.prioridad DESC, pr.id_promocion DESC";

        try {
            $filas = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Promocion::vigentes - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    // =================================================
    //  CÁLCULO
    // =================================================

    /** ¿La promoción aplica al producto indicado? */
    public static function aplicaA(array $promo, int $idProducto, int $idCategoria): bool
    {
        if ((int) ($promo['activo'] ?? 0) !== 1) {
            return false;
        }

        if (($promo['alcance'] ?? '') === 'producto') {
            return (int) ($promo['id_producto'] ?? 0) === $idProducto;
        }

        if (($promo['alcance'] ?? '') === 'categoria') {
            return (int) ($promo['id_categoria'] ?? 0) === $idCategoria;
        }

        return false;
    }

    /** Descuento por unidad que otorga una promoción sobre un precio. */
    public static function descuentoUnitario(array $promo, float $precio): float
    {
        $precio = max(0, round($precio, 2));
        $valor  = max(0, (float) ($promo['valor'] ?? 0));

        if (($promo['tipo_descuento'] ?? 'porcentaje') === 'monto') {
            return round(min($precio, $valor), 2);
        }

        $valor = min(100.0, $valor);

        return round($precio * ($valor / 100), 2);
    }

    /**
     * Mejor promoción aplicable a un producto.
     * Devuelve la promoción con `descuento_unitario` calculado.
     */
    public static function mejor(array $promociones, int $idProducto, int $idCategoria, float $precio): ?array
    {
        $mejor = null;

        foreach ($promociones as $promo) {
            if (!self::aplicaA($promo, $idProducto, $idCategoria)) {
                continue;
            }

            $descuento = self::descuentoUnitario($promo, $precio);

            if ($descuento <= 0.004) {
                continue;
            }

            $candidato = $promo;
            $candidato['descuento_unitario'] = $descuento;

            if ($mejor === null) {
                $mejor = $candidato;
                continue;
            }

            $prioridadNueva = (int) ($candidato['prioridad'] ?? 0);
            $prioridadMejor = (int) ($mejor['prioridad'] ?? 0);

            if ($prioridadNueva > $prioridadMejor) {
                $mejor = $candidato;
            } elseif ($prioridadNueva === $prioridadMejor && $descuento > (float) $mejor['descuento_unitario']) {
                $mejor = $candidato;
            }
        }

        return $mejor;
    }

    // =================================================
    //  CATÁLOGOS PARA EL FORMULARIO
    // =================================================

    /** Productos activos y disponibles para elegir el alcance. */
    public function productos(): array
    {
        try {
            return $this->conn->query(
                "SELECT id_producto, codigo, nombre, precio
                   FROM productos
                  WHERE estado = 1 AND disponible = 1
                  ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Promocion::productos - ' . $e->getMessage());
            return [];
        }
    }

    /** Categorías activas para elegir el alcance. */
    public function categorias(): array
    {
        try {
            return $this->conn->query(
                "SELECT id_categoria, nombre_categoria
                   FROM categorias_productos
                  WHERE estado = 1
                  ORDER BY orden ASC, nombre_categoria ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Promocion::categorias - ' . $e->getMessage());
            return [];
        }
    }

    // =================================================
    //  ESCRITURA
    // =================================================

    /** @return array{exito:bool,mensaje:string,id:int} */
    public function crear(array $datos): array
    {
        $d = $this->sanear($datos);

        if (!$d['ok']) {
            return ['exito' => false, 'mensaje' => $d['mensaje'], 'id' => 0];
        }

        $v = $d['datos'];

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO promociones
                    (nombre, descripcion, alcance, id_producto, id_categoria,
                     tipo_descuento, valor, prioridad, desde, hasta, activo)
                 VALUES
                    (:nombre, :descripcion, :alcance, :producto, :categoria,
                     :tipo, :valor, :prioridad, :desde, :hasta, :activo)"
            );

            $stmt->execute([
                ':nombre'      => $v['nombre'],
                ':descripcion' => $v['descripcion'] !== '' ? $v['descripcion'] : null,
                ':alcance'     => $v['alcance'],
                ':producto'    => $v['alcance'] === 'producto' ? $v['id_producto'] : null,
                ':categoria'   => $v['alcance'] === 'categoria' ? $v['id_categoria'] : null,
                ':tipo'        => $v['tipo_descuento'],
                ':valor'       => $v['valor'],
                ':prioridad'   => $v['prioridad'],
                ':desde'       => $v['desde'],
                ':hasta'       => $v['hasta'],
                ':activo'      => $v['activo'],
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Promoción creada correctamente',
                'id'      => (int) $this->conn->lastInsertId(),
            ];
        } catch (PDOException $e) {
            error_log('Promocion::crear - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo crear la promoción', 'id' => 0];
        }
    }

    /** @return array{exito:bool,mensaje:string,id:int} */
    public function actualizar(int $id, array $datos): array
    {
        if ($id <= 0) {
            return ['exito' => false, 'mensaje' => 'Promoción no válida', 'id' => 0];
        }

        $d = $this->sanear($datos);

        if (!$d['ok']) {
            return ['exito' => false, 'mensaje' => $d['mensaje'], 'id' => 0];
        }

        $v = $d['datos'];

        try {
            $stmt = $this->conn->prepare(
                "UPDATE promociones
                    SET nombre = :nombre,
                        descripcion = :descripcion,
                        alcance = :alcance,
                        id_producto = :producto,
                        id_categoria = :categoria,
                        tipo_descuento = :tipo,
                        valor = :valor,
                        prioridad = :prioridad,
                        desde = :desde,
                        hasta = :hasta,
                        activo = :activo
                  WHERE id_promocion = :id"
            );

            $stmt->execute([
                ':nombre'      => $v['nombre'],
                ':descripcion' => $v['descripcion'] !== '' ? $v['descripcion'] : null,
                ':alcance'     => $v['alcance'],
                ':producto'    => $v['alcance'] === 'producto' ? $v['id_producto'] : null,
                ':categoria'   => $v['alcance'] === 'categoria' ? $v['id_categoria'] : null,
                ':tipo'        => $v['tipo_descuento'],
                ':valor'       => $v['valor'],
                ':prioridad'   => $v['prioridad'],
                ':desde'       => $v['desde'],
                ':hasta'       => $v['hasta'],
                ':activo'      => $v['activo'],
                ':id'          => $id,
            ]);

            return ['exito' => true, 'mensaje' => 'Promoción actualizada', 'id' => $id];
        } catch (PDOException $e) {
            error_log('Promocion::actualizar - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo actualizar la promoción', 'id' => $id];
        }
    }

    /** @return array{exito:bool,mensaje:string} */
    public function cambiarEstado(int $id, int $activo): array
    {
        if ($id <= 0) {
            return ['exito' => false, 'mensaje' => 'Promoción no válida'];
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE promociones SET activo = :activo WHERE id_promocion = :id"
            );
            $stmt->execute([
                ':activo' => $activo === 1 ? 1 : 0,
                ':id'     => $id,
            ]);

            return [
                'exito'   => true,
                'mensaje' => $activo === 1 ? 'Promoción activada' : 'Promoción desactivada',
            ];
        } catch (PDOException $e) {
            error_log('Promocion::cambiarEstado - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo cambiar el estado'];
        }
    }

    /** @return array{exito:bool,mensaje:string} */
    public function eliminar(int $id): array
    {
        if ($id <= 0) {
            return ['exito' => false, 'mensaje' => 'Promoción no válida'];
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM promociones WHERE id_promocion = :id");
            $stmt->execute([':id' => $id]);

            return ['exito' => true, 'mensaje' => 'Promoción eliminada'];
        } catch (PDOException $e) {
            error_log('Promocion::eliminar - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo eliminar la promoción'];
        }
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private function normalizar(array $f): array
    {
        $desde = $f['desde'] ?? null;
        $hasta = $f['hasta'] ?? null;
        $ahora = time();

        $vigente = ((int) ($f['activo'] ?? 0)) === 1;

        if ($vigente && $desde && strtotime((string) $desde) > $ahora) {
            $vigente = false;
        }

        if ($vigente && $hasta && strtotime((string) $hasta) < $ahora) {
            $vigente = false;
        }

        $alcance = (string) ($f['alcance'] ?? 'producto');

        $situacion = 'inactiva';

        if (((int) ($f['activo'] ?? 0)) === 1) {
            if ($vigente) {
                $situacion = 'vigente';
            } elseif ($desde && strtotime((string) $desde) > $ahora) {
                $situacion = 'programada';
            } elseif ($hasta && strtotime((string) $hasta) < $ahora) {
                $situacion = 'vencida';
            } else {
                $situacion = 'vigente';
            }
        }

        return [
            'id'               => (int) $f['id_promocion'],
            'nombre'           => (string) $f['nombre'],
            'descripcion'      => (string) ($f['descripcion'] ?? ''),
            'alcance'          => $alcance,
            'id_producto'      => isset($f['id_producto']) ? (int) $f['id_producto'] : 0,
            'id_categoria'     => isset($f['id_categoria']) ? (int) $f['id_categoria'] : 0,
            'producto'         => (string) ($f['producto_nombre'] ?? ''),
            'producto_codigo'  => (string) ($f['producto_codigo'] ?? ''),
            'categoria'        => (string) ($f['nombre_categoria'] ?? ''),
            'objetivo'         => $alcance === 'producto'
                ? ((string) ($f['producto_nombre'] ?? '') ?: 'Producto')
                : ((string) ($f['nombre_categoria'] ?? '') ?: 'Categoría'),
            'tipo_descuento'   => (string) ($f['tipo_descuento'] ?? 'porcentaje'),
            'valor'            => (float) $f['valor'],
            'prioridad'        => (int) ($f['prioridad'] ?? 0),
            'desde'            => $desde ? date('d/m/Y H:i', strtotime((string) $desde)) : '',
            'hasta'            => $hasta ? date('d/m/Y H:i', strtotime((string) $hasta)) : '',
            'desde_iso'        => $desde ? date('Y-m-d\TH:i', strtotime((string) $desde)) : '',
            'hasta_iso'        => $hasta ? date('Y-m-d\TH:i', strtotime((string) $hasta)) : '',
            'desde_raw'        => $desde ? (string) $desde : '',
            'hasta_raw'        => $hasta ? (string) $hasta : '',
            'activo'           => ((int) ($f['activo'] ?? 0)) === 1 ? 1 : 0,
            'vigente'          => $vigente ? 1 : 0,
            'situacion'        => $situacion,
            'etiqueta'         => ($f['tipo_descuento'] ?? 'porcentaje') === 'monto'
                ? '− Bs ' . number_format((float) $f['valor'], 2, ',', '.')
                : '− ' . rtrim(rtrim(number_format((float) $f['valor'], 2, ',', '.'), '0'), ',') . '%',
        ];
    }

    private function sanear(array $datos): array
    {
        $nombre = trim(strip_tags((string) ($datos['nombre'] ?? '')));
        $descripcion = trim(strip_tags((string) ($datos['descripcion'] ?? '')));
        $alcance = strtolower(trim((string) ($datos['alcance'] ?? 'producto')));
        $tipo = strtolower(trim((string) ($datos['tipo_descuento'] ?? 'porcentaje')));

        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'Escribe el nombre de la promoción'];
        }
        if (mb_strlen($nombre) > 140) {
            return ['ok' => false, 'mensaje' => 'El nombre no puede superar los 140 caracteres'];
        }
        if (!in_array($alcance, self::ALCANCES, true)) {
            return ['ok' => false, 'mensaje' => 'Alcance de la promoción no válido'];
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            return ['ok' => false, 'mensaje' => 'Tipo de descuento no válido'];
        }

        $idProducto = (int) ($datos['id_producto'] ?? 0);
        $idCategoria = (int) ($datos['id_categoria'] ?? 0);

        if ($alcance === 'producto') {
            if ($idProducto <= 0) {
                return ['ok' => false, 'mensaje' => 'Selecciona el producto de la promoción'];
            }

            $stmt = $this->conn->prepare("SELECT 1 FROM productos WHERE id_producto = :id LIMIT 1");
            $stmt->execute([':id' => $idProducto]);
            if (!$stmt->fetchColumn()) {
                return ['ok' => false, 'mensaje' => 'El producto seleccionado no existe'];
            }
        } else {
            $idCategoria = $idCategoria > 0 ? $idCategoria : 0;

            if ($idCategoria <= 0) {
                return ['ok' => false, 'mensaje' => 'Selecciona la categoría de la promoción'];
            }

            $stmt = $this->conn->prepare("SELECT 1 FROM categorias_productos WHERE id_categoria = :id LIMIT 1");
            $stmt->execute([':id' => $idCategoria]);
            if (!$stmt->fetchColumn()) {
                return ['ok' => false, 'mensaje' => 'La categoría seleccionada no existe'];
            }
        }

        $valor = round((float) ($datos['valor'] ?? 0), 2);
        if ($valor <= 0) {
            return ['ok' => false, 'mensaje' => 'El valor del descuento debe ser mayor que cero'];
        }
        if ($tipo === 'porcentaje' && $valor > 100) {
            return ['ok' => false, 'mensaje' => 'El porcentaje no puede superar el 100 %'];
        }

        $prioridad = (int) ($datos['prioridad'] ?? 0);
        if ($prioridad < 0) {
            return ['ok' => false, 'mensaje' => 'La prioridad no puede ser negativa'];
        }

        $desde = $this->aFecha($datos['desde'] ?? '');
        $hasta = $this->aFecha($datos['hasta'] ?? '');

        if ($desde === '__ERROR__' || $hasta === '__ERROR__') {
            return ['ok' => false, 'mensaje' => 'Revisa las fechas de vigencia'];
        }

        if ($desde !== null && $hasta !== null && strtotime($desde) > strtotime($hasta)) {
            return ['ok' => false, 'mensaje' => 'La fecha inicial no puede ser posterior a la final'];
        }

        return [
            'ok' => true,
            'mensaje' => '',
            'datos' => [
                'nombre'        => $nombre,
                'descripcion'   => mb_substr($descripcion, 0, 255),
                'alcance'       => $alcance,
                'id_producto'   => $idProducto,
                'id_categoria'  => $idCategoria,
                'tipo_descuento'=> $tipo,
                'valor'         => $valor,
                'prioridad'     => $prioridad,
                'desde'         => $desde,
                'hasta'         => $hasta,
                'activo'        => ((int) ($datos['activo'] ?? 1)) === 1 ? 1 : 0,
            ],
        ];
    }

    private function aFecha($valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        $marca = strtotime($valor);

        if ($marca === false) {
            return '__ERROR__';
        }

        return date('Y-m-d H:i:s', $marca);
    }
}
