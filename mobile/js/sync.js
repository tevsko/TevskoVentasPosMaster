/**
 * SpacePark Mobile - Sincronización Automática
 */

/**
 * Intenta sincronizar todos los reportes pendientes
 */
async function syncPendingReports() {
    if (!navigator.onLine) return;

    const pendingReports = await window.spOffline.getPendingReports();
    if (pendingReports.length === 0) return;

    console.log(`Iniciando sincronización de ${pendingReports.length} reportes...`);

    let successCount = 0;
    let failCount = 0;

    for (const report of pendingReports) {
        try {
            const success = await sendReportToServer(report);
            if (success) {
                await window.spOffline.removeSyncedReport(report.id);
                successCount++;
            } else {
                failCount++;
            }
        } catch (error) {
            console.error('Error sincronizando reporte:', error);
            failCount++;
        }
    }

    if (successCount > 0) {
        showSyncNotification(`Sincronizados ${successCount} reportes pendientes.`);
    }
}

/**
 * Envía un solo reporte al servidor
 */
async function sendReportToServer(reportData) {
    const API_SUBMIT = `${window.location.origin}/api/mobile/submit_report.php`;

    try {
        const response = await fetch(API_SUBMIT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(reportData)
        });

        const data = await response.json();
        return response.ok && data.success;
    } catch (error) {
        return false;
    }
}

/**
 * Escucha cambios de conectividad
 */
window.addEventListener('online', () => {
    console.log('Dispositivo online. Iniciando sincronización...');
    updateConnectionStatus(true);
    syncPendingReports();
});

window.addEventListener('offline', () => {
    console.log('Dispositivo offline.');
    updateConnectionStatus(false);
});

/**
 * Actualiza el indicador visual de conexión
 */
function updateConnectionStatus(isOnline) {
    const statusIndicator = document.getElementById('connection-status');
    if (!statusIndicator) return;

    if (isOnline) {
        statusIndicator.textContent = 'En línea';
        statusIndicator.className = 'status-badge online';
    } else {
        statusIndicator.textContent = 'Modo Offline';
        statusIndicator.className = 'status-badge offline';
    }
}

/**
 * Muestra notificación de sincronización
 */
function showSyncNotification(message) {
    // Aquí podríamos usar la API de Notificaciones si el usuario dio permiso
    // Por ahora lo mostramos en el alert-container de la app
    if (typeof showAlert === 'function') {
        showAlert(message, 'success');
    }
}

// Iniciar chequeo al cargar
document.addEventListener('DOMContentLoaded', () => {
    updateConnectionStatus(navigator.onLine);
    if (navigator.onLine) {
        syncPendingReports();
    }
});

window.spSync = {
    syncPendingReports
};
