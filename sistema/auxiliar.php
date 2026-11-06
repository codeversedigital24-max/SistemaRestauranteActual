 <!-- NIVEL 1: Módulo "Configuración General" -->
                   <?php if ($esSuperAdmin || $consulta->tieneModulo($id_usuario, 1)): ?>
                       <div class="dropdown-submenu">
                           <button class="dropdown-submenu-trigger">
                               <i class="fas fa-globe"></i> Configuración General
                               <i class="fas fa-chevron-right submenu-arrow"></i>
                           </button>
                           <div class="dropdown-submenu-menu">

                               <!-- NIVEL 2: Detalles del módulo -->
                               <?php if ($esSuperAdmin || $consulta->tieneDetalle($id_usuario, 1)): ?>
                                   <a href="empresa" class="dropdown-item">
                                       <i class="fas fa-building"></i> Empresa
                                   </a>
                               <?php endif; ?>

                               <?php if ($esSuperAdmin || $consulta->tieneDetalle($id_usuario, 2)): ?>
                                   <a href="#" class="dropdown-item">
                                       <i class="fas fa-address-card"></i> Datos Fiscales
                                   </a>
                               <?php endif; ?>

                               <!-- EJEMPLO DE NIVEL 3: Desgloses dentro de un detalle -->
                               <div class="dropdown-submenu">
                                   <button class="dropdown-submenu-trigger">
                                       <i class="fas fa-cog"></i> Configuración Avanzada
                                       <i class="fas fa-chevron-right submenu-arrow"></i>
                                   </button>
                                   <div class="dropdown-submenu-menu">

                                       <!-- NIVEL 3: Desgloses (acciones específicas) -->
                                       <?php if ($esSuperAdmin || $consulta->tieneDesglose($id_usuario, 1)): ?>
                                           <a href="#" class="dropdown-item">
                                               <i class="fas fa-sliders-h"></i> Preferencias
                                           </a>
                                       <?php endif; ?>

                                       <?php if ($esSuperAdmin || $consulta->tieneDesglose($id_usuario, 2)): ?>
                                           <a href="#" class="dropdown-item">
                                               <i class="fas fa-palette"></i> Apariencia
                                           </a>
                                       <?php endif; ?>

                                       <?php if ($esSuperAdmin || $consulta->tieneDesglose($id_usuario, 3)): ?>
                                           <a href="#" class="dropdown-item">
                                               <i class="fas fa-language"></i> Idioma
                                           </a>
                                       <?php endif; ?>

                                   </div>
                               </div>

                           </div>
                       </div>
                   <?php endif; ?>