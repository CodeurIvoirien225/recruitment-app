<?php
include_once '../includes/cors.php';
include_once '../includes/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['employee_id']) || !isset($data['test_id']) || !isset($data['answers']) || !isset($data['score'])) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

$employee_id = $data['employee_id'];
$test_id = $data['test_id'];
$answers = $data['answers'];
$score = $data['score']; // ✅ score réel sur 20

// --- Connexion PDO ---
$db = new Database();
$conn = $db->getConnection();

try {
    // --- Mise à jour du candidat avec le vrai score ---
    $stmt = $conn->prepare("UPDATE employees SET status = 'completed', score = :score WHERE id = :id");
    $stmt->bindParam(':score', $score);
    $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();

    
    echo json_encode([
        'success' => true,
        'score' => $score,
        'message' => 'Test soumis avec succès !'
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la soumission du test'
    ]);
}
?>
