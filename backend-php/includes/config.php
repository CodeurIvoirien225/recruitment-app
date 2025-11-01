<?php
include_once __DIR__ . '/cors.php';

// Charger les identifiants sensibles
$config = include(__DIR__ . '/mot_de_pass.php');

// Configuration de la base de données
define('DB_HOST', $config['DB_HOST']);
define('DB_NAME', $config['DB_NAME']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);

// Configuration SMTP
define('SMTP_HOST', $config['SMTP_HOST']);
define('SMTP_PORT', $config['SMTP_PORT']);
define('SMTP_USER', $config['SMTP_USER']);
define('SMTP_PASS', $config['SMTP_PASS']);
define('SMTP_FROM_EMAIL', $config['SMTP_FROM_EMAIL']);
define('SMTP_FROM_NAME', $config['SMTP_FROM_NAME']);

// Clé secrète JWT
define('JWT_SECRET', $config['JWT_SECRET']);

// URL de l'application
define('APP_URL', $config['APP_URL']);

// Fonction pour valider les données
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour générer une réponse JSON
function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
?>
