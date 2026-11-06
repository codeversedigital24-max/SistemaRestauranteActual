<?php
/**
 * =====================================================
 * MODELO: Producto
 * -----------------------------------------------------
 * Única puerta a las tablas `productos` y
 * `categorias_productos` del módulo de productos.
 *
 * Reglas de negocio:
 *   · Precio base obligatorio (> 0).
 *   · Descuento en porcentaje (0-100).
 *   · Precio de oferta opcional (debe ser menor al base).
 *   · Oferta activa con ventana de fechas opcional.
 *   · El precio final se calcula aquí, nunca en el JS.
 * =====================================================
 */

require_once __DIR__ . '/ProductoImagen.php';
require_once __DIR__ . '/../compras/Kardex.php';

class Producto
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    /** Lista completa (activos e inactivos) para la tabla del módulo. */
    public function listar(): array
    {
        $sql = "SELECT p.*,
                       c.nombre_categoria,
                       c.icono AS categoria_icono
                FROM productos p
                INNER JOIN categorias_productos c
                        ON c.id_categoria = p.id_categoria
                ORDER BY p.estado DESC, p.destacado DESC, c.orden ASC, p.nombre ASC";

        try {
            $filas = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Producto::listar - ' . $e->getMessage());
            return [];
        }

        $imagenes = (new ProductoImagen($this->conn))->mapaPorProductos(array_column($filas, 'id_producto'));

        return array_map(fn(array $f): array => $this->normalizar($f, $imagenes[(int) $f['id_producto']] ?? []), $filas);
    }

    /** Detalle de un producto (para precargar el modal). */
    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "SELECT p.*,
                       c.nombre_categoria,
                       c.icono AS categoria_icono
                FROM productos p
                INNER JOIN categorias_productos c
                        ON c.id_categoria = p.id_categoria
                WHERE p.id_producto = :id
                LIMIT 1";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Producto::obtener - ' . $e->getMessage());
            return null;
        }

        if (!$fila) {
            return null;
        }

        $imagenes = (new ProductoImagen($this->conn))->listar((int) $fila['id_producto']);

        return $this->normalizar($fila, $imagenes);
    }

    /** Categorías activas para el combo del modal. */
    public function categorias(): array
    {
        try {
            return $this->conn
                ->query("SELECT id_categoria, nombre_categoria, icono, orden
                         FROM categorias_productos
                         WHERE estado = 1
                         ORDER BY orden, nombre_categoria")
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Producto::categorias - ' . $e->getMessage());
            return [];
        }
    }

    // =================================================
    //  ESCRITURA
    // =================================================

    /**
     * Alta de producto.
     *
     * @return array{exito: bool, mensaje: string, id: int}
     */
    public function crear(array $datos): array
    {
        $d = $this->sanear($datos);

        if ($d['codigo'] === '') {
            $d['codigo'] = $this->codigoAutomatico();
        }

        $error = $this->validar($d);
        if ($error !== '') {
            return self::err($error);
        }

        if ($this->existeCodigo($d['codigo'])) {
            return self::err('Ese código de producto ya existe');
        }

        try {
            $sql = "INSERT INTO productos
                        (id_categoria, codigo, nombre, descripcion, imagen,
                         precio, descuento_porcentaje, precio_oferta, oferta_activa,
                         oferta_desde, oferta_hasta,
                         costo, impuesto_porcentaje,
                         tiempo_preparacion, control_stock, stock, stock_minimo,
                         disponible, destacado, estado)
                    VALUES
                        (:id_categoria, :codigo, :nombre, :descripcion, :imagen,
                         :precio, :descuento, :precio_oferta, :oferta_activa,
                         :oferta_desde, :oferta_hasta,
                         :costo, :impuesto,
                         :tiempo, :control_stock, :stock, :stock_minimo,
                         :disponible, :destacado, :estado)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($this->parametros($d));

            return [
                'exito'   => true,
                'mensaje' => 'Producto creado correctamente',
                'id'      => (int) $this->conn->lastInsertId(),
            ];
        } catch (PDOException $e) {
            return $this->errorBaseDatos($e, 'No se pudo crear el producto');
        }
    }

    /**
     * Modificación de producto.
     *
     * @return array{exito: bool, mensaje: string, id: int}
     */
    public function actualizar(array $datos): array
    {
        $d = $this->sanear($datos);

        if ($d['id'] <= 0) {
            return self::err('Producto no válido para modificar');
        }

        $anterior = $this->obtener($d['id']);

        if (!$anterior) {
            return self::err('Ese producto ya no existe');
        }

        if ($d['codigo'] === '') {
            $d['codigo'] = $this->codigoAutomatico();
        }

        $error = $this->validar($d);
        if ($error !== '') {
            return self::err($error);
        }

        if ($this->existeCodigo($d['codigo'], $d['id'])) {
            return self::err('Ese código de producto ya existe');
        }

        try {
            $sql = "UPDATE productos SET
                        id_categoria         = :id_categoria,
                        codigo               = :codigo,
                        nombre               = :nombre,
                        descripcion          = :descripcion,
                        imagen               = :imagen,
                        precio               = :precio,
                        descuento_porcentaje = :descuento,
                        precio_oferta        = :precio_oferta,
                        oferta_activa        = :oferta_activa,
                        oferta_desde         = :oferta_desde,
                        oferta_hasta         = :oferta_hasta,
                        costo                = :costo,
                        impuesto_porcentaje  = :impuesto,
                        tiempo_preparacion   = :tiempo,
                        control_stock        = :control_stock,
                        stock                = :stock,
                        stock_minimo         = :stock_minimo,
                        disponible           = :disponible,
                        destacado            = :destacado,
                        estado               = :estado
                    WHERE id_producto = :id";

            $params = $this->parametros($d);
            $params[':id'] = $d['id'];

            $this->conn->prepare($sql)->execute($params);

            // Reajuste manual de stock → movimiento en el kardex.
            // Nunca debe impedir que el producto se actualice.
            $this->registrarReajusteStock($anterior, $d);

            return [
                'exito'   => true,
                'mensaje' => 'Producto actualizado correctamente',
                'id'      => $d['id'],
            ];
        } catch (PDOException $e) {
            return $this->errorBaseDatos($e, 'No se pudo actualizar el producto');
        }
    }

    /** Baja lógica: estado = 0 (conserva historial de ventas). */
    public function eliminar(int $id): array
    {
        if ($id <= 0) {
            return self::err('Producto no válido');
        }

        if (!$this->obtener($id)) {
            return self::err('Ese producto ya no existe');
        }

        try {
            $this->conn
                ->prepare('UPDATE productos SET estado = 0 WHERE id_producto = :id')
                ->execute([':id' => $id]);

            return [
                'exito'   => true,
                'mensaje' => 'Producto dado de baja correctamente',
                'id'      => $id,
            ];
        } catch (PDOException $e) {
            error_log('Producto::eliminar - ' . $e->getMessage());
            return self::err('No se pudo dar de baja el producto');
        }
    }

    /** Cambia sólo la disponibilidad (agotado / disponible). */
    public function cambiarDisponibilidad(int $id, int $disponible): array
    {
        if ($id <= 0) {
            return self::err('Producto no válido');
        }

        try {
            $this->conn
                ->prepare('UPDATE productos SET disponible = :d WHERE id_producto = :id')
                ->execute([
                    ':d'  => $disponible === 0 ? 0 : 1,
                    ':id' => $id,
                ]);

            return [
                'exito'   => true,
                'mensaje' => $disponible === 0 ? 'Producto marcado como no disponible' : 'Producto disponible para venta',
                'id'      => $id,
            ];
        } catch (PDOException $e) {
            error_log('Producto::cambiarDisponibilidad - ' . $e->getMessage());
            return self::err('No se pudo cambiar la disponibilidad');
        }
    }

    // =================================================
    //  VALIDACIÓN / SANEO
    // =================================================

    private function validar(array $d): string
    {
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-_]{1,19}$/', $d['codigo'])) {
            return 'El código debe tener 2-20 caracteres: letras, números, guion o guion bajo';
        }

        if (mb_strlen($d['nombre']) < 3) {
            return 'El nombre debe tener al menos 3 caracteres';
        }

        if (mb_strlen($d['nombre']) > 120) {
            return 'El nombre no puede superar los 120 caracteres';
        }

        if (!$this->categoriaExiste($d['id_categoria'])) {
            return 'La categoría seleccionada no existe o está inactiva';
        }

        if ($d['precio'] <= 0) {
            return 'El precio debe ser mayor que cero';
        }

        if ($d['descuento_porcentaje'] < 0 || $d['descuento_porcentaje'] > 100) {
            return 'El descuento debe estar entre 0 y 100 %';
        }

        if ($d['precio_oferta'] !== null && $d['precio_oferta'] > 0) {
            if ($d['precio_oferta'] >= $d['precio']) {
                return 'El precio de oferta debe ser menor al precio normal';
            }
        }

        if ($d['oferta_activa'] === 1 && $d['descuento_porcentaje'] <= 0 && ($d['precio_oferta'] === null || $d['precio_oferta'] <= 0)) {
            return 'Para activar la oferta indica un descuento o un precio de oferta';
        }

        if ($d['oferta_desde'] !== null && $d['oferta_hasta'] !== null
            && strtotime($d['oferta_hasta']) < strtotime($d['oferta_desde'])) {
            return 'La fecha de fin de la oferta no puede ser anterior a la de inicio';
        }

        if ($d['costo'] < 0) {
            return 'El costo no puede ser negativo';
        }

        if ($d['impuesto_porcentaje'] < 0 || $d['impuesto_porcentaje'] > 100) {
            return 'El impuesto debe estar entre 0 y 100 %';
        }

        if ($d['stock'] < 0 || $d['stock_minimo'] < 0) {
            return 'El stock no puede ser negativo';
        }

        return '';
    }

    private function sanear(array $d): array
    {
        $limpia = static fn(string $v): string => trim(strip_tags($v));

        $precio = $this->aDecimal($d['precio'] ?? 0);
        $precioOferta = $this->aDecimal($d['precio_oferta'] ?? null);

        return [
            'id'                   => (int) ($d['id'] ?? 0),
            'id_categoria'         => (int) ($d['id_categoria'] ?? 0),
            'codigo'               => mb_strtoupper($limpia((string) ($d['codigo'] ?? ''))),
            'nombre'               => $limpia((string) ($d['nombre'] ?? '')),
            'descripcion'          => $limpia((string) ($d['descripcion'] ?? '')),
            'imagen'               => $limpia((string) ($d['imagen'] ?? '')),
            'precio'               => max(0, round($precio, 2)),
            'descuento_porcentaje' => max(0, min(100, round($this->aDecimal($d['descuento_porcentaje'] ?? 0), 2))),
            'precio_oferta'        => $precioOferta > 0 ? round($precioOferta, 2) : null,
            'oferta_activa'        => !empty($d['oferta_activa']) ? 1 : 0,
            'oferta_desde'         => $this->aFecha($d['oferta_desde'] ?? null),
            'oferta_hasta'         => $this->aFecha($d['oferta_hasta'] ?? null),
            'costo'                => max(0, round($this->aDecimal($d['costo'] ?? 0), 2)),
            'impuesto_porcentaje'  => max(0, min(100, round($this->aDecimal($d['impuesto_porcentaje'] ?? 0), 2))),
            'tiempo_preparacion'   => max(0, (int) ($d['tiempo_preparacion'] ?? 0)),
            'control_stock'        => !empty($d['control_stock']) ? 1 : 0,
            'stock'                => max(0, round($this->aDecimal($d['stock'] ?? 0), 3)),
            'stock_minimo'         => max(0, round($this->aDecimal($d['stock_minimo'] ?? 0), 3)),
            'disponible'           => ((int) ($d['disponible'] ?? 1)) === 0 ? 0 : 1,
            'destacado'            => !empty($d['destacado']) ? 1 : 0,
            'estado'               => ((int) ($d['estado'] ?? 1)) === 0 ? 0 : 1,
        ];
    }

    private function parametros(array $d): array
    {
        return [
            ':id_categoria'  => $d['id_categoria'],
            ':codigo'        => $d['codigo'],
            ':nombre'        => $d['nombre'],
            ':descripcion'   => $d['descripcion'] !== '' ? $d['descripcion'] : null,
            ':imagen'        => $d['imagen'] !== '' ? $d['imagen'] : null,
            ':precio'        => $d['precio'],
            ':descuento'     => $d['descuento_porcentaje'],
            ':precio_oferta' => $d['precio_oferta'],
            ':oferta_activa' => $d['oferta_activa'],
            ':oferta_desde'  => $d['oferta_desde'],
            ':oferta_hasta'  => $d['oferta_hasta'],
            ':costo'         => $d['costo'],
            ':impuesto'      => $d['impuesto_porcentaje'],
            ':tiempo'        => $d['tiempo_preparacion'],
            ':control_stock' => $d['control_stock'],
            ':stock'         => $d['stock'],
            ':stock_minimo'  => $d['stock_minimo'],
            ':disponible'    => $d['disponible'],
            ':destacado'     => $d['destacado'],
            ':estado'        => $d['estado'],
        ];
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    /**
     * Registra en el kardex el reajuste manual de stock (tipo 'ajuste',
     * origen 'ajuste') cuando la edición del producto cambia la
     * existencia. Cualquier fallo queda en el log y no afecta la
     * actualización del producto.
     */
    private function registrarReajusteStock(array $anterior, array $nuevo): void
    {
        $stockAnterior = (float) ($anterior['stock'] ?? 0);
        $stockNuevo = (float) ($nuevo['stock'] ?? 0);
        $cantidad = round($stockNuevo - $stockAnterior, 3);

        if (abs($cantidad) < 0.0001) {
            return;
        }

        try {
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $idSucursal = $this->sucursalSesion($idUsuario);

            if ($idSucursal <= 0) {
                error_log('Producto::registrarReajusteStock - sin sucursal disponible');
                return;
            }

            $resultado = (new Kardex($this->conn))->registrarAjuste(
                $idSucursal,
                (int) ($nuevo['id'] ?? 0),
                $cantidad,
                (float) ($nuevo['costo'] ?? 0),
                'Reajuste manual de stock',
                $idUsuario
            );

            if (empty($resultado['exito'])) {
                error_log(
                    'Producto::registrarReajusteStock - '
                    . ($resultado['mensaje'] ?? 'No se pudo registrar el ajuste')
                );
            }
        } catch (Throwable $e) {
            error_log('Producto::registrarReajusteStock - ' . $e->getMessage());
        }
    }

    /** Sucursal del usuario en sesión (0 → primera sucursal activa). */
    private function sucursalSesion(int $idUsuario): int
    {
        $idSucursal = 0;

        if ($idUsuario > 0) {
            try {
                $stmt = $this->conn->prepare(
                    'SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1'
                );
                $stmt->execute([':id' => $idUsuario]);
                $idSucursal = (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log('Producto::sucursalSesion - ' . $e->getMessage());
            }
        }

        if ($idSucursal <= 0) {
            try {
                $idSucursal = (int) $this->conn
                    ->query('SELECT id_sucursal FROM sucursales WHERE estado = 1 ORDER BY id_sucursal LIMIT 1')
                    ->fetchColumn();
            } catch (PDOException $e) {
                error_log('Producto::sucursalSesion - ' . $e->getMessage());
                return 0;
            }
        }

        return max(0, $idSucursal);
    }

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0];
    }

    private function categoriaExiste(int $idCategoria): bool
    {
        if ($idCategoria <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare(
            'SELECT 1 FROM categorias_productos
             WHERE id_categoria = :id AND estado = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $idCategoria]);

        return (bool) $stmt->fetchColumn();
    }

    private function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $sql = 'SELECT 1 FROM productos WHERE codigo = :codigo';
        $params = [':codigo' => $codigo];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id_producto != :id';
            $params[':id'] = $excluirId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /** Código legible cuando el usuario lo deja vacío. */
    private function codigoAutomatico(): string
    {
        for ($i = 0; $i < 12; $i++) {
            $codigo = 'PRD-' . strtoupper(bin2hex(random_bytes(3)));

            if (!$this->existeCodigo($codigo)) {
                return $codigo;
            }
        }

        return 'PRD-' . strtoupper(substr((string) time(), -6));
    }

    private function aDecimal($valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace(',', '.', trim($valor));
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    /** Acepta "2026-10-31T18:30" o "2026-10-31 18:30:00". */
    private function aFecha($valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        $texto = str_replace('T', ' ', $texto);
        $timestamp = strtotime($texto);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function errorBaseDatos(PDOException $e, string $mensaje): array
    {
        if ($e->getCode() === '23000') {
            return self::err('Ese código de producto ya existe');
        }

        error_log('Producto - ' . $e->getMessage());

        return self::err($mensaje);
    }

    /** Calcula precio final, ahorro y estado de vigencia de la oferta. */
    private function normalizar(array $f, array $imagenes = []): array
    {
        $precio = (float) $f['precio'];
        $descuento = (float) ($f['descuento_porcentaje'] ?? 0);

        $precioOferta = null;
        if (isset($f['precio_oferta']) && $f['precio_oferta'] !== null && $f['precio_oferta'] !== '') {
            $precioOferta = (float) $f['precio_oferta'];
        }

        $ofertaActiva = ((int) ($f['oferta_activa'] ?? 0)) === 1;
        $ahora = time();

        $vigente = $ofertaActiva;

        if ($vigente && !empty($f['oferta_desde']) && strtotime($f['oferta_desde']) > $ahora) {
            $vigente = false;
        }

        if ($vigente && !empty($f['oferta_hasta']) && strtotime($f['oferta_hasta']) < $ahora) {
            $vigente = false;
        }

        $precioFinal = $precio;

        if ($vigente) {
            if ($precioOferta !== null && $precioOferta > 0 && $precioOferta < $precio) {
                $precioFinal = $precioOferta;
            } elseif ($descuento > 0) {
                $precioFinal = $precio * (1 - ($descuento / 100));
            }
        }

        $precioFinal = max(0, min($precio, round($precioFinal, 2)));
        $ahorro = max(0, round($precio - $precioFinal, 2));
        $descuentoReal = $precio > 0 ? round(($ahorro / $precio) * 100, 2) : 0;

        $legacy = (string) ($f['imagen'] ?? '');
        $principal = $imagenes[0] ?? null;
        $rutaPrincipal = $principal ? (string) $principal['archivo'] : $legacy;

        return [
            'id'                   => (int) $f['id_producto'],
            'id_categoria'         => (int) $f['id_categoria'],
            'categoria'            => (string) ($f['nombre_categoria'] ?? ''),
            'categoria_icono'      => (string) ($f['categoria_icono'] ?? 'fa-utensils'),
            'codigo'               => (string) $f['codigo'],
            'nombre'               => (string) $f['nombre'],
            'descripcion'          => (string) ($f['descripcion'] ?? ''),
            'imagen'               => $legacy,
            'imagen_url'           => ProductoImagen::urlPublica($legacy),
            'imagen_principal'     => $rutaPrincipal,
            'imagen_principal_url' => ProductoImagen::urlPublica($rutaPrincipal),
            'imagenes'             => $imagenes,
            'precio'               => $precio,
            'precio_oferta'        => $precioOferta,
            'precio_final'         => $precioFinal,
            'descuento'            => $descuento,
            'descuento_real'       => $descuentoReal,
            'ahorro'               => $ahorro,
            'oferta_activa'        => $ofertaActiva ? 1 : 0,
            'oferta_vigente'       => $vigente ? 1 : 0,
            'oferta_desde'         => $f['oferta_desde'] ?? null,
            'oferta_hasta'         => $f['oferta_hasta'] ?? null,
            'costo'                => (float) ($f['costo'] ?? 0),
            'impuesto'             => (float) ($f['impuesto_porcentaje'] ?? 0),
            'tiempo_preparacion'   => (int) ($f['tiempo_preparacion'] ?? 0),
            'control_stock'        => (int) ($f['control_stock'] ?? 0),
            'stock'                => (float) ($f['stock'] ?? 0),
            'stock_minimo'         => (float) ($f['stock_minimo'] ?? 0),
            'disponible'           => (int) ($f['disponible'] ?? 1),
            'destacado'            => (int) ($f['destacado'] ?? 0),
            'estado'               => (int) ($f['estado'] ?? 1),
            'creado'               => !empty($f['created_at']) ? date('d/m/Y', strtotime($f['created_at'])) : '—',
        ];
    }
}

