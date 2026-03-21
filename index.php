<?php
/**
 * VIZENGO - Página Principal / Login
 * Sistema de Gestión de Pedidos de Ropa Deportiva
 */
require_once 'config.php';
startSecureSession();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Sistema de Gestión de Pedidos</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #2B4FFF; --primary-dark: #1a35cc; --accent: #FFD23F;
            --success: #06d6a0; --danger: #ef476f;
            --bg: #0d1117; --surface: #161b22; --border: rgba(255,255,255,0.08);
            --text: #e6edf3; --muted: #8b949e;
        }
        body {
            font-family: 'Barlow', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex;
            align-items: center; justify-content: center;
            overflow: hidden; position: relative;
        }
        .bg-grid {
            position: fixed; inset: 0;
            background-image: linear-gradient(rgba(43,79,255,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(43,79,255,0.05) 1px, transparent 1px);
            background-size: 40px 40px; z-index: 0;
        }
        .bg-glow {
            position: fixed; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(43,79,255,0.12) 0%, transparent 70%);
            top: -100px; right: -100px; z-index: 0;
            animation: pulse 6s ease-in-out infinite;
        }
        .bg-glow-2 {
            position: fixed; width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,210,63,0.06) 0%, transparent 70%);
            bottom: -50px; left: -50px; z-index: 0;
            animation: pulse 8s ease-in-out infinite reverse;
        }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.1);opacity:0.7} }
        .login-wrapper {
            position: relative; z-index: 1;
            width: 100%; max-width: 440px; padding: 20px;
            animation: fadeUp 0.6s ease;
        }
        @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
        .brand { text-align: center; margin-bottom: 32px; }
        .brand-logo {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 3rem; font-weight: 800;
            letter-spacing: 2px; color: white; line-height: 1;
        }
        .brand-logo span { color: var(--accent); }
        .brand-tagline { font-size: 0.8rem; color: var(--muted); letter-spacing: 3px; text-transform: uppercase; margin-top: 4px; }
        .login-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px; padding: 36px;
        }
        .login-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.4rem; font-weight: 700; color: var(--text);
            margin-bottom: 24px; text-transform: uppercase; letter-spacing: 1px;
        }
        .field-group { margin-bottom: 18px; }
        .field-label {
            display: block; font-size: 0.78rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--muted); margin-bottom: 8px;
        }
        .field-input {
            width: 100%; background: rgba(255,255,255,0.04);
            border: 1px solid var(--border); border-radius: 10px;
            padding: 12px 16px; color: var(--text);
            font-family: 'Barlow', sans-serif; font-size: 0.95rem;
            transition: all 0.2s; outline: none;
        }
        .field-input:focus {
            border-color: var(--primary); background: rgba(43,79,255,0.06);
            box-shadow: 0 0 0 3px rgba(43,79,255,0.15);
        }
        .field-input::placeholder { color: #484f58; }
        .btn-login {
            width: 100%; background: var(--primary); color: white; border: none;
            border-radius: 10px; padding: 14px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
            cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden;
        }
        .btn-login:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-login:disabled { background: var(--muted); cursor: not-allowed; }
        .error-msg { color: var(--danger); font-size: 0.82rem; margin-top: 12px; text-align: center; display: none; }
        .error-msg.show { display: block; animation: shake 0.3s; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
        .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
        .divider-line { flex: 1; height: 1px; background: var(--border); }
        .divider-text { font-size: 0.75rem; color: var(--muted); white-space: nowrap; }
        .demo-access { display: flex; flex-direction: column; gap: 8px; }
        .demo-btn {
            background: transparent; border: 1px solid var(--border); border-radius: 8px;
            padding: 10px 14px; color: var(--muted); font-family: 'Barlow', sans-serif;
            font-size: 0.82rem; cursor: pointer; text-align: left;
            display: flex; align-items: center; gap: 10px; transition: all 0.2s;
        }
        .demo-btn:hover { border-color: rgba(255,255,255,0.2); color: var(--text); background: rgba(255,255,255,0.03); }
        .demo-btn i { width: 16px; color: var(--accent); }
        .footer-note { text-align: center; margin-top: 20px; font-size: 0.78rem; color: #484f58; }
        .spinner { display: none; width: 16px; height: 16px; border: 2px solid transparent; border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; }
        .btn-login.loading .spinner { display: inline-block; }
        .btn-login.loading .btn-text { opacity: 0.7; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow"></div>
    <div class="bg-glow-2"></div>
    <div class="login-wrapper">
        <div class="brand">
            <div class="brand-logo">VIZEN<span>GO</span></div>
            <div class="brand-tagline">Sistema de Gestión de Pedidos</div>
        </div>
        <div class="login-card">
            <div class="login-title">Iniciar Sesión</div>
            <form id="loginForm" onsubmit="return handleLogin(event)">
                <div class="field-group">
                    <label class="field-label" for="inputUsuario">Usuario</label>
                    <input type="text" id="inputUsuario" class="field-input" placeholder="Tu nombre de usuario" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="inputPassword">Contraseña</label>
                    <input type="password" id="inputPassword" class="field-input" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="fas fa-sign-in-alt" style="margin-right:8px;"></i>INGRESAR</span>
                </button>
                <div class="error-msg" id="errorMsg"><i class="fas fa-exclamation-circle"></i> <span id="errorText">Usuario o contraseña incorrectos.</span></div>
            </form>
            
            <div class="divider">
                <div class="divider-line"></div>
                <div class="divider-text">Acceso rápido (Demo)</div>
                <div class="divider-line"></div>
            </div>
            
            <div class="demo-access">
                <button class="demo-btn" onclick="demoLogin('luis', 'password')">
                    <i class="fas fa-handshake"></i>
                    <span>Luis - Vendedor</span>
                </button>
                <button class="demo-btn" onclick="demoLogin('carolina', 'password')">
                    <i class="fas fa-palette"></i>
                    <span>Carolina - Diseñador</span>
                </button>
                <button class="demo-btn" onclick="demoLogin('admin', 'password')">
                    <i class="fas fa-shield-alt"></i>
                    <span>Admin - Administrador</span>
                </button>
            </div>
        </div>
        <div class="footer-note">© 2025 VIZENGO · Ropa Deportiva por Pedidos</div>
    </div>

    <script>
        // API Base URL
        const API_URL = 'api/auth.php';
        
        // Demo login
        function demoLogin(username, password) {
            document.getElementById('inputUsuario').value = username;
            document.getElementById('inputPassword').value = password;
            handleLogin(new Event('submit'));
        }
        
        // Handle login
        async function handleLogin(e) {
            e.preventDefault();
            
            const username = document.getElementById('inputUsuario').value.trim();
            const password = document.getElementById('inputPassword').value;
            const btn = document.getElementById('btnLogin');
            const errorMsg = document.getElementById('errorMsg');
            const errorText = document.getElementById('errorText');
            
            if (!username || !password) {
                showError('Por favor complete todos los campos');
                return false;
            }
            
            // Show loading
            btn.classList.add('loading');
            btn.disabled = true;
            errorMsg.classList.remove('show');
            
            try {
                const response = await fetch(API_URL + '?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Save user data to sessionStorage
                    sessionStorage.setItem('vz_user', JSON.stringify(data.data.user));
                    sessionStorage.setItem('vz_rol', data.data.user.rol);
                    sessionStorage.setItem('vz_nombre', data.data.user.nombre);
                    sessionStorage.setItem('vz_csrf', data.data.csrf_token);
                    
                    // Redirect to dashboard
                    window.location.href = 'dashboard.php';
                } else {
                    showError(data.error || 'Error al iniciar sesión');
                }
            } catch (error) {
                console.error('Login error:', error);
                showError('Error de conexión. Intente nuevamente.');
            } finally {
                btn.classList.remove('loading');
                btn.disabled = false;
            }
            
            return false;
        }
        
        function showError(message) {
            const errorMsg = document.getElementById('errorMsg');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorMsg.classList.add('show');
            setTimeout(() => errorMsg.classList.remove('show'), 5000);
        }
        
        // Enter key support
        document.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                handleLogin(e);
            }
        });
    </script>
</body>
</html>
