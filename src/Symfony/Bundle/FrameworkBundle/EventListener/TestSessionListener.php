<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\HttpKernel\EventListener\TestSessionListener as BaseTestSessionListener;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * TestSessionListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TestSessionListener extends BaseTestSessionListener
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getSession()
    {
        if (!$this->container->has('session')) {
            return;
        }

        return $this->container->get('session');
    }
}
