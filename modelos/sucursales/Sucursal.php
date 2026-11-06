<?php
/**
 * =====================================================
 * MODELO: Sucursal  (modelos/sucursales/Sucursal.php)
 * -----------------------------------------------------
 * CRUD sobre la tabla real `sucursales`.
 * =====================================================
 */

class Sucursal
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    /**
     * Índice: sucursales con número de usuarios asignados.
     *
     * @param int|null $idSucursal  null = todas (sólo SuperAdministrador);
     *                              0    = sin sucursal: sin datos;
     *                              >0   = forzar esa sucursal.
     */
    public function listar(?int $idSucursal = null): array
    {
        if ($idSucursal !== null && $idSucursal <= 0) {
            return [];
        }

        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM usuarios u
                        WHERE u.id_sucursal = s.id_sucursal AND u.estado != 0) AS usuarios
                FROM sucursales s";

        $params = [];

        if ($idSucursal !== null) {
            $sql .= " WHERE s.id_sucursal = :sucursal";
            $params[':sucursal'] = $idSucursal;
        }

        $sql .= " ORDER BY s.estado DESC, s.nombre_sucursal ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Sucursal::listar - ' . $e->getMessage());
            return [];
        }

        return array_map(fn(array $f): array => $this->normalizar($f), $filas);
    }

    /** Detalle de una sucursal */
    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare(
                "SELECT s.*,
                        (SELECT COUNT(*) FROM usuarios u
                         WHERE u.id_sucursal = s.id_sucursal AND u.estado != 0) AS usuarios
                 FROM sucursales s
                 WHERE s.id_sucursal = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Sucursal::obtener - ' . $e->getMessage());
            return null;
        }

        return $fila ? $this->normalizar($fila) : null;
    }

    /** Alta */
    public function crear(array $datos): array
    {
        $d = $this->sanear($datos);

        $falta = $this->faltantes($d);
        if ($falta !== []) {
            return self::err('Falta completar: ' . implode(', ', $falta));
        }

        $forma = $this->validarForma($d);
        if ($forma !== '') {
            return self::err($forma);
        }

        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return self::err('El correo no tiene un formato válido');
        }

        $dup = $this->existeDuplicado($d);
        if ($dup !== null) {
            return $dup;
        }

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO sucursales
                    (codigo_sucursal, nombre_sucursal, direccion, telefono, email, ciudad, estado)
                 VALUES (:codigo, :nombre, :direccion, :telefono, :email, :ciudad, :estado)"
            );

            $stmt->execute([
                ':codigo'    => $d['codigo'],
                ':nombre'    => $d['nombre'],
                ':direccion' => $d['direccion'] !== '' ? $d['direccion'] : null,
                ':telefono'  => $d['telefono'] !== '' ? $d['telefono'] : null,
                ':email'     => $d['email'] !== '' ? $d['email'] : null,
                ':ciudad'    => $d['ciudad'] !== '' ? $d['ciudad'] : null,
                ':estado'    => $d['estado'],
            ]);

            return [
                'exito'   => true,
                'mensaje' => 'Sucursal creada correctamente',
                'id'      => (int) $this->conn->lastInsertId(),
            ];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['exito' => false, 'mensaje' => 'Ese código ya está en uso', 'id' => 0];
            }
            error_log('Sucursal::crear - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo crear la sucursal', 'id' => 0];
        }
    }

    /** Actualización */
    public function actualizar(array $datos): array
    {
        $d = $this->sanear($datos);

        if ($d['id'] <= 0) {
            return self::err('Sucursal no válida');
        }

        if (!$this->obtener($d['id'])) {
            return self::err('Esa sucursal ya no existe');
        }

        $falta = $this->faltantes($d);
        if ($falta !== []) {
            return self::err('Falta completar: ' . implode(', ', $falta));
        }

        $forma = $this->validarForma($d);
        if ($forma !== '') {
            return self::err($forma);
        }

        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return self::err('El correo no tiene un formato válido');
        }

        $dup = $this->existeDuplicado($d, $d['id']);
        if ($dup !== null) {
            return $dup;
        }

        try {
            $stmt = $this->conn->prepare(
                "UPDATE sucursales
                 SET codigo_sucursal = :codigo,
                     nombre_sucursal = :nombre,
                     direccion = :direccion,
                     telefono = :telefono,
                     email = :email,
                     ciudad = :ciudad,
                     estado = :estado
                 WHERE id_sucursal = :id"
            );

            $stmt->execute([
                ':codigo'    => $d['codigo'],
                ':nombre'    => $d['nombre'],
                ':direccion' => $d['direccion'] !== '' ? $d['direccion'] : null,
                ':telefono'  => $d['telefono'] !== '' ? $d['telefono'] : null,
                ':email'     => $d['email'] !== '' ? $d['email'] : null,
                ':ciudad'    => $d['ciudad'] !== '' ? $d['ciudad'] : null,
                ':estado'    => $d['estado'],
                ':id'        => $d['id'],
            ]);

            return ['exito' => true, 'mensaje' => 'Sucursal actualizada', 'id' => $d['id']];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['exito' => false, 'mensaje' => 'Ese código ya está en uso', 'id' => 0];
            }
            error_log('Sucursal::actualizar - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo actualizar', 'id' => 0];
        }
    }

    /** Baja lógica (no se permite si tiene usuarios) */
    public function eliminar(int $id): array
    {
        $suc = $this->obtener($id);

        if (!$suc) {
            return ['exito' => false, 'mensaje' => 'Esa sucursal ya no existe', 'id' => 0];
        }

        if ((int) $suc['usuarios'] > 0) {
            return [
                'exito'   => false,
                'mensaje' => 'Tiene ' . $suc['usuarios'] . ' usuario(s) asignado(s): reasignalos antes de darla de baja',
                'id'      => 0,
            ];
        }

        try {
            $stmt = $this->conn->prepare('UPDATE sucursales SET estado = 0 WHERE id_sucursal = :id');
            $stmt->execute([':id' => $id]);

            return ['exito' => true, 'mensaje' => 'Sucursal dada de baja', 'id' => $id];
        } catch (PDOException $e) {
            error_log('Sucursal::eliminar - ' . $e->getMessage());
            return ['exito' => false, 'mensaje' => 'No se pudo eliminar', 'id' => 0];
        }
    }

    // =================================================
    //  AUXILIARES
    // =================================================

    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0];
    }

    private function sanear(array $d): array
    {
        $limpio = static fn(string $v): string => trim(strip_tags($v));

        $codigo = $limpio((string) ($d['codigo'] ?? ''));

        return [
            'id'        => (int) ($d['id'] ?? 0),
            'codigo'    => mb_strtoupper($codigo),
            'nombre'    => $limpio((string) ($d['nombre'] ?? '')),
            'direccion' => $limpio((string) ($d['direccion'] ?? '')),
            'telefono'  => $limpio((string) ($d['telefono'] ?? '')),
            'email'     => mb_strtolower($limpio((string) ($d['email'] ?? ''))),
            'ciudad'    => $limpio((string) ($d['ciudad'] ?? '')),
            'estado'    => (int) ($d['estado'] ?? 1) === 0 ? 0 : 1,
        ];
    }

    private function faltantes(array $d): array
    {
        $falta = [];

        if ($d['codigo'] === '') {
            $falta[] = 'Código';
        }

        if (mb_strlen($d['nombre']) < 3) {
            $falta[] = 'Nombre (mínimo 3)';
        }

        return $falta;
    }

    private function validarForma(array $d): string
    {
        if (!preg_match('/^[A-Z0-9][A-Z0-9\-_]{2,19}$/', $d['codigo'])) {
            return 'El código debe tener 3-20 caracteres: letras, números, guion o guion bajo';
        }

        return '';
    }

    private function existeDuplicado(array $d, ?int $excluirId = null): ?array
    {
        $cond = '';
        $params = [];

        if ($excluirId !== null) {
            $cond = ' AND id_sucursal != :excluir';
            $params[':excluir'] = $excluirId;
        }

        $stmt = $this->conn->prepare(
            "SELECT codigo_sucursal, nombre_sucursal
             FROM sucursales
             WHERE (codigo_sucursal = :codigo OR nombre_sucursal = :nombre) {$cond}
             LIMIT 1"
        );
        $stmt->execute(array_merge([
            ':codigo' => $d['codigo'],
            ':nombre' => $d['nombre'],
        ], $params));

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        if (mb_strtoupper((string) $fila['codigo_sucursal']) === $d['codigo']) {
            return self::err('Ese código ya existe');
        }

        return self::err('Ya existe una sucursal con ese nombre');
    }

    private function normalizar(array $f): array
    {
        return [
            'id'        => (int) $f['id_sucursal'],
            'codigo'    => (string) $f['codigo_sucursal'],
            'nombre'    => (string) $f['nombre_sucursal'],
            'direccion' => (string) ($f['direccion'] ?? ''),
            'telefono'  => (string) ($f['telefono'] ?? ''),
            'email'     => (string) ($f['email'] ?? ''),
            'ciudad'    => (string) ($f['ciudad'] ?? ''),
            'estado'    => (int) ($f['estado'] ?? 1),
            'usuarios'  => (int) ($f['usuarios'] ?? 0),
            'creada'    => !empty($f['created_at']) ? date('d/m/Y', strtotime($f['created_at'])) : '—',
        ];
    }
}