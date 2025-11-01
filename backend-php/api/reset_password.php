<?php
include_once '../includes/cors.php';


include_once '../includes/config.php';
require '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    jsonResponse(false, "Erreur de connexion : " . $e->getMessage());
}

$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';

if ($action === 'request_reset') {
    $email = validateInput($input['email'] ?? '');

    if (empty($email)) {
        jsonResponse(false, "Veuillez saisir votre adresse email.");
    }

    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse(false, "Aucun compte trouvé avec cet email.");
    }

    // Générer un token unique
    $token = bin2hex(random_bytes(32));
    $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Enregistrer le token dans la BDD
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $token, $expires_at]);

    // Lien de réinitialisation
    $resetLink = APP_URL . "/reset-password?token=" . $token;

    // Envoyer l'email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';       // ✅ important pour les accents
        $mail->Encoding = 'base64'; 
        $mail->Subject = "Réinitialisation de votre mot de passe";
        $mail->Body = "
            <p>Bonjour,</p>
            <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien ci-dessous pour en choisir un nouveau :</p>
            <p><a href='$resetLink'>$resetLink</a></p>
            <p>Ce lien expirera dans 1 heure.</p>
            <p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet email.</p>
            <p>--<br>Système de Recrutement</p>
        ";

        // à commenter en production
            $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];


        $mail->send();
        jsonResponse(true, "Un email de réinitialisation a été envoyé à votre adresse.");
    } catch (Exception $e) {
        jsonResponse(false, "Erreur lors de l'envoi de l'email : " . $mail->ErrorInfo);
    }
}

elseif ($action === 'update_password') {
    $token = validateInput($input['token'] ?? '');
    $new_password = validateInput($input['new_password'] ?? '');

    if (empty($token) || empty($new_password)) {
        jsonResponse(false, "Données incomplètes.");
    }

    // Vérifier le token
    $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetData) {
        jsonResponse(false, "Lien invalide ou expiré.");
    }

    if (strtotime($resetData['expires_at']) < time()) {
        jsonResponse(false, "Le lien de réinitialisation a expiré.");
    }

    // Mettre à jour le mot de passe
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedPassword, $resetData['email']]);

    // Supprimer le token
    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$resetData['email']]);

    jsonResponse(true, "Mot de passe mis à jour avec succès !");
}

else {
    jsonResponse(false, "Action non reconnue.");
}
?>
