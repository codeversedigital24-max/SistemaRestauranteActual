<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<title><?php echo $tituloPagina ?? 'Panel'; ?> | Restaurante Moderno</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
<!-- CSS Global del Sistema -->
<link rel="stylesheet" href="../assets/css/sistema.css" />
<link rel="stylesheet" href="../assets/css/formularios.css">
<!-- DataTable CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* Sólo particularidades de esta página (todo lo demás vive en modulos.css) */
    .contador-header {
        font-size: 12.5px;
        color: var(--mod-texto-tenue, #a79ea2);
        font-weight: 600;
    }

    /* Cabecera con acento de color */
    .content-card {
        border-top: 3px solid var(--mod-primaria, #d9232d);
    }
</style>
<style>
    .main-content {
        padding: var(--spacing-md);
        background: var(--white);
        min-height: calc(100vh - var(--navbar-height));
    }

    .form-container-fluid {
        max-width: 100%;
        margin: 0;
        padding: 0;
        background: var(--white);
    }

    .card-header-elegant {
        background: var(--primary-gradient);
        padding: var(--spacing-xl) var(--spacing-2xl);
        border-radius: var(--radius);
        margin-bottom: var(--spacing-xl);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: var(--spacing-md);
        box-shadow: var(--shadow-md);
    }

    .card-header-elegant .header-left {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }

    .card-header-elegant .header-icon {
        width: 56px;
        height: 56px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        backdrop-filter: blur(4px);
    }

    .card-header-elegant h1 {
        font-size: var(--font-size-2xl);
        font-weight: var(--font-weight-bold);
        color: var(--white);
        margin: 0;
        line-height: 1.2;
    }

    .card-header-elegant h1 small {
        display: block;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-regular);
        opacity: 0.8;
        margin-top: 2px;
    }

    .card-header-elegant .header-badge {
        background: rgba(255, 255, 255, 0.15);
        padding: var(--spacing-sm) var(--spacing-lg);
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }

    .card-header-elegant .header-badge i {
        font-size: var(--font-size-sm);
    }

    /* ============================================
           PAGE CONTAINER
           ============================================ */
    .page-container {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: var(--spacing-lg);
        padding: 0;
        background: var(--white);
    }

    .form-section {
        background: var(--white);
        border-radius: var(--radius);
        padding: var(--spacing-lg);
        box-shadow: var(--shadow);
        border: 1px solid rgba(0, 0, 0, 0.04);
        height: fit-content;
        position: sticky;
        top: calc(var(--navbar-height) + 20px);
    }

    .table-section {
        background: var(--white);
        border-radius: var(--radius);
        padding: var(--spacing-lg);
        box-shadow: var(--shadow);
        border: 1px solid rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .form-section .section-title {
        font-size: var(--font-size-md);
        font-weight: var(--font-weight-bold);
        color: var(--primary);
        margin-bottom: var(--spacing-md);
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }

    .form-section .section-title i {
        color: var(--secondary);
    }

    /* ============================================
           ESTILOS PARA DATATABLE
           ============================================ */
    .dataTables_wrapper {
        padding: 0;
    }

    .dataTables_wrapper .dataTables_filter input {
        border: 2px solid var(--gray-lighter);
        border-radius: var(--radius);
        padding: 8px 14px;
        font-family: var(--font-family);
        font-size: var(--font-size-sm);
        transition: var(--transition);
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(108, 27, 58, 0.08);
    }

    .dataTables_wrapper .dataTables_length select {
        border: 2px solid var(--gray-lighter);
        border-radius: var(--radius);
        padding: 8px 14px;
        font-family: var(--font-family);
        font-size: var(--font-size-sm);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 8px 14px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--gray-lighter);
        margin: 0 2px;
        transition: var(--transition);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--primary);
        color: var(--white) !important;
        border-color: var(--primary);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--light-gray);
        border-color: var(--primary);
    }

    .badge-success {
        background: var(--success-bg);
        color: var(--success);
        padding: 4px 12px;
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    .badge-danger {
        background: var(--error-bg);
        color: var(--error);
        padding: 4px 12px;
        border-radius: var(--radius-full);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    .text-primary {
        color: var(--primary);
        font-weight: var(--font-weight-semibold);
    }

    .btn-editar {
        background: var(--secondary);
        color: var(--white);
    }

    .btn-editar:hover {
        background: var(--secondary-dark);
        color: var(--white);
    }

    /* ============================================
           LOADING OVERLAY
           ============================================ */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .loading-overlay.active {
        display: flex;
    }

    .loading-overlay .spinner-box {
        background: var(--white);
        padding: var(--spacing-xl);
        border-radius: var(--radius);
        text-align: center;
        box-shadow: var(--shadow-xl);
    }

    .loading-overlay .spinner-box .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid var(--gray-lighter);
        border-top-color: var(--primary);
        border-radius: var(--radius-full);
        animation: spin 0.8s linear infinite;
        margin: 0 auto var(--spacing-md);
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ============================================
           RESPONSIVE
           ============================================ */
    @media (max-width: 1024px) {
        .page-container {
            grid-template-columns: 1fr;
            gap: var(--spacing-md);
        }

        .form-section {
            position: static;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: var(--spacing-sm);
        }

        .card-header-elegant {
            padding: var(--spacing-lg);
            flex-direction: column;
            align-items: flex-start;
        }

        .card-header-elegant .header-left {
            width: 100%;
        }

        .card-header-elegant h1 {
            font-size: var(--font-size-lg);
        }

        .card-header-elegant .header-icon {
            width: 44px;
            height: 44px;
            font-size: 1.4rem;
        }

        .form-section,
        .table-section {
            padding: var(--spacing-md);
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_length select {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .card-header-elegant .header-badge {
            display: none;
        }

        .page-container {
            gap: var(--spacing-sm);
        }

        .form-section,
        .table-section {
            padding: var(--spacing-sm);
        }
    }
</style>

<!-- Estilo compartido de módulos (tablas, formularios, modal, badges).
         Se carga al final del head: gana por orden sobre lo anterior. -->
<link rel="stylesheet" href="../assets/css/modulos.css" />