<?php
/**
 * =====================================================
 * CONFIGURACIÓN  (sistema/configuracion.php)
 * -----------------------------------------------------
 * Formulario único para editar `configuracion_empresa`.
 * Incluye todos los campos (fiscales, contacto, ubicación,
 * logos, facturación, redes y preferencias).
 *
 * Datos  : ../modelos/configuracion/obtener_configuracion.php
 * API    : ../modelos/configuracion/configuracion_api.php
 * Red    : ../modelos/configuracion/configuracion_api.js
 * UI     : ../modelos/configuracion/configuracion.js
 * =====================================================
 */

require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Configuración';

include 'validacionSesion.php';
include 'validacion_permisos.php';

// Acceso: privilegio VER sobre el ítem Configuración de la empresa (config/permisos.php)
rm_exigir_permiso('ver', Permisos::ITEM_CONFIG_EMPRESA);
$rmAcciones = rm_acciones(Permisos::ITEM_CONFIG_EMPRESA);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <script>window.RM_ACCIONES = <?php echo json_encode($rmAcciones); ?>;</script>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .cfg-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 12px;
        }

        .cfg-seccion {
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(16, 12, 14, .04);
        }

        .cfg-cabecera {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            background: linear-gradient(180deg, #fff, #fdfafa);
            border-bottom: 1px solid #f1e7e8;
            border-left: 4px solid var(--mod-primaria, #d9232d);
        }

        .cfg-cabecera i {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: var(--mod-primaria-suave, #ffe9ea);
            color: var(--mod-primaria, #d9232d);
            font-size: 14px;
        }

        .cfg-cabecera h3 {
            margin: 0;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--mod-tinta, #1c1416);
        }

        /* Tarjeta de identidad de la empresa */
        .cfg-identidad {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
            background:
                radial-gradient(700px 120px at 100% -40%, #fff1f2, transparent 60%),
                linear-gradient(135deg, #ffffff, #fcfcfd);
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(16, 12, 14, .04);
        }

        .cfg-id-logo {
            width: 58px;
            height: 58px;
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            overflow: hidden;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: 12px;
            background: #f4f4f6;
            color: #c7cdd8;
            font-size: 23px;
        }

        .cfg-id-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #fff;
            display: block;
        }

        .cfg-id-info {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .cfg-id-et {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .cfg-id-info b {
            font-size: 18px;
            line-height: 1.2;
            color: var(--mod-tinta, #1c1416);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cfg-id-meta {
            font-size: 12px;
            color: var(--mod-texto, #6b6165);
        }

        .cfg-id-estado {
            margin-left: auto;
            flex: 0 0 auto;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
            background: var(--mod-verde-fondo, #e5f7ec);
            color: var(--mod-verde, #14803d);
        }

        .cfg-id-estado.inactivo {
            background: #f1f1f4;
            color: var(--mod-texto, #6b6165);
        }

        @media (max-width: 560px) {
            .cfg-identidad {
                flex-wrap: wrap;
            }

            .cfg-id-estado {
                margin-left: 0;
            }

            .cfg-id-info b {
                font-size: 15px;
            }
        }

        .cfg-cuerpo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
            padding: 12px 14px 14px;
        }

        .cfg-cuerpo.ancho-1 {
            grid-template-columns: 1fr;
        }

        .cfg-cuerpo.ancho-2 .cfg-campo.ancho-2 {
            grid-column: span 2;
        }

        @media (max-width: 620px) {
            .cfg-cuerpo.ancho-2 .cfg-campo.ancho-2 {
                grid-column: span 1;
            }
        }

        .cfg-campo {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .cfg-campo label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: var(--mod-texto-tenue, #a79ea2);
            margin: 0;
        }

        .cfg-campo input,
        .cfg-campo select,
        .cfg-campo textarea {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            border: 1.5px solid var(--mod-linea-fuerte, #d9d9e0);
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
            font-size: 12.5px;
            color: var(--mod-tinta, #1c1416);
        }

        .cfg-campo textarea {
            height: auto;
            padding: 8px 10px;
            min-height: 60px;
            resize: vertical;
        }

        .cfg-campo input:focus,
        .cfg-campo select:focus,
        .cfg-campo textarea:focus {
            outline: none;
            border-color: var(--mod-primaria, #d9232d);
            box-shadow: 0 0 0 3px var(--mod-primaria-suave, #ffe9ea);
        }

        .cfg-campo input:disabled {
            background: #f4f4f6;
            color: var(--mod-texto-tenue);
            cursor: not-allowed;
        }

        .cfg-ayuda {
            font-size: 10.5px;
            color: var(--mod-texto-tenue, #a79ea2);
        }

        .cfg-ayuda.error {
            color: var(--mod-primaria);
            font-weight: 700;
        }

        /* Logos */
        .cfg-logo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cfg-logo-preview {
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #f4f4f6;
            border: 2px dashed #d9d9e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #c7cdd8;
            font-size: 26px;
        }

        .cfg-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #fff;
        }

        .cfg-logo-acciones {
            display: flex;
            gap: 6px;
        }

        .cfg-logo-acciones button {
            flex: 1 1 auto;
            height: 30px;
            border-radius: 7px;
            border: 1px solid var(--mod-linea-fuerte, #d9d9e0);
            background: #fff;
            font-family: inherit;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--mod-texto, #6b6165);
            cursor: pointer;
        }

        .cfg-logo-acciones button:hover {
            border-color: var(--mod-primaria);
            color: var(--mod-primaria);
        }

        /* Barra de guardado fija al pie */
        .cfg-barra {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 14px;
            background: #fff;
            border: 1px solid var(--mod-linea, #ececef);
            border-radius: var(--mod-radio-sm, 9px);
            position: sticky;
            bottom: 10px;
            z-index: 5;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, .04);
        }

        .cfg-barra .cb-nota {
            font-size: 12px;
            color: var(--mod-texto-tenue);
            margin-right: auto;
        }
    </style>

    <script defer src="../modelos/configuracion/configuracion_api.js"></script>
    <script defer src="../modelos/configuracion/configuracion.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-gear" aria-hidden="true"></i>
                    Configuración de la empresa
                </h3>
            </div>

            <div class="card-body">

                <div id="cfgCarga" class="tabla-carga visible" role="status" aria-live="polite">
                    <span class="mini-spin" aria-hidden="true"></span>
                    <span>Cargando configuración...</span>
                </div>

                <form id="formConfiguracion" novalidate autocomplete="off" style="display:none;">
                    <input type="hidden" id="cfg_id" value="" />

                    <!-- Identidad de la empresa -->
                    <div class="cfg-identidad">
                        <div class="cfg-id-logo" id="cfgIdLogo">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="cfg-id-info">
                            <span class="cfg-id-et">Empresa</span>
                            <b id="cfgIdNombre">—</b>
                            <span class="cfg-id-meta" id="cfgIdMeta">NIT: —</span>
                        </div>
                        <span class="cfg-id-estado" id="cfgIdEstado">Activo</span>
                    </div>

                    <div class="cfg-layout">

                        <!-- ============================================
                             DATOS GENERALES
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-building"></i>
                                <h3>Datos generales</h3>
                            </div>
                            <div class="cfg-cuerpo ancho-2">
                                <div class="cfg-campo" data-campo="razon_social">
                                    <label for="cfg_razon_social">Razón social *</label>
                                    <input type="text" id="cfg_razon_social" maxlength="200" required />
                                    <span class="cfg-ayuda">Nombre legal de la empresa</span>
                                </div>

                                <div class="cfg-campo" data-campo="nombre_comercial">
                                    <label for="cfg_nombre_comercial">Nombre comercial</label>
                                    <input type="text" id="cfg_nombre_comercial" maxlength="200" />
                                    <span class="cfg-ayuda">Como aparece en la carta</span>
                                </div>

                                <div class="cfg-campo" data-campo="nit">
                                    <label for="cfg_nit">NIT</label>
                                    <input type="text" id="cfg_nit" maxlength="20" />
                                </div>

                                <div class="cfg-campo" data-campo="regimen">
                                    <label for="cfg_regimen">Régimen</label>
                                    <input type="text" id="cfg_regimen" maxlength="50" placeholder="General, simplificado…" />
                                </div>

                                <div class="cfg-campo ancho-2" data-campo="actividad_economica">
                                    <label for="cfg_actividad_economica">Actividad económica</label>
                                    <input type="text" id="cfg_actividad_economica" maxlength="100" />
                                </div>

                                <div class="cfg-campo" data-campo="estado">
                                    <label for="cfg_estado">Estado</label>
                                    <select id="cfg_estado">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <!-- ============================================
                             CONTACTO
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-address-book"></i>
                                <h3>Contacto</h3>
                            </div>
                            <div class="cfg-cuerpo ancho-2">
                                <div class="cfg-campo" data-campo="telefono1">
                                    <label for="cfg_telefono1">Teléfono 1</label>
                                    <input type="tel" id="cfg_telefono1" maxlength="20" />
                                </div>

                                <div class="cfg-campo" data-campo="telefono2">
                                    <label for="cfg_telefono2">Teléfono 2</label>
                                    <input type="tel" id="cfg_telefono2" maxlength="20" />
                                </div>

                                <div class="cfg-campo" data-campo="email_principal">
                                    <label for="cfg_email_principal">Correo principal</label>
                                    <input type="email" id="cfg_email_principal" maxlength="100" />
                                </div>

                                <div class="cfg-campo" data-campo="email_secundario">
                                    <label for="cfg_email_secundario">Correo secundario</label>
                                    <input type="email" id="cfg_email_secundario" maxlength="100" />
                                </div>

                                <div class="cfg-campo ancho-2" data-campo="sitio_web">
                                    <label for="cfg_sitio_web">Sitio web</label>
                                    <input type="url" id="cfg_sitio_web" maxlength="150" placeholder="https://…" />
                                </div>
                            </div>
                        </section>

                        <!-- ============================================
                             UBICACIÓN
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-location-dot"></i>
                                <h3>Ubicación</h3>
                            </div>
                            <div class="cfg-cuerpo ancho-2">
                                <div class="cfg-campo" data-campo="pais">
                                    <label for="cfg_pais">País</label>
                                    <input type="text" id="cfg_pais" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="departamento">
                                    <label for="cfg_departamento">Departamento</label>
                                    <input type="text" id="cfg_departamento" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="provincia">
                                    <label for="cfg_provincia">Provincia</label>
                                    <input type="text" id="cfg_provincia" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="ciudad">
                                    <label for="cfg_ciudad">Ciudad</label>
                                    <input type="text" id="cfg_ciudad" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="zona">
                                    <label for="cfg_zona">Zona / Barrio</label>
                                    <input type="text" id="cfg_zona" maxlength="100" />
                                </div>

                                <div class="cfg-campo" data-campo="codigo_postal">
                                    <label for="cfg_codigo_postal">Código postal</label>
                                    <input type="text" id="cfg_codigo_postal" maxlength="20" />
                                </div>

                                <div class="cfg-campo ancho-2" data-campo="direccion">
                                    <label for="cfg_direccion">Dirección</label>
                                    <input type="text" id="cfg_direccion" maxlength="255" />
                                </div>
                            </div>
                        </section>

                        <!-- ============================================
                             LOGOS E IMÁGENES
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-image"></i>
                                <h3>Logos e imágenes</h3>
                            </div>
                            <div class="cfg-cuerpo">
                                <?php foreach ([
                                    ['logo',        'Logo principal',         'image/png,image/jpeg,image/webp,image/svg+xml'],
                                    ['logo_favicon','Favicon',                'image/png,image/x-icon,image/svg+xml'],
                                    ['logo_blanco', 'Logo blanco (para PDFs)', 'image/png,image/jpeg,image/webp,image/svg+xml'],
                                    ['banner',      'Banner',                  'image/png,image/jpeg,image/webp'],
                                ] as $lg): ?>
                                    <div class="cfg-logo" data-campo="<?php echo $lg[0]; ?>">
                                        <label><?php echo $lg[1]; ?></label>
                                        <div class="cfg-logo-preview" id="prev_<?php echo $lg[0]; ?>">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                        <div class="cfg-logo-acciones">
                                            <button type="button" data-logo-pick="<?php echo $lg[0]; ?>">
                                                <i class="fa-solid fa-upload"></i> Subir
                                            </button>
                                            <button type="button" data-logo-clear="<?php echo $lg[0]; ?>">
                                                <i class="fa-solid fa-xmark"></i> Quitar
                                            </button>
                                        </div>
                                        <input type="file" id="file_<?php echo $lg[0]; ?>" accept="<?php echo $lg[2]; ?>" hidden />
                                        <input type="hidden" id="cfg_<?php echo $lg[0]; ?>" value="" />
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- ============================================
                             FACTURACIÓN
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-file-invoice"></i>
                                <h3>Facturación</h3>
                            </div>
                            <div class="cfg-cuerpo ancho-2">
                                <div class="cfg-campo" data-campo="autorizacion_factura">
                                    <label for="cfg_autorizacion_factura">Autorización factura</label>
                                    <input type="text" id="cfg_autorizacion_factura" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="numero_autorizacion">
                                    <label for="cfg_numero_autorizacion">N° autorización</label>
                                    <input type="text" id="cfg_numero_autorizacion" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="fecha_autorizacion">
                                    <label for="cfg_fecha_autorizacion">Fecha autorización</label>
                                    <input type="date" id="cfg_fecha_autorizacion" />
                                </div>

                                <div class="cfg-campo" data-campo="llave_dosificacion">
                                    <label for="cfg_llave_dosificacion">Llave dosificación</label>
                                    <input type="text" id="cfg_llave_dosificacion" maxlength="255" />
                                </div>

                                <div class="cfg-campo" data-campo="numero_sucursal">
                                    <label for="cfg_numero_sucursal">N° sucursal</label>
                                    <input type="text" id="cfg_numero_sucursal" maxlength="10" />
                                </div>

                                <div class="cfg-campo" data-campo="codigo_actividad">
                                    <label for="cfg_codigo_actividad">Código actividad</label>
                                    <input type="text" id="cfg_codigo_actividad" maxlength="20" />
                                </div>

                                <div class="cfg-campo" data-campo="matricula_comercio">
                                    <label for="cfg_matricula_comercio">Matrícula de comercio</label>
                                    <input type="text" id="cfg_matricula_comercio" maxlength="50" />
                                </div>

                                <div class="cfg-campo" data-campo="certificado_digital">
                                    <label for="cfg_certificado_digital">Certificado digital</label>
                                    <input type="text" id="cfg_certificado_digital" maxlength="255" />
                                </div>
                            </div>
                        </section>

                        <!-- ============================================
                             REDES SOCIALES
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-share-nodes"></i>
                                <h3>Redes sociales</h3>
                            </div>
                            <div class="cfg-cuerpo ancho-2">
                                <?php foreach ([
                                    ['facebook',  'Facebook',  'fa-facebook',  'https://facebook.com/…',  150],
                                    ['instagram', 'Instagram', 'fa-instagram', 'https://instagram.com/…', 150],
                                    ['twitter',   'Twitter / X', 'fa-twitter', 'https://twitter.com/…',   150],
                                    ['youtube',   'YouTube',   'fa-youtube',   'https://youtube.com/…',   150],
                                    ['linkedin',  'LinkedIn',  'fa-linkedin',  'https://linkedin.com/…',  150],
                                    ['tiktok',    'TikTok',    'fa-tiktok',    'https://tiktok.com/…',    150],
                                    ['whatsapp',  'WhatsApp',  'fa-whatsapp',  '+591 …',                   20],
                                ] as $rs): ?>
                                    <div class="cfg-campo" data-campo="<?php echo $rs[0]; ?>">
                                        <label for="cfg_<?php echo $rs[0]; ?>">
                                            <i class="fa-brands <?php echo $rs[2]; ?>" style="color:#d9232d"></i>
                                            <?php echo $rs[1]; ?>
                                        </label>
                                        <input type="text" id="cfg_<?php echo $rs[0]; ?>" maxlength="<?php echo (int) $rs[4]; ?>" placeholder="<?php echo $rs[3]; ?>" />
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- ============================================
                             PREFERENCIAS
                             ============================================ -->
                        <section class="cfg-seccion">
                            <div class="cfg-cabecera">
                                <i class="fa-solid fa-sliders"></i>
                                <h3>Moneda y formato</h3>
                            </div>
                            <div class="cfg-cuerpo ancho-2">
                                <div class="cfg-campo" data-campo="moneda_principal">
                                    <label for="cfg_moneda_principal">Moneda principal</label>
                                    <select id="cfg_moneda_principal">
                                        <option value="BOB">BOB · Boliviano</option>
                                        <option value="USD">USD · Dólar</option>
                                        <option value="ARS">ARS · Peso argentino</option>
                                        <option value="CLP">CLP · Peso chileno</option>
                                        <option value="PEN">PEN · Sol</option>
                                        <option value="COP">COP · Peso colombiano</option>
                                    </select>
                                </div>

                                <div class="cfg-campo" data-campo="zona_horaria">
                                    <label for="cfg_zona_horaria">Zona horaria</label>
                                    <input type="text" id="cfg_zona_horaria" maxlength="50" list="cfg_zonas_horarias" placeholder="America/La_Paz" />
                                    <datalist id="cfg_zonas_horarias">
                                        <option value="America/La_Paz"></option>
                                        <option value="America/Argentina/Buenos_Aires"></option>
                                        <option value="America/Asuncion"></option>
                                        <option value="America/Sao_Paulo"></option>
                                        <option value="America/Montevideo"></option>
                                        <option value="America/Lima"></option>
                                        <option value="America/Bogota"></option>
                                        <option value="America/Caracas"></option>
                                        <option value="America/Panama"></option>
                                        <option value="America/Mexico_City"></option>
                                        <option value="America/New_York"></option>
                                        <option value="America/Los_Angeles"></option>
                                        <option value="Europe/Madrid"></option>
                                        <option value="UTC"></option>
                                    </datalist>
                                    <span class="cfg-ayuda">Se aplica a PHP y a la base de datos</span>
                                </div>

                                <div class="cfg-campo" data-campo="formato_fecha">
                                    <label for="cfg_formato_fecha">Formato de fecha</label>
                                    <select id="cfg_formato_fecha">
                                        <option value="d/m/Y">dd/mm/aaaa</option>
                                        <option value="d-m-Y">dd-mm-aaaa</option>
                                        <option value="Y-m-d">aaaa-mm-dd</option>
                                    </select>
                                </div>

                                <div class="cfg-campo" data-campo="formato_hora">
                                    <label for="cfg_formato_hora">Formato de hora</label>
                                    <select id="cfg_formato_hora">
                                        <option value="H:i">24 horas (14:30)</option>
                                        <option value="h:i A">12 horas (02:30 PM)</option>
                                    </select>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="cfg-barra">
                        <span class="cb-nota" id="cfgNota">Los cambios se guardan todos juntos.</span>
                        <button type="button" class="btn-mod btn-mod-claro" id="cfgReset">
                            <i class="fa-solid fa-rotate-left"></i> Descartar cambios
                        </button>
                        <?php if ($rmAcciones['modificar']): ?>
                            <button type="submit" class="btn-mod btn-mod-primario" id="cfgGuardar">
                                <i class="fa-solid fa-floppy-disk"></i> <span>Guardar configuración</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </section>
    </main>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>