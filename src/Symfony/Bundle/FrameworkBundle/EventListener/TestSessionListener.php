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

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0. Use Symfony\Component\HttpKernel\EventListener\TestSessionListener instead.', TestSessionListener::class), E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\EventListener\AbstractTestSessionListener;

/**
 * TestSessionListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0.
 */
class TestSessionListener extends AbstractTestSessionListener
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getSession()
    {
        return $this->container->get('session', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }
}
