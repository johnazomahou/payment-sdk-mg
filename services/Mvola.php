<?php
// services/Mvola.php
require_once __DIR__ . '/../interfaces/PaymentService.php';



class Mvola implements PaymentService {

    public string $accessToken = '';

    private string $XCorrelationId;

    

    function generateCorrelationId() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, // version 4
            mt_rand(0, 0x3fff) | 0x8000, // variant
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    function generateTransactionId(): string {
        $transactionId = 'TXN-' . date('Ymd-His') . '-' . substr(uniqid(), -5);
        return $transactionId; // Ou une autre logique si nécessaire
    }
    

    public function recuperertoken() {
        // Simuler la récupération d'un token

        // Remplacez ces valeurs par vos propres clés d'API

         $consumerKey = "your_consumer_key"; // Remplacez par votre consumerKey
        $consumerSecret = "your_consumer_secret"; // Remplacez par votre consumerSecret



        $credentials = base64_encode($consumerKey . ":" . $consumerSecret);
        $ch = curl_init();

        // Configurer la requête cURL


            //         PRODUCTION 
            // This API present THREE (3) resources 
            // https://devapi.mvola.mg 
            // https://api.mvola.mg 
        

        // https://developer.mvola.mg/oauth2/token
        curl_setopt($ch, CURLOPT_URL, "https://api.mvola.mg/token");
        curl_setopt($ch, CURLOPT_POST, 1);

        $postFields = "grant_type=client_credentials&scope=EXT_INT_MVOLA_SCOPE";

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
       
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Basic $credentials"
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À éviter en production

        // Exécuter la requête
        $response = curl_exec($ch);

        $resultData = json_decode($response, true);

        $this->accessToken = $resultData["access_token"];

        if (!isset($resultData["access_token"])) {
                       
            echo json_encode(["message" => $resultData], JSON_PRETTY_PRINT);
            die();
        }
        

        return   $resultData;
    }

    public function payer($numero, $montant) {

        $response = [
            "token"=>$this->accessToken ,
            "montant" => $numero,
            "numero" => $montant, 
        ];
      
       
        $url = "https://api.mvola.mg/mvola/mm/transactions/type/merchantpay/1.0.0/";
        
      
        $correlationId = $this->generateCorrelationId();
        $transactionId = $this->generateTransactionId();
        // Date au format ISO 8601 avec millisecondes
        
        $microtime = microtime(true);
        $utcDate = new DateTime("now", new DateTimeZone("UTC"));
        $microtime = microtime(true);
        $milliseconds = sprintf('%03d', ($microtime - floor($microtime)) * 1000);
        $formattedDate = $utcDate->format("Y-m-d\TH:i:s.") . $milliseconds . "Z";


        $this->XCorrelationId = $correlationId;
        
        
        $headers = [
            "Authorization: Bearer $this->accessToken",
            "Version: 1.0",
            "X-CorrelationID: $correlationId",
            "UserLanguage: FR",  // ou "MG" selon la langue
            "UserAccountIdentifier: msisdn;0343500003",  //  pour la sandbox utilser 0343500003 ou 0343500004 (dans la production ca change)
            "partnerName: myapp",  // Remplacez par le nom de votre entreprise ou partenaire
            "Content-Type: application/json",
            "Cache-Control: no-cache"
        ];



        $body = [
            "amount" => $montant,
            "currency" => "Ar",
            "descriptionText" => "Paiement pour service internet",
            "requestingOrganisationTransactionReference" => $transactionId,
            "requestDate" => $formattedDate,
            "originalTransactionReference" => $transactionId,
            "debitParty" => [
                [
                    "key" => "msisdn",
                    "value" => $numero  // Assurez-vous que $numero est bien formaté
                ]
            ],
            "creditParty" => [
                [
                    "key" => "msisdn",
                    "value" => "0343500003"  // Numéro marchand donc pour le test en front utilsez 0343500004
                ]
            ],
            "metadata" => [
                [
                    "key" => "partnerName",
                    "value" => "myapp"
                ],
                [
                    "key" => "fc",
                    "value" => "USD"  // Ajoutez le champ "fc" (devise étrangère)
                ],
                [
                    "key" => "amountFc",
                    "value" => '1' // Ajoutez le montant en devise étrangère
                ]
            ]
        ];
        
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));


        // Options de sécurité
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Exécuter la requête

        $response = curl_exec($ch);



        if(curl_errno($ch)) {
            return 'Erreur Curl : ' . curl_error($ch);
        }

        curl_close($ch);

        $resultData = json_decode($response, true);

        $resultFinal = [
            'transactionData' => [
                'api_name' => 'MVOLA',
                'transaction_ref' => $resultData['serverCorrelationId'] ?? null,
                'header' => json_encode($headers),
                'payer_number' => $numero,
                'amount' => $montant,
                'data_api' => json_encode($body),
                'api_response' => $response,
                'payment_status' => 'pending'
            ],
            'resultData' => $resultData
        ];
        
       
      
        return $resultFinal;
    }

    public function verifierStatut($transactionId) {


        $url = "https://api.mvola.mg/mvola/mm/transactions/type/merchantpay/1.0.0/status/".$transactionId;
        

        $correlationId = $this->generateCorrelationId();
    
        $headers = [
            "Authorization: Bearer $this->accessToken",
            "Version: 1.0",
            "X-CorrelationID:  $correlationId",
            "UserLanguage: FR",  // ou "MG" selon la langue
            "UserAccountIdentifier: msisdn;0343500003",  // Remplacez par le msisdn et partenaire approprié
            "partnerName: myapp",  // Remplacez par le nom de votre entreprise ou partenaire
            "Content-Type: application/json",
            "Cache-Control: no-cache"
        ];

        
        $ch = curl_init($url);
  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    

        // Options de sécurité
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Exécuter la requête

        $response = curl_exec($ch);

        if(curl_errno($ch)) {
            return 'Erreur Curl : ' . curl_error($ch);
        }

        curl_close($ch);

        $resultData = json_decode($response, true);

        //temporairement

       // $resultData['status'] = 'completed';
        return $resultData;
    }
}
?>
