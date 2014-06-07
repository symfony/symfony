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

use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configures the ExceptionHandler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListener implements EventSubscriberInterface
{
    private $exceptionHandler;

    public function __construct($exceptionHandler)
    {
        if (is_callable($exceptionHandler)) {
            $this->exceptionHandler = $exceptionHandler;
        }
    }

    public function configure()
    {
        if ($this->exceptionHandler) {
            $handler = set_exception_handler('var_dump');
            $handler = is_array($handler) ? $handler[0] : null;
            restore_exception_handler();
            if ($handler instanceof ExceptionHandler) {
                $handler->setHandler($this->exceptionHandler);
            }
            $this->exceptionHandler = null;
        }
    }

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::REQUEST => array('configure', 2048));
    }
}
