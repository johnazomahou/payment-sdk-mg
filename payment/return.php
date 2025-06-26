<?php
/**
 * FICHIER DE RETOUR ORANGE MONEY
 * 
 * Ce fichier est appelé automatiquement par Orange Money après qu'un utilisateur
 * termine son paiement (succès ou échec).
 * 
 * URL d'exemple : https://monsite.com/return.php?transaction_id=TX123456
 */

// Importer les services nécessaires
require_once __DIR__ . '/../services/OrangeMoney.php';
require_once __DIR__ . '/../services/Mvola.php';
require_once __DIR__ . '/../services/AirtelMoney.php';
require_once __DIR__ . '/../PaymentManager.php';

// Configuration du fuseau horaire Madagascar
date_default_timezone_set('Indian/Antananarivo');

// ========================================
// VÉRIFICATION DE LA TRANSACTION
// ========================================

// Vérifier si l'ID de transaction est présent dans l'URL
if (isset($_GET['transaction_id'])) {
    
    // Récupérer l'ID de transaction depuis l'URL
    $transactionId = $_GET['transaction_id'];
    
    // Afficher l'ID pour déboguer (à supprimer en production)
    echo "Transaction ID reçue : " . htmlspecialchars($transactionId) . "<br>";
    
    // ========================================
    // INITIALISATION DU SERVICE ORANGE MONEY
    // ========================================
    
    // Créer le service Orange Money
    $service = new OrangeMoney();
    $manager = new PaymentManager($service);
    
    // Récupérer le token d'authentification
    $token = $manager->recuperertoken();
    
    // Vérifier si le token a été obtenu avec succès
    if (isset($token["access_token"])) {
        
        // ========================================
        // CONFIGURATION DU SERVICE (SI NÉCESSAIRE)
        // ========================================
        
        // SUGGESTION : Récupérer les données de la transaction depuis votre base de données
        $transaction = obtenirTransactionDepuisBDD($transactionId);
        if (!$transaction) {
            echo "Transaction non trouvée dans la base de données";
            exit;
        }
        
      
        $rawAmount = $transaction['amount']; // ex: "1000.00"
        $formattedAmount = rtrim(rtrim($rawAmount, '0'), '.'); // Supprimer les zéros inutiles
      
        $response_api = json_decode($transaction['api_response'], true); // e princie vos devriez stoke le  résultat de l'API dans votre base de données
        $service->pay_token = $response_api['pay_token']; // c'est necessaire pour la vérification du statut
        $service->amount = $rawAmount;
        
        // ========================================
        // VÉRIFICATION DU STATUT DE LA TRANSACTION
        // ========================================
        
        // Vérifier le statut final de la transaction auprès d'Orange Money
        $result = $manager->verifierStatut($transactionId);
        
        // Variables pour suivre le résultat
        $nouveau_statut = 'pending';
        $delivrer_service = 'non';
        $reponse_api = [];
        
        // Analyser la réponse d'Orange Money
        if ($result['status'] === 'SUCCESS') {
            // ✅ PAIEMENT RÉUSSI
            $nouveau_statut = 'completed';
            $delivrer_service = 'oui';
            $reponse_api = $result;
            
            echo "<h2 style='color: green;'>✅ Paiement réussi !</h2>";
            
        } elseif ($result['status'] === 'FAILED') {
            //  PAIEMENT ÉCHOUÉ
            $nouveau_statut = 'failed';
            $delivrer_service = 'non';
            $reponse_api = $result;
            
            echo "<h2 style='color: red;'> Paiement échoué</h2>";
            
        } else {
            // ⏳ STATUT INCONNU OU EN ATTENTE
            $nouveau_statut = 'pending';
            $delivrer_service = 'non';
            
            echo "<h2 style='color: orange;'>⏳ Statut de paiement en attente</h2>";
        }
        
        // ========================================
        // TRAITEMENT SELON LE RÉSULTAT
        // ========================================
        
        if ($nouveau_statut == 'completed' && $delivrer_service == 'oui') {
            // 🎉 PAIEMENT CONFIRMÉ - ACTIONS À EFFECTUER
            
            echo "<p>Votre paiement a été confirmé avec succès !</p>";
          
         
           
            
        } else {
            //  PAIEMENT NON CONFIRMÉ - ACTIONS D'ÉCHEC
            
            if ($nouveau_statut == 'failed') {
                echo "<p>Désolé, votre paiement n'a pas pu être traité.</p>";
                
                // SUGGESTIONS D'ACTIONS EN CAS D'ÉCHEC :
                
    
                
            } else {
                // Statut en attente ou inconnu
                echo "<p>Le statut de votre paiement n'est pas encore déterminé.</p>";
                
                // SUGGESTIONS POUR STATUT EN ATTENTE :
               
               
            }
        }
        
       
        
    } else {
        //  ERREUR D'AUTHENTIFICATION
        echo "<h2 style='color: red;'> Erreur d'authentification</h2>";
        echo "<p>Impossible de vérifier le statut du paiement. Erreur de token.</p>";
        
        // SUGGESTIONS EN CAS D'ERREUR DE TOKEN :
        // 1. Vérifier la configuration des clés API
        // 2. Contacter l'administrateur système
        // 3. Enregistrer l'erreur dans les logs
        // error_log("Erreur token Orange Money pour transaction: $transactionId");
        
        echo "<p>Veuillez contacter notre support technique.</p>";
    }
    
} else {
    //  AUCUN ID DE TRANSACTION
    echo "<h2 style='color: red;'> Paramètre manquant</h2>";
    echo "<p>Aucun ID de transaction fourni.</p>";
    
    // SUGGESTIONS SI AUCUN ID DE TRANSACTION :
    // 1. Rediriger vers la page d'accueil
    // header("Location: index.php");
    // 2. Afficher un message d'erreur convivial
    // 3. Enregistrer l'erreur dans les logs
    // error_log("Accès à return.php sans transaction_id depuis IP: " . $_SERVER['REMOTE_ADDR']);
    
    echo "<a href='index.php'>Retour à l'accueil</a>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat du Paiement Orange Money</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏦 Résultat du Paiement Orange Money</h1>
        
        <!-- Le contenu PHP s'affiche ici -->
        
        <hr>
        <div style="text-align: center; margin-top: 30px;">
            <a href="../index.html" class="btn">🏠 Retour à l'accueil</a>
         
        </div>
    </div>
</body>
</html>