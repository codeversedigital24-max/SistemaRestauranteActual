 <!-- ========================================== -->
 <!-- FOOTER CORTO MODERNO -->
 <!-- ========================================== -->
 <footer class="footer-modern">
     <div class="footer-container">
         <div class="footer-content">
             <div class="footer-left">
                 <span class="footer-copy">
                     &copy; <?php echo date('Y'); ?> <strong>Sistema de Control</strong>.
                     Todos los derechos reservados.
                 </span>
             </div>
             <div class="footer-center">
                 <span class="footer-dev">
                     <i class="fas fa-code"></i> Desarrollado por
                     <strong>CodeVerseDigital</strong>
                     <span class="footer-dev-name">Ing. Elwin Ibarra | 72954584</span>
                 </span>
             </div>
             <div class="footer-right">
                 <span class="footer-version">
                     <i class="fas fa-tag"></i> v2.0
                 </span>
             </div>
         </div>
     </div>
 </footer>

 <style>
     /* ============================================
       FOOTER CORTO MODERNO
       ============================================ */
     .footer-modern {
         background: var(--primary-gradient);
         color: var(--white);
         margin-top: auto;
         width: 100%;
         padding: 12px 0;
         border-top: 2px solid var(--secondary);
         box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
     }

     .footer-container {
         max-width: 1400px;
         margin: 0 auto;
         padding: 0 var(--spacing-lg);
     }

     .footer-content {
         display: flex;
         justify-content: space-between;
         align-items: center;
         flex-wrap: wrap;
         gap: var(--spacing-sm);
         font-size: var(--font-size-sm);
         opacity: 0.9;
     }

     /* ============================================
       LEFT - COPYRIGHT
       ============================================ */
     .footer-left .footer-copy {
         display: flex;
         align-items: center;
         gap: 4px;
     }

     .footer-left .footer-copy strong {
         color: var(--secondary);
         font-weight: var(--font-weight-semibold);
     }

     /* ============================================
       CENTER - DESARROLLADO POR
       ============================================ */
     .footer-center .footer-dev {
         display: flex;
         align-items: center;
         gap: var(--spacing-xs);
     }

     .footer-center .footer-dev i {
         color: var(--secondary);
         font-size: var(--font-size-sm);
     }

     .footer-center .footer-dev strong {
         color: var(--secondary);
         font-weight: var(--font-weight-semibold);
     }

     .footer-center .footer-dev .footer-dev-name {
         background: rgba(255, 255, 255, 0.1);
         padding: 2px 10px;
         border-radius: var(--radius-full);
         font-size: var(--font-size-xs);
         opacity: 0.8;
         border: 1px solid rgba(255, 255, 255, 0.05);
     }

     /* ============================================
       RIGHT - VERSIÓN
       ============================================ */
     .footer-right .footer-version {
         display: flex;
         align-items: center;
         gap: 4px;
         background: rgba(255, 255, 255, 0.08);
         padding: 4px 12px;
         border-radius: var(--radius-full);
         font-size: var(--font-size-xs);
         border: 1px solid rgba(255, 255, 255, 0.05);
     }

     .footer-right .footer-version i {
         color: var(--secondary);
         font-size: var(--font-size-xs);
     }

     /* ============================================
       RESPONSIVE
       ============================================ */
     @media (max-width: 768px) {
         .footer-container {
             padding: 0 var(--spacing-md);
         }

         .footer-content {
             flex-direction: column;
             text-align: center;
             gap: 6px;
             font-size: var(--font-size-xs);
         }

         .footer-center .footer-dev {
             flex-wrap: wrap;
             justify-content: center;
         }

         .footer-center .footer-dev .footer-dev-name {
             display: block;
             margin-top: 2px;
         }

         .footer-right .footer-version {
             font-size: var(--font-size-xs);
             padding: 2px 10px;
         }
     }

     @media (max-width: 480px) {
         .footer-container {
             padding: 0 var(--spacing-sm);
         }

         .footer-content {
             font-size: var(--font-size-xs);
             gap: 4px;
         }

         .footer-center .footer-dev {
             flex-direction: column;
             gap: 2px;
         }

         .footer-center .footer-dev .footer-dev-name {
             display: inline-block;
             margin-top: 0;
         }

         .footer-left .footer-copy {
             flex-wrap: wrap;
             justify-content: center;
         }
     }
 </style>

 <script>
     document.addEventListener('DOMContentLoaded', function() {
         // ============================================
         // TOGGLE MENÚ MÓVIL
         // ============================================
         const mobileToggle = document.getElementById('mobileToggle');
         const navbarMenu = document.getElementById('navbarMenu');

         if (mobileToggle) {
             mobileToggle.addEventListener('click', function(e) {
                 e.stopPropagation();
                 navbarMenu.classList.toggle('open');
                 const icon = this.querySelector('i');
                 icon.classList.toggle('fa-bars');
                 icon.classList.toggle('fa-times');
                 closeAllSubmenus();
             });
         }

         // ============================================
         // VARIABLES GLOBALES
         // ============================================
         const configDropdown = document.getElementById('configDropdown');
         const configTrigger = document.getElementById('configTrigger');
         const navDropdowns = document.querySelectorAll('.nav-dropdown');

         // ============================================
         // FUNCIONES AUXILIARES
         // ============================================
         function closeAllSubmenus() {
             document.querySelectorAll('.dropdown-submenu.open').forEach(submenu => {
                 submenu.classList.remove('open');
             });
         }

         function closeMainMenuMobile() {
             if (window.innerWidth <= 991) {
                 navbarMenu.classList.remove('open');
                 if (mobileToggle) {
                     const icon = mobileToggle.querySelector('i');
                     icon.classList.add('fa-bars');
                     icon.classList.remove('fa-times');
                 }
             }
         }

         function closeAllMenus() {
             closeAllSubmenus();
             navDropdowns.forEach(dropdown => dropdown.classList.remove('open'));
             if (window.innerWidth <= 991) {
                 closeMainMenuMobile();
             }
         }

         // ============================================
         // TOGGLE CONFIGURACIÓN
         // ============================================
         if (configTrigger) {
             configTrigger.addEventListener('click', function(e) {
                 e.stopPropagation();
                 e.preventDefault();

                 // En móvil: abrir el menú principal si está cerrado
                 if (window.innerWidth <= 991 && !navbarMenu.classList.contains('open')) {
                     navbarMenu.classList.add('open');
                     if (mobileToggle) {
                         const icon = mobileToggle.querySelector('i');
                         icon.classList.add('fa-times');
                         icon.classList.remove('fa-bars');
                     }
                 }

                 // Toggle del dropdown
                 configDropdown.classList.toggle('open');

                 // Si se cierra, cerrar submenús
                 if (!configDropdown.classList.contains('open')) {
                     closeAllSubmenus();
                 }
             });
         }

         // ============================================
         // TOGGLE DE LOS DEMÁS DROPDOWNS (Compras, etc.)
         // ============================================
         navDropdowns.forEach(dropdown => {
             if (dropdown.id === 'configDropdown') return;

             const trigger = dropdown.querySelector('.nav-dropdown-trigger');
             if (!trigger) return;

             trigger.addEventListener('click', function(e) {
                 e.stopPropagation();
                 e.preventDefault();

                 // En móvil: abrir el menú principal si está cerrado
                 if (window.innerWidth <= 991 && !navbarMenu.classList.contains('open')) {
                     navbarMenu.classList.add('open');
                     if (mobileToggle) {
                         const icon = mobileToggle.querySelector('i');
                         icon.classList.add('fa-times');
                         icon.classList.remove('fa-bars');
                     }
                 }

                 // Acordeón: cerrar los otros dropdowns
                 navDropdowns.forEach(otro => {
                     if (otro !== dropdown) otro.classList.remove('open');
                 });

                 dropdown.classList.toggle('open');

                 if (!dropdown.classList.contains('open')) {
                     closeAllSubmenus();
                 }
             });
         });

         // ============================================
         // MANEJO DE SUBMENÚS (ACORDEÓN)
         // ============================================
         document.querySelectorAll('.dropdown-submenu-trigger').forEach(trigger => {
             trigger.addEventListener('click', function(e) {
                 e.stopPropagation();
                 // NO usar e.preventDefault() para permitir el comportamiento nativo en móvil

                 const parent = this.closest('.dropdown-submenu');
                 const isOpen = parent.classList.contains('open');

                 // En móvil: asegurar que el menú principal esté abierto
                 if (window.innerWidth <= 991 && !navbarMenu.classList.contains('open')) {
                     navbarMenu.classList.add('open');
                     if (mobileToggle) {
                         const icon = mobileToggle.querySelector('i');
                         icon.classList.add('fa-times');
                         icon.classList.remove('fa-bars');
                     }
                 }

                 // CERRAR TODOS LOS SUBMENÚS (acordeón)
                 const parentMenu = parent.closest('.nav-dropdown-menu') || parent.closest('.dropdown-submenu-menu');
                 if (parentMenu) {
                     const siblings = parentMenu.querySelectorAll('.dropdown-submenu');
                     siblings.forEach(sibling => {
                         if (sibling !== parent) {
                             sibling.classList.remove('open');
                         }
                     });
                 }

                 // ABRIR/CERRAR EL ACTUAL
                 parent.classList.toggle('open');
             });
         });

         // ============================================
         // CLICK EN ITEMS DEL MENÚ PRINCIPAL
         // ============================================
         document.querySelectorAll('.navbar-menu .nav-item:not(.nav-dropdown *)').forEach(item => {
             item.addEventListener('click', function(e) {
                 if (window.innerWidth <= 991) {
                     closeMainMenuMobile();
                 }
                 // Marcar como activo
                 document.querySelectorAll('.navbar-menu .nav-item').forEach(i => i.classList.remove('active'));
                 this.classList.add('active');
             });
         });

         // ============================================
         // CERRAR AL HACER CLICK FUERA
         // ============================================
         document.addEventListener('click', function(e) {
             // Si el click es fuera del menú principal
             if (!navbarMenu.contains(e.target) && !mobileToggle.contains(e.target)) {
                 if (window.innerWidth <= 991) {
                     closeMainMenuMobile();
                 }
             }

             // Si el click es fuera de cualquier dropdown del menú
             let dropdownAbierto = false;

             navDropdowns.forEach(dropdown => {
                 if (!dropdown.contains(e.target)) {
                     dropdown.classList.remove('open');
                 } else {
                     dropdownAbierto = true;
                 }
             });

             if (!dropdownAbierto) {
                 closeAllSubmenus();
             }

             // Si el click es fuera del user menu
             const userMenu = document.getElementById('userMenu');
             if (userMenu && !userMenu.contains(e.target)) {
                 userMenu.classList.remove('active');
             }
         });

         // ============================================
         // CERRAR CON ESC
         // ============================================
         document.addEventListener('keydown', function(e) {
             if (e.key === 'Escape') {
                 closeAllMenus();
                 const userMenu = document.getElementById('userMenu');
                 if (userMenu) {
                     userMenu.classList.remove('active');
                 }
             }
         });

         // ============================================
         // USER MENU TOGGLE
         // ============================================
         const userMenu = document.getElementById('userMenu');
         const userTrigger = document.getElementById('userMenuTrigger');

         if (userTrigger) {
             userTrigger.addEventListener('click', function(e) {
                 e.stopPropagation();
                 userMenu.classList.toggle('active');
             });
         }

         /**
          * Logout con confirmación Swal.
          * Intercepta el click en el enlace de "Cerrar Sesión" y hace POST a logout.php.
          */
         (function() {
             "use strict";

             function redirigirAlLogin() {
                 // Redirect relativo a sistema/ (donde vive el menú)
                 window.location.replace("../login.php?logout=success");
             }

             document.querySelectorAll('.dropdown-item.danger').forEach(function(item) {
                 item.addEventListener('click', function(e) {
                     var href = item.getAttribute('href') || '';
                     if (href.indexOf('logout.php') === -1) return;

                     e.preventDefault();

                     Swal.fire({
                         icon: "question",
                         title: "¿Cerrar sesión?",
                         text: "Se cerrará tu sesión actual y volverás al login.",
                         showCancelButton: true,
                         confirmButtonText: "Sí, salir",
                         cancelButtonText: "Cancelar",
                         confirmButtonColor: "#d9232d",
                         cancelButtonColor: "#9aa0a6",
                         reverseButtons: true,
                         allowOutsideClick: false
                     }).then(function(r) {
                         if (!r.isConfirmed) return;

                         // 1) Loader inmediato
                         Swal.fire({
                             title: "Cerrando sesión…",
                             text: "Redirigiendo al login…",
                             allowOutsideClick: false,
                             allowEscapeKey: false,
                             didOpen: function() {
                                 Swal.showLoading();
                             }
                         });

                         var meta = document.querySelector('meta[name="csrf-token"]');
                         var csrf = (meta && meta.content) || "";

                         // 2) Fallback duro: si el fetch no vuelve en 3s, redirigimos igual.
                         var yaRedirigido = false;
                         var redirigir = function() {
                             if (yaRedirigido) return;
                             yaRedirigido = true;
                             redirigirAlLogin();
                         };
                         var watchdog = setTimeout(redirigir, 3000);

                         // 3) POST al backend para destruir sesión
                         fetch(href, {
                                 method: "POST",
                                 credentials: "same-origin",
                                 headers: {
                                     Accept: "application/json",
                                     "X-CSRF-Token": csrf
                                 }
                             })
                             .then(function(resp) {
                                 return resp.json().catch(function() {
                                     return null;
                                 });
                             })
                             .then(function() {
                                 clearTimeout(watchdog);
                                 // Aunque el backend devuelva JSON con redirect, forzamos
                                 // el destino correcto para no depender de rutas.
                                 redirigir();
                             })
                             .catch(function() {
                                 clearTimeout(watchdog);
                                 redirigir();
                             });
                     });
                 });
             });
         })();

         // ============================================
         // EFECTO HOVER PARA DESKTOP
         // ============================================
         function setupDesktopHover() {
             if (window.innerWidth > 991) {
                 document.querySelectorAll('.dropdown-submenu').forEach(submenu => {
                     submenu.addEventListener('mouseenter', function() {
                         const siblings = this.closest('.nav-dropdown-menu').querySelectorAll('.dropdown-submenu');
                         siblings.forEach(sibling => {
                             if (sibling !== this) {
                                 sibling.classList.remove('open');
                             }
                         });
                         this.classList.add('open');
                     });

                     submenu.addEventListener('mouseleave', function(e) {
                         const relatedTarget = e.relatedTarget;
                         if (relatedTarget && !this.contains(relatedTarget)) {
                             setTimeout(() => {
                                 if (!this.querySelector('.dropdown-submenu-menu:hover')) {
                                     this.classList.remove('open');
                                 }
                             }, 150);
                         }
                     });
                 });
             }
         }

         setupDesktopHover();

         // ============================================
         // REINICIAR AL REDIMENSIONAR
         // ============================================
         let resizeTimer;
         window.addEventListener('resize', function() {
             clearTimeout(resizeTimer);
             resizeTimer = setTimeout(() => {
                 if (window.innerWidth > 991) {
                     closeAllSubmenus();
                     navDropdowns.forEach(dropdown => dropdown.classList.remove('open'));
                 } else {
                     closeAllMenus();
                 }
                 setupDesktopHover();
             }, 250);
         });

         // ============================================
         // TOAST DE BIENVENIDA (sólo en el dashboard; los módulos avisan solos)
         if (location.pathname.indexOf('dashboard') < 0) {
             return;
         }
         // ============================================
         setTimeout(() => {
             showToast(
                 '¡Bienvenido!',
                 'Has iniciado sesión correctamente como <?php echo htmlspecialchars($rolNombre); ?>',
                 'success',
                 4000
             );
         }, 500);

         console.log('✅ Dashboard cargado correctamente');
         console.log('👤 Usuario:', <?php echo json_encode($usuario); ?>);
     });

     // ============================================
     // TOAST SYSTEM
     // ============================================
     window.showToast = function(title, message, type = 'info', duration = 5000) {
         const container = document.querySelector('.toast-container') || (() => {
             const c = document.createElement('div');
             c.className = 'toast-container';
             document.body.appendChild(c);
             return c;
         })();

         const icons = {
             success: 'fa-check-circle',
             error: 'fa-exclamation-circle',
             warning: 'fa-exclamation-triangle',
             info: 'fa-info-circle'
         };

         const toast = document.createElement('div');
         toast.className = `toast ${type}`;
         toast.innerHTML = `
        <i class="fas ${icons[type] || icons.info} toast-icon"></i>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close">&times;</button>
    `;

         container.appendChild(toast);

         const timeout = setTimeout(() => {
             removeToast(toast);
         }, duration);

         toast.querySelector('.toast-close').addEventListener('click', () => {
             clearTimeout(timeout);
             removeToast(toast);
         });

         toast.addEventListener('click', (e) => {
             if (e.target === toast || e.target.classList.contains('toast-content')) {
                 clearTimeout(timeout);
                 removeToast(toast);
             }
         });

         return toast;
     };

     function removeToast(toast) {
         toast.classList.add('fade-out');
         setTimeout(() => {
             if (toast.parentNode) {
                 toast.parentNode.removeChild(toast);
             }
         }, 300);
     }
 </script>