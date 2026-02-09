/**
 * SpacePark Mobile - Aplicación Principal (Versión Diagnóstico 1.8 - Máxima Compatibilidad)
 */

(function () {
    console.log('[DEBUG] Script app.js iniciado');

    // 1. Capturador de errores global
    window.onerror = function (msg, url, lineNo, columnNo, error) {
        var errText = "[CRASH] " + msg + " (L:" + lineNo + ")";
        console.error(errText, error);

        var locName = document.getElementById('location-name');
        if (locName) locName.textContent = 'ERROR FATAL';

        var alertBox = document.getElementById('alert-container');
        if (alertBox) {
            alertBox.innerHTML = '<div style="background:#fee2e2; border:1px solid #ef4444; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px; font-family:sans-serif;">' +
                '<strong>Error de Sistema:</strong><br>' + errText + '<br>' +
                '<p style="margin-top:10px; font-size:12px;">Esto suele ocurrir por scripts viejos en caché o navegador incompatible.</p>' +
                '<button onclick="location.href=\'/mobile/index.html\'" style="background:#b91c1c; color:white; border:none; padding:8px 12px; border-radius:4px; margin-top:5px; cursor:pointer;">Ir al Login y Reiniciar</button>' +
                '</div>';
        }
        return false;
    };

    // Configuración
    var API_BASE = window.location.origin;
    var API_PRODUCTS = API_BASE + '/api/mobile/get_products.php';
    var API_SUBMIT = API_BASE + '/api/mobile/submit_report.php';

    // Estado global
    var products = [];
    var photoBase64 = null;
    var expenseCount = 0;

    /**
     * Inicio de la aplicación
     */
    async function startApp() {
        console.log('[DEBUG] startApp() ejecutando...');
        var locHeader = document.getElementById('location-name');
        if (locHeader) locHeader.textContent = 'Verificando...';

        try {
            // Paso 1: Verificar spMobile
            if (!window.spMobile) {
                // Si no está, esperamos medio segundo por si es un tema de carga parcial
                await new Promise(function (r) { setTimeout(r, 500); });
                if (!window.spMobile) throw new Error('Carga incompleta (auth.js faltante).');
            }

            var token = window.spMobile.getToken();
            if (!token || token === 'null' || token === 'undefined') {
                console.warn('[DEBUG] Sin sesión, redirigiendo al login');
                window.location.href = '/mobile/';
                return;
            }

            // Paso 2: Cargar Perfil
            if (locHeader) locHeader.textContent = 'Cargando Perfil...';
            loadEmployeeData();

            // Paso 3: Fecha Hoy
            var reportDateInput = document.getElementById('report-date');
            if (reportDateInput) {
                reportDateInput.value = new Date().toISOString().split('T')[0];
            }

            // Paso 4: Cargar Productos
            if (locHeader) locHeader.textContent = 'Buscando Productos...';
            await loadProducts();

            // Paso 5: Activar Cálculos
            setupCalculationListeners();

            console.log('[DEBUG] Inicialización terminada.');

        } catch (err) {
            console.error('[DEBUG] Falla en inicio:', err.message);
            if (locHeader) locHeader.textContent = 'ERROR';
            showAlert('Falla al iniciar: ' + err.message, 'error');
        }
    }

    function loadEmployeeData() {
        var employee = window.spMobile.getEmployee();
        var locEl = document.getElementById('location-name');
        var empEl = document.getElementById('employee-name');
        var salEl = document.getElementById('salary-amount');

        if (employee && employee.name) {
            if (locEl) locEl.textContent = employee.location_name || 'Mi Arcade';
            if (empEl) empEl.textContent = employee.name;
            if (salEl) salEl.textContent = formatCurrency(employee.daily_salary || 0);
        } else {
            throw new Error('Datos de sesión corruptos. Re-ingresa.');
        }
    }

    async function loadProducts() {
        var pContainer = document.getElementById('products-container');
        var token = window.spMobile.getToken();
        var employee = window.spMobile.getEmployee();

        if (!employee || !employee.location_id) throw new Error('ID de local no encontrado.');

        try {
            var url = API_PRODUCTS + '?token=' + token + '&location_id=' + employee.location_id;
            var response = await fetch(url);

            if (!response.ok) {
                if (response.status === 429) throw new Error('Bloqueo por exceso de uso (429). Espera 2 min.');
                throw new Error('Error de red: ' + response.status);
            }

            var data = await response.json();
            if (data && data.success) {
                products = data.products;
                if (window.spOffline) window.spOffline.cacheProducts(employee.location_id, products);
                renderProducts();
            } else {
                throw new Error(data.error || 'No se pudieron cargar productos.');
            }
        } catch (error) {
            console.warn('[DEBUG] Error de red, intentando caché local:', error.message);
            if (window.spOffline) {
                var cached = await window.spOffline.getCachedProducts(employee.location_id);
                if (cached) {
                    products = cached;
                    renderProducts();
                    showAlert('Modo Offline: Datos cargados de memoria.', 'warning');
                } else {
                    if (pContainer) pContainer.innerHTML = '<p style="color:red; text-align:center;">' + error.message + '</p>';
                }
            } else {
                if (pContainer) pContainer.innerHTML = '<p style="color:red; text-align:center;">' + error.message + '</p>';
            }
        }
    }

    function renderProducts() {
        var container = document.getElementById('products-container');
        if (!container) return;

        if (!products || products.length === 0) {
            container.innerHTML = '<p style="color:gray; text-align:center;">Sin productos activos.</p>';
            return;
        }

        var html = '';
        for (var i = 0; i < products.length; i++) {
            var p = products[i];
            html += '<div style="margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">' +
                '<label style="display:block; margin-bottom:4px; font-weight:bold; font-size:14px;">' + (p.name || 'Prod') + ' - ' + formatCurrency(p.price) + '</label>' +
                '<input type="number" class="product-quantity" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;" ' +
                'data-id="' + p.id + '" data-price="' + p.price + '" inputmode="numeric" min="0" value="0">' +
                '</div>';
        }
        container.innerHTML = html;

        var inputs = document.querySelectorAll('.product-quantity');
        for (var j = 0; j < inputs.length; j++) {
            inputs[j].addEventListener('input', calculateSales);
        }
    }

    function setupCalculationListeners() {
        var ids = ['cash-received', 'mercadopago-received', 'transfer-received'];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (el) el.addEventListener('input', calculatePayments);
        }
        var salInput = document.getElementById('employee-salary-input');
        if (salInput) salInput.addEventListener('input', calculateExpectedCash);
    }

    function calculateSales() {
        var total = 0;
        var inputs = document.querySelectorAll('.product-quantity');
        for (var i = 0; i < inputs.length; i++) {
            total += (parseFloat(inputs[i].value) || 0) * (parseFloat(inputs[i].dataset.price) || 0);
        }
        updateValueDisplay('total-sales', total);
        updateValueDisplay('summary-sales', total);
        calculateExpectedCash();
    }

    function calculatePayments() {
        var c = parseFloat(document.getElementById('cash-received').value) || 0;
        var m = parseFloat(document.getElementById('mercadopago-received').value) || 0;
        var t = parseFloat(document.getElementById('transfer-received').value) || 0;
        var total = c + m + t;
        updateValueDisplay('total-payments', total);
        updateValueDisplay('summary-payments', total);
        calculateExpectedCash();
    }

    function calculateExpenses() {
        var total = 0;
        var inputs = document.querySelectorAll('.expense-amount');
        for (var i = 0; i < inputs.length; i++) {
            total += parseFloat(inputs[i].value) || 0;
        }
        updateValueDisplay('total-expenses', total);
        updateValueDisplay('summary-expenses', total);
        calculateExpectedCash();
    }

    function calculateExpectedCash() {
        var tp = parseNumberFromCurrency('total-payments');
        var te = parseNumberFromCurrency('total-expenses');

        var salEl = document.getElementById('employee-salary-input');
        var salary = parseFloat(salEl ? salEl.value : 0) || 0;

        var expected = tp - te - salary;

        updateValueDisplay('summary-salary', salary);
        updateValueDisplay('expected-cash-amount', expected);

        var display = document.getElementById('expected-cash-display');
        if (display) display.className = expected >= 0 ? 'expected-cash positive' : 'expected-cash negative';
    }

    // Funciones Helper
    function updateValueDisplay(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = formatCurrency(val);
    }

    function parseNumberFromCurrency(id) {
        var el = document.getElementById(id);
        if (!el) return 0;
        return parseFloat(el.textContent.replace(/[^0-9.-]+/g, '')) || 0;
    }

    function formatCurrency(v) {
        return '$' + parseFloat(v).toFixed(2);
    }

    function showAlert(msg, type) {
        var cont = document.getElementById('alert-container');
        if (cont) {
            var color = type === 'success' ? '#059669' : (type === 'warning' ? '#d97706' : '#dc2626');
            cont.innerHTML = '<div style="background:' + color + '10; color:' + color + '; border:1px solid ' + color + '; padding:10px; border-radius:5px; margin-bottom:15px; font-weight:bold;">' + msg + '</div>';
            window.scrollTo(0, 0);
        }
    }

    // Funciones globales para botones
    window.addExpense = function () {
        expenseCount++;
        var container = document.getElementById('expenses-container');
        if (!container) return;

        var div = document.createElement('div');
        div.className = 'expense-item';
        div.id = 'expense-' + expenseCount;
        div.style.background = '#f9fafb';
        div.style.padding = '10px';
        div.style.borderRadius = '8px';
        div.style.marginBottom = '10px';
        div.style.border = '1px solid #eee';

        div.innerHTML = '<input type="text" placeholder="¿En qué se gastó?" style="width:100%; margin-bottom:5px; padding:8px; border:1px solid #ccc; border-radius:4px;">' +
            '<input type="number" class="expense-amount" placeholder="Monto" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;" inputmode="numeric">' +
            '<button type="button" style="background:#dc2626; color:white; border:none; padding:5px 10px; border-radius:4px; margin-top:5px; font-size:12px;" onclick="removeExpense(' + expenseCount + ')">Eliminar</button>';

        container.appendChild(div);
        div.querySelector('.expense-amount').addEventListener('input', calculateExpenses);
    };

    window.removeExpense = function (id) {
        var el = document.getElementById('expense-' + id);
        if (el) { el.parentNode.removeChild(el); calculateExpenses(); }
    };

    window.capturePhoto = function () {
        var input = document.getElementById('photo-input');
        if (!input) return;
        input.click();
        input.onchange = async function (e) {
            var file = e.target.files[0];
            if (!file) return;
            try {
                var header = document.getElementById('location-name');
                if (header) header.textContent = 'Procesando...';

                var reader = new FileReader();
                reader.onload = function (ev) {
                    var img = new Image();
                    img.onload = function () {
                        var canvas = document.createElement('canvas');
                        var w = img.width; var h = img.height;
                        if (w > 800) { h = (h * 800) / w; w = 800; }
                        canvas.width = w; canvas.height = h;
                        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                        photoBase64 = canvas.toDataURL('image/jpeg', 0.6);

                        document.getElementById('photo-img').src = photoBase64;
                        document.getElementById('photo-preview').style.display = 'block';
                        document.getElementById('photo-preview').classList.remove('hidden');
                        document.getElementById('photo-btn').style.display = 'none';
                        document.getElementById('change-photo-btn').style.display = 'block';
                        document.getElementById('change-photo-btn').classList.remove('hidden');
                        loadEmployeeData();
                    };
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            } catch (err) {
                showAlert('Falla con la foto', 'error');
                loadEmployeeData();
            }
        };
    };

    window.submitReport = async function () {
        if (!photoBase64) { showAlert('Toma la foto del reporte manuscrito', 'error'); return; }
        if (!confirm('¿Seguro quieres enviar el reporte?')) return;

        var btn = document.getElementById('submit-btn');
        if (btn) { btn.disabled = true; btn.innerText = 'Enviando...'; }

        try {
            var date = document.getElementById('report-date').value;
            var sold = [];
            var qtyInputs = document.querySelectorAll('.product-quantity');
            for (var i = 0; i < qtyInputs.length; i++) {
                var q = parseFloat(qtyInputs[i].value) || 0;
                if (q > 0) sold.push({ product_id: qtyInputs[i].dataset.id, quantity: q });
            }

            var expenses = [];
            var exItems = document.querySelectorAll('.expense-item');
            for (var j = 0; j < exItems.length; j++) {
                var desc = exItems[j].querySelector('input[type="text"]').value;
                var amt = parseFloat(exItems[j].querySelector('.expense-amount').value) || 0;
                if (desc && amt > 0) expenses.push({ description: desc, amount: amt });
            }

            var salElInput = document.getElementById('employee-salary-input');
            var salaryVal = parseFloat(salElInput ? salElInput.value : 0) || 0;

            var payload = {
                token: window.spMobile.getToken(),
                report_date: date,
                products_sold: sold,
                cash_received: document.getElementById('cash-received').value,
                mercadopago_received: document.getElementById('mercadopago-received').value,
                transfer_received: document.getElementById('transfer-received').value,
                expenses: expenses,
                employee_salary: salaryVal,
                employee_paid: salaryVal > 0,
                photo_base64: photoBase64
            };

            var res = await fetch(API_SUBMIT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            var data = await res.json();
            if (data.success) {
                showAlert('¡Reporte Enviado!', 'success');
                setTimeout(function () { location.reload(); }, 2000);
            } else {
                throw new Error(data.error);
            }
        } catch (err) {
            showAlert('Error al enviar: ' + err.message, 'error');
            if (btn) { btn.disabled = false; btn.innerText = 'Reintentar Enviar'; }
        }
    };

    // Lanzamiento
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startApp);
    } else {
        startApp();
    }
})();
