<?php

namespace RivascoTech\FedaPay\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

/**
 * Trait à ajouter sur le modèle Order du projet hôte.
 *
 *   use RivascoTech\FedaPay\Traits\HasFedaPayPayments;
 *
 *   class Order extends Model implements Payable
 *   {
 *       use HasFedaPayPayments;
 *       // ...
 *   }
 */
trait HasFedaPayPayments
{
    /**
     * Toutes les transactions FedaPay liées à ce modèle.
     */
    public function fedaPayTransactions(): MorphMany
    {
        return $this->morphMany(FedaPayTransaction::class, 'payable');
    }

    /**
     * Dernière transaction FedaPay (la plus récente).
     */
    public function latestFedaPayTransaction(): MorphOne
    {
        return $this->morphOne(FedaPayTransaction::class, 'payable')
            ->latestOfMany();
    }

    /**
     * Vérifie si une transaction approuvée existe pour ce modèle.
     */
    public function hasPaidWithFedaPay(): bool
    {
        return $this->fedaPayTransactions()
            ->where('status', 'approved')
            ->exists();
    }
}
