<?php
/**
 * Système de paiement mobile simple pour Madagascar
 * Supporte Orange Money, MVola et Airtel Money
 * 
 * Comment utiliser :
 * - Envoyer une requête POST avec : forfait, numero, network
 * - Le script retourne du JSON avec le résultat
 * Auteur : JOHN AZOMAHOU 
 * WHATSAPP : +229 97509779
 * SITE WEB: johnazomahou.com
 * Date : 2025-06-26
 */

// Importer les fichiers nécessaires
require_once 'services/OrangeMoney.php';
require_once 'services/Mvola.php';
require_once 'services/AirtelMoney.php';
require_once 'PaymentManager.php';

// Configuration de base
ini_set('max_execution_time', 300); // 5 minutes max
date_default_timezone_set('Indian/Antananarivo'); // Heure de Madagascar

// Vérifier si les données sont envoyées
if (!isset($_POST['forfait']) || !isset($_POST['numero']) || !isset($_POST['network'])) {
    echo json_encode([
        "success" => false,
        "message" => "Données manquantes : forfait, numero et network requis"
    ]);
    exit;
}

// Récupérer les données
$forfait = $_POST['forfait'];  // Montant à payer
$numero = $_POST['numero'];    // Numéro de téléphone
$network = $_POST['network'];  // Orange Money, MVola ou Airtel Money

// Variables pour suivre le paiement
$paiement_reussi = false;
$message_erreur = "";

// ========================================
// ORANGE MONEY
// ========================================
if ($network == "Orange Money") {
    
    // Créer le service Orange Money
    $service = new OrangeMoney();
    $manager = new PaymentManager($service);
    
    // Étape 1 : Récupérer le token
    $token = $manager->recuperertoken();
    
    if (isset($token["error"])) {
        // Problème avec le token
        echo json_encode([
            "success" => false,
            "message" => "Impossible d'obtenir le token Orange Money"
        ]);
        exit;
    }
    
    if (isset($token["access_token"])) {
        // Étape 2 : Initier le paiement
        $payment = $manager->payer($numero, $forfait);
        
        if (isset($payment['resultData']["payment_url"])) {
            // Succès : Orange Money retourne une URL de paiement
            $transaction_id = $payment['transactionData']['transaction_ref'];
            
            echo json_encode([
                "success" => true,
                "message" => "URL de paiement créée",
                "payment_url" => $payment['resultData']["payment_url"],
                "return_url" => "https://monsite.com/retour?transaction=" . $transaction_id
            ]);
        } else {
            // Échec
            echo json_encode([
                "success" => false,
                "message" => "Erreur lors de la création du paiement Orange Money"
            ]);
        }
    }
}

// ========================================
// MVOLA
// ========================================
elseif ($network == "MVola") {
    
    // Créer le service MVola
    $service = new Mvola();
    $manager = new PaymentManager($service);
    
    // Étape 1 : Récupérer le token
    $token = $manager->recuperertoken();
    
    if (isset($token["error"])) {
        echo json_encode([
            "success" => false,
            "message" => "Impossible d'obtenir le token MVola"
        ]);
        exit;
    }
    
    if (isset($token["access_token"])) {
        // Étape 2 : Initier le paiement
        $payment = $manager->payer($numero, $forfait);
        
        // Vérifier s'il y a une erreur immédiate
        if (isset($payment["resultData"]["code"])) {
            echo json_encode([
                "success" => false,
                "message" => "Erreur MVola : " . $payment["resultData"]["code"]
            ]);
            exit;
        }
        
        // Étape 3 : Vérifier le statut plusieurs fois
        if (isset($payment['resultData']["status"])) {
            $transaction_ref = $payment['transactionData']['transaction_ref'];
            
            // Vérifier 5 fois, toutes les 8 secondes
            for ($i = 0; $i < 5; $i++) {
                $resultat = $manager->verifierStatut($transaction_ref);
                
                if ($resultat['status'] === 'completed') {
                    // Paiement réussi !
                    echo json_encode([
                        "success" => true,
                        "message" => "Paiement MVola réussi",
                        "transaction_ref" => $transaction_ref
                    ]);
                    exit;
                    
                } elseif ($resultat['status'] === 'failed') {
                    // Paiement échoué
                    echo json_encode([
                        "success" => false,
                        "message" => "Paiement MVola échoué",
                        "transaction_ref" => $transaction_ref
                    ]);
                    exit;
                }
                
                // Attendre 8 secondes avant de vérifier à nouveau
                sleep(8);
            }
            
            // Si on arrive ici, le paiement est toujours en attente
            echo json_encode([
                "success" => false,
                "message" => "Timeout : Paiement MVola toujours en attente"
            ]);
        }
    }
}

// ========================================
// AIRTEL MONEY
// ========================================
elseif ($network == "Airtel Money") {
    
    // Créer le service Airtel Money
    $service = new AirtelMoney();
    $manager = new PaymentManager($service);
    
    // Étape 1 : Récupérer le token
    $token = $manager->recuperertoken();
    
    if (isset($token["error"])) {
        echo json_encode([
            "success" => false,
            "message" => "Impossible d'obtenir le token Airtel Money"
        ]);
        exit;
    }
    
    if (isset($token["access_token"])) {
        // Étape 2 : Initier le paiement (envoie un SMS/push à l'utilisateur)
        $payment = $manager->payer($numero, $forfait);
        
        // Vérifier s'il y a une erreur immédiate
        if (isset($payment["resultData"]["code"])) {
            echo json_encode([
                "success" => false,
                "message" => "Erreur Airtel Money : " . $payment["resultData"]["code"]
            ]);
            exit;
        }
        
        // Vérifier si le push a été envoyé
        $status_initial = $payment['resultData']['status']['success'] ?? false;
        
        if ($status_initial === true) {
            $transaction_ref = $payment['transactionData']['transaction_ref'];
            
            // Étape 3 : Vérifier le statut plusieurs fois
            // Vérifier 5 fois, toutes les 8 secondes
            for ($i = 0; $i < 5; $i++) {
                $resultat = $manager->verifierStatut($transaction_ref);
                $statut = $resultat["data"]["transaction"]["status"];
                
                if ($statut === "TS") {
                    // TS = Transaction Successful (Réussie)
                    echo json_encode([
                        "success" => true,
                        "message" => "Paiement Airtel Money réussi",
                        "transaction_ref" => $transaction_ref
                    ]);
                    exit;
                    
                } elseif ($statut === "TF") {
                    // TF = Transaction Failed (Échouée)
                    echo json_encode([
                        "success" => false,
                        "message" => "Paiement Airtel Money échoué",
                        "transaction_ref" => $transaction_ref
                    ]);
                    exit;
                }
                
                // Attendre 8 secondes avant de vérifier à nouveau
                sleep(8);
            }
            
            // Si on arrive ici, le paiement est toujours en attente
            echo json_encode([
                "success" => false,
                "message" => "Timeout : Paiement Airtel Money toujours en attente"
            ]);
            
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Impossible d'envoyer la demande de paiement Airtel Money"
            ]);
        }
    }
}

// ========================================
// RÉSEAU NON SUPPORTÉ
// ========================================
else {
    echo json_encode([
        "success" => false,
        "message" => "Réseau non supporté. Utilisez : Orange Money, MVola ou Airtel Money"
    ]);
}

?>