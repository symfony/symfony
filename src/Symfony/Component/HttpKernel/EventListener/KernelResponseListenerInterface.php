<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Implement this interface to allow automatically add the "kernel.event_listener" tag with event "kernel.response".
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
interface KernelResponseListenerInterface
{
    /**
     * @param FilterResponseEvent $event
     */
    public function __invoke(FilterResponseEvent $event): void;
}
