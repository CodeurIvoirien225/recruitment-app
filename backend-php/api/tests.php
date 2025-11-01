<?php
include_once '../includes/cors.php';

include_once '../includes/config.php';
include_once '../includes/database.php';

// Gérer les différentes méthodes HTTP
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer les tests d'un recruteur
    if (!isset($_GET['recruiter_id'])) {
        jsonResponse(false, 'ID recruteur manquant');
    }
    
    $recruiter_id = validateInput($_GET['recruiter_id']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "SELECT * FROM tests WHERE recruiter_id = :recruiter_id ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':recruiter_id' => $recruiter_id]);
        
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, 'Tests récupérés', $tests);
    } catch (PDOException $e) {
        error_log("Erreur: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de la récupération des tests');
    }
}



elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Créer un nouveau test
    $data = json_decode(file_get_contents("php://input"), true); // AJOUT: true pour array associatif
    
    if (!isset($data['recruiter_id']) || !isset($data['title']) || !isset($data['date']) || 
        !isset($data['time']) || !isset($data['duration'])) {
        jsonResponse(false, 'Tous les champs sont requis');
    }
    
    $recruiter_id = validateInput($data['recruiter_id']);
    $title = validateInput($data['title']);
    $description = isset($data['description']) ? validateInput($data['description']) : '';
    $date = validateInput($data['date']);
    $time = validateInput($data['time']);
    $duration = validateInput($data['duration']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Commencer une transaction
        $db->beginTransaction();
        
        // 1. Insérer le test
        $query = "INSERT INTO tests SET recruiter_id=:recruiter_id, title=:title, description=:description, 
                 date=:date, time=:time, duration=:duration";
        $stmt = $db->prepare($query);
        
        $stmt->execute([
            ':recruiter_id' => $recruiter_id,
            ':title' => $title,
            ':description' => $description,
            ':date' => $date,
            ':time' => $time,
            ':duration' => $duration
        ]);
        
        $test_id = $db->lastInsertId();
        
        // 2. Insérer les questions (NOUVEAU)
        if (isset($data['questions']) && is_array($data['questions'])) {
            $questionQuery = "INSERT INTO questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_answer) 
                             VALUES (:test_id, :question_text, :option_a, :option_b, :option_c, :option_d, :correct_answer)";
            $questionStmt = $db->prepare($questionQuery);
            
            foreach ($data['questions'] as $question) {
                // Valider les champs de la question
                if (empty($question['question_text']) || empty($question['option_a']) || 
                    empty($question['option_b']) || empty($question['option_c']) || 
                    empty($question['option_d']) || empty($question['correct_answer'])) {
                    throw new Exception('Tous les champs de la question sont requis');
                }
                
                $questionStmt->execute([
                    ':test_id' => $test_id,
                    ':question_text' => validateInput($question['question_text']),
                    ':option_a' => validateInput($question['option_a']),
                    ':option_b' => validateInput($question['option_b']),
                    ':option_c' => validateInput($question['option_c']),
                    ':option_d' => validateInput($question['option_d']),
                    ':correct_answer' => validateInput($question['correct_answer'])
                ]);
            }
        }
        
        // Valider la transaction
        $db->commit();
        
        jsonResponse(true, 'Test et questions créés avec succès', ['test_id' => $test_id]);
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        error_log("Erreur: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de la création du test: ' . $e->getMessage());
    }
}


elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Vérifier que l'ID est présent dans l'URL
    if (!isset($_GET['test_id'])) {
        jsonResponse(false, 'ID du test manquant');
    }

    $test_id = validateInput($_GET['test_id']);

    $database = new Database();
    $db = $database->getConnection();

    try {
        // Supprimer le test
        $stmt = $db->prepare("DELETE FROM tests WHERE id = :test_id");
        $stmt->execute([':test_id' => $test_id]);

        jsonResponse(true, 'Test supprimé avec succès');
    } catch (PDOException $e) {
        error_log("Erreur: " . $e->getMessage());
        jsonResponse(false, 'Erreur lors de la suppression du test');
    }
}



else {
    jsonResponse(false, 'Méthode non autorisée');
}


// Fonction de réponse JSON (assurez-vous qu'elle existe)

?>