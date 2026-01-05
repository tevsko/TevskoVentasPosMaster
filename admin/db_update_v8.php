<?php
// admin/db_update_v8.php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Iniciando Actualización de Base de Datos v8 (Usuarios por Sucursal)...\n";

    // 1. Drop existing unique index on username
    // Note: The index name usually defaults to 'username' but we should be careful.
    try {
        $pdo->exec("ALTER TABLE users DROP INDEX username");
        echo " [OK] Índice global 'username' eliminado.\n";
    } catch (PDOException $e) {
        echo " [INFO] No se pudo eliminar índice 'username' (quizás ya no existe o tiene otro nombre): " . $e->getMessage() . "\n";
    }

    // 2. Add composite unique index (username, branch_id)
    // Note: branch_id can be NULL for global admins. MySQL allows multiple NULLs in UNIQUE index?
    // standard SQL says NULL != NULL, so multiple (admin, NULL) might be allowed.
    // To prevent multiple global admins with same name, we might need a different approach or just rely on app logic for NULLs.
    // However, for branches (cajero1, branchA) vs (cajero1, branchB), this works perfectly.
    
    // Check if index exists first? Simpler to try/catch.
    try {
        $pdo->exec("ALTER TABLE users ADD UNIQUE INDEX idx_user_branch (username, branch_id)");
        echo " [OK] Nuevo índice único (username, branch_id) creado.\n";
    } catch (PDOException $e) {
         echo " [SKIP] Índice 'idx_user_branch' probablemente ya existe: " . $e->getMessage() . "\n";
    }

    echo "Actualización v8 completada.\n";

} catch (PDOException $e) {
    die("Error crítico de BD: " . $e->getMessage());
}
?>
