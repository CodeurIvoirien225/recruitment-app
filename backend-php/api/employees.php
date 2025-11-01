<?php
include_once '../includes/config.php';
include_once '../includes/database.php';
include_once '../includes/mailer.php';

// Activer le logging des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les employés d'un test
    if (!isset($_GET['test_id'])) {
        error_log("ID test manquant dans la requête GET");
        jsonResponse(false, 'ID test manquant');
    }
    
    
    $test_id = validateInput($_GET['test_id']);
    error_log("Récupération des employés pour test_id: " . $test_id);
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "SELECT * FROM employees WHERE test_id = :test_id ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':test_id' => $test_id]);
        
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Employés trouvés: " . count($employees));
        jsonResponse(true, 'Employés récupérés', $employees);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des employés: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de la récupération des employés: ' . $e->getMessage());
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ajouter un nouvel employé
    $rawData = file_get_contents("php://input");
    error_log("Données reçues pour ajouter employé: " . $rawData);
    
    $data = json_decode($rawData);
    
    if (!isset($data->test_id) || !isset($data->email) || !isset($data->password)) {
        error_log("Champs manquants dans la requête POST");
        jsonResponse(false, 'Tous les champs sont requis');
    }
    
    $test_id = validateInput($data->test_id);
    $email = validateInput($data->email);
    $password = password_hash($data->password, PASSWORD_BCRYPT);
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Vérifier si l'email existe déjà pour ce test
        $checkQuery = "SELECT id FROM employees WHERE email = :email AND test_id = :test_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([':email' => $email, ':test_id' => $test_id]);
        
        if ($checkStmt->rowCount() > 0) {
            error_log("Email déjà utilisé pour ce test: " . $email);
            jsonResponse(false, 'Cet email est déjà utilisé pour ce test');
        }
        
        // Récupérer le titre du test pour l'email
        $testQuery = "SELECT title FROM tests WHERE id = :test_id";
        $testStmt = $db->prepare($testQuery);
        $testStmt->execute([':test_id' => $test_id]);
        $test = $testStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            error_log("Test non trouvé: " . $test_id);
            jsonResponse(false, 'Test non trouvé');
        }
        
        // Insérer le nouvel employé
        $query = "INSERT INTO employees SET test_id=:test_id, email=:email, password=:password";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([':test_id' => $test_id, ':email' => $email, ':password' => $password])) {
            $employee_id = $db->lastInsertId();
            error_log("Employé ajouté avec ID: " . $employee_id);
            
            // Envoyer l'email d'invitation
            $emailSent = sendTestInvitation($email, $data->password, $test_id, $test['title']);
            
            if ($emailSent) {
                jsonResponse(true, 'Employé ajouté et email envoyé avec succès', ['employee_id' => $employee_id]);
            } else {
                jsonResponse(true, 'Employé ajouté mais erreur lors de l\'envoi de l\'email', ['employee_id' => $employee_id]);
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("Erreur d'exécution: " . print_r($errorInfo, true));
            jsonResponse(false, 'Erreur lors de l\'ajout de l\'employé: ' . $errorInfo[2]);
        }
    } catch (PDOException $e) {
        error_log("Exception PDO: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de l\'ajout de l\'employé: ' . $e->getMessage());
    }
}


elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Lire le JSON envoyé
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        error_log("ID du candidat manquant dans la requête DELETE");
        jsonResponse(false, 'ID du candidat manquant');
    }

    $employee_id = validateInput($data['id']);
    $database = new Database();
    $db = $database->getConnection();
    try {
        $stmt = $db->prepare("DELETE FROM employees WHERE id = :id");
        $stmt->execute([':id' => $employee_id]);
        if ($stmt->rowCount() > 0) {
            error_log("Candidat supprimé avec succès: " . $employee_id);
            jsonResponse(true, 'Candidat supprimé avec succès');
        } else {
            error_log("Aucun candidat trouvé avec l'ID: " . $employee_id);
            jsonResponse(false, 'Aucun candidat trouvé avec cet ID');
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du candidat: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de la suppression du candidat');
    }
}



else {
    error_log("Méthode non autorisée: " . $_SERVER['REQUEST_METHOD']);
    jsonResponse(false, 'Méthode non autorisée');
}
?>