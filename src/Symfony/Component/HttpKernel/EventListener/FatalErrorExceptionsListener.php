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

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects a fatal error exceptions handler into the ErrorHandler.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class FatalErrorExceptionsListener implements EventSubscriberInterface
{
    private $handler = null;

    public function __construct($handler)
    {
        if (is_callable($handler)) {
            $this->handler = $handler;
        }
    }

    public function injectHandler()
    {
        if ($this->handler) {
            ErrorHandler::setFatalErrorExceptionHandler($this->handler);
            $this->handler = null;
        }
    }

    public static function getSubscribedEvents()
    {
        // Don't register early as e.g. the Router is generally required by the handler
        return array(KernelEvents::REQUEST => array('injectHandler', 8));
    }
}
