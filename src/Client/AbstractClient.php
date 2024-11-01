<?php

declare(strict_types=1);

namespace BinancePay\WC\Client;

use BinancePay\WC\Exception\BadRequestException;
use BinancePay\WC\Exception\ForbiddenException;
use BinancePay\WC\Exception\RequestException;
use BinancePay\WC\Http\ClientInterface;
use BinancePay\WC\Http\CurlClient;
use BinancePay\WC\Http\Response;

class AbstractClient
{

    private string $apiKey;

    private string $apiSecret;

    private string $baseUrl;

    private string $apiPath = '/binancepay/openapi/';

	private float $timestamp;

	private string $nonce;

	private string $payload;

	private string $signature;

    private ClientInterface $httpClient;

    public function __construct(string $baseUrl, string $apiKey, string $apiSecret, ClientInterface $client = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
		$this->timestamp = \round(microtime(true) * 1000);
		$this->nonce = \bin2hex(\random_bytes(16));

        // Use the $client parameter to use a custom cURL client, for example if you need to disable CURLOPT_SSL_VERIFYHOST and CURLOPT_SSL_VERIFYPEER
        if ($client === null) {
            $client = new CurlClient();
        }
        $this->httpClient = $client;
    }

    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function getApiUrl(): string
    {
        return $this->baseUrl . $this->apiPath;
    }

    protected function getApiKey(): string
    {
        return $this->apiKey;
    }

    protected function getApiSecret(): string
    {
        return $this->apiSecret;
    }

    protected function getTimestamp(): float
    {
        return $this->timestamp;
    }

    protected function getNonce(): string
    {
        return $this->nonce;
    }

    protected function getPayload(): string
    {
        return $this->payload;
    }

    protected function getSignature(): string
    {
        return $this->signature;
    }

    protected function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    protected function getRequestHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'BinancePay-Timestamp' => $this->getTimestamp(),
            'BinancePay-Nonce' => $this->getNonce(),
            'BinancePay-Certificate-SN' => $this->getApiKey(),
        ];
    }

    protected function getExceptionByStatusCode(
        string $method,
        string $url,
        Response $response
    ): RequestException {
        $exceptions = [
            ForbiddenException::STATUS => ForbiddenException::class,
            BadRequestException::STATUS => BadRequestException::class,
        ];

        $class = $exceptions[$response->getStatus()] ?? RequestException::class;
        $e = new $class($method, $url, $response);
        return $e;
    }

	protected function signTransaction(string $jsonData): void {
		$this->payload = $this->getTimestamp() . "\n" . $this->getNonce() . "\n" . $jsonData . "\n";
		$this->signature = strtoupper(hash_hmac('SHA512', $this->payload, $this->getApiSecret()));
	}
}
