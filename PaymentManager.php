<?php
// PaymentManager.php

class PaymentManager {
    private $service;

    public function __construct(PaymentService $service) {
        $this->service = $service;
    }

    public function payer($numero, $montant) {
        return $this->service->payer($numero, $montant);
    }

    public function recuperertoken() {
        return $this->service->recuperertoken();
    }

    public function verifierStatut($transactionId) {
        return $this->service->verifierStatut($transactionId);
    }
}
?>
