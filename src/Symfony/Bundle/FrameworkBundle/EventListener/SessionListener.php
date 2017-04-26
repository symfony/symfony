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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;

@trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use Symfony\Component\HttpKernel\EventListener\SessionListener instead.', SessionListener::class), E_USER_DEPRECATED);

/**
 * Sets the session in the request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use Symfony\Component\HttpKernel\EventListener\SessionListener instead
 */
class SessionListener extends AbstractSessionListener
{
    private $container;

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
