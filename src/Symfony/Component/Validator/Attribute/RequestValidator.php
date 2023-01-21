<?php

namespace Symfony\Component\Validator\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class RequestValidator
{
    public const ORDER_ATTRIBUTES = 'attributes';
    public const ORDER_SERIALIZE = 'serialize';
    public const ORDER_QUERY = 'query';
    public const ORDER_REQUEST = 'request';

    public function __construct(
        public string $class,
        public bool $override = true,
        public array $order = [
            self::ORDER_ATTRIBUTES,
            self::ORDER_QUERY,
            self::ORDER_REQUEST,
        ],
        public string $serializedFormat = 'json'
    ) {
    }
}
