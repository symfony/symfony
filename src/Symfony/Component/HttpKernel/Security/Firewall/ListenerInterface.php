<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\EventDispatcher\EventDispatcher;

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
     * Typical events are: "core.security", and "core.response"
     * 
     * @param EventDispatcher $dispatcher
     * @return void
     */
    function register(EventDispatcher $dispatcher);
    
    /**
     * The implementation must remove this listener from any events that it had
     * connected to in register().
     * 
     * It may remove this listener from "core.security", but this is ensured by
     * the firewall anyway.
     * 
     * @param EventDispatcher $dispatcher
     * @return void
     */
    function unregister(EventDispatcher $dispatcher);
}