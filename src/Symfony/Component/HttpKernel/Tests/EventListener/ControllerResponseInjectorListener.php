<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

class ControllerResponseInjectorListener
{
    protected $newParameters = array();
    
    public function __construct(array $newParameters)
    {
        $this->newParameters = $newParameters;
    }
    
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $controllerResult = $event->getControllerResult();
        
        $event->setControllerResult(array_merge($controllerResult, $this->newParameters));
    }
}
