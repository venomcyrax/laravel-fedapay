<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clés API FedaPay
    |--------------------------------------------------------------------------
    | public_key : clé publique (Checkout.js côté front)
    | secret_key : clé secrète côté serveur — NE JAMAIS exposer
    */
    'public_key'  => env('FEDAPAY_PUBLIC_KEY', ''),
    'secret_key'  => env('FEDAPAY_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Environnement
    |--------------------------------------------------------------------------
    | "sandbox" pour les tests, "live" pour la production
    */
    'environment' => env('FEDAPAY_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Clé secrète Webhook
    |--------------------------------------------------------------------------
    | Dashboard FedaPay → Webhooks → Clé de signature
    */
    'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Devise par défaut
    |--------------------------------------------------------------------------
    */
    'currency' => env('FEDAPAY_CURRENCY', 'XOF'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | callback_url : URL de retour après paiement (redirect)
    | webhook_path : endpoint écouté par FedaPay (sans slash initial)
    | middleware   : middlewares appliqués aux routes du package
    */
    'routes' => [
        'callback_url' => env('FEDAPAY_CALLBACK_URL', '/checkout/callback'),
        'webhook_path' => 'webhooks/fedapay',
        'middleware'   => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode test (bypass paiement réel)
    |--------------------------------------------------------------------------
    | Si true, FedaPayManager::initiate() retourne immédiatement un
    | résultat "approved" sans appeler l'API FedaPay.
    */
    'test_mode' => env('FEDAPAY_TEST_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Paiement direct Mobile Money
    |--------------------------------------------------------------------------
    | Opérateurs supportés pour le paiement sans redirection (push USSD/OTP).
    | Chaque opérateur doit avoir : id, label, endpoint, country, enabled.
    */
    'direct_payment_enabled' => env('FEDAPAY_DIRECT_ENABLED', false),

    'operators' => [
        // ── Bénin ──────────────────────────────────────────────────────────
        [
            'id'       => 'mtn_bj',
            'label'    => 'MTN Bénin',
            'endpoint' => 'mtn_open',
            'country'  => 'bj',
            'enabled'  => false,
        ],
        [
            'id'       => 'moov_bj',
            'label'    => 'Moov Bénin',
            'endpoint' => 'moov',
            'country'  => 'bj',
            'enabled'  => false,
        ],
        [
            'id'       => 'sbin_bj',
            'label'    => 'Celtiis Bénin',
            'endpoint' => 'sbin',
            'country'  => 'bj',
            'enabled'  => false,
        ],
        // ── Togo ───────────────────────────────────────────────────────────
        [
            'id'       => 'togocel_tg',
            'label'    => 'T-Money / Mixx by Yas (Togo)',
            'endpoint' => 'togocel',
            'country'  => 'tg',
            'enabled'  => false,
        ],
        [
            'id'       => 'moov_tg',
            'label'    => 'Moov Togo',
            'endpoint' => 'moov_tg',
            'country'  => 'tg',
            'enabled'  => false,
        ],
        // ── Côte d'Ivoire ──────────────────────────────────────────────────
        [
            'id'       => 'mtn_ci',
            'label'    => 'MTN Côte d\'Ivoire',
            'endpoint' => 'mtn_ci',
            'country'  => 'ci',
            'enabled'  => false,
        ],
        // ── Niger ──────────────────────────────────────────────────────────
        [
            'id'       => 'airtel_ne',
            'label'    => 'Airtel Niger',
            'endpoint' => 'airtel_ne',
            'country'  => 'ne',
            'enabled'  => false,
        ],
        // ── Sénégal ────────────────────────────────────────────────────────
        [
            'id'       => 'free_sn',
            'label'    => 'Free Sénégal',
            'endpoint' => 'free_sn',
            'country'  => 'sn',
            'enabled'  => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Table des transactions
    |--------------------------------------------------------------------------
    | Nom de la table gérée par le package.
    */
    'table' => 'fedapay_transactions',

];
