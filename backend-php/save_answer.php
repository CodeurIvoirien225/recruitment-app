<?php
header('Content-Type: application/json');

include_once __DIR__ . '/includes/cors.php';
include_once __DIR__ . '/includes/config.php';
include_once __DIR__ . '/includes/database.php';

// Récupérer les données JSON
$input = file_get_contents("php://input");
$data = json_decode($input, true);


if (!$data) {
    error_log("Données JSON invalides ou manquantes. Input: " . $input);
    echo json_encode(['success' => false, 'message' => 'Payload manquant ou JSON invalide']);
    exit;
}


// Vérifier les données
if (!isset($data['candidate_id'], $data['test_id'], $data['answers']) || !is_array($data['answers'])) {
    error_log("Données manquantes: " . print_r($data, true));
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$candidate_id = intval($data['candidate_id']);
$test_id = intval($data['test_id']);
$answers = $data['answers'];

error_log("=== NOUVELLE SOUMISSION ===");
error_log("Candidate ID: $candidate_id, Test ID: $test_id");
error_log("Réponses reçues: " . print_r($answers, true));

try {
    $db = (new Database())->getConnection();

    // Commencer une transaction pour plus de sécurité
    $db->beginTransaction();

    // Récupérer les questions et réponses correctes pour le BON test_id
    $stmt = $db->prepare("SELECT id, correct_answer FROM questions WHERE test_id = :test_id");
    $stmt->execute([':test_id' => $test_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$questions) {
        error_log("AUCUNE QUESTION trouvée pour test_id: $test_id");
        echo json_encode(['success' => false, 'message' => 'Aucune question trouvée pour ce test']);
        exit;
    }

    $total = count($questions);
    $correct = 0;
    $answerDetails = [];

    error_log("Nombre de questions trouvées: " . $total);

    foreach ($questions as $q) {
        $qid = $q['id'];
        $correctAnswer = trim($q['correct_answer']);
        
        error_log("Question ID: $qid, Réponse correcte: '$correctAnswer'");

        // Récupérer la réponse donnée
        $given = '';
        if (isset($answers[$qid])) {
            $given = $answers[$qid];
        } elseif (isset($answers[(string)$qid])) {
            $given = $answers[(string)$qid];
        }
        
        // Nettoyer la réponse
        if (is_string($given)) {
            $given = trim($given);
        } else {
            $given = '';
        }

        error_log("Question $qid - Réponse donnée: '$given', Réponse correcte: '$correctAnswer'");

        // Comparaison case-insensitive
        $isCorrect = false;
        if (!empty($given) && strtoupper($given) === strtoupper($correctAnswer)) {
            $correct++;
            $isCorrect = true;
            error_log("→ CORRECT");
        } else {
            error_log("→ INCORRECT");
        }

        $answerDetails[] = [
            'question_id' => $qid,
            'given' => $given,
            'correct' => $correctAnswer,
            'is_correct' => $isCorrect
        ];

        // Enregistrer la réponse
        $stmtAns = $db->prepare("
            INSERT INTO answers (candidate_id, test_id, question_id, selected_option, created_at, updated_at)
            VALUES (:candidate_id, :test_id, :question_id, :selected_option, NOW(), NOW())
            ON DUPLICATE KEY UPDATE selected_option = :selected_option, updated_at = NOW()
        ");
        $stmtAns->execute([
            ':candidate_id' => $candidate_id,
            ':test_id' => $test_id,
            ':question_id' => $qid,
            ':selected_option' => $given
        ]);
    }

    // Calculer le score sur 100 (comme vos autres scores: 95, 63, 75)
$score = $total > 0 ? round(($correct / $total) * 20, 1) : 0; // arrondi à 1 décimale

    error_log("Résultat final - Correct: $correct/$total, Score: $score/100");

    // Mettre à jour l'employé
    $upd = $db->prepare("
        UPDATE employees 
        SET score = :score, status = 'completed', completed_at = NOW() 
        WHERE id = :candidate_id AND test_id = :test_id
    ");
    $upd->execute([
        ':score' => $score,
        ':candidate_id' => $candidate_id,
        ':test_id' => $test_id
    ]);

    $rowsUpdated = $upd->rowCount();

    // Valider la transaction
    $db->commit();

    error_log("Mise à jour BDD réussie - Score: $score, Lignes modifiées: $rowsUpdated");

    echo json_encode([
        'success' => true,
        'message' => 'Réponses traitées avec succès',
        'data' => [
            'score' => $score,
            'correct' => $correct,
            'total' => $total,
            'rows_updated' => $rowsUpdated
        ]
    ]);

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("ERREUR save_answers.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>