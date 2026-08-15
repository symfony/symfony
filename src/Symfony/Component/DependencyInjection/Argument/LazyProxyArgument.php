<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Represents a reference to a service that should be injected as a lazy proxy.
 *
 * @author HypeMC <hypemc@gmail.com>
 */
class LazyProxyArgument implements ArgumentInterface
{
    use ArgumentTrait;

    private Reference $service;
    private array $interfaces;
    private ?Reference $resolvedReference = null;

    /**
     * @param string|string[] $interfaces The interface(s) the proxy should implement, or none to proxy the service's own class
     */
    public function __construct(Reference $service, string|array $interfaces = [])
    {
        $this->service = $service;
        $this->setInterfaces($interfaces);
    }

    public function getValues(): array
    {
        return [$this->service, $this->interfaces, $this->resolvedReference];
    }

    public function setValues(array $values): void
    {
        $this->service = $values[0];

        if (isset($values[1])) {
            $this->setInterfaces($values[1]);
        }

        $this->resolvedReference = $values[2] ?? null;
    }

    private function setInterfaces(string|array $interfaces): void
    {
        $isValid = \is_string($interfaces)
            ? '' !== $interfaces
            : $interfaces === array_filter($interfaces, static fn ($interface) => \is_string($interface) && '' !== $interface);

        if (!$isValid) {
            throw new InvalidArgumentException('A lazy proxy argument expects $interfaces to be a non-empty string or an array of non-empty strings.');
        }

        $this->interfaces = \is_string($interfaces) ? [$interfaces] : $interfaces;
    }
}
