<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This EventDispatcher automatically gets the kernel listeners injected
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher
{
    public function registerKernelListeners(array $kernelListeners)
    {
        foreach ($kernelListeners as $priority => $listeners) {
            foreach ($listeners as $listener) {
                $listener->register($this, $priority);
            }
        }
    }
}
