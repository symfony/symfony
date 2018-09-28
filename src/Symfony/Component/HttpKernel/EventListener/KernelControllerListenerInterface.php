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

use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Implement this interface to allow automatically add the "kernel.event_listener" tag with event "kernel.controller".
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
interface KernelControllerListenerInterface
{
    /**
     * @param FilterControllerEvent $event
     */
    public function __invoke(FilterControllerEvent $event): void;
}
