<?php
include_once(__DIR__ . "/includes/cors.php");
include_once(__DIR__ . "/includes/database.php"); // Classe Database

header("Content-Type: application/json");

// Vérifier test_id
if (!isset($_GET['test_id'])) {
    echo json_encode(["success" => false, "message" => "Test ID manquant"]);
    exit;
}

$test_id = intval($_GET['test_id']);

// Créer la connexion PDO
$database = new Database();
$pdo = $database->getConnection(); // <- IMPORTANT

try {
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = :test_id");
    $stmt->execute(['test_id' => $test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo json_encode(["success" => false, "message" => "Test introuvable"]);
        exit;
    }


    $stmtQ = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d 
                            FROM questions WHERE test_id = :test_id");
    $stmtQ->execute(['test_id' => $test_id]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "test" => $test,
        "questions" => $questions
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
