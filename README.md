# laravel-fedapay

Module FedaPay modulaire et réutilisable pour Laravel 11/12.
Supporte Mobile Money (MTN, Moov) avec paiement par redirection ou direct (push USSD/OTP).

---

## Installation

### 1. Ajouter le package

**Via Packagist (quand publié) :**
```bash
composer require rivascotech/laravel-fedapay
```

**En local (développement) — dans `composer.json` du projet hôte :**
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-fedapay"
        }
    ],
    "require": {
        "rivascotech/laravel-fedapay": "@dev"
    }
}
```
Puis : `composer update rivascotech/laravel-fedapay`

### 2. Publier la config
```bash
php artisan vendor:publish --tag=fedapay-config
```

### 3. Lancer la migration
```bash
php artisan migrate
```
Crée la table `fedapay_transactions`.

### 4. Variables d'environnement
```dotenv
FEDAPAY_PUBLIC_KEY=pk_sandbox_xxxx
FEDAPAY_SECRET_KEY=sk_sandbox_xxxx
FEDAPAY_ENV=sandbox                  # sandbox | live
FEDAPAY_WEBHOOK_SECRET=wh_sandbox_xxxx
FEDAPAY_CURRENCY=XOF
FEDAPAY_CALLBACK_URL=/checkout/callback
FEDAPAY_TEST_MODE=false
FEDAPAY_DIRECT_ENABLED=false
```

---

## Intégration sur le modèle Order

### 1. Implémenter le contrat `Payable`

```php
use RivascoTech\FedaPay\Contracts\Payable;
use RivascoTech\FedaPay\Traits\HasFedaPayPayments;

class Order extends Model implements Payable
{
    use HasFedaPayPayments;

    public function getPayableAmount(): int
    {
        return (int) $this->total_amount; // en centimes, ex: 5000 pour 5 000 XOF
    }

    public function getPayableDescription(): string
    {
        return 'Commande #' . $this->order_number;
    }

    public function getPayableReference(): string
    {
        return 'ORDER-' . $this->id;
    }

    public function getPayableCustomer(): array
    {
        return [
            'firstname' => $this->user->name,
            'lastname'  => '',
            'email'     => $this->user->email ?? 'noreply@example.com',
        ];
    }
}
```

---

## Utilisation

### Paiement avec redirection (tous opérateurs)

```php
use RivascoTech\FedaPay\Facades\FedaPay;

public function pay(Order $order)
{
    $result = FedaPay::initiate($order);
    return redirect($result['redirect_url']);
}
```

### Paiement direct Mobile Money (sans redirection)

```php
$result = FedaPay::direct(
    payable: $order,
    phone:      '64000001',
    country:    'bj',
    operatorId: 'mtn_bj',
);
// $result['type'] === 'direct'
// $result['transaction_id'] === ID FedaPay
```

### Résolution automatique (redirect ou direct selon config)

```php
// Laisse le manager choisir selon config('fedapay.direct_payment_enabled')
$result = FedaPay::resolve($order, $operatorId, $phone);
```

### Mode test (bypass API)

```php
// Dans .env : FEDAPAY_TEST_MODE=true
// Ou directement :
$result = FedaPay::fake($order);
// $result['transaction']->status === 'approved'
```

### Retour après paiement (callback URL)

```php
use FedaPay\Transaction;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

public function callback(Request $request)
{
    $fedapayId = $request->query('id');
    $transaction = Transaction::retrieve($fedapayId);

    $tx = FedaPayTransaction::where('fedapay_id', $fedapayId)->firstOrFail();
    $order = $tx->payable; // votre modèle Order

    if ($transaction->status === 'approved') {
        $order->update(['status' => 'paid']);
        return redirect()->route('checkout.success');
    }

    return redirect()->route('checkout.failed');
}
```

---

## Webhook

### Configuration sur FedaPay Dashboard
Endpoint à déclarer : `https://votre-domaine.com/webhooks/fedapay`

Le chemin est configurable :
```php
// config/fedapay.php
'routes' => [
    'webhook_path' => 'webhooks/fedapay',
],
```

### Écouter les events dans le projet hôte

Le module dispatch des events Laravel — votre app écoute et déclenche sa logique :

```php
// app/Providers/EventServiceProvider.php
use RivascoTech\FedaPay\Events\TransactionApproved;
use App\Listeners\HandlePaymentApproved;

protected $listen = [
    TransactionApproved::class => [HandlePaymentApproved::class],
];
```

```php
// app/Listeners/HandlePaymentApproved.php
use RivascoTech\FedaPay\Events\TransactionApproved;

class HandlePaymentApproved
{
    public function handle(TransactionApproved $event): void
    {
        $order = $event->transaction->payable; // votre modèle Order

        $order->update(['status' => 'paid', 'paid_at' => now()]);

        // Générer les billets, envoyer l'email, etc.
        app(TicketService::class)->generateForOrder($order);
        $order->user->notify(new OrderPaidNotification($order));
    }
}
```

### Events disponibles

| Event | Déclencheur FedaPay |
|---|---|
| `TransactionApproved` | `transaction.approved` |
| `TransactionDeclined` | `transaction.declined` |
| `TransactionCanceled` | `transaction.canceled` |
| `TransactionRefunded` | `transaction.refunded` |
| `TransactionTransferred` | `transaction.transferred` |

---

## Helpers sur le modèle (via `HasFedaPayPayments`)

```php
$order->fedaPayTransactions();        // MorphMany — toutes les transactions
$order->latestFedaPayTransaction();   // MorphOne — la plus récente
$order->hasPaidWithFedaPay();         // bool — transaction approved existante
```

---

## Opérateurs Mobile Money

```php
FedaPay::enabledOperators();          // Liste des opérateurs actifs
FedaPay::findOperator('mtn_bj');      // Trouve un opérateur par ID
FedaPay::supportsDirectPayment('mtn_bj'); // bool
```

Activer un opérateur dans `config/fedapay.php` :
```php
'operators' => [
    ['id' => 'mtn_bj', 'label' => 'MTN Bénin', 'endpoint' => 'mtn_open', 'country' => 'bj', 'enabled' => true],
    // ...
],
```

---

## Structure de la table `fedapay_transactions`

| Colonne | Type | Description |
|---|---|---|
| `id` | bigint | PK |
| `payable_type` | string | Classe du modèle hôte (ex: `App\Models\Order`) |
| `payable_id` | bigint | ID du modèle hôte |
| `fedapay_id` | string | ID transaction FedaPay (unique) |
| `fedapay_token` | string | Token de paiement FedaPay |
| `merchant_reference` | string | Référence marchand |
| `amount` | int | Montant en centimes |
| `currency` | string | Devise (XOF) |
| `status` | string | pending / approved / declined / canceled / refunded / transferred |
| `driver` | string | redirect / direct / test |
| `operator_id` | string | ID opérateur Mobile Money |
| `metadata` | json | Payload complet FedaPay |
| `paid_at` | datetime | Date du paiement approuvé |

---

## Licence

MIT — [RivascoTech](https://github.com/venomcyrax)
