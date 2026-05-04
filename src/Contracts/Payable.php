<?php

namespace RivascoTech\FedaPay\Contracts;

/**
 * Contrat à implémenter sur le modèle "commande" du projet hôte.
 *
 * Exemple minimal sur App\Models\Order :
 *
 *   class Order extends Model implements Payable
 *   {
 *       use HasFedaPayPayments;
 *
 *       public function getPayableAmount(): int   { return (int) $this->total_amount; }
 *       public function getPayableDescription(): string { return 'Commande #' . $this->id; }
 *       public function getPayableReference(): string  { return 'ORDER-' . $this->id; }
 *       public function getPayableCustomer(): array
 *       {
 *           return [
 *               'firstname' => $this->user->name,
 *               'lastname'  => '',
 *               'email'     => $this->user->email ?? 'noreply@example.com',
 *           ];
 *       }
 *   }
 */
interface Payable
{
    /**
     * Montant en centimes (entier). Ex : 5000 pour 5 000 XOF.
     */
    public function getPayableAmount(): int;

    /**
     * Description affichée sur la page FedaPay.
     */
    public function getPayableDescription(): string;

    /**
     * Référence marchand unique (apparaît dans le dashboard FedaPay).
     */
    public function getPayableReference(): string;

    /**
     * Informations client envoyées à FedaPay.
     * Clés attendues : firstname, lastname, email.
     * Clé optionnelle pour paiement direct : phone_number => ['number' => '...', 'country' => '...']
     *
     * @return array{firstname: string, lastname: string, email: string}
     */
    public function getPayableCustomer(): array;
}
