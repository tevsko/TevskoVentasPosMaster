<?php
// cgi_wrapper.php
// Wrapper para php-cgi que garantiza supresión de errores

// PASO 1: Configurar PHP para NO mostrar errores
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_errors.log');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// PASO 2: Iniciar output buffering INMEDIATAMENTE
ob_start();

// PASO 3: Registrar handlers de error
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
});

set_exception_handler(function($exception) {
    error_log("PHP Exception: " . $exception->getMessage());
    ob_clean();
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<h1>Error del Sistema</h1>';
    echo '<p>Ha ocurrido un error. Por favor contacte al administrador.</p>';
    echo '</body></html>';
    exit(1);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("PHP Fatal: " . $error['message']);
        ob_clean();
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error Fatal</title></head><body>';
        echo '<h1>Error Fatal</h1>';
        echo '<p>El sistema encontró un error crítico.</p>';
        echo '</body></html>';
    }
});

// PASO 4: Determinar qué archivo ejecutar
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';

// Si no hay script filename, construirlo
if (empty($scriptFilename)) {
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
    $scriptFilename = $documentRoot . $scriptName;
}

// PASO 5: Verificar que el archivo existe
if (!file_exists($scriptFilename)) {
    // Intentar index.php por defecto
    $scriptFilename = __DIR__ . '/index.php';
    if (!file_exists($scriptFilename)) {
        ob_clean();
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404</title></head><body>';
        echo '<h1>Página No Encontrada</h1>';
        echo '</body></html>';
        exit;
    }
}

// PASO 6: Ejecutar el script solicitado
try {
    require $scriptFilename;
} catch (Throwable $e) {
    error_log("Throwable: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<h1>Error del Sistema</h1>';
    echo '<p>Ha ocurrido un error inesperado.</p>';
    echo '</body></html>';
}

// PASO 7: Enviar output
ob_end_flush();
