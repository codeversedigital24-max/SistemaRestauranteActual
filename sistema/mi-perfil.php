<?php
require_once '../config/config.php';
rm_iniciar_sesion();

$tituloPagina = 'Mi Perfil';

include 'validacionSesion.php';

// Cualquier usuario logueado puede entrar
$idPropio = (int) $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'cabecera/cabecera.php'; ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(rm_csrf_token()); ?>" />

    <style>
        .swal2-container {
            z-index: 20000 !important;
        }

        /* ============================================================
           LAYOUT GENERAL
           ============================================================ */
        .perf-layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: 10px;
            align-items: stretch;
        }

        @media (max-width: 860px) {
            .perf-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================================
           TARJETA LATERAL
           ============================================================ */
        .perf-side {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .perf-side .cabecera {
            padding: 16px 14px 14px;
            background:
                radial-gradient(420px 100px at 100% 0%, rgba(217, 35, 45, .25), transparent 70%),
                linear-gradient(135deg, #241a1d 0%, #171112 100%);
            color: #fff;
            text-align: center;
        }

        .perf-avatar {
            position: relative;
            width: 92px;
            height: 92px;
            margin: 0 auto 8px;
            border-radius: 50%;
            overflow: hidden;
            background: #f4f0f0;
            border: 3px solid rgba(255, 255, 255, .15);
            display: grid;
            place-items: center;
            color: #c7cdd8;
            font-size: 34px;
        }

        .perf-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .perf-avatar .btn-foto {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 2px solid #171112;
            background: var(--mod-primaria);
            color: #fff;
            font-size: 11px;
            cursor: pointer;
            display: grid;
            place-items: center;
            padding: 0;
        }

        .perf-side .nombre {
            font-size: 14px;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
        }

        .perf-side .rol {
            font-size: 11px;
            opacity: .7;
            margin-top: 2px;
        }

        .perf-side .sucursal {
            font-size: 10.5px;
            opacity: .55;
            margin-top: 2px;
        }

        .perf-side .cuerpo {
            padding: 10px 14px 12px;
            display: flex;
            flex-direction: column;
            font-size: 12px;
            color: #6b6165;
            flex: 1 1 auto;
            justify-content: flex-start;
        }

        .perf-side .cuerpo .dato {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            padding: 7px 0;
            border-bottom: 1px dashed #f0eaeb;
            line-height: 1.25;
        }

        .perf-side .cuerpo .dato:last-child {
            border-bottom: none;
        }

        .perf-side .cuerpo .dato span {
            color: #a79ea2;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .3px;
            font-weight: 700;
        }

        .perf-side .cuerpo .dato b {
            color: #1c1416;
            font-size: 12px;
            text-align: right;
            max-width: 60%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ============================================================
           FORMULARIO
           ============================================================ */
        .perf-form {
            background: #fff;
            border: 1px solid #ececef;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .perf-form .head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid #ececef;
            background: linear-gradient(180deg, #fff, #fcfafa);
        }

        .perf-form .head h2 {
            margin: 0;
            font-size: 13.5px;
            color: #1c1416;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .perf-form .head i {
            color: var(--mod-primaria);
            font-size: 14px;
        }

        /* Grid del formulario: 2 columnas fijas en desktop */
        .perf-form .cuerpo {
            padding: 12px 16px 14px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 12px;
        }

        @media (max-width: 640px) {
            .perf-form .cuerpo {
                grid-template-columns: 1fr;
            }
        }

        .perf-form .seccion {
            grid-column: 1 / -1;
            margin: 8px 0 0;
            padding-top: 8px;
            border-top: 1px dashed #ececef;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
            text-transform: uppercase;
            color: var(--mod-primaria);
        }

        .perf-form .campo {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .perf-form .campo.ancho-2 {
            grid-column: span 2;
        }

        @media (max-width: 640px) {
            .perf-form .campo.ancho-2 {
                grid-column: auto;
            }
        }

        /* Input más bajo */
        .perf-form label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
            color: #a79ea2;
            margin: 0;
        }

        .perf-form input,
        .perf-form select {
            height: 34px;
            width: 100%;
            padding: 0 10px;
            border: 1.5px solid #e4e0e1;
            border-radius: 8px;
            background: #fcfbfb;
            font-family: inherit;
            font-size: 12.5px;
            color: #1c1416;
            outline: none;
            transition: .14s ease;
        }

        .perf-form input:focus,
        .perf-form select:focus {
            border-color: #efb4b8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(217, 35, 45, .07);
        }

        .perf-form input:disabled {
            background: #f5f3f3;
            color: #a79ea2;
            cursor: not-allowed;
        }

        .perf-form .ayuda {
            font-size: 10px;
            color: #b8b0b3;
            line-height: 1.2;
        }

        /* Botones */
        .perf-form .acciones {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 6px;
            padding-top: 10px;
            border-top: 1px dashed #ececef;
        }

        .perf-form .acciones .btn-mod {
            height: 36px;
            padding: 0 16px;
            font-size: 12.5px;
        }

        /* Ojo password */
        .campo-ojo {
            position: relative;
        }

        .campo-ojo .ojo {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            border: 0;
            background: transparent;
            color: #a79ea2;
            cursor: pointer;
            font-size: 12px;
            border-radius: 6px;
            padding: 0;
            display: grid;
            place-items: center;
        }

        .campo-ojo .ojo:hover {
            background: #f2eeee;
        }

        /* ============================================================
           MODAL FOTO
           ============================================================ */
        #modalFoto .modal-caja {
            width: min(380px, 100%);
        }

        .foto-preview-modal {
            width: 160px;
            height: 160px;
            margin: 0 auto 10px;
            border-radius: 50%;
            overflow: hidden;
            background: #f4f0f0;
            border: 3px dashed #d9d9e0;
            display: grid;
            place-items: center;
            color: #c7cdd8;
            font-size: 52px;
        }

        .foto-preview-modal img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .foto-preview-modal.con-foto {
            border-style: solid;
            border-color: var(--mod-primaria);
        }

        .foto-acciones {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .foto-acciones button {
            flex: 1 1 auto;
            height: 34px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .foto-acciones .btn-claro {
            background: #fff;
            border: 1px solid #ececef;
            color: #6b6165;
        }

        .foto-acciones .btn-peligro {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }
    </style>

    <script defer src="../modelos/perfil/perfil_api.js"></script>
    <script defer src="../modelos/perfil/perfil.js"></script>
</head>

<body>
    <?php include 'menu/menu.php'; ?>

    <main class="main-content">
        <section class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-circle"></i> Mi Perfil</h3>
            </div>

            <div class="card-body">

                <div class="tabla-carga visible" id="perfCarga">
                    <span class="mini-spin"></span> <span>Cargando tu perfil…</span>
                </div>

                <div class="perf-layout" id="perfContenido" style="display:none;">

                    <!-- Tarjeta izquierda -->
                    <aside class="perf-side">
                        <div class="cabecera">
                            <div class="perf-avatar" id="perfAvatar">
                                <i class="fas fa-user"></i>
                                <button type="button" class="btn-foto" id="perfAbrirFoto" title="Cambiar foto">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <h2 class="nombre" id="perfNombre">—</h2>
                            <div class="rol" id="perfRol">—</div>
                            <div class="sucursal" id="perfSucursal">—</div>
                        </div>
                        <div class="cuerpo">
                            <div class="dato"><span>Usuario</span><b id="perfUsername">—</b></div>
                            <div class="dato"><span>Correo</span><b id="perfEmail">—</b></div>
                            <div class="dato"><span>Último login</span><b id="perfUltimoLogin">—</b></div>
                        </div>
                    </aside>

                    <!-- Formulario derecho -->
                    <section class="perf-form">
                        <div class="head">
                            <i class="fas fa-id-card"></i>
                            <h2>Datos personales</h2>
                        </div>

                        <form id="formPerfil" novalidate autocomplete="off">
                            <div class="cuerpo">

                                <div class="campo">
                                    <label for="p_nombres">Nombres *</label>
                                    <input type="text" id="p_nombres" name="nombres" maxlength="100" required />
                                </div>
                                <div class="campo">
                                    <label for="p_apellidos">Apellidos *</label>
                                    <input type="text" id="p_apellidos" name="apellidos" maxlength="100" required />
                                </div>

                                <div class="campo">
                                    <label for="p_cedula">Cédula</label>
                                    <input type="text" id="p_cedula" name="cedula" maxlength="20" inputmode="numeric" />
                                </div>
                                <div class="campo">
                                    <label for="p_fecha_nacimiento">Fecha de nacimiento</label>
                                    <input type="date" id="p_fecha_nacimiento" name="fecha_nacimiento" />
                                </div>

                                <div class="campo">
                                    <label for="p_genero">Género</label>
                                    <select id="p_genero" name="genero">
                                        <option value="">— Sin especificar —</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Femenino</option>
                                        <option value="O">Otro</option>
                                    </select>
                                </div>
                                <div class="campo">
                                    <label for="p_telefono">Teléfono móvil</label>
                                    <input type="tel" id="p_telefono" name="telefono" maxlength="20" inputmode="tel" />
                                </div>

                                <div class="campo">
                                    <label for="p_telefono_fijo">Teléfono fijo</label>
                                    <input type="tel" id="p_telefono_fijo" name="telefono_fijo" maxlength="20" inputmode="tel" />
                                </div>
                                <div class="campo">
                                    <label for="p_direccion">Dirección</label>
                                    <input type="text" id="p_direccion" name="direccion" maxlength="255" />
                                </div>

                                <div class="seccion">Acceso y seguridad</div>

                                <div class="campo">
                                    <label for="p_username">Usuario</label>
                                    <input type="text" id="p_username" name="username" maxlength="50" minlength="3" autocapitalize="none" required />
                                    <span class="ayuda">3-50 caracteres, sin espacios</span>
                                </div>
                                <div class="campo">
                                    <label for="p_email">Correo</label>
                                    <input type="email" id="p_email" name="email" maxlength="100" autocapitalize="none" required />
                                </div>

                                <div class="seccion">Cambiar contraseña (opcional)</div>

                                <div class="campo">
                                    <label for="p_password_actual">Contraseña actual</label>
                                    <div class="campo-ojo">
                                        <input type="password" id="p_password_actual" name="password_actual" autocomplete="current-password" maxlength="72" />
                                        <button type="button" class="ojo" data-ojo="p_password_actual" aria-label="Mostrar"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <span class="ayuda">Sólo si vas a cambiar la contraseña</span>
                                </div>
                                <div class="campo"></div>

                                <div class="campo">
                                    <label for="p_password">Contraseña nueva</label>
                                    <div class="campo-ojo">
                                        <input type="password" id="p_password" name="password" minlength="8" maxlength="72" autocomplete="new-password" />
                                        <button type="button" class="ojo" data-ojo="p_password" aria-label="Mostrar"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <span class="ayuda">Mínimo 8 caracteres</span>
                                </div>
                                <div class="campo">
                                    <label for="p_password2">Confirmar contraseña</label>
                                    <div class="campo-ojo">
                                        <input type="password" id="p_password2" name="password2" minlength="8" maxlength="72" autocomplete="new-password" />
                                        <button type="button" class="ojo" data-ojo="p_password2" aria-label="Mostrar"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <span class="ayuda">Debe coincidir con la nueva</span>
                                </div>

                                <div class="acciones">
                                    <button type="button" class="btn-mod btn-mod-claro" id="perfReset">Descartar cambios</button>
                                    <button type="submit" class="btn-mod btn-mod-primario" id="perfGuardar">
                                        <i class="fas fa-floppy-disk"></i> Guardar cambios
                                    </button>
                                </div>

                            </div>
                        </form>
                    </section>

                </div>
            </div>
        </section>
    </main>

    <!-- Modal de foto -->
    <div class="modal-overlay" id="modalFoto" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-caja">
            <div class="modal-cabecera">
                <h2>
                    <span class="ico-titulo"><i class="fa-solid fa-camera"></i></span>
                    <span style="color:#fff">Foto de perfil</span>
                </h2>
                <button type="button" class="modal-cerrar" id="modalFotoCerrar">&times;</button>
            </div>
            <div class="modal-cuerpo" style="padding:16px;">
                <div class="foto-preview-modal" id="fotoPreviewModal">
                    <i class="fas fa-user"></i>
                </div>
                <div class="foto-acciones">
                    <button type="button" class="btn-claro" id="fotoElegir">
                        <i class="fas fa-upload"></i> Elegir imagen
                    </button>
                    <button type="button" class="btn-peligro" id="fotoQuitar">
                        <i class="fas fa-trash"></i> Quitar
                    </button>
                </div>
                <p style="font-size:11.5px;color:#a79ea2;margin:10px 0 0;text-align:center;">
                    JPG, PNG o WEBP · máx. 2 MB
                </p>
                <input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" hidden />
            </div>
        </div>
    </div>

    <script src="../assets/js/sistema.js"></script>
    <?php include 'footer/footer.php'; ?>
</body>

</html>