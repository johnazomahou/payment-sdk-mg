<?php
// services/AirtelMoney.php
require_once __DIR__ . '/../interfaces/PaymentService.php';



class AirtelMoney implements PaymentService {

    // Staging -- https://openapi.airtel.africa/
    // Production -- https://openapi.airtel.africa/
    public string $accessToken = '';

    
    private string $publicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArUj2SQKLCdTqJ3/ZL6nkh1N3rtjXBBM+0hBUrhJ/VNSMTBixpD+JjeNaHbONcrvJGSstC2tcVfD04s9xGIKr9TT6hCYaqGojLeuLimVdXzaP5DzDyrHY8mYgHL+/EGRDh+/7B56Gw8UZxOBPtF6Wjjq0TWGcw5YOW1lSPUeaD+kupmDFlMRk26fASELwkYo5NkHgL/w+XzXw8gDZtrNS6L8UX2mfqdQ9qKpdMP3ztfOUPjmTvIbTKrGLx0U2sUSQINtMxZQzsYaXIGoZ2thvbIhJMDFBNbznuv1n8b03Q3MAnEK/xCduQBUkUg1syy7jZMT4ETDeFuW2NMZhteaadwIDAQAB';
  
        //   https://openapi.airtel.africa/merchant/v2/payments/


    function generateReference() {
            $prefix = "Tic";
            $random = strtoupper(bin2hex(random_bytes(5))); // 10 caractères alphanumériques
            $reference = $prefix . $random;

            // S'assurer que la référence est bien entre 4 et 64 caractères
            return substr($reference, 0, 64);
    }


    // public function generateTransactionId() {
    //         return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    //             mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    //             mt_rand(0, 0xffff),
    //             mt_rand(0, 0x0fff) | 0x4000,
    //             mt_rand(0, 0x3fff) | 0x8000,
    //             mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    //         );
    // }


       public function generateTransactionId(){
            $prefix = 'TICKO'; // 5 lettres
            $random = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)); // 10 caractères
            return $prefix . $random; // Total = 15 caractères
        }




  
       
   

        public function recuperertoken() {
           
                


            
                $client_id = "your_client_id"; // Remplacez par votre client_id
                $client_secret = "your_client_secret"; // Remplacez par votre client_secret

                $data = array(
                    "client_id" => $client_id,
                    "client_secret" => $client_secret,
                    "grant_type" => "client_credentials"
                );


                $ch = curl_init();

                // Définir les options

                // https://openapi.airtel.africa/
                curl_setopt($ch, CURLOPT_URL, "https://openapi.airtel.africa/auth/oauth2/token");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json",
                    "Accept: application/json"
                ));
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Pour que curl_exec retourne la réponse

                // Exécuter la requête
                $response = curl_exec($ch);

                $resultData = json_decode($response, true);

                // Vérifier s'il y a une erreur
                if (curl_errno($ch)) {

                    return   $resultData;
                } 

                // Fermer la session cURL
                curl_close($ch);
                
                if (!isset($resultData["access_token"])) {
                            
                    echo json_encode(["message" => $resultData], JSON_PRETTY_PRINT);
                    die();
                }

                $this->accessToken = $resultData["access_token"];
                
        
                    return   $resultData;
                }

    public function payer($numero, $montant) {

        // Nettoyage du numéro : s'assurer qu'il a bien 9 chiffres
        if (strlen($numero) === 10 && $numero[0] === '0') {
            $numero = substr($numero, 1); // Retire le 0 initial
        }
        // Si le numéro a déjà 9 chiffres, il reste inchangé
        //donc par defaut les numéros malgache on doit enlever le 0 initial

        $url = "https://openapi.airtel.africa/merchant/v1/payments/";
        $accessToken = $this->accessToken;
        $transactionId = $this->generateTransactionId();

        $body = [
            "reference" => $this->generateReference(),
            "subscriber" => [
                "country" => "MG",
                "currency" => "MGA",
                "msisdn" => $numero
            ],
            "transaction" => [
                "amount" => $montant,
                "country" => "MG",
                "currency" => "MGA",
                "id" => $transactionId
            ]
        ];


    
        $headers = [
            'Accept: */* ',
            'Content-Type: application/json',
            'X-Country: MG',
            'X-Currency: MGA',
            'Authorization: Bearer ' . $accessToken,
        ];


        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

         curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Accept: application/json"
        ));
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); // ENVOI CORRECT
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return 'Erreur Curl : ' . curl_error($ch);
        }

        curl_close($ch);
        $resultData = json_decode($response, true);
  

        $resultFinal = [
            'transactionData' => [
                'api_name' => 'AIRTEL_MONEY_MG',
                'transaction_ref' =>  $transactionId,
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

            $url = "https://openapi.airtel.africa/standard/v1/payments/" . $transactionId;

            $headers = [
                "Accept: */*",
                "X-Country: MG",
                "X-Currency: MGA",
                "Authorization: Bearer " . $this->accessToken // Assurez-vous que $this->accessToken contient le bon token
            ];
 
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Sécurité (vous pouvez activer ces options en production)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);

            if(curl_errno($ch)) {
                return 'Erreur Curl : ' . curl_error($ch);
            }

            curl_close($ch);

            $resultData = json_decode($response, true);


             //$resultData["data"]["transaction"]["status"]="TS";

            return $resultData;
    }

}
?>

