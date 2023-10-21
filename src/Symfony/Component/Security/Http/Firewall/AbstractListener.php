<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * A base class for listeners that can tell whether they should authenticate incoming requests.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractListener implements FirewallListenerInterface
{
    final public function __invoke(RequestEvent $event): void
    {
        if (false !== $this->supports($event->getRequest())) {
            $this->authenticate($event);
        }
    }

    public static function getPriority(): int
    {
        return 0; // Default
    }
}
