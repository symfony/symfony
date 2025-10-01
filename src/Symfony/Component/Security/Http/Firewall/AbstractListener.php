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
    /**
     * @deprecated since Symfony 7.4, to be removed in 8.0
     */
    final public function __invoke(RequestEvent $event): void
    {
        trigger_deprecation('symfony/security-http', '7.4', 'The "%s()" method is deprecated since Symfony 7.4 and will be removed in 8.0.', __METHOD__);

        if (false !== $this->supports($event->getRequest())) {
            $this->authenticate($event);
        }
    }

    public static function getPriority(): int
    {
        return 0; // Default
    }
}
