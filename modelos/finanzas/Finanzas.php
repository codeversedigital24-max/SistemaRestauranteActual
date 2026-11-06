<?php

/**
 * =====================================================
 * MODELO: Finanzas  (modelos/finanzas/Finanzas.php)
 * -----------------------------------------------------
 * Acceso a monedas, medios_pago y bancos.
 * Todas las operaciones son CRUD + baja lógica + borrado
 * físico protegido.
 * =====================================================
 */

class Finanzas
{
    private PDO $conn;

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    // =====================================================
    // MONEDAS
    // =====================================================

    public function monedas(): array
    {
        try {
            $filas = $this->conn->query(
                "SELECT * FROM monedas ORDER BY es_base DESC, estado DESC, codigo ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Finanzas::monedas - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($m) => [
            'id'           => (int) $m['id_moneda'],
            'codigo'       => (string) $m['codigo'],
            'nombre'       => (string) $m['nombre'],
            'simbolo'      => (string) $m['simbolo'],
            'decimales'    => (int) $m['decimales'],
            'tasa_cambio'  => (float) $m['tasa_cambio'],
            'es_base'      => (int) $m['es_base'],
            'estado'       => (int) $m['estado'],
        ], $filas);
    }

    public function monedaGuardar(array $d): array
    {
        $id       = (int) ($d['id'] ?? 0);
        $codigo   = mb_strtoupper(trim(strip_tags($d['codigo'] ?? '')));
        $nombre   = trim(strip_tags($d['nombre'] ?? ''));
        $simbolo  = trim(strip_tags($d['simbolo'] ?? ''));
        $decimal  = max(0, min(6, (int) ($d['decimales'] ?? 2)));
        $tasa     = (float) ($d['tasa_cambio'] ?? 1);
        $es_base  = !empty($d['es_base']) ? 1 : 0;
        $estado   = (int) ($d['estado'] ?? 1) === 0 ? 0 : 1;

        if ($codigo === '' || $nombre === '' || $simbolo === '') {
            return self::err('Código, nombre y símbolo son obligatorios');
        }
        if (!preg_match('/^[A-Z0-9]{2,8}$/', $codigo)) {
            return self::err('El código debe tener 2-8 caracteres alfanuméricos');
        }
        if ($tasa <= 0) {
            return self::err('La tasa de cambio debe ser mayor que cero');
        }

        try {
            $this->conn->beginTransaction();

            // Sólo una moneda base
            if ($es_base === 1) {
                $this->conn->exec("UPDATE monedas SET es_base = 0" . ($id > 0 ? " WHERE id_moneda != {$id}" : ""));
            }

            if ($id > 0) {
                $stmt = $this->conn->prepare(
                    "UPDATE monedas
                        SET codigo = :c, nombre = :n, simbolo = :s, decimales = :d,
                            tasa_cambio = :t, es_base = :b, estado = :e
                      WHERE id_moneda = :id"
                );
                $stmt->execute([
                    ':c' => $codigo,
                    ':n' => $nombre,
                    ':s' => $simbolo,
                    ':d' => $decimal,
                    ':t' => $tasa,
                    ':b' => $es_base,
                    ':e' => $estado,
                    ':id' => $id,
                ]);
                $nuevoId = $id;
            } else {
                $stmt = $this->conn->prepare(
                    "INSERT INTO monedas (codigo, nombre, simbolo, decimales, tasa_cambio, es_base, estado)
                     VALUES (:c, :n, :s, :d, :t, :b, :e)"
                );
                $stmt->execute([
                    ':c' => $codigo,
                    ':n' => $nombre,
                    ':s' => $simbolo,
                    ':d' => $decimal,
                    ':t' => $tasa,
                    ':b' => $es_base,
                    ':e' => $estado,
                ]);
                $nuevoId = (int) $this->conn->lastInsertId();
            }

            $this->conn->commit();
            return ['exito' => true, 'mensaje' => $id > 0 ? 'Moneda actualizada' : 'Moneda creada', 'id' => $nuevoId];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            if ($e->getCode() === '23000') return self::err('Ese código de moneda ya existe');
            error_log('Finanzas::monedaGuardar - ' . $e->getMessage());
            return self::err('No se pudo guardar la moneda');
        }
    }

    public function monedaEliminar(int $id, bool $definitivo = false): array
    {
        if ($id <= 0) return self::err('Moneda no válida');

        try {
            // Si es base, no se puede eliminar
            $st = $this->conn->prepare("SELECT es_base FROM monedas WHERE id_moneda = :id LIMIT 1");
            $st->execute([':id' => $id]);
            $esBase = (int) $st->fetchColumn();

            if ($esBase === 1) return self::err('No puedes eliminar la moneda base');

            // ¿Está referenciada por bancos?
            $st = $this->conn->prepare("SELECT COUNT(*) FROM bancos WHERE id_moneda = :id");
            $st->execute([':id' => $id]);
            $enUso = (int) $st->fetchColumn();

            if ($definitivo && $enUso > 0) {
                return self::err('Esta moneda está asignada a ' . $enUso . ' banco(s). Desactívala en lugar de borrarla.');
            }

            if ($definitivo) {
                $this->conn->prepare("DELETE FROM monedas WHERE id_moneda = :id")->execute([':id' => $id]);
                return ['exito' => true, 'mensaje' => 'Moneda eliminada definitivamente', 'id' => $id];
            }

            $this->conn->prepare("UPDATE monedas SET estado = 0 WHERE id_moneda = :id")->execute([':id' => $id]);
            return ['exito' => true, 'mensaje' => 'Moneda desactivada', 'id' => $id];
        } catch (PDOException $e) {
            error_log('Finanzas::monedaEliminar - ' . $e->getMessage());
            return self::err('No se pudo eliminar la moneda');
        }
    }

    // =====================================================
    // MEDIOS DE PAGO
    // =====================================================

    public function medios(): array
    {
        try {
            $filas = $this->conn->query(
                "SELECT * FROM medios_pago ORDER BY estado DESC, orden ASC, nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Finanzas::medios - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($m) => [
            'id'                  => (int) $m['id_medio'],
            'codigo'              => (string) $m['codigo'],
            'nombre'              => (string) $m['nombre'],
            'icono'               => (string) ($m['icono'] ?: 'fa-money-bill'),
            'afecta_caja'         => (int) $m['afecta_caja'],
            'requiere_referencia' => (int) $m['requiere_referencia'],
            'orden'               => (int) $m['orden'],
            'estado'              => (int) $m['estado'],
        ], $filas);
    }

    public function medioGuardar(array $d): array
    {
        $id      = (int) ($d['id'] ?? 0);
        $codigo  = mb_strtolower(preg_replace('/\s+/', '', trim($d['codigo'] ?? '')));
        $nombre  = trim(strip_tags($d['nombre'] ?? ''));
        $icono   = trim(strip_tags($d['icono'] ?? 'fa-money-bill'));
        $afecta  = !empty($d['afecta_caja']) ? 1 : 0;
        $ref     = !empty($d['requiere_referencia']) ? 1 : 0;
        $orden   = (int) ($d['orden'] ?? 0);
        $estado  = (int) ($d['estado'] ?? 1) === 0 ? 0 : 1;

        if ($codigo === '' || $nombre === '') {
            return self::err('Código y nombre son obligatorios');
        }
        if (!preg_match('/^[a-z0-9_]{2,30}$/', $codigo)) {
            return self::err('El código debe tener 2-30 caracteres (a-z, 0-9, _)');
        }

        try {
            if ($id > 0) {
                $this->conn->prepare(
                    "UPDATE medios_pago
                        SET codigo = :c, nombre = :n, icono = :i, afecta_caja = :a,
                            requiere_referencia = :r, orden = :o, estado = :e
                      WHERE id_medio = :id"
                )->execute([
                    ':c' => $codigo,
                    ':n' => $nombre,
                    ':i' => $icono,
                    ':a' => $afecta,
                    ':r' => $ref,
                    ':o' => $orden,
                    ':e' => $estado,
                    ':id' => $id,
                ]);
                return ['exito' => true, 'mensaje' => 'Medio de pago actualizado', 'id' => $id];
            }

            $this->conn->prepare(
                "INSERT INTO medios_pago (codigo, nombre, icono, afecta_caja, requiere_referencia, orden, estado)
                 VALUES (:c, :n, :i, :a, :r, :o, :e)"
            )->execute([
                ':c' => $codigo,
                ':n' => $nombre,
                ':i' => $icono,
                ':a' => $afecta,
                ':r' => $ref,
                ':o' => $orden,
                ':e' => $estado,
            ]);

            return ['exito' => true, 'mensaje' => 'Medio de pago creado', 'id' => (int) $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return self::err('Ese código de medio ya existe');
            error_log('Finanzas::medioGuardar - ' . $e->getMessage());
            return self::err('No se pudo guardar el medio de pago');
        }
    }

    public function medioEliminar(int $id, bool $definitivo = false): array
    {
        if ($id <= 0) return self::err('Medio no válido');

        try {
            // ¿Existe alguna venta con este método?
            $st = $this->conn->prepare("SELECT codigo FROM medios_pago WHERE id_medio = :id LIMIT 1");
            $st->execute([':id' => $id]);
            $codigo = $st->fetchColumn();

            if (!$codigo) return self::err('Ese medio ya no existe');

            if ($definitivo) {
                $st = $this->conn->prepare("SELECT COUNT(*) FROM ventas WHERE metodo_pago = :c");
                $st->execute([':c' => $codigo]);
                if ((int) $st->fetchColumn() > 0) {
                    return self::err('Este medio tiene ventas registradas. Desactívalo en lugar de borrarlo.');
                }

                $this->conn->prepare("DELETE FROM medios_pago WHERE id_medio = :id")->execute([':id' => $id]);
                return ['exito' => true, 'mensaje' => 'Medio eliminado definitivamente', 'id' => $id];
            }

            $this->conn->prepare("UPDATE medios_pago SET estado = 0 WHERE id_medio = :id")->execute([':id' => $id]);
            return ['exito' => true, 'mensaje' => 'Medio desactivado', 'id' => $id];
        } catch (PDOException $e) {
            error_log('Finanzas::medioEliminar - ' . $e->getMessage());
            return self::err('No se pudo eliminar el medio');
        }
    }

    // =====================================================
    // BANCOS
    // =====================================================

    public function bancos(): array
    {
        try {
            $filas = $this->conn->query(
                "SELECT b.*, m.codigo AS moneda_codigo, m.simbolo AS moneda_simbolo
                   FROM bancos b
                   LEFT JOIN monedas m ON m.id_moneda = b.id_moneda
                  ORDER BY b.estado DESC, b.nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Finanzas::bancos - ' . $e->getMessage());
            return [];
        }

        return array_map(fn($b) => [
            'id'             => (int) $b['id_banco'],
            'nombre'         => (string) $b['nombre'],
            'codigo'         => (string) ($b['codigo'] ?? ''),
            'nro_cuenta'     => (string) $b['nro_cuenta'],
            'titular'        => (string) ($b['titular'] ?? ''),
            'id_moneda'      => $b['id_moneda'] ? (int) $b['id_moneda'] : 0,
            'moneda_codigo'  => (string) ($b['moneda_codigo'] ?? ''),
            'moneda_simbolo' => (string) ($b['moneda_simbolo'] ?? ''),
            'observaciones'  => (string) ($b['observaciones'] ?? ''),
            'estado'         => (int) $b['estado'],
        ], $filas);
    }

    public function bancoGuardar(array $d): array
    {
        $id       = (int) ($d['id'] ?? 0);
        $nombre   = trim(strip_tags($d['nombre'] ?? ''));
        $codigo   = trim(strip_tags($d['codigo'] ?? ''));
        $nro      = trim(strip_tags($d['nro_cuenta'] ?? ''));
        $titular  = trim(strip_tags($d['titular'] ?? ''));
        $moneda   = (int) ($d['id_moneda'] ?? 0);
        $obs      = trim(strip_tags($d['observaciones'] ?? ''));
        $estado   = (int) ($d['estado'] ?? 1) === 0 ? 0 : 1;

        if ($nombre === '') return self::err('El nombre del banco es obligatorio');
        if ($nro === '')    return self::err('El número de cuenta es obligatorio');
        if ($moneda > 0) {
            $st = $this->conn->prepare("SELECT 1 FROM monedas WHERE id_moneda = :id LIMIT 1");
            $st->execute([':id' => $moneda]);
            if (!$st->fetchColumn()) return self::err('La moneda seleccionada no existe');
        }

        try {
            if ($id > 0) {
                $this->conn->prepare(
                    "UPDATE bancos
                        SET nombre = :n, codigo = :c, nro_cuenta = :nc, titular = :t,
                            id_moneda = :m, observaciones = :o, estado = :e
                      WHERE id_banco = :id"
                )->execute([
                    ':n' => $nombre,
                    ':c' => $codigo !== '' ? $codigo : null,
                    ':nc' => $nro,
                    ':t' => $titular !== '' ? $titular : null,
                    ':m' => $moneda > 0 ? $moneda : null,
                    ':o' => $obs !== '' ? $obs : null,
                    ':e' => $estado,
                    ':id' => $id,
                ]);
                return ['exito' => true, 'mensaje' => 'Banco actualizado', 'id' => $id];
            }

            $this->conn->prepare(
                "INSERT INTO bancos (nombre, codigo, nro_cuenta, titular, id_moneda, observaciones, estado)
                 VALUES (:n, :c, :nc, :t, :m, :o, :e)"
            )->execute([
                ':n' => $nombre,
                ':c' => $codigo !== '' ? $codigo : null,
                ':nc' => $nro,
                ':t' => $titular !== '' ? $titular : null,
                ':m' => $moneda > 0 ? $moneda : null,
                ':o' => $obs !== '' ? $obs : null,
                ':e' => $estado,
            ]);

            return ['exito' => true, 'mensaje' => 'Banco creado', 'id' => (int) $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            error_log('Finanzas::bancoGuardar - ' . $e->getMessage());
            return self::err('No se pudo guardar el banco');
        }
    }

    public function bancoEliminar(int $id, bool $definitivo = false): array
    {
        if ($id <= 0) return self::err('Banco no válido');

        try {
            if ($definitivo) {
                $this->conn->prepare("DELETE FROM bancos WHERE id_banco = :id")->execute([':id' => $id]);
                return ['exito' => true, 'mensaje' => 'Banco eliminado definitivamente', 'id' => $id];
            }

            $this->conn->prepare("UPDATE bancos SET estado = 0 WHERE id_banco = :id")->execute([':id' => $id]);
            return ['exito' => true, 'mensaje' => 'Banco desactivado', 'id' => $id];
        } catch (PDOException $e) {
            error_log('Finanzas::bancoEliminar - ' . $e->getMessage());
            return self::err('No se pudo eliminar el banco');
        }
    }

    // =====================================================
    // AUXILIAR
    // =====================================================
    private static function err(string $m): array
    {
        return ['exito' => false, 'mensaje' => $m, 'id' => 0];
    }
}
