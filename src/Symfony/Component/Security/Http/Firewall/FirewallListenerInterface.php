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

/**
 * Can be implemented by firewall listeners to define their priority in execution.
 *
 * @author Christian Scheb <me@christianscheb.de>
 */
interface FirewallListenerInterface
{
    /**
     * Defines the priority of the listener.
     * The higher the number, the earlier a listener is executed.
     */
    public static function getPriority(): int;
}
