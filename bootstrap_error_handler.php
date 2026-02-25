<?php
// bootstrap_error_handler.php
// Incluir PRIMERO en todos los archivos PHP para suprimir errores en headers

// Suprimir TODOS los errores de display
// Suprimir TODOS los errores de display
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

// Activar log de errores
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php_errors.log');

// Output buffering para capturar cualquier salida accidental
if (!ob_get_level()) {
    ob_start();
}

// Handler de errores personalizado que NO muestra nada
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log el error pero NO lo muestres
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Suprimir el error
});

// Handler de excepciones que NO muestra nada
set_exception_handler(function($exception) {
    // Log la excepción pero NO la muestres
    error_log("PHP Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Si estamos en un contexto web, mostrar página de error genérica
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        ob_clean(); // Limpiar cualquier salida previa
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Error - SpacePark POS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .error-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        p { color: #6c757d; line-height: 1.6; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 30px; background: #4a90e2; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>⚠️ Error del Sistema</h1>
        <p>Ha ocurrido un error inesperado. Por favor, contacte al administrador del sistema.</p>
        <p><small>Si este es un primer inicio, asegúrese de que el sistema esté correctamente configurado.</small></p>
        <a href="javascript:location.reload()" class="btn">Reintentar</a>
    </div>
</body>
</html>';
    }
    exit(1);
});

// Registrar shutdown function para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("PHP Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        if (php_sapi_name() !== 'cli') {
            ob_clean();
            http_response_code(500);
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Error Fatal - SpacePark POS</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .error-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        h1 { color: #dc3545; margin-bottom: 20px; }
        p { color: #6c757d; line-height: 1.6; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 30px; background: #4a90e2; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>❌ Error Fatal</h1>
        <p>El sistema ha encontrado un error crítico y no puede continuar.</p>
        <p><small>Verifique que todos los archivos estén correctamente instalados y que la base de datos esté inicializada.</small></p>
        <a href="javascript:location.reload()" class="btn">Reintentar</a>
    </div>
</body>
</html>';
        }
    }
});
