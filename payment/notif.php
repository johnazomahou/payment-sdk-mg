<?php
// Autorise toutes les origines (tu peux spécifier un domaine à la place de *)
header("Access-Control-Allow-Origin: *");

// Autorise uniquement les méthodes POST
header("Access-Control-Allow-Methods: POST");

// Autorise les headers personnalisés si l'API en envoie
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Si la méthode est OPTIONS (pré-vol), on répond sans exécuter le reste
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Traitement du corps de la requête (notification)
$payload = file_get_contents("php://input");

// Log pour debug ou traitement
file_put_contents("notif_log.txt", $payload . PHP_EOL, FILE_APPEND);

// Réponse attendue par l’API (à adapter selon leur doc)
echo "OK";
?>
