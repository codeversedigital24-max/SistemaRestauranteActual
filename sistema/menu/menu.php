   <style>
       .nav-dropdown {
           position: relative;
           display: inline-block;
       }

       .nav-item.dropdown {
           position: relative;
       }

       .submenu {
           display: none;
           padding-left: 25px;
           background: #f8f9fa;
       }

       .nav-item.dropdown:hover .submenu {
           display: block;
       }

       .submenu a {
           display: block;
           padding: 10px;
           text-decoration: none;
           color: inherit;
       }

       .submenu a:hover {
           background: #e9ecef;
       }

       .nav-dropdown-trigger {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: 8px 16px;
           border-radius: var(--radius);
           color: var(--gray-dark);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           transition: var(--transition);
           cursor: pointer;
           border: none;
           background: transparent;
           font-family: var(--font-family);
           position: relative;
       }

       .nav-dropdown-trigger:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .nav-dropdown-trigger.active {
           background: var(--primary);
           color: var(--white);
           box-shadow: var(--shadow-primary);
       }

       .nav-dropdown-trigger.active:hover {
           background: var(--primary-light);
       }

       .nav-dropdown-trigger i {
           font-size: var(--font-size-md);
       }

       .dropdown-arrow {
           font-size: var(--font-size-xs);
           transition: var(--transition);
           margin-left: 4px;
       }

       .nav-dropdown.open .dropdown-arrow {
           transform: rotate(180deg);
       }

       /* Menú desplegable */
       .nav-dropdown-menu {
           position: absolute;
           top: calc(100% + 8px);
           left: 0;
           min-width: 220px;
           background: var(--white);
           border-radius: var(--radius);
           box-shadow: var(--shadow-xl);
           padding: var(--spacing-sm);
           opacity: 0;
           visibility: hidden;
           transform: translateY(-10px);
           transition: var(--transition);
           border: 1px solid rgba(0, 0, 0, 0.05);
           z-index: 999;
       }

       .nav-dropdown.open .nav-dropdown-menu {
           opacity: 1;
           visibility: visible;
           transform: translateY(0);
       }

       .nav-dropdown-menu .dropdown-item {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: var(--spacing-sm) var(--spacing-md);
           color: var(--gray-dark);
           text-decoration: none;
           border-radius: var(--radius-sm);
           transition: var(--transition);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           border: none;
           background: transparent;
           width: 100%;
           cursor: pointer;
           font-family: var(--font-family);
       }

       .nav-dropdown-menu .dropdown-item:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .nav-dropdown-menu .dropdown-item i {
           width: 20px;
           text-align: center;
           font-size: var(--font-size-md);
           color: var(--gray-light);
       }

       .nav-dropdown-menu .dropdown-item:hover i {
           color: var(--primary);
       }

       .nav-dropdown-menu .dropdown-divider {
           height: 1px;
           background: var(--light-gray);
           margin: var(--spacing-xs) var(--spacing-sm);
       }

       @media (max-width: 991px) {
           .nav-dropdown {
               width: 100%;
           }

           .nav-dropdown-trigger {
               width: 100%;
               justify-content: flex-start;
               padding: var(--spacing-sm) var(--spacing-md);
           }

           .nav-dropdown-trigger .dropdown-arrow {
               margin-left: auto;
           }

           .nav-dropdown-menu {
               position: static;
               box-shadow: none;
               padding-left: var(--spacing-lg);
               border: none;
               opacity: 1;
               visibility: visible;
               transform: none;
               display: none;
               background: transparent;
           }

           .nav-dropdown.open .nav-dropdown-menu {
               display: block;
           }

           .nav-dropdown-menu .dropdown-item {
               padding: var(--spacing-xs) var(--spacing-sm);
               font-size: var(--font-size-sm);
           }

           .nav-dropdown-menu .dropdown-divider {
               margin: var(--spacing-xs) 0;
           }
       }

       .navbar-dashboard {
           position: sticky;
           top: 0;
           z-index: 1000;
           background: var(--white);
           padding: 0 var(--spacing-lg);
           height: var(--navbar-height);
           display: flex;
           align-items: center;
           justify-content: space-between;
           box-shadow: var(--shadow);
           border-bottom: 2px solid var(--secondary-light);
           width: 100%;
       }

       .navbar-brand {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           text-decoration: none;
           flex-shrink: 0;
       }

       .navbar-brand img {
           height: 36px;
           width: auto;
       }

       .navbar-brand .brand-text {
           font-size: var(--font-size-md);
           font-weight: var(--font-weight-bold);
           color: var(--primary);
           letter-spacing: -0.5px;
       }

       .navbar-brand .brand-text small {
           font-weight: var(--font-weight-regular);
           color: var(--gray);
           font-size: var(--font-size-xs);
           display: block;
           letter-spacing: 0;
       }

       /* Ítems de módulos aún no creados (href="#"): se ocultan aquí.
           Al crear la sección basta poner su enlace real y aparecerá. */
       .navbar-menu .nav-item[href="#"] {
           display: none;
       }

       /* Menú de navegación */
       .navbar-menu {
           display: flex;
           align-items: center;
           gap: var(--spacing-xs);
           flex: 1;
           justify-content: center;
           padding: 0 var(--spacing-lg);
       }

       .navbar-menu .nav-item {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: 8px 16px;
           border-radius: var(--radius);
           color: var(--gray-dark);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           transition: var(--transition);
           cursor: pointer;
           border: none;
           background: transparent;
           font-family: var(--font-family);
           text-decoration: none;
           position: relative;
       }

       .navbar-menu .nav-item:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .navbar-menu .nav-item.active {
           background: var(--primary);
           color: var(--white);
           box-shadow: var(--shadow-primary);
       }

       .navbar-menu .nav-item.active:hover {
           background: var(--primary-light);
       }

       .navbar-menu .nav-item i {
           font-size: var(--font-size-md);
       }

       .navbar-menu .nav-item .badge-nav {
           position: absolute;
           top: -4px;
           right: -4px;
           background: var(--error);
           color: var(--white);
           font-size: var(--font-size-xs);
           padding: 2px 6px;
           border-radius: var(--radius-full);
           font-weight: var(--font-weight-bold);
           min-width: 18px;
           text-align: center;
       }

       /* Acciones derecha */
       .navbar-actions {
           display: flex;
           align-items: center;
           gap: var(--spacing-md);
           flex-shrink: 0;
       }

       .navbar-actions .action-btn {
           width: 40px;
           height: 40px;
           border-radius: var(--radius-full);
           border: none;
           background: var(--light-gray);
           color: var(--gray-dark);
           font-size: var(--font-size-md);
           cursor: pointer;
           transition: var(--transition);
           display: flex;
           align-items: center;
           justify-content: center;
           position: relative;
       }

       .navbar-actions .action-btn:hover {
           background: var(--primary);
           color: var(--white);
       }

       .navbar-actions .action-btn .badge-nav {
           position: absolute;
           top: -2px;
           right: -2px;
           background: var(--error);
           color: var(--white);
           font-size: var(--font-size-xs);
           padding: 2px 6px;
           border-radius: var(--radius-full);
           font-weight: var(--font-weight-bold);
           min-width: 18px;
           text-align: center;
       }

       /* User Menu */
       .user-menu {
           position: relative;
           cursor: pointer;
       }

       .user-menu-trigger {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: 4px 12px 4px 4px;
           border-radius: var(--radius-full);
           background: var(--light-gray);
           transition: var(--transition);
           border: 2px solid transparent;
       }

       .user-menu-trigger:hover {
           border-color: var(--secondary);
           background: var(--white);
           box-shadow: var(--shadow);
       }

       .user-avatar {
           width: 36px;
           height: 36px;
           border-radius: var(--radius-full);
           display: flex;
           align-items: center;
           justify-content: center;
           font-weight: var(--font-weight-bold);
           font-size: var(--font-size-sm);
           color: var(--white);
           flex-shrink: 0;
       }

       .user-info {
           display: none;
       }

       .user-info .name {
           font-weight: var(--font-weight-semibold);
           font-size: var(--font-size-sm);
           color: var(--dark);
           line-height: 1.2;
       }

       .user-info .role {
           font-size: var(--font-size-xs);
           color: var(--gray-light);
           font-weight: var(--font-weight-medium);
           text-transform: uppercase;
           letter-spacing: 0.3px;
       }

       .user-menu .arrow {
           color: var(--gray-light);
           font-size: var(--font-size-xs);
           transition: var(--transition);
       }

       .user-menu.active .arrow {
           transform: rotate(180deg);
       }

       /* Dropdown */
       .user-dropdown {
           position: absolute;
           top: calc(100% + 8px);
           right: 0;
           background: var(--white);
           border-radius: var(--radius);
           box-shadow: var(--shadow-xl);
           min-width: 240px;
           padding: var(--spacing-sm);
           opacity: 0;
           visibility: hidden;
           transform: translateY(-10px);
           transition: var(--transition);
           border: 1px solid rgba(0, 0, 0, 0.05);
       }

       .user-menu.active .user-dropdown {
           opacity: 1;
           visibility: visible;
           transform: translateY(0);
       }

       .user-dropdown .dropdown-header {
           padding: var(--spacing-sm) var(--spacing-md);
           border-bottom: 1px solid var(--light-gray);
           margin-bottom: var(--spacing-sm);
       }

       .user-dropdown .dropdown-header .dname {
           font-weight: var(--font-weight-semibold);
           font-size: var(--font-size-sm);
           color: var(--dark);
       }

       .user-dropdown .dropdown-header .demail {
           font-size: var(--font-size-xs);
           color: var(--gray-light);
           margin-top: 2px;
       }

       .user-dropdown .dropdown-item {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: var(--spacing-sm) var(--spacing-md);
           color: var(--gray-dark);
           text-decoration: none;
           border-radius: var(--radius-sm);
           transition: var(--transition);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           border: none;
           background: transparent;
           width: 100%;
           cursor: pointer;
           font-family: var(--font-family);
       }

       .user-dropdown .dropdown-item:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .user-dropdown .dropdown-item i {
           width: 18px;
           text-align: center;
           font-size: var(--font-size-md);
       }

       .user-dropdown .dropdown-divider {
           height: 1px;
           background: var(--light-gray);
           margin: var(--spacing-xs) var(--spacing-sm);
       }

       .user-dropdown .dropdown-item.danger {
           color: var(--error);
       }

       .user-dropdown .dropdown-item.danger:hover {
           background: var(--error-bg);
           color: var(--error);
       }

       .main-content {
           flex: 1;
           padding: var(--spacing-lg);
           width: 100%;
       }

       .welcome-section {
           background: var(--white);
           border-radius: var(--radius);
           padding: var(--spacing-lg) var(--spacing-xl);
           margin-bottom: var(--spacing-lg);
           box-shadow: var(--shadow);
           border-left: 4px solid var(--primary);
           display: flex;
           align-items: center;
           justify-content: space-between;
           flex-wrap: wrap;
           gap: var(--spacing-md);
       }

       .welcome-text h1 {
           font-size: var(--font-size-2xl);
           font-weight: var(--font-weight-bold);
           color: var(--dark);
       }

       .welcome-text h1 span {
           color: var(--primary);
       }

       .welcome-text p {
           color: var(--gray);
           font-size: var(--font-size-sm);
           margin-top: var(--spacing-xs);
       }

       .welcome-badge {
           display: inline-flex;
           align-items: center;
           gap: var(--spacing-sm);
           background: var(--primary-gradient);
           color: var(--white);
           padding: var(--spacing-sm) var(--spacing-lg);
           border-radius: var(--radius-full);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-semibold);
           white-space: nowrap;
       }

       .welcome-badge i {
           font-size: var(--font-size-md);
       }

       .stats-grid {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
           gap: var(--spacing-md);
           margin-bottom: var(--spacing-lg);
       }

       .stat-card {
           background: var(--white);
           border-radius: var(--radius);
           padding: var(--spacing-lg);
           box-shadow: var(--shadow);
           transition: var(--transition);
           border: 1px solid rgba(0, 0, 0, 0.04);
       }

       .stat-card:hover {
           transform: translateY(-4px);
           box-shadow: var(--shadow-md);
       }

       .stat-card .stat-header {
           display: flex;
           align-items: center;
           justify-content: space-between;
           margin-bottom: var(--spacing-sm);
       }

       .stat-card .stat-icon {
           width: 48px;
           height: 48px;
           border-radius: var(--radius);
           display: flex;
           align-items: center;
           justify-content: center;
           font-size: var(--font-size-xl);
           color: var(--white);
       }

       .stat-card .stat-icon.primary {
           background: var(--primary-gradient);
       }

       .stat-card .stat-icon.secondary {
           background: var(--secondary-gradient);
       }

       .stat-card .stat-icon.green {
           background: linear-gradient(135deg, #2ECC71, #27AE60);
       }

       .stat-card .stat-icon.blue {
           background: linear-gradient(135deg, #3498DB, #2980B9);
       }

       .stat-card .stat-icon.purple {
           background: linear-gradient(135deg, #9B59B6, #8E44AD);
       }

       .stat-card .stat-icon.orange {
           background: linear-gradient(135deg, #F39C12, #E67E22);
       }

       .stat-card .stat-number {
           font-size: var(--font-size-3xl);
           font-weight: var(--font-weight-bold);
           color: var(--dark);
           line-height: 1.1;
       }

       .stat-card .stat-label {
           font-size: var(--font-size-sm);
           color: var(--gray-light);
           font-weight: var(--font-weight-medium);
           margin-top: var(--spacing-xs);
       }

       .stat-card .stat-change {
           font-size: var(--font-size-xs);
           font-weight: var(--font-weight-semibold);
           padding: 2px 10px;
           border-radius: var(--radius-full);
       }

       .stat-change.up {
           color: var(--success);
           background: var(--success-bg);
       }

       .stat-change.down {
           color: var(--error);
           background: var(--error-bg);
       }

       .content-grid {
           display: grid;
           grid-template-columns: 2fr 1fr;
           gap: var(--spacing-lg);
       }

       .content-card {
           background: var(--white);
           border-radius: var(--radius);
           box-shadow: var(--shadow);
           border: 1px solid rgba(0, 0, 0, 0.04);
           overflow: hidden;
       }

       .content-card .card-header {
           padding: var(--spacing-md) var(--spacing-lg);
           border-bottom: 1px solid var(--light-gray);
           display: flex;
           align-items: center;
           justify-content: space-between;
           flex-wrap: wrap;
           gap: var(--spacing-sm);
       }

       .content-card .card-header h3 {
           font-size: var(--font-size-md);
           font-weight: var(--font-weight-semibold);
           color: var(--dark);
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
       }

       .content-card .card-header h3 i {
           color: var(--primary);
       }

       .content-card .card-body {
           padding: var(--spacing-lg);
       }

       .placeholder-content {
           display: flex;
           flex-direction: column;
           align-items: center;
           justify-content: center;
           padding: var(--spacing-2xl) var(--spacing-lg);
           color: var(--gray-light);
           text-align: center;
       }

       .placeholder-content i {
           font-size: 3.5rem;
           color: var(--secondary-light);
           margin-bottom: var(--spacing-md);
       }

       .placeholder-content h4 {
           color: var(--gray);
           font-weight: var(--font-weight-semibold);
           margin-bottom: var(--spacing-xs);
       }

       .placeholder-content p {
           font-size: var(--font-size-sm);
           max-width: 300px;
           color: var(--gray-light);
       }

       .mobile-toggle {
           display: none;
           background: none;
           border: none;
           font-size: var(--font-size-xl);
           color: var(--dark);
           cursor: pointer;
           padding: var(--spacing-xs);
       }

       @media (min-width: 992px) {
           .user-info {
               display: block;
           }
       }

       @media (max-width: 991px) {
           .navbar-menu {
               display: none;
               position: absolute;
               top: var(--navbar-height);
               left: 0;
               right: 0;
               background: var(--white);
               flex-direction: column;
               padding: var(--spacing-md);
               box-shadow: var(--shadow-lg);
               border-bottom: 2px solid var(--secondary-light);
               gap: var(--spacing-xs);
           }

           .navbar-menu.open {
               display: flex;
           }

           .navbar-menu .nav-item {
               width: 100%;
               padding: var(--spacing-sm) var(--spacing-md);
               justify-content: flex-start;
           }

           .mobile-toggle {
               display: block;
           }

           .content-grid {
               grid-template-columns: 1fr;
           }

           .welcome-section {
               padding: var(--spacing-md);
               flex-direction: column;
               align-items: flex-start;
           }

           .welcome-text h1 {
               font-size: var(--font-size-xl);
           }

           .stats-grid {
               grid-template-columns: repeat(2, 1fr);
               gap: var(--spacing-sm);
           }

           .stat-card {
               padding: var(--spacing-md);
           }

           .stat-card .stat-number {
               font-size: var(--font-size-2xl);
           }
       }

       @media (max-width: 576px) {
           .navbar-dashboard {
               padding: 0 var(--spacing-sm);
               height: 56px;
           }

           .navbar-brand .brand-text {
               font-size: var(--font-size-sm);
           }

           .navbar-brand .brand-text small {
               display: none;
           }

           .navbar-brand img {
               height: 28px;
           }

           .main-content {
               padding: var(--spacing-sm);
           }

           .stats-grid {
               grid-template-columns: 1fr 1fr;
               gap: var(--spacing-xs);
           }

           .stat-card {
               padding: var(--spacing-sm);
           }

           .stat-card .stat-icon {
               width: 36px;
               height: 36px;
               font-size: var(--font-size-md);
           }

           .stat-card .stat-number {
               font-size: var(--font-size-xl);
           }

           .stat-card .stat-label {
               font-size: var(--font-size-xs);
           }

           .welcome-text h1 {
               font-size: var(--font-size-lg);
           }

           .welcome-badge {
               font-size: var(--font-size-xs);
               padding: var(--spacing-xs) var(--spacing-md);
           }

           .content-card .card-header {
               padding: var(--spacing-sm) var(--spacing-md);
           }

           .content-card .card-body {
               padding: var(--spacing-md);
           }

           .user-info {
               display: none !important;
           }

           .user-menu-trigger {
               padding: 4px;
           }

           .user-avatar {
               width: 32px;
               height: 32px;
               font-size: var(--font-size-xs);
           }

           .navbar-actions .action-btn {
               width: 34px;
               height: 34px;
               font-size: var(--font-size-sm);
           }

           .user-dropdown {
               min-width: 200px;
               right: -8px;
           }
       }


       @keyframes fadeInUp {
           from {
               opacity: 0;
               transform: translateY(20px);
           }

           to {
               opacity: 1;
               transform: translateY(0);
           }
       }

       .welcome-section {
           animation: fadeInUp 0.5s ease;
       }

       .stat-card {
           animation: fadeInUp 0.5s ease forwards;
           opacity: 0;
       }

       .stat-card:nth-child(1) {
           animation-delay: 0.05s;
       }

       .stat-card:nth-child(2) {
           animation-delay: 0.1s;
       }

       .stat-card:nth-child(3) {
           animation-delay: 0.15s;
       }

       .stat-card:nth-child(4) {
           animation-delay: 0.2s;
       }

       .content-card {
           animation: fadeInUp 0.5s ease forwards;
           opacity: 0;
       }

       .content-card:nth-child(1) {
           animation-delay: 0.1s;
       }

       .content-card:nth-child(2) {
           animation-delay: 0.15s;
       }



       /* Dropdown principal */
       .nav-dropdown {
           position: relative;
           display: inline-block;
       }

       .nav-dropdown-trigger {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: 8px 16px;
           border-radius: var(--radius);
           color: var(--gray-dark);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           transition: var(--transition);
           cursor: pointer;
           border: none;
           background: transparent;
           font-family: var(--font-family);
           position: relative;
       }

       .nav-dropdown-trigger:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .nav-dropdown-trigger.active {
           background: var(--primary);
           color: var(--white);
           box-shadow: var(--shadow-primary);
       }

       .nav-dropdown-trigger.active:hover {
           background: var(--primary-light);
       }

       .nav-dropdown-trigger i {
           font-size: var(--font-size-md);
       }

       .dropdown-arrow {
           font-size: var(--font-size-xs);
           transition: var(--transition);
           margin-left: 4px;
       }

       .nav-dropdown.open .dropdown-arrow {
           transform: rotate(180deg);
       }

       /* Menú desplegable */
       .nav-dropdown-menu {
           position: absolute;
           top: calc(100% + 8px);
           left: 0;
           min-width: 240px;
           background: var(--white);
           border-radius: var(--radius);
           box-shadow: var(--shadow-xl);
           padding: var(--spacing-sm);
           opacity: 0;
           visibility: hidden;
           transform: translateY(-10px);
           transition: var(--transition);
           border: 1px solid rgba(0, 0, 0, 0.05);
           z-index: 999;
       }

       .nav-dropdown.open .nav-dropdown-menu {
           opacity: 1;
           visibility: visible;
           transform: translateY(0);
       }

       /* Items del menú */
       .nav-dropdown-menu .dropdown-item {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: var(--spacing-sm) var(--spacing-md);
           color: var(--gray-dark);
           text-decoration: none;
           border-radius: var(--radius-sm);
           transition: var(--transition);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           border: none;
           background: transparent;
           width: 100%;
           cursor: pointer;
           font-family: var(--font-family);
       }

       .nav-dropdown-menu .dropdown-item:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .nav-dropdown-menu .dropdown-item i {
           width: 20px;
           text-align: center;
           font-size: var(--font-size-md);
           color: var(--gray-light);
       }

       .nav-dropdown-menu .dropdown-item:hover i {
           color: var(--primary);
       }

       .nav-dropdown-menu .dropdown-divider {
           height: 1px;
           background: var(--light-gray);
           margin: var(--spacing-xs) var(--spacing-sm);
       }



       .dropdown-submenu {
           position: relative;
       }

       .dropdown-submenu-trigger {
           display: flex;
           align-items: center;
           gap: var(--spacing-sm);
           padding: var(--spacing-sm) var(--spacing-md);
           color: var(--gray-dark);
           text-decoration: none;
           border-radius: var(--radius-sm);
           transition: var(--transition);
           font-size: var(--font-size-sm);
           font-weight: var(--font-weight-medium);
           border: none;
           background: transparent;
           width: 100%;
           cursor: pointer;
           font-family: var(--font-family);
       }

       .dropdown-submenu-trigger:hover {
           background: var(--light-gray);
           color: var(--primary);
       }

       .dropdown-submenu-trigger i:first-child {
           width: 20px;
           text-align: center;
           font-size: var(--font-size-md);
           color: var(--gray-light);
       }

       .dropdown-submenu-trigger:hover i:first-child {
           color: var(--primary);
       }

       .submenu-arrow {
           margin-left: auto;
           font-size: var(--font-size-xs);
           color: var(--gray-light);
           transition: var(--transition);
       }

       .dropdown-submenu.open .submenu-arrow {
           transform: rotate(90deg);
           color: var(--primary);
       }

       /* Menú del submenú (anidado) */
       .dropdown-submenu-menu {
           position: absolute;
           top: 0;
           left: 100%;
           min-width: 220px;
           background: var(--white);
           border-radius: var(--radius);
           box-shadow: var(--shadow-xl);
           padding: var(--spacing-sm);
           opacity: 0;
           visibility: hidden;
           transform: translateX(-10px);
           transition: var(--transition);
           border: 1px solid rgba(0, 0, 0, 0.05);
           z-index: 1000;
       }

       .dropdown-submenu.open .dropdown-submenu-menu {
           opacity: 1;
           visibility: visible;
           transform: translateX(0);
       }

       .dropdown-submenu-menu .dropdown-item {
           padding: var(--spacing-sm) var(--spacing-md);
           font-size: var(--font-size-sm);
           white-space: nowrap;
       }


       @media (max-width: 991px) {
           .nav-dropdown {
               width: 100%;
           }

           .nav-dropdown-trigger {
               width: 100%;
               justify-content: flex-start;
               padding: var(--spacing-sm) var(--spacing-md);
           }

           .nav-dropdown-trigger .dropdown-arrow {
               margin-left: auto;
           }

           .nav-dropdown-menu {
               position: static;
               box-shadow: none;
               padding-left: var(--spacing-lg);
               border: none;
               opacity: 1;
               visibility: visible;
               transform: none;
               display: none;
               background: transparent;
           }

           .nav-dropdown.open .nav-dropdown-menu {
               display: block;
           }

           .nav-dropdown-menu .dropdown-item {
               padding: var(--spacing-xs) var(--spacing-sm);
               font-size: var(--font-size-sm);
           }

           .nav-dropdown-menu .dropdown-divider {
               margin: var(--spacing-xs) 0;
           }

           /* Submenús en móvil */
           .dropdown-submenu-menu {
               position: static;
               box-shadow: none;
               padding-left: var(--spacing-lg);
               border: none;
               opacity: 1;
               visibility: visible;
               transform: none;
               display: none;
               background: transparent;
           }

           .dropdown-submenu.open .dropdown-submenu-menu {
               display: block;
           }

           .dropdown-submenu-trigger {
               padding: var(--spacing-xs) var(--spacing-sm);
               font-size: var(--font-size-sm);
           }

           /* Touch friendly */
           .dropdown-submenu-trigger,
           .dropdown-submenu-menu .dropdown-item,
           .nav-dropdown-trigger {
               padding: 12px 16px !important;
               min-height: 44px;
           }

           .dropdown-submenu-trigger:active,
           .dropdown-submenu-menu .dropdown-item:active,
           .nav-dropdown-trigger:active {
               background: var(--primary-light) !important;
               color: var(--white) !important;
           }

           .nav-dropdown-menu {
               max-height: 60vh;
               overflow-y: auto;
               -webkit-overflow-scrolling: touch;
           }
       }
   </style>
   <nav class="navbar-dashboard">
       <!-- Logo -->
       <a href="dashboard.php" class="navbar-brand">
           <span class="brand-symbol"><i class="fas fa-utensils"></i></span>
           <div class="brand-text">
               Restaurante Moderno
               <small>Comandas · Caja · Delivery</small>
           </div>
       </a>

       <!-- Botón Hamburguesa -->
       <button class="mobile-toggle" id="mobileToggle">
           <i class="fas fa-bars"></i>
       </button>

       <?php include __DIR__ . '/menu_nav.php'; ?><!-- Acciones derecha -->
       <div class="navbar-actions">

           <!-- User Menu -->
           <div class="user-menu" id="userMenu">
               <div class="user-menu-trigger" id="userMenuTrigger">
                   <div class="user-avatar" style="background: <?php echo $rolColor; ?>;">
                       <?php echo $iniciales; ?>
                   </div>
                   <div class="user-info">
                       <div class="name"><?php echo htmlspecialchars($nombreCompleto); ?></div>
                       <div class="role"><?php echo htmlspecialchars($rolNombre); ?></div>
                   </div>
                   <i class="fas fa-chevron-down arrow"></i>
               </div>

               <div class="user-dropdown">
                   <div class="dropdown-header">
                       <div class="dname"><?php echo htmlspecialchars($nombreCompleto); ?></div>
                       <div class="demail"><?php echo htmlspecialchars($email); ?></div>
                   </div>

                   <a href="mi-perfil.php" class="dropdown-item">
                       <i class="fas fa-user-circle"></i> Mi Perfil
                   </a>

                   <div class="dropdown-divider"></div>
                   <a href="../logout.php" class="dropdown-item danger">
                       <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                   </a>
               </div>
           </div>
       </div>
   </nav>