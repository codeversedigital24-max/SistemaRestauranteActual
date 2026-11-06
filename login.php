<?php
require_once 'config/config.php';

rm_iniciar_sesion();

// ============================================
// CSRF: token por sesión (lo consume api/login.php)
// ============================================
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Si ya hay una sesión válida, no volver a pedir credenciales.
if (!empty($_SESSION['token'])) {
    try {
        $login = new Login();
        if ($login->validarToken($_SESSION['token'])) {
            header('Location: sistema/dashboard.php');
            exit;
        }
    } catch (Throwable $e) {
        error_log("login.php: no se pudo validar la sesión previa - " . $e->getMessage());
    }
    // Token invencido/expirado: limpiar para no arrastrar basura.
    $_SESSION = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
    <meta name="color-scheme" content="light" />
    <title>Iniciar sesión | Restaurante Moderno</title>

    <!-- Tipografía -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet" />

    <!-- Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <!-- SweetAlert2: notificaciones -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Estilos propios -->
    <link rel="stylesheet" href="assets/css/login.css" />
</head>

<body>

<main class="auth">

    <!-- Panel de marca -->
    <aside class="auth-side" aria-hidden="true">
        <div class="brand">
            <span class="brand-mark"><i class="fa-solid fa-burger"></i></span>
            <span class="brand-name">Restaurante Moderno</span>
        </div>

        <div class="auth-side-body">
            <h1 class="auth-title">Tu cocina,<br /><span>bajo control.</span></h1>
            <p class="auth-lead">
                Pedidos, mesas, delivery e inventario en un solo lugar.
                Entra y sigue sirviendo.
            </p>

            <ul class="auth-features">
                <li><i class="fa-solid fa-circle-check"></i> Pedidos en tiempo real</li>
                <li><i class="fa-solid fa-circle-check"></i> Caja y delivery conectados</li>
                <li><i class="fa-solid fa-circle-check"></i> Inventario bajo control</li>
            </ul>
        </div>

        <p class="auth-side-foot">&copy; <?php echo date('Y'); ?> Restaurante Moderno &middot; v2.0</p>
    </aside>

    <!-- Formulario -->
    <section class="auth-form">
        <div class="auth-card">
            <header class="auth-head">
                <span class="auth-badge"><i class="fa-solid fa-utensils"></i> Bienvenido</span>
                <h2>Iniciar sesión</h2>
                <p>Ingresa con tu usuario o correo electrónico</p>
            </header>

            <form id="loginForm" autocomplete="off" novalidate>
                <div class="field">
                    <label for="email">Usuario o correo</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input
                            type="text"
                            id="email"
                            name="email"
                            placeholder="admin@restaurante.com"
                            required
                            minlength="3"
                            maxlength="100"
                            autocapitalize="none"
                            autocorrect="off"
                            spellcheck="false"
                            autofocus />
                    </div>
                    <small class="field-msg" id="emailError"></small>
                </div>

                <div class="field">
                    <label for="password">Contraseña</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            minlength="8"
                            maxlength="72"
                            autocomplete="current-password" />
                        <button type="button" class="toggle-pass" id="togglePassword" aria-label="Mostrar contraseña">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <small class="field-msg" id="passwordError"></small>
                </div>

                <div class="auth-options">
                    <label class="check">
                        <input type="checkbox" id="remember" />
                        <span>Recordarme</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary" id="loginBtn">
                    <span class="btn-text">Ingresar</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <footer class="auth-foot">
                <p><i class="fa-solid fa-shield-halved"></i> Sesión cifrada y protegida contra intentos repetidos</p>
            </footer>
        </div>
    </section>

</main>

<!-- Lógica de acceso -->
<script src="assets/js/login.js"></script>
</body>

</html>