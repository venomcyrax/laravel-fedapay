<?php

use Illuminate\Support\Facades\Route;
use RivascoTech\FedaPay\Http\Controllers\WebhookController;

Route::post(
    config('fedapay.routes.webhook_path', 'webhooks/fedapay'),
    [WebhookController::class, 'handle']
)
->name('fedapay.webhook')
->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
