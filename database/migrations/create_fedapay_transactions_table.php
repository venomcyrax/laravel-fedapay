<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('fedapay.table', 'fedapay_transactions');

        Schema::create($table, function (Blueprint $table) {
            $table->id();

            // Relation polymorphique vers le modèle du projet hôte (ex: Order)
            $table->morphs('payable');

            // Données FedaPay
            $table->string('fedapay_id')->unique()->comment('ID transaction FedaPay');
            $table->string('fedapay_token')->nullable()->comment('Token de paiement FedaPay');
            $table->string('merchant_reference')->nullable()->comment('Référence marchand');

            // Montant & devise
            $table->unsignedInteger('amount')->comment('Montant en centimes');
            $table->string('currency', 10)->default('XOF');

            // Statut : pending | approved | declined | canceled | refunded | transferred | expired
            $table->string('status')->default('pending')->index();

            // Driver utilisé : redirect | direct
            $table->string('driver')->default('redirect');

            // Opérateur Mobile Money (paiement direct uniquement)
            $table->string('operator_id')->nullable();

            // Réponse complète de l'API FedaPay
            $table->json('metadata')->nullable()->comment('Payload complet de la réponse FedaPay');

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('fedapay.table', 'fedapay_transactions'));
    }
};
