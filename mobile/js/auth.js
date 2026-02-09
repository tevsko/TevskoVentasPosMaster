/**
 * SpacePark Mobile - Autenticación (Versión Compatibilidad 1.5)
 */

(function () {
    // Exportar funciones para uso global inmediatamente
    var clearSession = function () {
        localStorage.removeItem('sp_mobile_token');
        localStorage.removeItem('sp_mobile_employee');
    };

    window.spMobile = {
        logout: function () { logout(); },
        clearSession: clearSession,
        getToken: function () { return localStorage.getItem('sp_mobile_token'); },
        getEmployee: function () {
            try {
                return JSON.parse(localStorage.getItem('sp_mobile_employee') || '{}');
            } catch (e) { return {}; }
        }
    };

    var API_BASE = window.location.origin;
    var API_AUTH = API_BASE + '/api/mobile/auth.php';

    var deferredPrompt;

    document.addEventListener('DOMContentLoaded', function () {
        var path = window.location.pathname;
        // Compatibilidad: usar indexOf en lugar de endsWith
        var isLoginPage = (path.indexOf('/mobile/') !== -1 && path.indexOf('report.html') === -1) ||
            path.indexOf('index.html') !== -1;

        console.log('[Auth] Init - Path:', path, 'isLoginPage:', isLoginPage);

        if (isLoginPage) {
            var token = localStorage.getItem('sp_mobile_token');
            if (token) validateToken(token);

            // Manejo de Instalación PWA
            var installBtn = document.getElementById('pwa-install-btn');
            window.addEventListener('beforeinstallprompt', function (e) {
                console.log('[PWA] Detectada capacidad de instalación');
                e.preventDefault();
                deferredPrompt = e;
                if (installBtn) installBtn.style.display = 'block';
            });

            if (installBtn) {
                installBtn.addEventListener('click', function () {
                    if (!deferredPrompt) return;
                    installBtn.style.display = 'none';
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') {
                            console.log('[PWA] Usuario aceptó instalación');
                        }
                        deferredPrompt = null;
                    });
                });
            }
        }

        var loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }
    });

    async function handleLogin(e) {
        e.preventDefault();
        var userEl = document.getElementById('username');
        var passEl = document.getElementById('password');
        if (!userEl || !passEl) return;

        var username = userEl.value.trim();
        var password = passEl.value;

        if (!username || !password) {
            alert('Completa todos los campos');
            return;
        }

        var urlParams = new URLSearchParams(window.location.search);
        var tenant = urlParams.get('tenant');

        try {
            var res = await fetch(API_AUTH, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'login',
                    username: username,
                    password: password,
                    tenant: tenant
                })
            });
            var data = await res.json();

            if (data && data.success) {
                localStorage.setItem('sp_mobile_token', data.token);
                localStorage.setItem('sp_mobile_employee', JSON.stringify(data.employee));
                window.location.href = '/mobile/report.html';
            } else {
                alert(data.error || 'Credenciales inválidas');
            }
        } catch (error) {
            alert('Error de conexión');
        }
    }

    async function validateToken(token) {
        var isReportPage = window.location.href.indexOf('report.html') !== -1;

        try {
            var res = await fetch(API_AUTH, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'validate_token', token: token })
            });
            var data = await res.json();

            if (data && data.success) {
                localStorage.setItem('sp_mobile_employee', JSON.stringify(data.employee));
                if (!isReportPage) window.location.href = '/mobile/report.html';
            } else {
                if (isReportPage) {
                    clearSession();
                    window.location.href = '/mobile/';
                }
            }
        } catch (e) {
            console.warn('[Auth] Error validación serv:', e.message);
        }
    }

    async function logout() {
        var token = localStorage.getItem('sp_mobile_token');
        if (token) {
            try {
                await fetch(API_AUTH, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout', token: token })
                });
            } catch (e) { }
        }
        clearSession();
        window.location.href = '/mobile/';
    }
})();
