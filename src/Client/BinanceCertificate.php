<?php

declare(strict_types=1);

namespace BinancePay\WC\Client;

use BinancePay\WC\Exception\BinancePayException;
use BinancePay\WC\Helper\Logger;

class BinanceCertificate extends AbstractClient
{
	public function getCertificate(): array {

		Logger::debug( 'Running BinanceCertificate::getCertificate()).' );

		$url = $this->getApiUrl() . 'certificates';
		$headers = $this->getRequestHeaders();
		$method = 'POST';

		$jsonData = json_encode([], JSON_THROW_ON_ERROR);

		$this->signTransaction($jsonData);
		$headers['Binancepay-Signature'] = $this->getSignature();

		$response = $this->getHttpClient()->request($method, $url, $headers, $jsonData);

		if (in_array($response->getStatus() ,[200, 201])) {
			return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		} else {
			throw $this->getExceptionByStatusCode($method, $url, $response);
		}
	}
}
