 <div class="modal-overlay" id="modalUsuario" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
     <div class="modal-caja">

         <div class="modal-cabecera">
             <h2 id="modalTitulo">
                 <span class="ico-titulo" id="modalIcono"><i class="fa-solid fa-user-plus"></i></span>
                 <span id="modalTexto" style="color: white;">Nuevo usuario</span>
             </h2>
             <button type="button" class="modal-cerrar" id="modalCerrar" aria-label="Cerrar">&times;</button>
         </div>

         <form id="formNuevoUsuario" novalidate autocomplete="off" data-accion="crear" data-id="" style="display:flex;flex-direction:column;min-height:0;flex:1 1 auto;overflow:hidden;">

             <div class="modal-cuerpo">

                 <div class="campo" data-campo="nombres">
                     <label for="f_nombres">
                         <i class="fa-solid fa-user txt-ico"></i> Nombres <span class="req">*</span>
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-id-card ico-izq"></i>
                         <input class="input-modern" type="text" id="f_nombres" name="nombres" maxlength="100" placeholder="María Elena" required />
                     </div>
                     <span class="ayuda">Requerido</span>
                 </div>

                 <div class="campo" data-campo="apellidos">
                     <label for="f_apellidos">
                         <i class="fa-solid fa-users txt-ico"></i> Apellidos <span class="req">*</span>
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-address-card ico-izq"></i>
                         <input class="input-modern" type="text" id="f_apellidos" name="apellidos" maxlength="100" placeholder="López Vargas" required />
                     </div>
                     <span class="ayuda">Requerido</span>
                 </div>

                 <div class="campo" data-campo="cedula">
                     <label for="f_cedula">
                         <i class="fa-solid fa-id-badge txt-ico"></i> Cédula
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-passport ico-izq"></i>
                         <input class="input-modern" type="text" id="f_cedula" name="cedula" maxlength="20" inputmode="numeric" placeholder="12345678" />
                     </div>
                     <span class="ayuda">Opcional · única si se informa</span>
                 </div>

                 <div class="campo" data-campo="telefono">
                     <label for="f_telefono">
                         <i class="fa-solid fa-phone txt-ico"></i> Teléfono
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-mobile-screen-button ico-izq"></i>
                         <input class="input-modern" type="text" id="f_telefono" name="telefono" maxlength="20" inputmode="tel" placeholder="71234567" />
                     </div>
                     <span class="ayuda">Opcional</span>
                 </div>

                 <div class="campo" data-campo="correo">
                     <label for="f_email">
                         <i class="fa-solid fa-envelope txt-ico"></i> Correo <span class="req">*</span>
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-at ico-izq"></i>
                         <input class="input-modern" type="email" id="f_email" name="email" maxlength="100" placeholder="nombre@restaurante.com" autocapitalize="none" required />
                     </div>
                     <span class="ayuda">Único · sirve para iniciar sesión</span>
                 </div>

                 <div class="campo" data-campo="username">
                     <label for="f_username">
                         <i class="fa-solid fa-user-check txt-ico"></i> Usuario (login) <span class="req">*</span>
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-fingerprint ico-izq"></i>
                         <input class="input-modern" type="text" id="f_username" name="username" maxlength="50" minlength="3" placeholder="maria.lopez" autocapitalize="none" required />
                     </div>
                     <span class="ayuda">3 a 50 caracteres, sin espacios · único</span>
                 </div>

                 <div class="campo" data-campo="rol">
                     <label for="f_rol">
                         <i class="fa-solid fa-user-shield txt-ico"></i> Rol <span class="req">*</span>
                     </label>
                     <div class="wrap-input">
                         <i class="fa-solid fa-sitemap ico-izq" style="display:none"></i>
                         <select class="select-modern" id="f_rol" name="id_rol" required>
                             <option value="">— Selecciona un rol —</option>
                             <?php foreach ($roles as $r): ?>
                                 <option value="<?php echo (int) $r['id_rol']; ?>">
                                     <?php echo htmlspecialchars($r['nombre_rol']); ?>
                                 </option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                     <span class="ayuda">Define qué puede ver y hacer</span>
                 </div>

                 <div class="campo" data-campo="sucursal">
                     <label for="f_sucursal">
                         <i class="fa-solid fa-store txt-ico"></i> Sucursal
                     </label>
                     <div class="wrap-input">
                         <select class="select-modern" id="f_sucursal" name="id_sucursal">
                             <option value="0">— Sin sucursal (global) —</option>
                             <?php foreach ($sucursales as $s): ?>
                                 <option value="<?php echo (int) $s['id_sucursal']; ?>">
                                     <?php echo htmlspecialchars($s['nombre_sucursal']); ?>
                                 </option>
                             <?php endforeach; ?>
                         </select>
                     </div>
                     <span class="ayuda">Opcional · vacío = acceso global</span>
                 </div>

                 <div class="campo" data-campo="estado">
                     <label for="f_estado">
                         <i class="fa-solid fa-toggle-on txt-ico"></i> Estado <span class="req">*</span>
                     </label>
                     <div class="wrap-input">
                         <select class="select-modern" id="f_estado" name="estado" required>
                             <option value="1">Activo — puede iniciar sesión</option>
                             <option value="0">De baja — no puede iniciar sesión</option>
                         </select>
                     </div>
                     <span class="ayuda">Los usuarios de baja conservan su historial y pueden darse de alta después</span>
                 </div>

                 <div class="campo" data-campo="password">
                     <label for="f_password">
                         <i class="fa-solid fa-lock txt-ico"></i> <span id="etiquetaPassword">Contraseña</span> <span class="req">*</span>
                     </label>
                     <div class="wrap-input campo-ojo">
                         <i class="fa-solid fa-key ico-izq"></i>
                         <input class="input-modern" type="password" id="f_password" name="password" minlength="8" maxlength="72" placeholder="Mínimo 8 caracteres" autocomplete="new-password" />
                         <button type="button" class="ojo" data-ojo="f_password" aria-label="Mostrar contraseña">
                             <i class="fa-solid fa-eye"></i>
                         </button>
                     </div>
                     <span class="ayuda" id="ayudaPassword">Mínimo 8 caracteres</span>
                 </div>

                 <div class="campo" data-campo="password2">
                     <label for="f_password2">
                         <i class="fa-solid fa-lock txt-ico"></i> Confirmar contraseña <span class="req">*</span>
                     </label>
                     <div class="wrap-input campo-ojo">
                         <i class="fa-solid fa-shield-halved ico-izq"></i>
                         <input class="input-modern" type="password" id="f_password2" name="password2" minlength="8" maxlength="72" placeholder="Repite la contraseña" autocomplete="new-password" />
                         <button type="button" class="ojo" data-ojo="f_password2" aria-label="Mostrar contraseña">
                             <i class="fa-solid fa-eye"></i>
                         </button>
                     </div>
                     <span class="ayuda">Debe coincidir con la anterior</span>
                 </div>
                 <div class="campo campo-foto" data-campo="foto">
                     <label>
                         <i class="fa-solid fa-camera txt-ico"></i> Foto de perfil
                     </label>

                     <div class="foto-wrap">
                         <div class="foto-preview" id="fotoPreview">
                             <i class="fa-solid fa-user"></i>
                         </div>

                         <div class="foto-acciones">
                             <button type="button" class="btn-mod btn-mod-claro btn-sm" id="btnElegirFoto">
                                 <i class="fa-solid fa-upload"></i> Elegir imagen
                             </button>
                             <button type="button" class="btn-mod btn-mod-peligro btn-sm" id="btnQuitarFoto" style="display:none;">
                                 <i class="fa-solid fa-trash"></i> Quitar
                             </button>
                             <span class="ayuda" id="fotoAyuda">JPG, PNG o WEBP · máx. 2 MB</span>
                         </div>

                         <input type="file" id="f_foto" name="foto" accept="image/png,image/jpeg,image/webp" hidden />
                     </div>
                 </div>
                 <input type="hidden" id="f_id" value="" />

             </div>

             <div class="modal-pie">
                 <span class="txt-tenue" id="pieNota" style="margin-right:auto; font-size:12px;"></span>
                 <button type="button" class="btn-mod btn-mod-claro" id="btnCancelar">
                     <i class="fa-solid fa-xmark"></i> Cancelar
                 </button>
                 <button type="submit" class="btn-mod btn-mod-primario" id="btnGuardar">
                     <i class="fa-solid fa-floppy-disk"></i> <span>Guardar</span>
                 </button>
             </div>
         </form>

     </div>
 </div>