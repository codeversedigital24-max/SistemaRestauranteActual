<?php
/**
 * =====================================================
 * GET  modelos/configuracion/obtener_configuracion.php
 * -----------------------------------------------------
 * Devuelve la fila única de configuracion_empresa.
 * Si no existe, devuelve null y defaults.
 * =====================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');

rm_iniciar_sesion();

$responder = static function (int $c, bool $ok, string $m, array $d = []): void {
    http_response_code($c);
    echo json_encode(
        ['success'=>$ok,'message'=>$m,'data'=>$d,'timestamp'=>date('c')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
};

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    $responder(401, false, 'No autorizado');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    $responder(405, false, 'Método no permitido');
}

try {
    $pdo = Conexion::getInstance()->getConnection();
    require_once __DIR__ . '/../compras/ContextoCompras.php';
    require_once __DIR__ . '/Empresa.php';

    $usuario = ContextoCompras::usuario($pdo, (int) $_SESSION['id_usuario']);
    if (!$usuario) $responder(401, false, 'La sesión ya no es válida');

    rm_exigir_api('ver', Permisos::ITEM_CONFIG_EMPRESA);

    $modelo = new Empresa($pdo);
    $fila   = $modelo->obtener();

    // Normalizamos para el frontend
    $config = $fila ?: [
        'id_config'             => 0,
        'razon_social'          => '',
        'nombre_comercial'      => '',
        'nit'                   => '',
        'regimen'               => '',
        'actividad_economica'   => '',
        'telefono1'             => '',
        'telefono2'             => '',
        'email_principal'       => '',
        'email_secundario'      => '',
        'sitio_web'             => '',
        'pais'                  => 'Bolivia',
        'departamento'          => '',
        'provincia'             => '',
        'ciudad'                => '',
        'zona'                  => '',
        'direccion'             => '',
        'codigo_postal'         => '',
        'logo'                  => '',
        'logo_favicon'          => '',
        'logo_blanco'           => '',
        'banner'                => '',
        'autorizacion_factura'  => '',
        'numero_autorizacion'   => '',
        'fecha_autorizacion'    => '',
        'llave_dosificacion'    => '',
        'numero_sucursal'       => '',
        'codigo_actividad'      => '',
        'matricula_comercio'    => '',
        'certificado_digital'   => '',
        'facebook'              => '',
        'instagram'             => '',
        'twitter'               => '',
        'youtube'               => '',
        'linkedin'              => '',
        'tiktok'                => '',
        'whatsapp'              => '',
        'moneda_principal'      => 'BOB',
        'zona_horaria'          => 'America/La_Paz',
        'formato_fecha'         => 'd/m/Y',
        'formato_hora'          => 'H:i',
        'estado'                => 1,
    ];

    // URLs públicas para los logos
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
    foreach (['logo', 'logo_favicon', 'logo_blanco', 'banner'] as $k) {
        $archivo = trim((string) ($config[$k] ?? ''));
        $config[$k . '_url'] = $archivo !== ''
            ? ($baseUrl !== '' ? $baseUrl . '/' . ltrim($archivo, '/') : '/' . ltrim($archivo, '/'))
            : '';
    }

    $responder(200, true, 'ok', [
        'configuracion' => $config,
        'es_nueva'      => $fila === null,
    ]);
} catch (PDOException $e) {
    error_log('obtener_configuracion.php PDO: ' . $e->getMessage());
    $responder(503, false, 'Base de datos no disponible');
} catch (Throwable $e) {
    error_log('obtener_configuracion.php: ' . $e->getMessage());
    $responder(500, false, 'Error interno del servidor');
}