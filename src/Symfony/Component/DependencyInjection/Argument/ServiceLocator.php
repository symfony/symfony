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

use Symfony\Component\DependencyInjection\ServiceLocator as BaseServiceLocator;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ServiceLocator extends BaseServiceLocator
{
    public function __construct(
        private \Closure $factory,
        private array $serviceMap,
        private ?array $serviceTypes = null,
    ) {
        parent::__construct($serviceMap);
    }

    public function get(string $id): mixed
    {
        return match (\count($this->serviceMap[$id] ?? [])) {
            0 => parent::get($id),
            1 => $this->serviceMap[$id][0],
            default => ($this->factory)(...$this->serviceMap[$id]),
        };
    }

    public function getProvidedServices(): array
    {
        return $this->serviceTypes ??= array_map(fn () => '?', $this->serviceMap);
    }
}
