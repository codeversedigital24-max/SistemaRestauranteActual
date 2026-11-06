<?php
/**
 * =====================================================
 * MODELO: Proveedor  (modelos/compras/Proveedor.php)
 * -----------------------------------------------------
 * Catálogo de proveedores usado por el módulo de compras.
 *
 * Reglas:
 *   · Nombre obligatorio (3-150 caracteres).
 *   · NIT opcional y único cuando se informa.
 *   · Email válido si se informa.
 *   · Baja lógica para no romper el historial de compras.
 * =====================================================
 */

class Proveedor
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =================================================
    //  LECTURA
    // =================================================

    public function listar(bool $soloActivos = false): array
    {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM compras c
                         WHERE c.id_proveedor = p.id_proveedor
                           AND c.estado = 'registrada') AS compras_count
                FROM proveedores p";

        if ($soloActivos) {
            $sql .= ' WHERE p.estado = 1';
        }

        $sql .= ' ORDER BY p.estado DESC, p.nombre ASC';

        try {
            $filas = $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Proveedor::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare('SELECT * FROM proveedores WHERE id_proveedor = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Proveedor::obtener - ' . $e->getMessage());
            return null;
        }

        return $fila ? $this->normalizar($fila) : null;
    }

    // =================================================
    //  ESCRITURA
    // =================================================

    public function crear(array $datos): array
    {
        $d = $this->sanear($datos);

        $error = $this->validar($d);
        if ($error !== '') {
            return self::err($error);
        }

        if ($this->nitExiste((string) $d['nit'])) {
            return self::err('Ese NIT ya está registrado en otro proveedor');
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO proveedores
                    (nombre, razon_social, nit, contacto, telefono, email,
                     direccion, ciudad, notas, estado)
                 VALUES
                    (:nombre, :razon, :nit, :contacto, :telefono, :email,
                     :direccion, :ciudad, :notas, :estado)"
            );

            $stmt->execute($this->parametros($d));

            return [
                'exito'   => true,
                'mensaje' => 'Proveedor registrado correctamente',
                'id'      => (int) $this->conn->lastInsertId(),
            ];
        } catch (PDOException $e) {
            return $this->errorBaseDatos($e, 'No se pudo registrar el proveedor');
        }
    }

    public function actualizar(array $datos): array
    {
        $d = $this->sanear($datos);

        if ($d['id'] <= 0) {
            return self::err('Proveedor no válido');
        }

        if (!$this->obtener($d['id'])) {
            return self::err('Ese proveedor ya no existe');
        }

        $error = $this->validar($d);
        if ($error !== '') {
            return self::err($error);
        }

        if ($this->nitExiste((string) $d['nit'], $d['id'])) {
            return self::err('Ese NIT ya está registrado en otro proveedor');
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE proveedores SET
                    nombre       = :nombre,
                    razon_social = :razon,
                    nit          = :nit,
                    contacto     = :contacto,
                    telefono     = :telefono,
                    email        = :email,
                    direccion    = :direccion,
                    ciudad       = :ciudad,
                    notas        = :notas,
                    estado       = :estado
                 WHERE id_proveedor = :id"
            );

            $params = $this->parametros($d);
            $params[':id'] = $d['id'];
            $stmt->execute($params);

            return [
                'exito'   => true,
                'mensaje' => 'Proveedor actualizado correctamente',
                'id'      => $d['id'],
            ];
        } catch (PDOException $e) {
            return $this->errorBaseDatos($e, 'No se pudo actualizar el proveedor');
        }
    }

    /** Baja lógica: conserva las compras históricas. */
    public function eliminar(int $id): array
    {
        if ($id <= 0) {
            return self::err('Proveedor no válido');
        }

        if (!$this->obtener($id)) {
            return self::err('Ese proveedor ya no existe');
        }

        try {
            $this->conn
                ->prepare('UPDATE proveedores SET estado = 0 WHERE id_proveedor = :id')
                ->execute([':id' => $id]);

            return [
                'exito'   => true,
                'mensaje' => 'Proveedor desactivado correctamente',
                'id'      => $id,
            ];
        } catch (PDOException $e) {
            error_log('Proveedor::eliminar - ' . $e->getMessage());
            return self::err('No se pudo desactivar el proveedor');
        }
    }

    // =================================================
    //  VALIDACIÓN / SANEO
    // =================================================

    private function validar(array $d): string
    {
        if (mb_strlen($d['nombre']) < 3) {
            return 'El nombre del proveedor debe tener al menos 3 caracteres';
        }

        if (mb_strlen($d['nombre']) > 150) {
            return 'El nombre no puede superar los 150 caracteres';
        }

        if ($d['razon_social'] !== '' && mb_strlen($d['razon_social']) > 150) {
            return 'La razón social no puede superar los 150 caracteres';
        }

        if ($d['nit'] !== '' && mb_strlen($d['nit']) > 30) {
            return 'El NIT no puede superar los 30 caracteres';
        }

        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo del proveedor no es válido';
        }

        if (mb_strlen($d['telefono']) > 30) {
            return 'El teléfono no puede superar los 30 caracteres';
        }

        if (mb_strlen($d['contacto']) > 100) {
            return 'El contacto no puede superar los 100 caracteres';
        }

        if (mb_strlen($d['direccion']) > 255 || mb_strlen($d['notas']) > 255) {
            return 'La dirección o las notas superan el largo permitido';
        }

        return '';
    }

    private function sanear(array $d): array
    {
        $limpia = static fn(string $v): string => trim(strip_tags($v));

        return [
            'id'           => (int) ($d['id'] ?? 0),
            'nombre'       => $limpia((string) ($d['nombre'] ?? '')),
            'razon_social' => $limpia((string) ($d['razon_social'] ?? '')),
            'nit'          => mb_strtoupper($limpia((string) ($d['nit'] ?? ''))),
            'contacto'     => $limpia((string) ($d['contacto'] ?? '')),
            'telefono'     => $limpia((string) ($d['telefono'] ?? '')),
            'email'        => mb_strtolower($limpia((string) ($d['email'] ?? ''))),
            'direccion'    => $limpia((string) ($d['direccion'] ?? '')),
            'ciudad'       => $limpia((string) ($d['ciudad'] ?? '')),
            'notas'        => $limpia((string) ($d['notas'] ?? '')),
            'estado'       => ((int) ($d['estado'] ?? 1)) === 0 ? 0 : 1,
        ];
    }

    private function parametros(array $d): array
    {
        return [
            ':nombre'    => $d['nombre'],
            ':razon'     => $d['razon_social'] !== '' ? $d['razon_social'] : null,
            ':nit'       => $d['nit'] !== '' ? $d['nit'] : null,
            ':contacto'  => $d['contacto'] !== '' ? $d['contacto'] : null,
            ':telefono'  => $d['telefono'] !== '' ? $d['telefono'] : null,
            ':email'     => $d['email'] !== '' ? $d['email'] : null,
            ':direccion' => $d['direccion'] !== '' ? $d['direccion'] : null,
            ':ciudad'    => $d['ciudad'] !== '' ? $d['ciudad'] : null,
            ':notas'     => $d['notas'] !== '' ? $d['notas'] : null,
            ':estado'    => $d['estado'],
        ];
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0];
    }

    private function nitExiste(string $nit, ?int $excluirId = null): bool
    {
        if ($nit === '') {
            return false;
        }

        $sql = 'SELECT 1 FROM proveedores WHERE nit = :nit';
        $params = [':nit' => $nit];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id_proveedor != :id';
            $params[':id'] = $excluirId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function errorBaseDatos(PDOException $e, string $mensaje): array
    {
        if ($e->getCode() === '23000') {
            return self::err('El NIT del proveedor ya está registrado');
        }

        error_log('Proveedor - ' . $e->getMessage());

        return self::err($mensaje);
    }

    private function normalizar(array $f): array
    {
        return [
            'id'            => (int) $f['id_proveedor'],
            'nombre'        => (string) $f['nombre'],
            'razon_social'  => (string) ($f['razon_social'] ?? ''),
            'nit'           => (string) ($f['nit'] ?? ''),
            'contacto'      => (string) ($f['contacto'] ?? ''),
            'telefono'      => (string) ($f['telefono'] ?? ''),
            'email'         => (string) ($f['email'] ?? ''),
            'direccion'     => (string) ($f['direccion'] ?? ''),
            'ciudad'        => (string) ($f['ciudad'] ?? ''),
            'notas'         => (string) ($f['notas'] ?? ''),
            'estado'        => (int) ($f['estado'] ?? 1),
            'compras_count' => (int) ($f['compras_count'] ?? 0),
            'creado'        => !empty($f['created_at']) ? date('d/m/Y', strtotime($f['created_at'])) : '—',
        ];
    }
}
