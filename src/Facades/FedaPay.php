<?php

namespace RivascoTech\FedaPay\Facades;

use Illuminate\Support\Facades\Facade;
use RivascoTech\FedaPay\FedaPayManager;

/**
 * @method static array initiate(\RivascoTech\FedaPay\Contracts\Payable $payable, array $options = [])
 * @method static array direct(\RivascoTech\FedaPay\Contracts\Payable $payable, string $phone, string $country, string $operatorId, array $options = [])
 * @method static array resolve(\RivascoTech\FedaPay\Contracts\Payable $payable, ?string $operatorId = null, ?string $phone = null)
 * @method static array fake(\RivascoTech\FedaPay\Contracts\Payable $payable)
 * @method static array enabledOperators()
 * @method static array|null findOperator(string $operatorId)
 * @method static bool supportsDirectPayment(string $operatorId)
 *
 * @see \RivascoTech\FedaPay\FedaPayManager
 */
class FedaPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FedaPayManager::class;
    }
}
