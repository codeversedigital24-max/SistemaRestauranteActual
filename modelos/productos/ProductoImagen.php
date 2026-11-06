<?php
/**
 * =====================================================
 * MODELO: ProductoImagen  (modelos/productos/ProductoImagen.php)
 * -----------------------------------------------------
 * Galería de imágenes de un producto (una o varias).
 *
 *   · Guarda archivos reales en assets/img/productos.
 *   · Valida tipo MIME real, peso y dimensiones.
 *   · Una sola imagen principal por producto.
 *   · Sincroniza productos.imagen con la principal
 *     (compatibilidad con el módulo anterior).
 * =====================================================
 */

class ProductoImagen
{
    private PDO $conn;

    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
    private const MIN_LADO = 120;
    private const MAX_LADO = 6000;
    private const MAX_IMAGENES = 10;

    private const MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    public function listar(int $idProducto): array
    {
        if ($idProducto <= 0) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare(
                'SELECT * FROM productos_imagenes
                 WHERE id_producto = :id
                 ORDER BY principal DESC, orden ASC, id_imagen ASC'
            );
            $stmt->execute([':id' => $idProducto]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoImagen::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    /**
     * Imágenes de varios productos en una sola consulta.
     *
     * @param int[] $idsProductos
     * @return array<int, array>
     */
    public function mapaPorProductos(array $idsProductos): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsProductos))));

        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM productos_imagenes
                 WHERE id_producto IN ($marcas)
                 ORDER BY principal DESC, orden ASC, id_imagen ASC"
            );
            $stmt->execute($ids);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('ProductoImagen::mapaPorProductos - ' . $e->getMessage());
            return [];
        }

        $mapa = [];

        foreach ($filas as $fila) {
            $imagen = $this->normalizar($fila);
            $mapa[$imagen['id_producto']][] = $imagen;
        }

        return $mapa;
    }

    /** Ruta relativa de la imagen principal (o null). */
    public function archivoPrincipal(int $idProducto): ?string
    {
        try {
            $stmt = $this->conn->prepare(
                'SELECT archivo FROM productos_imagenes
                 WHERE id_producto = :id AND principal = 1
                 LIMIT 1'
            );
            $stmt->execute([':id' => $idProducto]);
            $archivo = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('ProductoImagen::archivoPrincipal - ' . $e->getMessage());
            return null;
        }

        return $archivo !== false && $archivo !== null ? (string) $archivo : null;
    }

    // =================================================
    //  GUARDADO (subida + eliminación + principal)
    // =================================================

    /**
     * Guarda el estado de la galería de un producto.
     *
     * @param array $archivos Lista normalizada de $_FILES.
     * @param int[] $eliminar IDs de imágenes a eliminar.
     * @param string $principal '' | 'e:ID' | 'n:INDICE'
     * @return array{exito:bool, mensaje:string, imagenes:array}
     */
    public function guardar(int $idProducto, array $archivos, array $eliminar, string $principal): array
    {
        if ($idProducto <= 0) {
            return self::err('Producto no válido');
        }

        if (!$this->productoExiste($idProducto)) {
            return self::err('Ese producto ya no existe');
        }

        $eliminar = array_values(array_unique(array_filter(array_map('intval', $eliminar))));

        // Las imágenes a eliminar deben pertenecer al producto.
        $aEliminar = $this->filasPorIds($idProducto, $eliminar);

        $actuales = (int) $this->contar($idProducto);
        $nuevasValidas = 0;

        foreach ($archivos as $archivo) {
            $error = $this->validarArchivo($archivo);

            if ($error !== '') {
                return self::err($error);
            }

            $nuevasValidas++;
        }

        if ($actuales - count($aEliminar) + $nuevasValidas > self::MAX_IMAGENES) {
            return self::err('Un producto puede tener como máximo ' . self::MAX_IMAGENES . ' imágenes');
        }

        $subidas = [];
        $rutasSubidas = [];
        $archivosAEliminar = [];

        try {
            $this->conn->beginTransaction();

            // 1. Eliminar las marcadas
            if ($aEliminar !== []) {
                $ids = array_column($aEliminar, 'id_imagen');
                $marcas = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $this->conn->prepare(
                    "DELETE FROM productos_imagenes
                     WHERE id_producto = ? AND id_imagen IN ($marcas)"
                );
                $stmt->execute(array_merge([$idProducto], $ids));

                foreach ($aEliminar as $fila) {
                    $archivosAEliminar[] = (string) $fila['archivo'];
                }
            }

            // 2. Subir las nuevas
            $orden = $this->siguienteOrden($idProducto);

            foreach ($archivos as $indice => $archivo) {
                $guardada = $this->guardarArchivo($idProducto, $archivo, $orden);

                if (!$guardada['exito']) {
                    throw new RuntimeException($guardada['mensaje']);
                }

                $subidas[$indice] = $guardada['id_imagen'];
                $rutasSubidas[] = (string) ($guardada['archivo'] ?? '');
                $orden++;
            }

            // 3. Elegir principal
            $imagenes = $this->listar($idProducto);
            $idPrincipal = 0;

            if (strpos($principal, 'e:') === 0) {
                $candidato = (int) substr($principal, 2);

                foreach ($imagenes as $img) {
                    if ($img['id'] === $candidato) {
                        $idPrincipal = $candidato;
                        break;
                    }
                }
            } elseif (strpos($principal, 'n:') === 0) {
                $candidato = (int) substr($principal, 2);

                if (isset($subidas[$candidato])) {
                    $idPrincipal = $subidas[$candidato];
                }
            }

            if ($idPrincipal === 0) {
                // Conservar la principal actual si sigue existiendo.
                foreach ($imagenes as $img) {
                    if ($img['principal'] === 1) {
                        $idPrincipal = $img['id'];
                        break;
                    }
                }
            }

            if ($idPrincipal === 0 && $imagenes !== []) {
                $idPrincipal = (int) $imagenes[0]['id'];
            }

            $this->aplicarPrincipal($idProducto, $idPrincipal);
            $this->sincronizarProducto($idProducto);

            $this->conn->commit();

            // 4. Borrar los archivos físicos ya confirmados
            foreach ($archivosAEliminar as $ruta) {
                $this->borrarArchivo($ruta);
            }

            return [
                'exito'    => true,
                'mensaje'  => 'Galería actualizada correctamente',
                'imagenes' => $this->listar($idProducto),
            ];
        } catch (RuntimeException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $this->limpiarRutas($rutasSubidas);

            return self::err($e->getMessage());
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $this->limpiarRutas($rutasSubidas);

            error_log('ProductoImagen::guardar - ' . $e->getMessage());

            return self::err('No se pudo guardar la galería de imágenes');
        }
    }

    // =================================================
    //  VALIDACIÓN / ARCHIVOS
    // =================================================

    public function validarArchivo(array $archivo): string
    {
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return 'No se recibió ninguna imagen';
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'Una imagen supera el tamaño permitido por el servidor';
        }

        if ($error !== UPLOAD_ERR_OK) {
            return 'No se pudo subir una de las imágenes (código ' . $error . ')';
        }

        $tmp = (string) ($archivo['tmp_name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'El archivo recibido no es válido';
        }

        $tamano = (int) ($archivo['size'] ?? 0);

        if ($tamano <= 0 || $tamano > self::MAX_BYTES) {
            return 'Cada imagen debe pesar como máximo 5 MB';
        }

        $info = @getimagesize($tmp);

        if ($info === false) {
            return 'Uno de los archivos no es una imagen válida';
        }

        $mime = (string) ($info['mime'] ?? '');

        if (!isset(self::MIMES[$mime])) {
            return 'Formato no permitido: usa JPG, PNG o WEBP';
        }

        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);

        if ($ancho < self::MIN_LADO || $alto < self::MIN_LADO) {
            return 'Cada imagen debe medir al menos ' . self::MIN_LADO . ' px por lado';
        }

        if ($ancho > self::MAX_LADO || $alto > self::MAX_LADO) {
            return 'Cada imagen debe medir como máximo ' . self::MAX_LADO . ' px por lado';
        }

        return '';
    }

    private function guardarArchivo(int $idProducto, array $archivo, int $orden): array
    {
        $error = $this->validarArchivo($archivo);

        if ($error !== '') {
            return ['exito' => false, 'mensaje' => $error, 'id_imagen' => 0];
        }

        $tmp = (string) $archivo['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = (string) $info['mime'];
        $extension = self::MIMES[$mime];

        $directorio = $this->directorio();

        if (!is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return ['exito' => false, 'mensaje' => 'No se pudo crear la carpeta de imágenes', 'id_imagen' => 0];
        }

        $nombre = 'prod_' . $idProducto . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!@move_uploaded_file($tmp, $destino)) {
            return ['exito' => false, 'mensaje' => 'No se pudo mover la imagen al servidor', 'id_imagen' => 0];
        }

        @chmod($destino, 0644);

        $ruta = 'assets/img/productos/' . $nombre;

        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO productos_imagenes
                    (id_producto, archivo, nombre_original, tipo_mime, tamano, ancho, alto, orden, principal)
                 VALUES
                    (:producto, :archivo, :original, :mime, :tamano, :ancho, :alto, :orden, 0)'
            );

            $stmt->execute([
                ':producto' => $idProducto,
                ':archivo'  => $ruta,
                ':original' => $this->limpiarNombre((string) ($archivo['name'] ?? '')),
                ':mime'     => $mime,
                ':tamano'   => (int) $archivo['size'],
                ':ancho'    => (int) $info[0],
                ':alto'     => (int) $info[1],
                ':orden'    => $orden,
            ]);

            return [
                'exito'     => true,
                'mensaje'   => 'ok',
                'id_imagen' => (int) $this->conn->lastInsertId(),
                'archivo'   => $ruta,
            ];
        } catch (PDOException $e) {
            @unlink($destino);
            error_log('ProductoImagen::guardarArchivo - ' . $e->getMessage());

            return ['exito' => false, 'mensaje' => 'No se pudo registrar la imagen', 'id_imagen' => 0];
        }
    }

    private function aplicarPrincipal(int $idProducto, int $idImagen): void
    {
        $this->conn
            ->prepare('UPDATE productos_imagenes SET principal = 0 WHERE id_producto = :id')
            ->execute([':id' => $idProducto]);

        if ($idImagen > 0) {
            $this->conn
                ->prepare('UPDATE productos_imagenes SET principal = 1 WHERE id_producto = :id AND id_imagen = :imagen')
                ->execute([':id' => $idProducto, ':imagen' => $idImagen]);
        }
    }

    private function sincronizarProducto(int $idProducto): void
    {
        $archivo = $this->archivoPrincipal($idProducto);

        $this->conn
            ->prepare('UPDATE productos SET imagen = :imagen WHERE id_producto = :id')
            ->execute([
                ':imagen' => $archivo,
                ':id'     => $idProducto,
            ]);
    }

    private function directorio(): string
    {
        return BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'productos';
    }

    private function borrarArchivo(string $ruta): void
    {
        $ruta = ltrim(str_replace('\\', '/', $ruta), '/');

        if (strpos($ruta, 'assets/img/productos/') !== 0) {
            return;
        }

        $absoluta = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);

        if (is_file($absoluta)) {
            @unlink($absoluta);
        }
    }

    /**
     * Borra los archivos físicos subidos antes de revertir la transacción.
     *
     * @param string[] $rutas Rutas relativas devueltas por guardarArchivo().
     */
    private function limpiarRutas(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            $ruta = trim((string) $ruta);

            if ($ruta !== '') {
                $this->borrarArchivo($ruta);
            }
        }
    }

    private function siguienteOrden(int $idProducto): int
    {
        $stmt = $this->conn->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM productos_imagenes WHERE id_producto = :id');
        $stmt->execute([':id' => $idProducto]);

        return (int) $stmt->fetchColumn();
    }

    private function contar(int $idProducto): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM productos_imagenes WHERE id_producto = :id');
        $stmt->execute([':id' => $idProducto]);

        return (int) $stmt->fetchColumn();
    }

    private function filasPorIds(int $idProducto, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->conn->prepare(
            "SELECT id_imagen, archivo FROM productos_imagenes
             WHERE id_producto = ? AND id_imagen IN ($marcas)"
        );
        $stmt->execute(array_merge([$idProducto], $ids));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function productoExiste(int $idProducto): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM productos WHERE id_producto = :id LIMIT 1');
        $stmt->execute([':id' => $idProducto]);

        return (bool) $stmt->fetchColumn();
    }

    private function limpiarNombre(string $nombre): string
    {
        $nombre = trim(strip_tags($nombre));

        return mb_substr($nombre !== '' ? $nombre : 'imagen', 0, 255);
    }

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'imagenes' => []];
    }

    private function normalizar(array $f): array
    {
        $archivo = (string) $f['archivo'];

        return [
            'id'              => (int) $f['id_imagen'],
            'id_producto'     => (int) $f['id_producto'],
            'archivo'         => $archivo,
            'url'             => self::urlPublica($archivo),
            'nombre_original' => (string) ($f['nombre_original'] ?? ''),
            'tipo_mime'       => (string) ($f['tipo_mime'] ?? ''),
            'tamano'          => (int) ($f['tamano'] ?? 0),
            'ancho'           => (int) ($f['ancho'] ?? 0),
            'alto'            => (int) ($f['alto'] ?? 0),
            'orden'           => (int) ($f['orden'] ?? 0),
            'principal'       => (int) ($f['principal'] ?? 0),
        ];
    }

    public static function urlPublica(string $archivo): string
    {
        if ($archivo === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $archivo) || strpos($archivo, '//') === 0) {
            return $archivo;
        }

        $archivo = ltrim(str_replace('\\', '/', $archivo), '/');

        return defined('BASE_URL') ? BASE_URL . '/' . $archivo : $archivo;
    }
}

