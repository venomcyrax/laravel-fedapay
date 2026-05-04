<?php

namespace RivascoTech\FedaPay\Http\Controllers;

use FedaPay\Webhook;
use FedaPay\Error\SignatureVerification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RivascoTech\FedaPay\Events\TransactionApproved;
use RivascoTech\FedaPay\Events\TransactionCanceled;
use RivascoTech\FedaPay\Events\TransactionDeclined;
use RivascoTech\FedaPay\Events\TransactionRefunded;
use RivascoTech\FedaPay\Events\TransactionTransferred;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

/**
 * Gère les webhooks FedaPay et dispatch des events Laravel.
 * Aucune logique métier ici — tout est délégué aux listeners du projet hôte.
 */
class WebhookController extends Controller
{
    /**
     * POST /webhooks/fedapay (ou le chemin configuré dans fedapay.routes.webhook_path)
     */
    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('X-FEDAPAY-SIGNATURE');
        $secret    = config('fedapay.webhook_secret');

        // ── 1. Vérification de la signature ──────────────────────────────
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('[FedaPay] Webhook payload invalide: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerification $e) {
            Log::warning('[FedaPay] Webhook signature invalide: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventType = $event->type ?? null;
        $entity    = $event->entity ?? null;

        Log::info("[FedaPay] Webhook reçu: {$eventType}");

        // ── 2. Dispatcher ─────────────────────────────────────────────────
        match ($eventType) {
            'transaction.approved'    => $this->onApproved($entity),
            'transaction.canceled'    => $this->onStatusChange($entity, 'canceled', TransactionCanceled::class),
            'transaction.declined'    => $this->onStatusChange($entity, 'declined', TransactionDeclined::class),
            'transaction.refunded'    => $this->onStatusChange($entity, 'refunded', TransactionRefunded::class),
            'transaction.transferred' => $this->onStatusChange($entity, 'transferred', TransactionTransferred::class),
            default                   => Log::info("[FedaPay] Événement ignoré: {$eventType}"),
        };

        return response()->json(['received' => true]);
    }

    // ── Paiement approuvé — idempotent avec verrou pessimiste ────────────

    private function onApproved(mixed $entity): void
    {
        $fedapayId = $entity['id'] ?? null;
        if (! $fedapayId) return;

        DB::transaction(function () use ($fedapayId, $entity) {
            $tx = FedaPayTransaction::where('fedapay_id', (string) $fedapayId)
                ->lockForUpdate()
                ->first();

            // Idempotence : déjà traité → on sort
            if (! $tx || $tx->isApproved()) {
                Log::info("[FedaPay] Transaction #{$fedapayId} déjà approuvée — ignorée.");
                return;
            }

            $tx->update([
                'status'   => 'approved',
                'paid_at'  => now(),
                'metadata' => array_merge($tx->metadata ?? [], ['webhook_payload' => (array) $entity]),
            ]);

            // Le projet hôte écoute cet event pour sa logique métier
            TransactionApproved::dispatch($tx->fresh(), $entity);
        });
    }

    // ── Changement de statut générique ───────────────────────────────────

    private function onStatusChange(mixed $entity, string $status, string $eventClass): void
    {
        $fedapayId = $entity['id'] ?? null;
        if (! $fedapayId) return;

        $tx = FedaPayTransaction::where('fedapay_id', (string) $fedapayId)->first();
        if (! $tx) return;

        $tx->update([
            'status'   => $status,
            'metadata' => array_merge($tx->metadata ?? [], ['webhook_payload' => (array) $entity]),
        ]);

        $eventClass::dispatch($tx->fresh(), $entity);
    }
}
