<?php
include_once '../includes/config.php';
include_once '../includes/database.php';

// Cette API gérera les résultats des tests (à implémenter selon vos besoins)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les résultats d'un test
    if (!isset($_GET['test_id'])) {
        jsonResponse(false, 'ID test manquant');
    }
    
    $test_id = validateInput($_GET['test_id']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "SELECT e.id, e.email, e.status, e.score, e.started_at, e.completed_at 
                 FROM employees e 
                 WHERE e.test_id = :test_id 
                 ORDER BY e.completed_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':test_id' => $test_id]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, 'Résultats récupérés', $results);
    } catch (PDOException $e) {
        error_log("Erreur: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de la récupération des résultats');
    }
}
else {
    jsonResponse(false, 'Méthode non autorisée');
}
?>