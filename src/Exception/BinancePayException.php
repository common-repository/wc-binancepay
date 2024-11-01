<?php

declare(strict_types=1);

namespace BinancePay\WC\Exception;

class BinancePayException extends \RuntimeException
{
    public function __construct(string $message, int $code, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
