<?php

namespace RivascoTech\FedaPay\Contracts;

use RivascoTech\FedaPay\Models\FedaPayTransaction;

interface PaymentDriver
{
    /**
     * Initie un paiement pour l'objet payable donné.
     *
     * Retourne un tableau normalisé :
     * [
     *   'type'           => 'redirect' | 'direct',
     *   'redirect_url'   => string|null,   // URL FedaPay (redirect uniquement)
     *   'transaction_id' => string,        // ID FedaPay de la transaction
     *   'transaction'    => FedaPayTransaction,
     * ]
     *
     * @param Payable $payable  Modèle Order du projet hôte
     * @param array   $options  Options supplémentaires (phone, country, endpoint, etc.)
     * @return array
     * @throws \Exception
     */
    public function initiate(Payable $payable, array $options = []): array;
}
