<?php

namespace RivascoTech\FedaPay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

/**
 * Déclenché quand FedaPay confirme un paiement (webhook transaction.approved).
 *
 * Le projet hôte écoute cet event pour déclencher sa logique métier :
 *
 *   class HandlePaymentApproved
 *   {
 *       public function handle(TransactionApproved $event): void
 *       {
 *           $order = $event->transaction->payable; // votre modèle Order
 *           $order->update(['status' => 'paid']);
 *           // générer billets, envoyer email, etc.
 *       }
 *   }
 */
class TransactionApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly FedaPayTransaction $transaction,
        public readonly mixed              $payload = null,
    ) {}
}
