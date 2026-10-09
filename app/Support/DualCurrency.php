<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Qasa dual-currency helpers — never invent the unused leg via FX.
 */
final class DualCurrency
{
    public const USD = 'USD';

    public const IQD = 'IQD';

    /** @var list<string> */
    public const CURRENCIES = [self::USD, self::IQD];

    /**
     * Resolve a single-currency amount into dual legs (unused side = 0).
     *
     * @return array{currency: string, amount_usd: float, amount_iqd: float, exchange_rate: float}
     */
    public static function legs(string $currency, float|int|string $amount): array
    {
        $currency = strtoupper(trim($currency));
        if (! in_array($currency, self::CURRENCIES, true)) {
            throw new InvalidArgumentException('Currency must be USD or IQD.');
        }

        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return [
            'currency' => $currency,
            'amount_usd' => $currency === self::USD ? $amount : 0.0,
            'amount_iqd' => $currency === self::IQD ? $amount : 0.0,
            'exchange_rate' => 0.0,
        ];
    }

    public static function normalize(?string $currency, ?float $amountUsd = null, ?float $amountIqd = null): array
    {
        if ($currency !== null && $currency !== '') {
            $amount = strtoupper($currency) === self::USD
                ? (float) ($amountUsd ?? 0)
                : (float) ($amountIqd ?? 0);

            return self::legs($currency, $amount);
        }

        $usd = round((float) ($amountUsd ?? 0), 2);
        $iqd = round((float) ($amountIqd ?? 0), 2);

        if ($usd > 0 && $iqd > 0) {
            throw new InvalidArgumentException('Provide a single currency — do not blend USD and IQD on one write.');
        }

        if ($usd > 0) {
            return self::legs(self::USD, $usd);
        }

        if ($iqd > 0) {
            return self::legs(self::IQD, $iqd);
        }

        throw new InvalidArgumentException('A USD or IQD amount is required.');
    }

    public static function primaryAmount(array $legs): float
    {
        return $legs['currency'] === self::USD
            ? (float) $legs['amount_usd']
            : (float) $legs['amount_iqd'];
    }
}
