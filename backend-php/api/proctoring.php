<?php
include_once '../includes/config.php';
include_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->employee_id) || !isset($data->event_type)) {
        jsonResponse(false, 'Données manquantes');
    }
    
    $employee_id = validateInput($data->employee_id);
    $event_type = validateInput($data->event_type);
    $details = isset($data->details) ? validateInput($data->details) : '';
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "INSERT INTO proctoring_logs SET employee_id=:employee_id, event_type=:event_type, details=:details";
        $stmt = $db->prepare($query);
        
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':event_type' => $event_type,
            ':details' => $details
        ]);
        
        jsonResponse(true, 'Événement de proctoring enregistré');
    } catch (PDOException $e) {
        error_log("Erreur: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de l\'enregistrement de l\'événement');
    }
}
else {
    jsonResponse(false, 'Méthode non autorisée');
}
?>