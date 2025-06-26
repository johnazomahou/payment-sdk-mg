<?php
// services/OrangeMoney.php
require_once __DIR__ . '/../interfaces/PaymentService.php';


class OrangeMoney implements PaymentService {

                public string $accessToken = '';
                public string $pay_token = '';
                public string $amount = '';
   
                public function generateTransactionId(): string {
                    $prefix = 'TICKO'; // 5 caractères
                    $timestamp = date('ymdHis'); // 12 caractères (ex: 250602104512 = 2025-06-02 10:45:12)
                    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); // 6 caractères

                    // Total = 5 (prefix) + 12 (timestamp) + 6 (random) = 23 caractères
                    return $prefix . $timestamp . $random;
                }


                function generateReference() {
                        $prefix = "Tic";
                        $random = strtoupper(bin2hex(random_bytes(5))); // 10 caractères alphanumériques
                        $reference = $prefix . $random;

                        // S'assurer que la référence est bien entre 4 et 64 caractères
                        return substr($reference, 0, 64);
                }



    
                public function recuperertoken() {

                    // Remplace ces valeurs par tes véritables identifiants
                    $consumerKey = "votre client clee";        // Clé client
                    $consumerSecret = "yourconsummeurkey";     // Secret client

                    // Encodage Base64 de "client_id:client_secret"
                    $credentials = base64_encode($consumerKey . ":" . $consumerSecret);

                    $ch = curl_init();

                    
                    curl_setopt($ch, CURLOPT_URL, "https://api.orange.com/oauth/v3/token");
                    curl_setopt($ch, CURLOPT_POST, 1);

                    // Corps de la requête
                    $postFields = "grant_type=client_credentials";

                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

                    // En-têtes
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Authorization: Basic $credentials",
                        "Content-Type: application/x-www-form-urlencoded",
                        "Accept: application/json"
                    ));

                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À activer en production

                    // Exécuter la requête
                    $response = curl_exec($ch);

                    if ($response === false) {
                        echo json_encode(["error" => curl_error($ch)], JSON_PRETTY_PRINT);
                        curl_close($ch);
                        die();
                    }

                    curl_close($ch);

                    $resultData = json_decode($response, true);

                    if (!isset($resultData["access_token"])) {
                        echo json_encode(["message" => $resultData], JSON_PRETTY_PRINT);
                        die();
                    }

                    // Token récupéré avec succès
                    $this->accessToken = $resultData["access_token"];

                    return $resultData;

                }


                public function payer($numero, $montant) {
                       // Appel API Orange fictif ici

                                
                        $url = "https://api.orange.com/orange-money-webpay/mg/v1/webpayment";
                        $accessToken = $this->accessToken;
                        $transactionId = $this->generateTransactionId();


                        $headers = [
                                "Accept: application/json ",
                                "Content-Type: application/json",
                                "Authorization: Bearer " . $accessToken
                        ];

                        $body = [
                            "merchant_key" => "marchent key",//exempele 7b00df6 vous devrez la généter dans votre compte Orange Money
                            "currency" => "MGA",//OUV en sandbox
                            "order_id" => $transactionId,
                            "amount" => $montant,
                            "return_url" => "https://myapp.com/payment/return.php?transaction_id=$transactionId",
                            "cancel_url" => "https://myapp.com/",
                            "notif_url" => "https://myapp.com/payment/notify.php",
                            "lang" => "fr",
                            "reference" => $this->generateReference()
                        ];

                    
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); // ENVOI CORRECT
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);


                        $response = curl_exec($ch);

                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      
                        // echo json_encode(["message" => [$httpCode,$body,$headers]], JSON_PRETTY_PRINT);
                        // die();


                        if (curl_errno($ch)) {
                            return 'Erreur Curl : ' . curl_error($ch);
                        }

                        curl_close($ch);
                        $resultData = json_decode($response, true);


                       

                        $resultFinal = [
                            'transactionData' => [
                                'api_name' => 'ORANGE_MONEY_MG',
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

                     $url = "https://api.orange.com/orange-money-webpay/mg/v1/transactionstatus";

                    $data = [
                        "order_id" => $transactionId,
                        "amount" => intval($this->amount),
                        "pay_token" => $this->pay_token
                    ];


                    $ch = curl_init($url);

                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Accept: application/json",
                        "Authorization: Bearer {$this->accessToken}",
                        "Content-Type: application/json"
                    ]);

                    if(curl_errno($ch)) {
                            return 'Erreur Curl : ' . curl_error($ch);
                        }

                 $response = curl_exec($ch);

                 $resultData = json_decode($response, true);

                   

                    // $resultData['status'] = 'completed';
                 return $resultData;
                  
                  
                }

    }
?>
