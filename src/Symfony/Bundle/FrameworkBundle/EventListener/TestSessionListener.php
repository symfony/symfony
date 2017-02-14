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

@trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use %s instead.', TestSessionListener::class, BaseTestSessionListener::class), E_USER_DEPRECATED);

/**
 * TestSessionListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0.
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
