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
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class TestContainer extends Container
{
    private $kernel;
    private $privateServicesLocatorId;

    public function __construct(KernelInterface $kernel, string $privateServicesLocatorId)
    {
        $this->kernel = $kernel;
        $this->privateServicesLocatorId = $privateServicesLocatorId;
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        $this->getPublicContainer()->compile();
    }

    /**
     * {@inheritdoc}
     */
    public function isCompiled(): bool
    {
        return $this->getPublicContainer()->isCompiled();
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterBag(): ParameterBagInterface
    {
        return $this->getPublicContainer()->getParameterBag();
    }

    /**
     * {@inheritdoc}
     *
     * @return array|bool|float|int|string|null
     */
    public function getParameter($name)
    {
        return $this->getPublicContainer()->getParameter($name);
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($name): bool
    {
        return $this->getPublicContainer()->hasParameter($name);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($name, $value)
    {
        $this->getPublicContainer()->setParameter($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function set($id, $service)
    {
        $this->getPublicContainer()->set($id, $service);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id): bool
    {
        return $this->getPublicContainer()->has($id) || $this->getPrivateContainer()->has($id);
    }

    /**
     * {@inheritdoc}
     *
     * @return object|null
     */
    public function get($id, $invalidBehavior = /* self::EXCEPTION_ON_INVALID_REFERENCE */ 1)
    {
        return $this->getPrivateContainer()->has($id) ? $this->getPrivateContainer()->get($id) : $this->getPublicContainer()->get($id, $invalidBehavior);
    }

    /**
     * {@inheritdoc}
     */
    public function initialized($id): bool
    {
        return $this->getPublicContainer()->initialized($id);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        // ignore the call
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceIds(): array
    {
        return $this->getPublicContainer()->getServiceIds();
    }

    /**
     * {@inheritdoc}
     */
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
