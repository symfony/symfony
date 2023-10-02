<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

/**
 * Represents a service call.
 *
 * @author valtzu <valtzu@gmail.com>
 */
class ServiceCallMessage implements \Stringable
{
    public function __construct(
        private readonly string $serviceId,
        private readonly string $method = '__invoke',
        private readonly array $arguments = [],
    ) {
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function __toString(): string
    {
        return "@$this->serviceId".('__invoke' !== $this->method ? "::$this->method" : '');
    }
}
