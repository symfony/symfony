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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Interface that must be implemented by firewall listeners.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 4.3, turn listeners into callables instead
 */
interface ListenerInterface
{
    public function handle(GetResponseEvent $event);
}
