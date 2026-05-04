<?php

namespace RivascoTech\FedaPay\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle géré par le package — stocke toutes les transactions FedaPay.
 *
 * La colonne `payable_type` + `payable_id` permettent de relier n'importe
 * quel modèle du projet hôte (relation polymorphique).
 *
 * Statuts FedaPay : pending | approved | declined | canceled | refunded | transferred | expired
 */
class FedaPayTransaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'           => 'integer',
        'metadata'         => 'array',
        'paid_at'          => 'datetime',
    ];

    public function getTable(): string
    {
        return config('fedapay.table', 'fedapay_transactions');
    }

    // ── Relation polymorphique vers le modèle "commande" du projet hôte ──
    public function payable()
    {
        return $this->morphTo();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return in_array($this->status, ['declined', 'canceled', 'expired']);
    }
}
