<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendTestInvitation($employeeEmail, $employeePassword, $testId, $testTitle) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->SMTPDebug = 2; 
$mail->Debugoutput = 'error_log'; // ça enverra les logs dans error_log

        $mail->isSMTP();

$mail->SMTPOptions = [
    'ssl' => [
        'verify_peer' => false, // je dois remplacer par true en production
        'verify_peer_name' => false, // je dois remplacer par true en production
        'allow_self_signed' => false,
        'cafile' => 'C:/wamp64/bin/php/php7.4.33/extras/ssl/cacert.pem'
    ]
];


        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Destinataires
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($employeeEmail);

        // Contenu de l'email
        $testLink = APP_URL . '/employee-login?test_id=' . $testId;
        
        $mail->isHTML(true);
        $mail->Subject = 'Invitation à un test de recrutement - ' . $testTitle;
        $mail->Body    = "
            <h2>Vous avez été invité à passer un test de recrutement</h2>
            <p><strong>Test:</strong> $testTitle</p>
            <p>Vos identifiants de connexion sont :</p>
            <ul>
                <li><strong>Email:</strong> $employeeEmail</li>
                <li><strong>Mot de passe:</strong> $employeePassword</li>
                <li><strong>ID du test:</strong> $testId</li>
            </ul>
            <p>Cliquez sur le lien suivant pour accéder au test :</p>
            <a href='$testLink' style='padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Accéder au test</a>
            <br><br>
            <p><em>Ce lien est personnel et ne doit pas être partagé.</em></p>
            <hr>
            <p>Cordialement,<br>L'équipe de recrutement</p>
        ";
        
        $mail->AltBody = "Vous avez été invité à passer un test de recrutement: $testTitle\n\n"
                       . "Accédez-y via ce lien: $testLink\n"
                       . "Vos identifiants:\n"
                       . "Email: $employeeEmail\n"
                       . "Mot de passe: $employeePassword\n"
                       . "ID du test: $testId\n\n"
                       . "Ce lien est personnel et ne doit pas être partagé.";

        $mail->send();
        error_log("Email envoyé avec succès à: $employeeEmail");
        return true;
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email à $employeeEmail: {$mail->ErrorInfo}");
        return false;
    }
}
?>