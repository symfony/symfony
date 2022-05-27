<?php

namespace Symfony\Component\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Memoize
{
    public function __construct(
        public string  $pool,
        public ?string $keyGenerator = null,
        public ?int    $ttl = null,
    )
    {}
}
