<?php
/**
 * =====================================================
 * MODELO: MiPerfil  (modelos/perfil/MiPerfil.php)
 * -----------------------------------------------------
 * Sólo permite operar sobre el usuario en sesión.
 * No cambia rol, sucursal, estado, bloqueos ni permisos.
 * =====================================================
 */

class MiPerfil
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    public function obtener(int $idUsuario): ?array
    {
        if ($idUsuario <= 0) return null;

        $sql = "SELECT u.id_usuario, u.username, u.email, u.estado, u.ultimo_login,
                       u.id_rol, u.id_sucursal,
                       r.nombre_rol,
                       s.nombre_sucursal, s.ciudad,
                       d.nombres, d.apellidos, d.cedula,
                       d.telefono_movil, d.telefono_fijo,
                       d.direccion, d.fecha_nacimiento, d.genero,
                       d.foto_url, d.foto_perfil
                  FROM usuarios u
                  INNER JOIN roles r ON r.id_rol = u.id_rol
                  LEFT  JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                  LEFT  JOIN usuarios_datos d ON d.id_usuario = u.id_usuario
                 WHERE u.id_usuario = :id LIMIT 1";

        $st = $this->conn->prepare($sql);
        $st->execute([':id' => $idUsuario]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if (!$fila) return null;

        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $fotoPerfil = (string) ($fila['foto_perfil'] ?? '');
        $fotoUrl    = (string) ($fila['foto_url'] ?? '');
        $rutaFoto   = $fotoPerfil !== '' ? $fotoPerfil : $fotoUrl;

        return [
            'id'          => (int) $fila['id_usuario'],
            'username'    => (string) $fila['username'],
            'email'       => (string) $fila['email'],
            'estado'      => (int) $fila['estado'],
            'id_rol'      => (int) $fila['id_rol'],
            'rol'         => (string) $fila['nombre_rol'],
            'id_sucursal' => (int) ($fila['id_sucursal'] ?? 0),
            'sucursal'    => (string) ($fila['nombre_sucursal'] ?? ''),
            'ciudad'      => (string) ($fila['ciudad'] ?? ''),
            'nombres'     => (string) ($fila['nombres'] ?? ''),
            'apellidos'   => (string) ($fila['apellidos'] ?? ''),
            'cedula'      => (string) ($fila['cedula'] ?? ''),
            'telefono'    => (string) ($fila['telefono_movil'] ?? ''),
            'telefono_fijo' => (string) ($fila['telefono_fijo'] ?? ''),
            'direccion'   => (string) ($fila['direccion'] ?? ''),
            'fecha_nacimiento' => (string) ($fila['fecha_nacimiento'] ?? ''),
            'genero'      => (string) ($fila['genero'] ?? ''),
            'foto'        => $fotoPerfil,
            'foto_url'    => $fotoUrl,
            'foto_ruta'   => $rutaFoto,
            'foto_public' => $rutaFoto !== ''
                ? ($base !== '' ? $base . '/uploads/usuarios/' . ltrim($rutaFoto, '/') : '/uploads/usuarios/' . ltrim($rutaFoto, '/'))
                : '',
            'ultimo_login' => !empty($fila['ultimo_login'])
                ? date('d/m/Y H:i', strtotime($fila['ultimo_login'])) : 'Nunca',
        ];
    }

    public function actualizar(int $idUsuario, array $datos): array
    {
        if ($idUsuario <= 0) {
            return ['exito' => false, 'mensaje' => 'Usuario no válido', 'id' => 0];
        }

        $actual = $this->obtener($idUsuario);
        if (!$actual) {
            return ['exito' => false, 'mensaje' => 'Ese usuario ya no existe', 'id' => 0];
        }

        $nombres       = trim(strip_tags((string) ($datos['nombres'] ?? '')));
        $apellidos     = trim(strip_tags((string) ($datos['apellidos'] ?? '')));
        $cedula        = trim(strip_tags((string) ($datos['cedula'] ?? '')));
        $telefono      = trim(strip_tags((string) ($datos['telefono'] ?? '')));
        $telefonoFijo  = trim(strip_tags((string) ($datos['telefono_fijo'] ?? '')));
        $direccion     = trim(strip_tags((string) ($datos['direccion'] ?? '')));
        $fechaNac      = trim((string) ($datos['fecha_nacimiento'] ?? ''));
        $genero        = strtoupper(trim((string) ($datos['genero'] ?? '')));
        $username      = trim(strip_tags((string) ($datos['username'] ?? '')));
        $email         = mb_strtolower(trim(strip_tags((string) ($datos['email'] ?? ''))));
        $passwordActual= (string) ($datos['password_actual'] ?? '');
        $passwordNueva = (string) ($datos['password'] ?? '');
        $passwordNueva2= (string) ($datos['password2'] ?? '');

        if (mb_strlen($nombres) < 2)   return self::err('Nombres obligatorios');
        if (mb_strlen($apellidos) < 2) return self::err('Apellidos obligatorios');
        if (mb_strlen($username) < 3 || mb_strlen($username) > 50 || preg_match('/\s/', $username)) {
            return self::err('El usuario debe tener 3 a 50 caracteres sin espacios');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return self::err('Correo no válido');

        if (!in_array($genero, ['', 'M', 'F', 'O'], true)) $genero = '';

        if ($fechaNac !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
            return self::err('La fecha de nacimiento no es válida');
        }

        // Contraseña: sólo si la envió
        $cambiaPassword = ($passwordNueva !== '');
        if ($cambiaPassword) {
            if (mb_strlen($passwordNueva) < 8) {
                return self::err('La nueva contraseña debe tener al menos 8 caracteres');
            }
            if ($passwordNueva !== $passwordNueva2) {
                return self::err('La nueva contraseña y su confirmación no coinciden');
            }
            if ($passwordActual === '') {
                return self::err('Debes indicar tu contraseña actual para cambiarla');
            }

            $st = $this->conn->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = :id LIMIT 1");
            $st->execute([':id' => $idUsuario]);
            $hash = (string) $st->fetchColumn();

            if (!password_verify($passwordActual, $hash)) {
                return self::err('La contraseña actual no es correcta');
            }
        }

        // Duplicados (username/email) excluyendo al propio usuario
        $st = $this->conn->prepare(
            "SELECT 1 FROM usuarios
              WHERE id_usuario != :id
                AND (username = :u OR email = :e)
              LIMIT 1"
        );
        $st->execute([':id' => $idUsuario, ':u' => $username, ':e' => $email]);
        if ($st->fetchColumn()) {
            return self::err('Ese usuario o correo ya está registrado');
        }

        try {
            $this->conn->beginTransaction();

            // 1) usuarios: username, email, password_hash (si aplica)
            $campos = 'username = :username, email = :email';
            $params = [':username' => $username, ':email' => $email, ':id' => $idUsuario];

            if ($cambiaPassword) {
                $campos .= ', password_hash = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL';
                $params[':hash'] = password_hash($passwordNueva, PASSWORD_DEFAULT);
            }

            $this->conn->prepare("UPDATE usuarios SET {$campos} WHERE id_usuario = :id")
                ->execute($params);

            // 2) usuarios_datos: crear si no existe, actualizar si existe
            $existe = $this->existeFila('usuarios_datos', 'id_usuario', $idUsuario);

            if ($existe) {
                $this->conn->prepare(
                    "UPDATE usuarios_datos
                        SET nombres = :nombres,
                            apellidos = :apellidos,
                            cedula = :cedula,
                            telefono_movil = :telefono,
                            telefono_fijo = :telefono_fijo,
                            direccion = :direccion,
                            fecha_nacimiento = :fecha_nac,
                            genero = :genero
                      WHERE id_usuario = :id"
                )->execute([
                    ':nombres'     => $nombres,
                    ':apellidos'   => $apellidos,
                    ':cedula'      => $cedula      !== '' ? $cedula      : null,
                    ':telefono'    => $telefono    !== '' ? $telefono    : null,
                    ':telefono_fijo' => $telefonoFijo !== '' ? $telefonoFijo : null,
                    ':direccion'   => $direccion   !== '' ? $direccion   : null,
                    ':fecha_nac'   => $fechaNac    !== '' ? $fechaNac    : null,
                    ':genero'      => $genero      !== '' ? $genero      : null,
                    ':id'          => $idUsuario,
                ]);
            } else {
                $this->conn->prepare(
                    "INSERT INTO usuarios_datos
                        (id_usuario, nombres, apellidos, cedula, telefono_movil,
                         telefono_fijo, direccion, fecha_nacimiento, genero)
                     VALUES
                        (:id, :nombres, :apellidos, :cedula, :telefono,
                         :telefono_fijo, :direccion, :fecha_nac, :genero)"
                )->execute([
                    ':id'          => $idUsuario,
                    ':nombres'     => $nombres,
                    ':apellidos'   => $apellidos,
                    ':cedula'      => $cedula      !== '' ? $cedula      : null,
                    ':telefono'    => $telefono    !== '' ? $telefono    : null,
                    ':telefono_fijo' => $telefonoFijo !== '' ? $telefonoFijo : null,
                    ':direccion'   => $direccion   !== '' ? $direccion   : null,
                    ':fecha_nac'   => $fechaNac    !== '' ? $fechaNac    : null,
                    ':genero'      => $genero      !== '' ? $genero      : null,
                ]);
            }

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Perfil actualizado correctamente',
                'id'      => $idUsuario,
                'perfil'  => $this->obtener($idUsuario),
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') return self::err('Ese usuario, correo o cédula ya están registrados');
            error_log('MiPerfil::actualizar - ' . $e->getMessage());
            return self::err('No se pudo actualizar el perfil');
        }
    }

    public function actualizarFoto(int $idUsuario, string $rutaArchivo): array
    {
        if ($idUsuario <= 0) return self::err('Usuario no válido');

        $actual = $this->obtener($idUsuario);
        if (!$actual) return self::err('Ese usuario ya no existe');

        try {
            // Borrar archivo anterior
            if (!empty($actual['foto'])) {
                $rutaVieja = BASE_PATH . '/uploads/usuarios/' . basename((string) $actual['foto']);
                if (is_file($rutaVieja)) @unlink($rutaVieja);
            }

            $existe = $this->existeFila('usuarios_datos', 'id_usuario', $idUsuario);

            if ($existe) {
                $this->conn->prepare(
                    "UPDATE usuarios_datos SET foto_perfil = :foto, foto_url = NULL WHERE id_usuario = :id"
                )->execute([':foto' => $rutaArchivo, ':id' => $idUsuario]);
            } else {
                $this->conn->prepare(
                    "INSERT INTO usuarios_datos (id_usuario, foto_perfil) VALUES (:id, :foto)"
                )->execute([':id' => $idUsuario, ':foto' => $rutaArchivo]);
            }

            return ['exito' => true, 'mensaje' => 'Foto actualizada', 'ruta' => $rutaArchivo];
        } catch (PDOException $e) {
            error_log('MiPerfil::actualizarFoto - ' . $e->getMessage());
            return self::err('No se pudo actualizar la foto');
        }
    }

       public function quitarFoto(int $idUsuario): array
    {
        if ($idUsuario <= 0) return self::err('Usuario no válido');

        $actual = $this->obtener($idUsuario);
        if (!$actual) return self::err('Ese usuario ya no existe');

        try {
            // Borrar archivo físico si existía
            if (!empty($actual['foto'])) {
                $rutaVieja = BASE_PATH . '/uploads/usuarios/' . basename((string) $actual['foto']);
                if (is_file($rutaVieja)) @unlink($rutaVieja);
            }

            $this->conn->prepare(
                "UPDATE usuarios_datos
                    SET foto_perfil = NULL, foto_url = NULL
                  WHERE id_usuario = :id"
            )->execute([':id' => $idUsuario]);

            return ['exito' => true, 'mensaje' => 'Foto eliminada'];
        } catch (PDOException $e) {
            error_log('MiPerfil::quitarFoto - ' . $e->getMessage());
            return self::err('No se pudo eliminar la foto');
        }
    }

    // =====================================================
    // AUXILIARES
    // =====================================================

    private function existeFila(string $tabla, string $columna, $valor): bool
    {
        if (!preg_match('/^[a-zA-Z_]+$/', $tabla) || !preg_match('/^[a-zA-Z_]+$/', $columna)) {
            return false;
        }
        $st = $this->conn->prepare("SELECT 1 FROM {$tabla} WHERE {$columna} = :v LIMIT 1");
        $st->execute([':v' => $valor]);
        return (bool) $st->fetchColumn();
    }

    private static function err(string $m): array
    {
        return ['exito' => false, 'mensaje' => $m, 'id' => 0];
    }
}