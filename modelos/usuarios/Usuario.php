<?php

/**
 * =====================================================
 * MODELO: Usuario
 * -----------------------------------------------------
 * Única puerta a la tabla `usuarios` para este módulo.
 * Todo consulta preparada (sin SQL concatenado con datos
 * de entrada) y saneo previo.
 * =====================================================
 */

class Usuario
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    /**
     * Lista ligera: sólo lo que pinta la tabla.
     * Devuelve filas planas para JSON rápido.
     *
     * @param int|null $idSucursal  null = todas (sólo SuperAdministrador);
     *                              0    = usuario sin sucursal: lista vacía;
     *                              >0   = forzar esa sucursal.
     */
    public function listar(?int $idSucursal = null): array
    {
        // Alcance por sucursal: un usuario sin sucursal no ve datos.
        if ($idSucursal !== null && $idSucursal <= 0) {
            return [];
        }

        $sql = "SELECT u.id_usuario,
                       u.username,
                       u.email,
                       u.estado,
                       u.ultimo_login,
                       d.cedula,
                       d.telefono_movil,
                       r.nombre_rol,
                       s.nombre_sucursal,
                       CONCAT(COALESCE(d.nombres, ''), ' ', COALESCE(d.apellidos, '')) AS nombre_completo,
                       COALESCE(d.cedula, '')           AS cedula,
                       COALESCE(d.telefono_movil, '')   AS telefono,
                       COALESCE(d.foto_perfil, '')      AS foto_perfil
                FROM usuarios u
                INNER JOIN  roles r ON r.id_rol = u.id_rol
                LEFT JOIN   sucursales s ON s.id_sucursal = u.id_sucursal
                LEFT JOIN   usuarios_datos d ON d.id_usuario = u.id_usuario
                WHERE 1 = 1";
        $params = [];

        if ($idSucursal !== null) {
            $sql .= " AND u.id_sucursal = :sucursal";
            $params[':sucursal'] = $idSucursal;
        }

        $sql .= " ORDER BY u.id_usuario DESC";

        $resultado = [];

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Usuario::listar - ' . $e->getMessage());
        }

        return array_map(
            fn(array $fila): array => $this->normalizar($fila),
            $resultado
        );
    }

    /**
     * Un solo usuario con todos los datos que pide el modal de edición.
     */
    public function obtener(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "SELECT u.id_usuario,
                       u.username,
                       u.email,
                       u.estado,
                       u.id_rol,
                       u.id_sucursal,
                       r.nombre_rol,
                       s.nombre_sucursal,
                       TRIM(CONCAT(COALESCE(d.nombres, ''), ' ', COALESCE(d.apellidos, ''))) AS nombre_completo,
                       COALESCE(d.nombres, '')  AS nombres,
                       COALESCE(d.apellidos, '') AS apellidos,
                       COALESCE(d.cedula, '')    AS cedula,
                       COALESCE(d.telefono_movil, '') AS telefono,
                       COALESCE(d.foto_perfil, '') AS foto_perfil
                FROM usuarios u
                INNER JOIN roles r ON r.id_rol = u.id_rol
                LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                LEFT JOIN usuarios_datos d ON d.id_usuario = u.id_usuario
                WHERE u.id_usuario = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $base = $this->normalizar($fila);

        return $base + [
            'nombres'     => trim($fila['nombres']),
            'apellidos'   => trim($fila['apellidos']),
            'id_rol'      => (int) $fila['id_rol'],
            'id_sucursal' => (int) ($fila['id_sucursal'] ?? 0),
        ];
    }

    /**
     * Modifica usuario + sus datos personales.
     * La contraseña sólo cambia si se envía algo nuevo.
     *
     * @param array $datos  id + los mismos campos del alta (password vacío = no tocar)
     * @return array{exito: bool, mensaje: string, id: int}
     */
    public function actualizar(array $datos): array
    {
        $datos = $this->sanear($datos);
        $id = $datos['id'];
        if ($id <= 0) {
            return self::err('Usuario no válido para modificar');
        }

        // El usuario debe existir (también se pueden editar los dados de baja
        // para reactivarlos desde el propio modal).
        $actual = $this->obtener($id);
        if (!$actual) {
            return self::err('Ese usuario ya no existe');
        }

        foreach (['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'email' => 'Correo', 'username' => 'Usuario', 'id_rol' => 'Rol'] as $clave => $etiqueta) {
            if ($datos[$clave] === '' || $datos[$clave] === null) {
                return self::err("Falta el campo: {$etiqueta}");
            }
        }

        if (mb_strlen($datos['username']) > 50 || mb_strlen($datos['username']) < 3) {
            return self::err('El usuario debe tener entre 3 y 50 caracteres.');
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return self::err('El correo electrónico no tiene un formato válido.');
        }

        // Contraseña sólo si se quiere cambiar
        $cambiaPassword = ($datos['password'] !== '');
        if ($cambiaPassword) {
            if (mb_strlen($datos['password']) < 8) {
                return self::err('La contraseña debe tener al menos 8 caracteres.');
            }
            if ($datos['password'] !== $datos['password_confirmacion']) {
                return self::err('La contraseña y su confirmación no coinciden.');
            }
        }

        $rol = $this->buscarPorId('roles', 'id_rol', $datos['id_rol']);
        if (!$rol) {
            return self::err('El rol seleccionado no existe.');
        }

        if ($this->esRolSuperAdmin($datos['id_rol']) && (int) $datos['_usuario_logueado_rol'] !== 1) {
            return self::err('No tienes permiso para asignar SuperAdministradores.');
        }

        $dup = $this->existeDuplicado($datos, $id);
        if ($dup !== null) {
            return $dup;
        }

        // ---- Estado (alta / baja) ----
        $nuevoEstado = (int) $datos['estado'] === 0 ? 0 : 1;

        $errorEstado = $this->validarCambioEstado(
            $actual,
            $nuevoEstado,
            (int) $datos['_usuario_logueado_id'],
            (int) $datos['_usuario_logueado_rol']
        );

        if ($errorEstado !== null) {
            return self::err($errorEstado);
        }

        $this->conn->beginTransaction();

        try {
            $camposUsuario = 'username = :username,
                              email = :email,
                              id_rol = :rol,
                              id_sucursal = :sucursal,
                              estado = :estado';
            // --- Datos personales + foto ---
            $fotoSql     = '';
            $paramsDatos = [
                ':nombres'   => $datos['nombres'],
                ':apellidos' => $datos['apellidos'],
                ':cedula'    => $datos['cedula']   !== '' ? $datos['cedula']   : null,
                ':telefono'  => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                ':id'        => $id,
            ];

            if (!empty($datos['foto'])) {
                $fotoSql = ', foto_perfil = :foto';
                $paramsDatos[':foto'] = $datos['foto'];

                // Borrar archivo físico anterior (si existía)
                if (!empty($actual['foto'])) {
                    $rutaVieja = dirname(__DIR__, 2) . '/assets/img/usuarios/' . $actual['foto'];
                    if (is_file($rutaVieja)) {
                        @unlink($rutaVieja);
                    }
                }
            } elseif (!empty($datos['quitar_foto'])) {
                $fotoSql = ', foto_perfil = NULL';

                if (!empty($actual['foto'])) {
                    $rutaVieja = dirname(__DIR__, 2) . '/assets/img/usuarios/' . $actual['foto'];
                    if (is_file($rutaVieja)) {
                        @unlink($rutaVieja);
                    }
                }
            }

            $paramsUsuario = [
                ':username' => $datos['username'],
                ':email'    => $datos['email'],
                ':rol'      => (int) $datos['id_rol'],
                ':sucursal' => ((int) $datos['id_sucursal'] > 0) ? (int) $datos['id_sucursal'] : null,
                ':estado'   => $nuevoEstado,
                ':id'       => $id,
            ];

            $sqlUsuario = "UPDATE usuarios SET {$camposUsuario}";

            if ($cambiaPassword) {
                $sqlUsuario .= ', password_hash = :hash';
                $paramsUsuario[':hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
            }

            $sqlUsuario .= ' WHERE id_usuario = :id';

            $this->conn->prepare($sqlUsuario)->execute($paramsUsuario);

            // Si queda de baja, cerramos sus sesiones activas.
            if ($nuevoEstado === 0) {
                $this->conn->prepare('UPDATE tokens_sesion SET activo = 0 WHERE id_usuario = :id')
                    ->execute([':id' => $id]);
            }

            $this->conn->prepare(
                "UPDATE usuarios_datos
     SET nombres = :nombres,
         apellidos = :apellidos,
         cedula = :cedula,
         telefono_movil = :telefono{$fotoSql}
     WHERE id_usuario = :id"
            )->execute($paramsDatos);

            if (!$cambiaPassword && !$this->existeFila('usuarios_datos', 'id_usuario', $id)) {
                // Usuario antiguo sin fila en usuarios_datos
                $this->conn->prepare(
                    "INSERT INTO usuarios_datos
             (id_usuario, nombres, apellidos, cedula, telefono_movil, foto_perfil)
         VALUES (:id, :nombres, :apellidos, :cedula, :telefono, :foto)"
                )->execute([
                    ':id'        => $id,
                    ':nombres'   => $datos['nombres'],
                    ':apellidos' => $datos['apellidos'],
                    ':cedula'    => $datos['cedula']   !== '' ? $datos['cedula']   : null,
                    ':telefono'  => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                    ':foto'      => !empty($datos['foto']) ? $datos['foto'] : null,
                ]);
            }

            $this->conn->commit();

            return ['exito' => true, 'mensaje' => 'Usuario actualizado correctamente', 'id' => $id];
        } catch (PDOException $e) {
            $this->conn->rollBack();

            if ($e->getCode() === '23000') {
                if (stripos($e->getMessage(), 'email') !== false) {
                    return ['exito' => false, 'mensaje' => 'Ese correo ya está registrado', 'id' => 0];
                }
                if (stripos($e->getMessage(), 'username') !== false) {
                    return ['exito' => false, 'mensaje' => 'Ese nombre de usuario ya existe', 'id' => 0];
                }
                if (stripos($e->getMessage(), 'cedula') !== false) {
                    return ['exito' => false, 'mensaje' => 'Esa cédula ya está registrada', 'id' => 0];
                }
            }

            error_log('Usuario::actualizar - ' . $e->getMessage());

            return ['exito' => false, 'mensaje' => 'No se pudo actualizar el usuario', 'id' => 0];
        }
    }

    /**
     * Baja DEFINITIVA del usuario.
     * Sólo se permite si no tiene historial (ventas, pedidos, compras,
     * arqueos, kardex, cajas…). Si tiene historial, debe usarse
     * `cambiarEstado($id, 0, …)` para darlo de baja sin romper las
     * referencias de las tablas.
     *
     * @return array{exito: bool, mensaje: string, id: int}
     */
    public function eliminar(int $id, int $operadorId, int $nivelOperador): array
    {
        if ($id <= 0) {
            return ['exito' => false, 'mensaje' => 'Usuario no válido', 'id' => 0];
        }

        if ($id === $operadorId) {
            return ['exito' => false, 'mensaje' => 'No puedes eliminar tu propia cuenta', 'id' => 0];
        }

        $obj = $this->obtener($id);

        if (!$obj) {
            return ['exito' => false, 'mensaje' => 'Ese usuario ya no existe', 'id' => 0];
        }

        if ($this->esRolSuperAdmin($obj['id_rol']) && $nivelOperador !== 1) {
            return ['exito' => false, 'mensaje' => 'No puedes eliminar a un SuperAdministrador', 'id' => 0];
        }

        if (
            (int) $obj['estado'] === 1
            && $this->esRolSuperAdmin($obj['id_rol'])
            && $this->contarSuperAdminsActivos() <= 1
        ) {
            return ['exito' => false, 'mensaje' => 'No puedes eliminar al último SuperAdministrador activo', 'id' => 0];
        }

        // ---- Guardia de integridad: historial asociado ----
        $referencias = $this->referenciasUsuario($id);

        if ($referencias['total'] > 0) {
            $detalle = [];

            foreach ($referencias['detalle'] as $d) {
                $detalle[] = $d['etiqueta'] . ': ' . $d['total'];
            }

            return [
                'exito'   => false,
                'mensaje' => 'No se puede eliminar: el usuario tiene historial asociado ('
                    . implode(', ', $detalle)
                    . '). Usa "Dar de baja" para desactivarlo sin romper las tablas.',
                'id'      => 0,
            ];
        }

        try {
            $this->conn->beginTransaction();

            // Filas dependientes sin valor histórico: se eliminan con el usuario.
            $this->conn->prepare('DELETE FROM tokens_sesion WHERE id_usuario = :id')
                ->execute([':id' => $id]);
            $this->conn->prepare('DELETE FROM usuarios_permisos WHERE id_usuario = :id')
                ->execute([':id' => $id]);
            $this->conn->prepare('DELETE FROM usuarios_datos WHERE id_usuario = :id')
                ->execute([':id' => $id]);
            $this->conn->prepare('DELETE FROM usuarios WHERE id_usuario = :id')
                ->execute([':id' => $id]);

            $this->conn->commit();

            return ['exito' => true, 'mensaje' => 'Usuario eliminado definitivamente', 'id' => $id];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            error_log('Usuario::eliminar - ' . $e->getMessage());

            return [
                'exito'   => false,
                'mensaje' => 'No se pudo eliminar el usuario. Si tiene historial, usa "Dar de baja".',
                'id'      => 0,
            ];
        }
    }

    /**
     * Alta / baja lógica sin borrar el historial.
     *
     * @param int $estado 1 = activo, 0 = de baja
     */
    public function cambiarEstado(int $id, int $estado, int $operadorId, int $nivelOperador ): array
    {
        if ($id <= 0) {
            return ['exito' => false, 'mensaje' => 'Usuario no válido', 'id' => 0];
        }

        $estado = (int) $estado === 1 ? 1 : 0;

        $obj = $this->obtener($id);

        if (!$obj) {
            return ['exito' => false, 'mensaje' => 'Ese usuario ya no existe', 'id' => 0];
        }

        $error = $this->validarCambioEstado($obj, $estado, $operadorId, $nivelOperador);

        if ($error !== null) {
            return ['exito' => false, 'mensaje' => $error, 'id' => 0];
        }

        try {
            $this->conn->prepare(
                'UPDATE usuarios
                    SET estado = :estado,
                        bloqueado_hasta = NULL,
                        intentos_fallidos = 0
                  WHERE id_usuario = :id'
            )->execute([':estado' => $estado, ':id' => $id]);

            // Si se da de baja, cerramos sus sesiones activas.
            if ($estado === 0) {
                $this->conn->prepare('UPDATE tokens_sesion SET activo = 0 WHERE id_usuario = :id')
                    ->execute([':id' => $id]);
            }

            return [
                'exito'   => true,
                'mensaje' => $estado === 1
                    ? 'Usuario dado de alta correctamente'
                    : 'Usuario dado de baja correctamente',
                'id'      => $id,
            ];
        } catch (PDOException $e) {
            error_log('Usuario::cambiarEstado - ' . $e->getMessage());

            return ['exito' => false, 'mensaje' => 'No se pudo cambiar el estado del usuario', 'id' => 0];
        }
    }

    /**
     * Crea usuario + sus datos personales en una transacción.
     *
     * @param array $datos  modo, apellidos, email, username, password, rol, sucursal, ...
     * @return array{exito: bool, mensaje: string, id: int}
     */
    public function crear(array $datos): array
    {
        $datos = $this->sanear($datos);

        // ---------------------------------------------
        // 1) Validaciones básicas
        // ---------------------------------------------
        $faltantes = $this->faltantes($datos);
        if ($faltantes !== []) {
            return self::err('Faltan datos obligatorios: ' . implode(', ', $faltantes));
        }

        if (mb_strlen($datos['username']) > 50 || mb_strlen($datos['username']) < 3) {
            return self::err('El usuario debe tener entre 3 y 50 caracteres.');
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return self::err('El correo electrónico no tiene un formato válido.');
        }

        if (mb_strlen($datos['password']) < 8) {
            return self::err('La contraseña debe tener al menos 8 caracteres.');
        }

        if ($datos['password'] !== $datos['password_confirmacion']) {
            return self::err('La contraseña y su confirmación no coinciden.');
        }

        // ---------------------------------------------
        // 2) El rol debe existir
        // ---------------------------------------------
        $rol = $this->buscarPorId('roles', 'id_rol', $datos['id_rol']);
        if (!$rol) {
            return self::err('El rol seleccionado no existe.');
        }

        if ($this->esRolSuperAdmin($datos['id_rol']) && (int) ($datos['_usuario_logueado_rol'] ?? 0) !== 1) {
            return self::err('No tienes permiso para crear SuperAdministradores.');
        }

        // ---------------------------------------------
        // 3) Unicidad de usuario/correo/cédula
        // ---------------------------------------------
        $dup = $this->existeDuplicado($datos);
        if ($dup !== null) {
            return $dup;
        }

        // ---------------------------------------------
        // 4) Insertar (usuarios + usuarios_datos)
        // ---------------------------------------------
        $this->conn->beginTransaction();

        try {
            // 4.1) Tabla principal
            $nuevoEstado = (int) ($datos['estado'] ?? 1) === 0 ? 0 : 1;

            $sql = "INSERT INTO usuarios
                    (id_rol, id_sucursal, username, email, password_hash, intentos_fallidos, estado)
                VALUES (:rol, :sucursal, :username, :email, :hash, 0, :estado)";

            $this->conn->prepare($sql)->execute([
                ':rol'      => (int) $rol['id_rol'],
                ':sucursal' => ($datos['id_sucursal'] > 0) ? (int) $datos['id_sucursal'] : null,
                ':username' => $datos['username'],
                ':email'    => $datos['email'],
                ':hash'     => password_hash($datos['password'], PASSWORD_DEFAULT),
                ':estado'   => $nuevoEstado,
            ]);

            $nuevoId = (int) $this->conn->lastInsertId();   // ← AHORA sí existe

            // 4.2) Datos personales + foto (UNA sola vez)
            $sql = "INSERT INTO usuarios_datos
                    (id_usuario, nombres, apellidos, cedula, telefono_movil, foto_perfil)
                VALUES (:id, :nombres, :apellidos, :cedula, :telefono, :foto)";

            $this->conn->prepare($sql)->execute([
                ':id'        => $nuevoId,
                ':nombres'   => $datos['nombres'],
                ':apellidos' => $datos['apellidos'],
                ':cedula'    => $datos['cedula']   !== '' ? $datos['cedula']   : null,
                ':telefono'  => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                ':foto'      => !empty($datos['foto']) ? $datos['foto'] : null,
            ]);

            $this->conn->commit();

            return [
                'exito'   => true,
                'mensaje' => 'Usuario creado correctamente',
                'id'      => $nuevoId,
            ];
        } catch (PDOException $e) {
            $this->conn->rollBack();

            if ($e->getCode() === '23000') {
                if (stripos($e->getMessage(), 'email') !== false) {
                    return ['exito' => false, 'mensaje' => 'Ese correo ya está registrado', 'id' => 0];
                }
                if (stripos($e->getMessage(), 'username') !== false) {
                    return ['exito' => false, 'mensaje' => 'Ese nombre de usuario ya existe', 'id' => 0];
                }
                if (stripos($e->getMessage(), 'cedula') !== false) {
                    return ['exito' => false, 'mensaje' => 'Esa cédula ya está registrada', 'id' => 0];
                }
            }

            error_log('Usuario::crear - ' . $e->getMessage());

            return ['exito' => false, 'mensaje' => 'No se pudo crear el usuario', 'id' => 0];
        }
    }

    // =================================================
    //  PERMISOS (privilegios por usuario)
    // =================================================

    /**
     * Permisos guardados para un usuario.
     * @return array|null  lista de {perm, sub, item}  ·  null si falta la tabla
     */
    public function permisosDe(int $idUsuario): ?array
    {
        if ($idUsuario <= 0) {
            return null;
        }

        // Delega en el servicio central: devuelve también la ACCIÓN
        return Permisos::instancia($this->conn)->filasConcedidas($idUsuario);
    }

    /**
     * Reemplaza TODOS los permisos de un usuario (transacción).
     *
     * @param int    $idUsuario
     * @param array  $lista     [{perm, sub, item}, ...] ya calculada por la interfaz
     * @param array  $catalogo  usado sólo para VALIDAR que los ids existan
     * @return array{exito: bool, mensaje: string, id: int, guardados: int}
     */
    public function guardarPermisos(int $idUsuario, array $lista, array $catalogo): array
    {
        if ($idUsuario <= 0) {
            return ['exito' => false, 'mensaje' => 'Usuario no válido', 'id' => 0, 'guardados' => 0];
        }

        $ficha = $this->obtener($idUsuario);
        if (!$ficha) {
            return ['exito' => false, 'mensaje' => 'Ese usuario no existe', 'id' => 0, 'guardados' => 0];
        }

        if ($this->esRolSuperAdmin($ficha['id_rol'])) {
            return ['exito' => true, 'mensaje' => 'Los SuperAdministradores no necesitan permisos', 'id' => $idUsuario, 'guardados' => 0];
        }

        // ---------------------------------------------
        // Validar y EXPANDIR a nivel ítem:
        // usuarios_permisos guarda filas completas
        // (módulo + submenú + detalle, sin NULL),
        // así que un "todo el módulo" se convierte aquí
        // en un renglón por cada detalle que cuelga.
        // ---------------------------------------------
        $limpia = [];
        $visto  = [];
        $accionActual = 'ver';   // se actualiza con cada fila de la interfaz

        // Agrega el ítem con la acción vigente. Si la acción no es "ver",
        // también concede "ver" (no se puede operar algo que no se ve).
        $agregar = static function (int $p, int $s, int $i) use (&$limpia, &$visto, &$accionActual): void {
            $clave = $p . '-' . $s . '-' . $i . '-' . $accionActual;

            if (!isset($visto[$clave])) {
                $visto[$clave] = true;
                $limpia[] = ['perm' => $p, 'sub' => $s, 'item' => $i, 'accion' => $accionActual];
            }

            if ($accionActual !== 'ver') {
                $claveVer = $p . '-' . $s . '-' . $i . '-ver';

                if (!isset($visto[$claveVer])) {
                    $visto[$claveVer] = true;
                    $limpia[] = ['perm' => $p, 'sub' => $s, 'item' => $i, 'accion' => 'ver'];
                }
            }
        };

        foreach ($lista as $fila) {
            if (!is_array($fila)) {
                continue;   // se ignoran ruidos inesperados
            }

            $accionActual = strtolower(trim((string) ($fila['accion'] ?? 'ver')));

            if (!in_array($accionActual, Permisos::ACCIONES, true)) {
                $accionActual = 'ver';
            }

            $perm = (int) ($fila['perm'] ?? 0);
            $sub  = isset($fila['sub']) && $fila['sub'] !== null ? (int) $fila['sub'] : null;
            $item = isset($fila['item']) && $fila['item'] !== null ? (int) $fila['item'] : null;

            if (!isset($catalogo[$perm])) {
                return ['exito' => false, 'mensaje' => 'Permiso de módulo inexistente', 'id' => $idUsuario, 'guardados' => 0];
            }

            if ($sub === null && $item !== null) {
                return ['exito' => false, 'mensaje' => 'Permiso mal formado (item sin submenú)', 'id' => $idUsuario, 'guardados' => 0];
            }

            $subsCat = $catalogo[$perm]['submenus'];

            // Todo el módulo
            if ($sub === null) {
                $algunoM = false;

                foreach ($subsCat as $sid => $bloque) {
                    foreach ($bloque['items'] as $iid => $nombre) {
                        $agregar($perm, (int) $sid, (int) $iid);
                        $algunoM = true;
                    }
                }

                if (!$algunoM) {
                    return ['exito' => false, 'mensaje' => 'Ese módulo no tiene ítems para conceder', 'id' => $idUsuario, 'guardados' => 0];
                }

                continue;
            }

            if (!isset($subsCat[$sub])) {
                return ['exito' => false, 'mensaje' => 'Permiso de submenú inexistente', 'id' => $idUsuario, 'guardados' => 0];
            }

            // Todo el submenú
            if ($item === null) {
                $algunoS = false;

                foreach ($subsCat[$sub]['items'] as $iid => $nombre) {
                    $agregar($perm, $sub, (int) $iid);
                    $algunoS = true;
                }

                if (!$algunoS) {
                    return ['exito' => false, 'mensaje' => 'Ese submenú no tiene ítems para conceder', 'id' => $idUsuario, 'guardados' => 0];
                }

                continue;
            }

            if (!isset($subsCat[$sub]['items'][$item])) {
                return ['exito' => false, 'mensaje' => 'Permiso de detalle inexistente', 'id' => $idUsuario, 'guardados' => 0];
            }

            $agregar($perm, $sub, $item);
        }

        try {
            $this->conn->beginTransaction();

            $borrar = $this->conn->prepare('DELETE FROM usuarios_permisos WHERE id_usuario = :id');
            $borrar->execute([':id' => $idUsuario]);

            // El esquema nuevo guarda una fila por (usuario, ítem, ACCIÓN).
            // Con esquemas antiguos (sin columna accion) se guarda sólo "ver".
            $conAccion = Permisos::instancia($this->conn)->columnaAccion();

            $sqlInsertar = $conAccion
                ? 'INSERT INTO usuarios_permisos
                       (id_usuario, id_permiso, id_sub_permiso, id_detalle_permiso, accion, permitir, estado)
                   VALUES (:u, :p, :s, :i, :a, 1, 1)'
                : 'INSERT INTO usuarios_permisos
                       (id_usuario, id_permiso, id_sub_permiso, id_detalle_permiso, permitir, estado)
                   VALUES (:u, :p, :s, :i, 1, 1)';

            $insertar = $this->conn->prepare($sqlInsertar);

            foreach ($limpia as $fila) {
                $params = [
                    ':u' => $idUsuario,
                    ':p' => $fila['perm'],
                    ':s' => $fila['sub'],
                    ':i' => $fila['item'],
                ];

                if ($conAccion) {
                    $params[':a'] = $fila['accion'];
                }

                $insertar->execute($params);
            }

            $this->conn->commit();

            Permisos::instancia($this->conn)->limpiarCache($idUsuario);

            return [
                'exito'    => true,
                'mensaje'  => 'Permisos actualizados (' . count($limpia) . ' reglas)',
                'id'       => $idUsuario,
                'guardados' => count($limpia),
            ];
        } catch (PDOException $e) {
            if (
                in_array((string) (($e->errorInfo[0] ?? '') ?: ''), ['42S02', 'HY000'], true)
                || stripos($e->getMessage(), "doesn't exist") !== false
                || stripos($e->getMessage(), 'no existe') !== false
            ) {
                $this->conn->rollBack();
                error_log('Usuario::guardarPermisos - falta la tabla usuarios_permisos');

                return ['exito' => false, 'mensaje' => 'Falta la tabla usuarios_permisos en la base de datos', 'id' => 0, 'guardados' => 0];
            }

            $this->conn->rollBack();
            error_log('Usuario::guardarPermisos - ' . $e->getMessage());

            return ['exito' => false, 'mensaje' => 'No se pudieron guardar los permisos', 'id' => 0, 'guardados' => 0];
        }
    }

    // =================================================
    //  AUXILIARES PRIVADOS
    // =================================================

    /** Misma respuesta en todos los fallos internos */
    private static function err(string $mensaje): array
    {
        return ['exito' => false, 'mensaje' => $mensaje, 'id' => 0];
    }

    private function sanear(array $d): array
    {
        $limpia = static fn(string $v): string => trim(strip_tags($v));

        return [
            'id'                  => (int) ($d['id'] ?? 0),
            'nombres'             => $limpia((string) ($d['nombres'] ?? '')),
            'apellidos'           => $limpia((string) ($d['apellidos'] ?? '')),
            'email'               => mb_strtolower($limpia((string) ($d['email'] ?? ''))),
            'username'            => $limpia((string) ($d['username'] ?? '')),
            'password'            => (string) ($d['password'] ?? ''),
            'password_confirmacion' => (string) ($d['password_confirmacion'] ?? ($d['password'] ?? '')),
            'id_rol'              => $d['id_rol'] ?? null,
            'id_sucursal'         => $d['id_sucursal'] ?? 0,
            'cedula'              => $limpia((string) ($d['cedula'] ?? '')),
            'telefono'            => $limpia((string) ($d['telefono'] ?? '')),
            'estado'              => (int) ($d['estado'] ?? 1) === 0 ? 0 : 1,
            '_usuario_logueado_rol' => (int) ($d['_usuario_logueado_rol'] ?? 0),
            '_usuario_logueado_id'  => (int) ($d['_usuario_logueado_id'] ?? 0),
            'foto'        => isset($d['foto']) && $d['foto'] !== '' ? (string) $d['foto'] : null,
            'quitar_foto' => (int) ($d['quitar_foto'] ?? 0),
        ];
    }

    private function faltantes(array $d): array
    {
        $campos = [
            'nombres'  => 'Nombres',
            'apellidos' => 'Apellidos',
            'email'    => 'Correo',
            'username' => 'Usuario',
            'password' => 'Contraseña',
            'id_rol'   => 'Rol',
        ];

        $falta = [];

        foreach ($campos as $clave => $etiqueta) {
            if ($d[$clave] === '' || $d[$clave] === null) {
                $falta[] = $etiqueta;
            }
        }

        return $falta;
    }

    /**
     * Devuelve mensaje de duplicado si lo hay, o null si está libre.
     * @param int|null $excluirId  id propio que no debe contar (ediciones)
     */
    private function existeDuplicado(array $d, ?int $excluirId = null): ?array
    {
        $condicion = '';
        $params = [];

        if ($excluirId !== null && $excluirId > 0) {
            $condicion = ' AND id_usuario != :excluir';
            $params[':excluir'] = $excluirId;
        }

        $sql = "SELECT username, email, (SELECT cedula FROM usuarios_datos WHERE cedula = :cedula) AS cedula
                FROM usuarios
                WHERE (username = :username OR email = :email) {$condicion}
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_merge([
            ':username' => $d['username'],
            ':email'    => $d['email'],
            ':cedula'   => $d['cedula'],
        ], $params));
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($fila)) {
            return null;
        }

        if (($fila['username'] ?? '') === $d['username']) {
            return ['exito' => false, 'mensaje' => 'Ese nombre de usuario ya existe', 'id' => 0];
        }

        if (($fila['email'] ?? '') === $d['email']) {
            return ['exito' => false, 'mensaje' => 'Ese correo ya está registrado', 'id' => 0];
        }

        return ['exito' => false, 'mensaje' => 'Esa cédula ya está registrada', 'id' => 0];
    }

    /** ¿Existe una fila con columna = valor? (tabla/columna siempre validadas) */
    private function existeFila(string $tabla, string $columna, $valor): bool
    {
        if (!preg_match('/^[a-zA-Z_]+$/', $tabla) || !preg_match('/^[a-zA-Z_]+$/', $columna)) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT 1 FROM {$tabla} WHERE {$columna} = :v LIMIT 1");
        $stmt->execute([':v' => $valor]);

        return (bool) $stmt->fetchColumn();
    }

    /** ¿El rol es SuperAdministrador? (por NOMBRE, nunca por nivel_permiso) */
    private function esRolSuperAdmin($idRol): bool
    {
        if ((int) $idRol <= 0) {
            return false;
        }

        $rol = $this->buscarPorId('roles', 'id_rol', $idRol);

        return $rol !== null
            && strcasecmp(trim((string) $rol['nombre_rol']), 'SuperAdministrador') === 0;
    }

    private function buscarPorId(string $tabla, string $columna, $valor): ?array
    {
        if (!preg_match('/^[a-zA-Z_]+$/', $tabla) || !preg_match('/^[a-zA-Z_]+$/', $columna)) {
            return null;
        }

        $stmt = $this->conn->prepare("SELECT * FROM {$tabla} WHERE {$columna} = :v LIMIT 1");
        $stmt->execute([':v' => $valor]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Valida si se puede pasar al estado indicado.
     * Devuelve null si está permitido o el mensaje de error.
     */
    private function validarCambioEstado(array $actual, int $nuevoEstado, int $operadorId, int $nivelOperador): ?string
    {
        if ($nuevoEstado === (int) $actual['estado']) {
            return null;
        }

        if ($nuevoEstado === 1) {
            return null; // dar de alta siempre está permitido
        }

        if ($operadorId > 0 && (int) $actual['id'] === $operadorId) {
            return 'No puedes dar de baja tu propia cuenta';
        }

        if ($this->esRolSuperAdmin($actual['id_rol']) && $nivelOperador !== 1) {
            return 'No puedes dar de baja a un SuperAdministrador';
        }

        if ($this->esRolSuperAdmin($actual['id_rol']) && $this->contarSuperAdminsActivos() <= 1) {
            return 'No puedes dar de baja al último SuperAdministrador activo';
        }

        return null;
    }

    /** Cantidad de SuperAdministradores con estado activo. */
    private function contarSuperAdminsActivos(): int
    {
        try {
            $sql = "SELECT COUNT(*)
                      FROM usuarios u
                      INNER JOIN roles r ON r.id_rol = u.id_rol
                     WHERE u.estado = 1
                       AND LOWER(TRIM(r.nombre_rol)) = 'superadministrador'";

            return (int) $this->conn->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            error_log('Usuario::contarSuperAdminsActivos - ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Cuenta las referencias históricas del usuario en las tablas que
     * lo usan. Si hay historial NO se puede borrar físicamente.
     *
     * @return array{total:int, detalle:array<int,array{tabla:string,etiqueta:string,total:int}>}
     */
    private function referenciasUsuario(int $id): array
    {
        $checks = [
            'ventas'             => ['etiqueta' => 'ventas', 'columnas' => ['id_usuario']],
            'pedidos'            => ['etiqueta' => 'pedidos', 'columnas' => ['id_usuario']],
            'compras'            => ['etiqueta' => 'compras', 'columnas' => ['id_usuario']],
            'compras_pagos'      => ['etiqueta' => 'pagos de compras', 'columnas' => ['id_usuario']],
            'arqueos_caja'       => ['etiqueta' => 'arqueos de caja', 'columnas' => ['id_usuario_apertura', 'id_usuario_cierre']],
            'arqueo_movimientos' => ['etiqueta' => 'movimientos de caja', 'columnas' => ['id_usuario']],
            'kardex'             => ['etiqueta' => 'movimientos de kardex', 'columnas' => ['id_usuario']],
            'cajas_asignaciones' => ['etiqueta' => 'asignaciones de caja', 'columnas' => ['id_usuario', 'id_asignado_por']],
            'cajas'              => ['etiqueta' => 'cajas asignadas', 'columnas' => ['id_usuario_asignado']],
        ];

        $resultado = ['total' => 0, 'detalle' => []];

        foreach ($checks as $tabla => $info) {
            $columnas = $info['columnas'];

            // Tabla/columnas son constantes internas (no vienen del usuario).
            $where = implode(' OR ', array_map(
                static fn(string $c): string => "`{$c}` = ?",
                $columnas
            ));

            try {
                $stmt = $this->conn->prepare(
                    "SELECT COUNT(*) FROM `{$tabla}` WHERE {$where}"
                );
                $stmt->execute(array_fill(0, count($columnas), $id));
                $total = (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Si una tabla no existe en esta instalación, se ignora.
                $total = 0;
            }

            if ($total > 0) {
                $resultado['total'] += $total;
                $resultado['detalle'][] = [
                    'tabla'    => $tabla,
                    'etiqueta' => $info['etiqueta'],
                    'total'    => $total,
                ];
            }
        }

        return $resultado;
    }

    private function normalizar(array $fila): array
    {
        $fecha = $fila['ultimo_login'] ?? '';

        return [
            'id'         => (int) $fila['id_usuario'],
            'nombre'     => trim($fila['nombre_completo']),
            'username'   => (string) $fila['username'],
            'email'      => (string) $fila['email'],
            'cedula'     => (string) $fila['cedula'],
            'telefono'   => (string) $fila['telefono'],
            'rol'        => (string) $fila['nombre_rol'],
            'sucursal'   => (string) ($fila['nombre_sucursal'] ?? '—'),
            'estado'     => (int) $fila['estado'],
            'foto'       => (string) ($fila['foto_perfil'] ?? ''),   // sólo el nombre del archivo
            'ultimo_login' => $fecha !== '' ? date('d/m/Y H:i', strtotime($fecha)) : 'Nunca',
        ];
    }
}
