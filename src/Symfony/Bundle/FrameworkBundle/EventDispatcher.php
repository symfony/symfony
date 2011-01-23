<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This EventDispatcher automatically gets the kernel listeners injected
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher
{
    protected $container;
    protected $ids;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function registerKernelListeners(array $ids)
    {
        $this->ids = $ids;
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners($name)
    {
        if (!isset($this->ids[$name])) {
            return array();
        }

        $listeners = array();
        $all = $this->ids[$name];
        krsort($all);
        foreach ($all as $l) {
            foreach ($l as $id => $method) {
                $listeners[] = array($this->container->get($id), $method);
            }
        }

        return $listeners;
    }
}
