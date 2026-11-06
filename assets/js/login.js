/**
 * =====================================================
 * RESTAURANTE MODERNO - Acceso
 * JS moderno (ES2020) + SweetAlert2 para notificaciones
 * =====================================================
 * Flujo: validar -> POST JSON a api/login.php (con CSRF)
 *        -> sistema/dashboard.php
 * =====================================================
 */

(() => {
  "use strict";

  /** Endpoint de autenticación (relativo a la raíz del proyecto) */
  const API_LOGIN = "api/login.php";

  /** Tiempo máximo de espera de la petición (ms) */
  const TIMEOUT_MS = 15000;

  const $ = (sel, ctx = document) => ctx.querySelector(sel);

  const form = $("#loginForm");
  const inputUsuario = $("#email");
  const inputPassword = $("#password");
  const btnToggle = $("#togglePassword");
  const btnSubmit = $("#loginBtn");
  const chkRecordar = $("#remember");
  const metaCsrf = document.querySelector('meta[name="csrf-token"]');

  if (!form) return;

  const csrfToken = metaCsrf?.content ?? "";

  // ============================================
  // NOTIFICACIONES (SweetAlert2 en modo toast)
  // ============================================
  const avisar = (titulo, texto, tipo = "info", tiempo = 4000) => {
    if (typeof Swal === "undefined") {
      console.warn(`[${tipo}] ${titulo}: ${texto}`);
      return;
    }
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: tipo,
      title: titulo,
      text: texto,
      showConfirmButton: false,
      timer: tiempo,
      timerProgressBar: true,
    });
  };

  // ============================================
  // RECORDAR USUARIO (sólo el nombre, nunca un token)
  // ============================================
  const KEY_RECORDAR = "rm_login_usuario";

  const guardarUsuario = (valor) => {
    try {
      if (chkRecordar?.checked) {
        localStorage.setItem(KEY_RECORDAR, valor);
      } else {
        localStorage.removeItem(KEY_RECORDAR);
      }
    } catch {
      /* almacenamiento bloqueado: se ignora */
    }
  };

  const recordarUsuario = () => {
    try {
      const guardado = localStorage.getItem(KEY_RECORDAR);
      if (guardado && !inputUsuario.value) inputUsuario.value = guardado;
      if (guardado && chkRecordar) chkRecordar.checked = true;
    } catch {
      /* ignorar */
    }
  };

  // ============================================
  // MOSTRAR / OCULTAR CONTRASEÑA
  // ============================================
  btnToggle?.addEventListener("click", () => {
    const visible = inputPassword.type === "text";
    inputPassword.type = visible ? "password" : "text";
    btnToggle.setAttribute(
      "aria-label",
      visible ? "Mostrar contraseña" : "Ocultar contraseña"
    );
    btnToggle.innerHTML = visible
      ? '<i class="fa-solid fa-eye"></i>'
      : '<i class="fa-solid fa-eye-slash"></i>';
    inputPassword.focus({ preventScroll: true });
  });

  // ============================================
  // VALIDACIÓN EN LÍNEA
  // ============================================
  const marcarError = (input, mensaje = "") => {
    const campo = input.closest(".field");
    const msg = campo?.querySelector(".field-msg");
    campo?.classList.toggle("error", Boolean(mensaje));
    if (msg) msg.textContent = mensaje;
  };

  const validarUsuario = () => {
    const valor = inputUsuario.value.trim();
    if (!valor) {
      marcarError(inputUsuario, "Ingresa tu usuario o correo.");
      return false;
    }
    if (valor.length < 3) {
      marcarError(inputUsuario, "Debe tener al menos 3 caracteres.");
      return false;
    }
    marcarError(inputUsuario, "");
    return true;
  };

  const validarPassword = () => {
    const valor = inputPassword.value;
    if (!valor) {
      marcarError(inputPassword, "La contraseña es obligatoria.");
      return false;
    }
    if (valor.length < 8) {
      marcarError(inputPassword, "Mínimo 8 caracteres.");
      return false;
    }
    marcarError(inputPassword, "");
    return true;
  };

  inputUsuario.addEventListener("blur", validarUsuario);
  inputUsuario.addEventListener("input", () => {
    if (inputUsuario.closest(".field")?.classList.contains("error")) validarUsuario();
  });

  inputPassword.addEventListener("blur", validarPassword);
  inputPassword.addEventListener("input", () => {
    if (inputPassword.closest(".field")?.classList.contains("error")) validarPassword();
  });

  // ============================================
  // ESTADO DEL BOTÓN
  // ============================================
  const setLoad = (activo) => {
    form.setAttribute("aria-busy", String(activo));
    [inputUsuario, inputPassword, chkRecordar].forEach(
      (el) => el && (el.disabled = activo)
    );
    btnSubmit.disabled = activo;
    btnSubmit.classList.toggle("loading", activo);
    btnSubmit.innerHTML = activo
      ? '<span class="spinner" aria-hidden="true"></span><span class="btn-text">Verificando</span>'
      : '<span class="btn-text">Ingresar</span><i class="fa-solid fa-arrow-right"></i>';
  };

  // ============================================
  // PETICIÓN A LA API
  // ============================================
  const autenticar = async (usuario, password) => {
    const control = new AbortController();
    const reloj = setTimeout(() => control.abort(), TIMEOUT_MS);

    try {
      const respuesta = await fetch(API_LOGIN, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify({ email: usuario, password }),
        credentials: "same-origin",
        signal: control.signal,
      });

      // El servidor siempre responde JSON; si no, hay un problema del entorno.
      const datos = await respuesta.json().catch(() => null);

      if (!datos) {
        return { success: false, message: "Respuesta inválida del servidor." };
      }
      return { ...datos, status: respuesta.status };
    } catch (error) {
      const tardado = error?.name === "AbortError";
      return {
        success: false,
        message: tardado
          ? "El servidor tardó demasiado. Inténtalo de nuevo."
          : "No se pudo conectar con el servidor.",
      };
    } finally {
      clearTimeout(reloj);
    }
  };

  // ============================================
  // ENVÍO DEL FORMULARIO
  // ============================================
  const enviar = async (evento) => {
    evento.preventDefault();

    const okUsuario = validarUsuario();
    const okPassword = validarPassword();
    if (!okUsuario || !okPassword) {
      (okUsuario ? inputPassword : inputUsuario).focus();
      return; // nunca se peticiona con datos inválidos
    }

    const usuario = inputUsuario.value.trim();
    const password = inputPassword.value;

    setLoad(true);
    guardarUsuario(usuario);

    const rpta = await autenticar(usuario, password);

    if (rpta.success) {
      avisar(
        "¡Bienvenido!",
        rpta.message || "Sesión iniciada correctamente",
        "success",
        2500
      );

      const destino = rpta?.data?.redirect || "sistema/dashboard.php";
      // Pausa breve para que el aviso sea visible antes de navegar
      setTimeout(() => window.location.assign(destino), 650);
      return; // botón bloqueado: la página va a cambiar
    }

    avisar(
      "Acceso denegado",
      rpta.message || "No se pudo iniciar sesión",
      "error",
      5000
    );
    setLoad(false);
    inputPassword.value = "";
    inputPassword.focus();
  };

  form.addEventListener("submit", enviar);

  // Enter en el formulario dispara el mismo envío validado
  form.addEventListener("keydown", (evento) => {
    if (evento.key === "Enter") {
      evento.preventDefault();
      if (!btnSubmit.disabled) form.requestSubmit();
    }
  });

  // ============================================
  // ARRANQUE
  // ============================================
  recordarUsuario();
  inputUsuario.focus({ preventScroll: true });
})();