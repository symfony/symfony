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

use Doctrine\Common\EventManager;

/**
 * Interface that must be implemented by firewall listeners
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ListenerInterface
{
    /**
     * The implementation must connect this listener to all necessary events.
     *
     * Typical events are: "onCoreSecurity", and "filterCoreResponse"
     *
     * @param EventManager $evm
     */
    function register(EventManager $evm);

    /**
     * The implementation must remove this listener from any events that it had
     * connected to in register().
     *
     * It may remove this listener from "onCoreSecurity", but this is ensured by
     * the firewall anyway.
     *
     * @param EventManager $evm
     */
    function unregister(EventManager $evm);
}