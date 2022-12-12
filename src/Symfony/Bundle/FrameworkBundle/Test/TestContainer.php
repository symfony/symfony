<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A special container used in tests. This gives access to both public and
 * private services. The container will not include private services that has
 * been inlined or removed. Private services will be removed when they are not
 * used by other services.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class TestContainer extends Container
{
    private KernelInterface $kernel;
    private string $privateServicesLocatorId;

    public function __construct(KernelInterface $kernel, string $privateServicesLocatorId)
    {
        $this->kernel = $kernel;
        $this->privateServicesLocatorId = $privateServicesLocatorId;
    }

    public function compile()
    {
        $this->getPublicContainer()->compile();
    }

    public function isCompiled(): bool
    {
        return $this->getPublicContainer()->isCompiled();
    }

    public function getParameterBag(): ParameterBagInterface
    {
        return $this->getPublicContainer()->getParameterBag();
    }

    public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        return $this->getPublicContainer()->getParameter($name);
    }

    public function hasParameter(string $name): bool
    {
        return $this->getPublicContainer()->hasParameter($name);
    }

    public function setParameter(string $name, mixed $value)
    {
        $this->getPublicContainer()->setParameter($name, $value);
    }

    public function set(string $id, mixed $service)
    {
        $this->getPublicContainer()->set($id, $service);
    }

    public function has(string $id): bool
    {
        return $this->getPublicContainer()->has($id) || $this->getPrivateContainer()->has($id);
    }

    public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        return $this->getPrivateContainer()->has($id) ? $this->getPrivateContainer()->get($id) : $this->getPublicContainer()->get($id, $invalidBehavior);
    }

    public function initialized(string $id): bool
    {
        return $this->getPublicContainer()->initialized($id);
    }

    public function reset()
    {
        // ignore the call
    }

    public function getServiceIds(): array
    {
        return $this->getPublicContainer()->getServiceIds();
    }

    public function getRemovedIds(): array
    {
        return $this->getPublicContainer()->getRemovedIds();
    }

    private function getPublicContainer(): Container
    {
        if (null === $container = $this->kernel->getContainer()) {
            throw new \LogicException('Cannot access the container on a non-booted kernel. Did you forget to boot it?');
        }

        return $container;
    }

    private function getPrivateContainer(): ContainerInterface
    {
        return $this->getPublicContainer()->get($this->privateServicesLocatorId);
    }
}
