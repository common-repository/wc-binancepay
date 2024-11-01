<?php

declare(strict_types=1);

namespace BinancePay\WC\Helper;

use BinancePay\WC\Exception\BinancePayException;

class BinanceApiHelper {
	const RATES_CACHE_KEY = 'binancepay_exchange_rates';

	public static function getExchangeRate(string $stableCoin, string $storeCurrency): float {
		// Replace ticker with Coingecko ID if needed.
		// todo: refactor to more general approach; mappings per rate provider etc.
		if ($stableCoin === 'USDT') {
			$stableCoin = 'tether';
		}

		$storeCurrency = strtolower($storeCurrency);
		$stableCoin = strtolower($stableCoin);

		// Use transients API to cache pm for a few minutes to avoid too many requests to BTCPay Server.
		if ($cachedRates = get_transient(self::RATES_CACHE_KEY)) {
			if (isset($cachedRates[$stableCoin][$storeCurrency])) {
				return (float) $cachedRates[$stableCoin][$storeCurrency];
			}
		}

		// Todo: can be refactored to have ExchangeInterface and implementations for multiple rate providers beside Coingecko.
		$client = new \BinancePay\WC\Client\CoingeckoClient();
		try {
			$rates = $client->getRates([$stableCoin], [$storeCurrency]);
			// Store rates into cache.
			if (isset($rates[$stableCoin][$storeCurrency])) {
				set_transient( self::RATES_CACHE_KEY, $rates,5 * MINUTE_IN_SECONDS );
				return $rates[$stableCoin][$storeCurrency];
			}
		} catch (\Throwable $e) {
			Logger::debug('Error fetching rates: ' . $e->getMessage());
		}

		Logger::debug('Failed to fetch exchange rate for stableCoin: ' . $stableCoin . ' and storeCurrency: ' . $storeCurrency, true);
		throw new BinancePayException('Could not fetch exchange rates, aborting. ', 500);
	}

}
