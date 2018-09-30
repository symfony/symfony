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

use Symfony\Component\HttpKernel\Event\PostResponseEvent;

/**
 * Implement this interface to allow automatically add the "kernel.event_listener" tag with event "kernel.terminate".
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
interface KernelTerminateListenerInterface
{
    /**
     * @param PostResponseEvent $event
     */
    public function onKernelTerminate(PostResponseEvent $event): void;
}
