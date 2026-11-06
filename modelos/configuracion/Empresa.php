<?php
/**
 * =====================================================
 * MODELO: Empresa  (modelos/configuracion/Empresa.php)
 * -----------------------------------------------------
 * Única puerta a la tabla configuracion_empresa.
 * La fila es única (id_config = 1). Si no existe, se crea.
 * =====================================================
 */

class Empresa
{
    private PDO $conn;

    /** Campos escalares editables (los logos van aparte por upload). */
    private const CAMPOS = [
        'razon_social', 'nombre_comercial', 'nit', 'regimen', 'actividad_economica',
        'telefono1', 'telefono2', 'email_principal', 'email_secundario', 'sitio_web',
        'pais', 'departamento', 'provincia', 'ciudad', 'zona', 'direccion', 'codigo_postal',
        'logo', 'logo_favicon', 'logo_blanco', 'banner',
        'autorizacion_factura', 'numero_autorizacion', 'fecha_autorizacion',
        'llave_dosificacion', 'numero_sucursal', 'codigo_actividad',
        'matricula_comercio', 'certificado_digital',
        'facebook', 'instagram', 'twitter', 'youtube', 'linkedin', 'tiktok', 'whatsapp',
        'moneda_principal', 'zona_horaria',
        'formato_fecha', 'formato_hora',
        'estado',
    ];

    public function __construct(?PDO $conexion = null)
    {
        $this->conn = $conexion ?? Conexion::getInstance()->getConnection();
    }

    /** Devuelve la fila única o null si no existe. */
    public function obtener(): ?array
    {
        try {
            $fila = $this->conn->query(
                "SELECT * FROM configuracion_empresa ORDER BY id_config ASC LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Empresa::obtener - ' . $e->getMessage());
            return null;
        }

        return $fila ?: null;
    }

    /** Guarda (crea si no existe) la fila única. */
    public function guardar(array $datos): array
    {
        $d = $this->sanear($datos);

        $error = $this->validar($d);
        if ($error !== '') {
            return self::err($error);
        }

        $actual = $this->obtener();

        try {
            if ($actual === null) {
                $columnas = implode(', ', self::CAMPOS);
                $marcas   = ':' . implode(', :', self::CAMPOS);

                $sql = "INSERT INTO configuracion_empresa ({$columnas})
                        VALUES ({$marcas})";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute($this->parametros($d));

                return [
                    'exito'   => true,
                    'mensaje' => 'Configuración creada correctamente',
                    'id'      => (int) $this->conn->lastInsertId(),
                ];
            }

            $sets = implode(', ', array_map(fn($c) => "{$c} = :{$c}", self::CAMPOS));

            $sql = "UPDATE configuracion_empresa
                       SET {$sets}, updated_at = NOW()
                     WHERE id_config = :id";

            $params = $this->parametros($d);
            $params[':id'] = (int) $actual['id_config'];

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return [
                'exito'   => true,
                'mensaje' => 'Configuración actualizada correctamente',
                'id'      => (int) $actual['id_config'],
            ];
        } catch (PDOException $e) {
            error_log('Empresa::guardar - ' . $e->getMessage());
            return self::err('No se pudo guardar la configuración');
        }
    }

    /** Guarda la ruta de un archivo (logo/favicon/banner) tras subirlo. */
    public function guardarArchivo(string $campo, string $ruta): array
    {
        if (!in_array($campo, ['logo', 'logo_favicon', 'logo_blanco', 'banner'], true)) {
            return self::err('Campo de imagen no válido');
        }

        $actual = $this->obtener();

        try {
            if ($actual === null) {
                // Si aún no hay fila, la creamos con lo mínimo
                $this->conn->prepare(
                    "INSERT INTO configuracion_empresa (razon_social, {$campo}, estado)
                     VALUES ('Sin configurar', :ruta, 1)"
                )->execute([':ruta' => $ruta]);

                return ['exito' => true, 'mensaje' => 'Imagen guardada', 'ruta' => $ruta];
            }

            // Borrar archivo anterior si existía
            $anterior = trim((string) ($actual[$campo] ?? ''));
            if ($anterior !== '') {
                $abs = dirname(__DIR__, 2) . '/' . ltrim($anterior, '/');
                if (is_file($abs)) @unlink($abs);
            }

            $this->conn->prepare(
                "UPDATE configuracion_empresa SET {$campo} = :ruta, updated_at = NOW()
                 WHERE id_config = :id"
            )->execute([':ruta' => $ruta, ':id' => (int) $actual['id_config']]);

            return ['exito' => true, 'mensaje' => 'Imagen guardada', 'ruta' => $ruta];
        } catch (PDOException $e) {
            error_log('Empresa::guardarArchivo - ' . $e->getMessage());
            return self::err('No se pudo guardar la imagen');
        }
    }

    // --- auxiliares ---

    private static function err(string $m): array
    {
        return ['exito' => false, 'mensaje' => $m, 'id' => 0];
    }

    private function sanear(array $d): array
    {
        $limpio = static fn(string $v): string => trim(strip_tags($v));

        $r = [];
        foreach (self::CAMPOS as $c) {
            $valor = $d[$c] ?? '';

            // Enteros
            if ($c === 'estado') {
                $r[$c] = ($valor === '' || $valor === null)
                    ? 1
                    : ((int) $valor === 0 ? 0 : 1);
                continue;
            }

            $r[$c] = $limpio((string) $valor);
        }

        // Reglas concretas
        $r['email_principal']  = mb_strtolower($r['email_principal']);
        $r['email_secundario'] = mb_strtolower($r['email_secundario']);
        $r['nit']              = mb_strtoupper($r['nit']);
        $r['moneda_principal'] = $r['moneda_principal'] !== '' ? mb_strtoupper($r['moneda_principal']) : 'BOB';
        $r['zona_horaria']     = $r['zona_horaria'] !== '' ? $r['zona_horaria'] : 'America/La_Paz';

        // nombre_comercial es NOT NULL en la tabla
        if ($r['nombre_comercial'] === '') {
            $r['nombre_comercial'] = $r['razon_social'];
        }

        // Fecha
        $r['fecha_autorizacion'] = $r['fecha_autorizacion'] !== ''
            ? date('Y-m-d', strtotime($r['fecha_autorizacion']))
            : '';

        return $r;
    }

    private function validar(array $d): string
    {
        if (mb_strlen($d['razon_social']) < 3) {
            return 'La razón social debe tener al menos 3 caracteres';
        }
        if (mb_strlen($d['razon_social']) > 200) {
            return 'La razón social no puede superar los 200 caracteres';
        }
        if ($d['nit'] !== '' && mb_strlen($d['nit']) > 20) {
            return 'El NIT no puede superar los 20 caracteres';
        }
        if (mb_strlen($d['nombre_comercial']) > 200) {
            return 'El nombre comercial no puede superar los 200 caracteres';
        }
        if ($d['email_principal'] !== '' && !filter_var($d['email_principal'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo principal no es válido';
        }
        if ($d['email_secundario'] !== '' && !filter_var($d['email_secundario'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo secundario no es válido';
        }
        if (
            $d['sitio_web'] !== ''
            && !filter_var($d['sitio_web'], FILTER_VALIDATE_URL)
            && !preg_match('~^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(?:[/:?\#].*)?$~i', $d['sitio_web'])
        ) {
            return 'El sitio web no es válido';
        }
        if (!in_array($d['moneda_principal'], ['BOB','USD','ARS','CLP','PEN','COP'], true)) {
            return 'La moneda principal no es válida';
        }
        try {
            new DateTimeZone($d['zona_horaria']);
        } catch (Throwable $e) {
            return 'La zona horaria no es válida (ejemplo: America/La_Paz)';
        }
        if ($d['estado'] !== 0 && $d['estado'] !== 1) {
            return 'El estado debe ser Activo o Inactivo';
        }

        return '';
    }

    private function parametros(array $d): array
    {
        $params = [];
        foreach (self::CAMPOS as $c) {
            $v = $d[$c] ?? null;

            // Nulos para textos vacíos (excepto campos NOT NULL / opcionales que aceptan '')
            if ($v === '' && !in_array($c, ['nit', 'logo', 'logo_favicon', 'logo_blanco', 'banner'], true)) {
                $v = null;
            }

            $params[':' . $c] = $v;
        }
        return $params;
    }
}