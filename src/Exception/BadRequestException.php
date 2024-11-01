<?php

declare(strict_types=1);

namespace BinancePay\WC\Exception;

class BadRequestException extends RequestException
{
    public const STATUS = 400;
}
