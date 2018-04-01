<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Firewall;

use Symphony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Interface that must be implemented by firewall listeners.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ListenerInterface
{
    public function handle(GetResponseEvent $event);
}
