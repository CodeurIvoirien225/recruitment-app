<?php
include_once '../includes/cors.php';


include_once '../includes/config.php';
include_once '../includes/database.php';




// Vérifier que c'est bien une POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

// Récupérer les données JSON
$data = json_decode(file_get_contents("php://input"));
if (!$data) {
    jsonResponse(false, 'Données JSON invalides');
}

if (!isset($data->action)) {
    jsonResponse(false, 'Action non spécifiée');
}

// Connexion DB
$database = new Database();
$db = $database->getConnection();

try {
    if ($data->action === 'register_recruiter') {
        if (!isset($data->name) || !isset($data->email) || !isset($data->password) || !isset($data->company)) {
            jsonResponse(false, 'Tous les champs sont requis');
        }

        $name = validateInput($data->name);
        $email = validateInput($data->email);
        $password = password_hash($data->password, PASSWORD_BCRYPT);
        $company = validateInput($data->company);

        // Vérifier si l'email existe déjà
        $checkQuery = "SELECT id FROM users WHERE email = :email";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->rowCount() > 0) {
            jsonResponse(false, 'Cet email est déjà utilisé');
        }

        // Insérer le recruteur
        $query = "INSERT INTO users SET full_name=:name, email=:email, password=:password, company_name=:company";
        $stmt = $db->prepare($query);

        if ($stmt->execute([':name' => $name, ':email' => $email, ':password' => $password, ':company' => $company])) {
            jsonResponse(true, 'Recruteur inscrit avec succès');
        } else {
            jsonResponse(false, 'Erreur lors de l\'inscription');
        }

    } elseif ($data->action === 'login_recruiter') {
        if (!isset($data->email) || !isset($data->password)) {
            jsonResponse(false, 'Email et mot de passe requis');
        }

        $email = validateInput($data->email);
        $password = $data->password;

        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->execute([':email' => $email]);

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                jsonResponse(true, 'Connexion réussie', [
                    'recruiter' => [
                        'id' => $row['id'],
                        'name' => $row['full_name'],
                        'email' => $row['email'],
                        'company' => $row['company_name']
                    ]
                ]);
            } else {
                jsonResponse(false, 'Mot de passe incorrect');
            }
        } else {
            jsonResponse(false, 'Aucun compte avec cet email');
        }

} elseif ($data->action === 'login_employee') {
    if (!isset($data->email) || !isset($data->password) || !isset($data->test_id)) {
        jsonResponse(false, 'Tous les champs sont requis');
    }

    $email = validateInput($data->email);
    $password = $data->password;
    $test_id = validateInput($data->test_id);

    $query = "SELECT * FROM employees WHERE email = :email AND test_id = :test_id";
    $stmt = $db->prepare($query);
    $stmt->execute([':email' => $email, ':test_id' => $test_id]);

    if ($stmt->rowCount() == 1) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 🔥 VÉRIFICATION CRUCIALE : Si le test est déjà complété
        if ($row['status'] === 'completed') {
            jsonResponse(false, 'Vous avez déjà passé ce test. Vous ne pouvez pas le repasser.', [
                'employee' => [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'test_id' => $row['test_id'],
                    'status' => $row['status'],
                    'score' => $row['score']
                ]
            ]);
        }
        
        if (password_verify($password, $row['password'])) {
            // Mettre à jour le statut seulement si ce n'est pas déjà "in_progress"
            if ($row['status'] !== 'in_progress') {
                $updateQuery = "UPDATE employees SET status='in_progress', started_at=NOW() WHERE id=:id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([':id' => $row['id']]);
            }

            jsonResponse(true, 'Connexion réussie', [
                'employee' => [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'test_id' => $row['test_id'],
                    'status' => 'in_progress' // Toujours retourner in_progress
                ]
            ]);
        } else {
            jsonResponse(false, 'Mot de passe incorrect');
        }
    } else {
        jsonResponse(false, 'Aucun employé trouvé avec ces informations');
    }
} else {
        jsonResponse(false, 'Action non reconnue');
    }
} catch (PDOException $e) {
    error_log("Erreur base de données: " . $e->getMessage());
    jsonResponse(false, 'Erreur serveur');
}
?>
