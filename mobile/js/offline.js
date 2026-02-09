/**
 * SpacePark Mobile - Almacenamiento Offline (IndexedDB)
 */

const DB_NAME = 'spaceparkMobile';
const DB_VERSION = 1;
const STORE_REPORTS = 'pendingReports';
const STORE_PRODUCTS = 'products';

/**
 * Abre la conexión a la base de datos
 */
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = (event) => {
            console.error('Error al abrir IndexedDB:', event.target.error);
            reject(event.target.error);
        };

        request.onsuccess = (event) => {
            resolve(event.target.result);
        };

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            // Almacén para reportes pendientes de sincronizar
            if (!db.objectStoreNames.contains(STORE_REPORTS)) {
                db.createObjectStore(STORE_REPORTS, { keyPath: 'id', autoIncrement: true });
            }

            // Almacén para caché de productos
            if (!db.objectStoreNames.contains(STORE_PRODUCTS)) {
                db.createObjectStore(STORE_PRODUCTS, { keyPath: 'location_id' });
            }
        };
    });
}

/**
 * Guarda un reporte offline
 */
async function saveOfflineReport(reportData) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_REPORTS], 'readwrite');
        const store = transaction.objectStore(STORE_REPORTS);

        // Agregar marca de tiempo local
        reportData.offline_at = new Date().toISOString();
        reportData.is_offline_sync = 1;

        const request = store.add(reportData);

        request.onsuccess = () => {
            console.log('Reporte guardado offline');
            resolve(request.result);
        };

        request.onerror = (event) => {
            console.error('Error al guardar reporte offline:', event.target.error);
            reject(event.target.error);
        };
    });
}

/**
 * Obtiene todos los reportes pendientes
 */
async function getPendingReports() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_REPORTS], 'readonly');
        const store = transaction.objectStore(STORE_REPORTS);
        const request = store.getAll();

        request.onsuccess = () => {
            resolve(request.result);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

/**
 * Elimina un reporte sincronizado
 */
async function removeSyncedReport(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_REPORTS], 'readwrite');
        const store = transaction.objectStore(STORE_REPORTS);
        const request = store.delete(id);

        request.onsuccess = () => {
            resolve();
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

/**
 * Guarda productos en caché
 */
async function cacheProducts(locationId, products) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_PRODUCTS], 'readwrite');
        const store = transaction.objectStore(STORE_PRODUCTS);

        const request = store.put({
            location_id: parseInt(locationId),
            products: products,
            updated_at: new Date().toISOString()
        });

        request.onsuccess = () => {
            resolve();
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

/**
 * Obtiene productos desde el caché
 */
async function getCachedProducts(locationId) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([STORE_PRODUCTS], 'readonly');
        const store = transaction.objectStore(STORE_PRODUCTS);
        const request = store.get(parseInt(locationId));

        request.onsuccess = () => {
            resolve(request.result ? request.result.products : null);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

// Exportar funciones
window.spOffline = {
    saveOfflineReport,
    getPendingReports,
    removeSyncedReport,
    cacheProducts,
    getCachedProducts
};
