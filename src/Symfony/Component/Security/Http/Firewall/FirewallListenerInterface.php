<?php

namespace Symfony\Component\Security\Http\Firewall;

interface FirewallListenerInterface
{
    /**
     * Defines the priority of the listener.
     * The higher the number, the earlier a listener is executed.
     *
     * @return int
     */
    public function getPriority(): int;
}
