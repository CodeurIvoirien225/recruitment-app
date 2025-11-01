<?php
// Inclusion de la connexion PDO
include_once __DIR__ . '/includes/database.php';

// Récupérer le JSON envoyé par le client
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Vérification du JSON
if ($data === null || !isset($data['employee_id'], $data['score_de_credibilite'])) {
    echo json_encode([
        'success' => false,
        'message' => 'JSON vide, invalide ou données manquantes',
        'received' => $data
    ]);
    exit;
}

$employee_id = (int)$data['employee_id'];
$score = (float)$data['score_de_credibilite'];

try {
    // Connexion à la base
    $db = new Database();
    $pdo = $db->getConnection();

    // Mettre à jour le score
    $stmt = $pdo->prepare("UPDATE employees SET score_de_credibilite = ? WHERE id = ?");
    $stmt->execute([$score, $employee_id]);

    $updatedRows = $stmt->rowCount();

    // Réponse JSON
    echo json_encode([
        'success' => true,
        'updated_rows' => $updatedRows,
        'employee_id' => $employee_id,
        'score_de_credibilite' => $score
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur SQL',
        'error' => $e->getMessage()
    ]);
}
?>
