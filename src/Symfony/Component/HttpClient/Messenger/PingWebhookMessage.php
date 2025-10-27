<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Messenger;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final readonly class PingWebhookMessage implements \Stringable
{
    public function __construct(
        public string $method,
        public string $url,
        public array $options = [],
        public bool $throw = true,
    ) {
    }

    public function __toString(): string
    {
        return "[{$this->method}] {$this->url}";
    }
}
