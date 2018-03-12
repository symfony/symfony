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

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TestContainer extends Container
{
    private $publicContainer;
    private $privateContainer;

    public function __construct(?ParameterBagInterface $parameterBag, SymfonyContainerInterface $publicContainer, PsrContainerInterface $privateContainer)
    {
        $this->parameterBag = $parameterBag ?? $publicContainer->getParameterBag();
        $this->publicContainer = $publicContainer;
        $this->privateContainer = $privateContainer;
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        $this->publicContainer->compile();
    }

    /**
     * {@inheritdoc}
     */
    public function isCompiled()
    {
        return $this->publicContainer->isCompiled();
    }

    /**
     * {@inheritdoc}
     */
    public function set($id, $service)
    {
        $this->publicContainer->set($id, $service);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        return $this->publicContainer->has($id) || $this->privateContainer->has($id);
    }

    /**
     * {@inheritdoc}
     */
    public function get($id, $invalidBehavior = /* self::EXCEPTION_ON_INVALID_REFERENCE */ 1)
    {
        return $this->privateContainer->has($id) ? $this->privateContainer->get($id) : $this->publicContainer->get($id, $invalidBehavior);
    }

    /**
     * {@inheritdoc}
     */
    public function initialized($id)
    {
        return $this->publicContainer->initialized($id);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->publicContainer->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceIds()
    {
        return $this->publicContainer->getServiceIds();
    }

    /**
     * {@inheritdoc}
     */
    public function getRemovedIds()
    {
        return $this->publicContainer->getRemovedIds();
    }
}
