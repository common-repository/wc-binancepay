<?php

declare(strict_types=1);

namespace BinancePay\WC\Client;

use BinancePay\WC\Exception\BinancePayException;
use BinancePay\WC\Helper\Logger;
use BinancePay\WC\Helper\PreciseNumber;

class BinanceOrder extends AbstractClient
{
    public function createOrder(
		string $returnUrl,
		string $cancelUrl,
        PreciseNumber $stableCoinAmount,
	    string $stableCoin,
        string $orderId
    ): array {

	    Logger::debug('Running BinanceOrder::createOrder().');

	    $url = $this->getApiUrl() . 'v2/order';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

		$data = [
			'env' => ['terminalType' => 'WEB'],
			'orderAmount' => $stableCoinAmount->__toString(),
			'merchantTradeNo' => 'wc' . $orderId . 'r' . mt_rand(1,9999), // todo: reuse existing payment request
			'currency' => $stableCoin,
			'goods' => [ // todo fix that?
				'goodsType' => '01',
				'goodsCategory' => '0000',
				'referenceGoodsId' => $orderId,
				'goodsName' => 'Order ID - ' . $orderId ,
			],
			'returnUrl' => $returnUrl,
			'cancelUrl' => $cancelUrl,
		];
		$jsonData = json_encode($data, JSON_THROW_ON_ERROR);

		$this->signTransaction($jsonData);

		$headers['Binancepay-Signature'] = $this->getSignature();

		Logger::debug('Request headers: ' . print_r($headers));
		Logger::debug('Signature: ' . $this->getSignature());
		Logger::debug('Payload: ' . $this->getPayload());
		Logger::debug('JsonData: ' . $jsonData);

        $response = $this->getHttpClient()->request($method, $url, $headers, $jsonData);

        if (in_array($response->getStatus() ,[200, 201])) {
            return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function queryOrder(
        ?string $prepayId = null,
	    ?string $orderId = null
    ): array {

		Logger::debug('Running BinanceOrder::getOrderQuery().');

		if (empty($prepayId) && empty($orderId)) {
			throw new BinancePayException('You need to provide either a prepaidId or an orderId.', 500);
		}

        $url = $this->getApiUrl() . 'v2/order/query';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

		$data = [];
	    if ($prepayId) {
		    $data['prepayId'] = $prepayId;
	    } else if ($orderId) {
		    $data['merchantTradeNo'] = $orderId;
	    }

		$jsonData = json_encode($data, JSON_THROW_ON_ERROR);
	    $this->signTransaction($jsonData);

	    $headers['Binancepay-Signature'] = $this->getSignature();

	    Logger::debug('Request headers: ' . print_r($headers, true));
	    Logger::debug('Signature: ' . $this->getSignature());
	    Logger::debug('Payload: ' . $this->getPayload());
	    Logger::debug('JsonData: ' . $jsonData);

        $response = $this->getHttpClient()->request($method, $url, $headers, $jsonData);

        if ($response->getStatus() === 200) {
            return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
