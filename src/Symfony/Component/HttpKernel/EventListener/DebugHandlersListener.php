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

use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\AbstractExceptionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Configures errors and exceptions handlers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugHandlersListener implements EventSubscriberInterface
{
    private $exceptionHandler;
    private $logger;
    private $levels;
    private $debug;

    /**
     * @param callable             $exceptionHandler A handler that will be called on Exception
     * @param LoggerInterface|null $logger           A PSR-3 logger
     * @param array|int            $levels           An array map of E_* to LogLevel::* or an integer bit field of E_* constants
     * @param bool                 $debug            Enables/disables debug mode
     */
    public function __construct($exceptionHandler, LoggerInterface $logger = null, $levels = null, $debug = true)
    {
        if (is_callable($exceptionHandler)) {
            $this->exceptionHandler = $exceptionHandler;
        }
        $this->logger = $logger;
        $this->levels = $levels;
        $this->debug = $debug;
    }

    public function configure()
    {
        if ($this->logger) {
            $handler = set_error_handler('var_dump', 0);
            $handler = is_array($handler) ? $handler[0] : null;
            restore_error_handler();
            if ($handler instanceof ErrorHandler) {
                if ($this->debug) {
                    $handler->throwAt(-1);
                }
                $handler->setDefaultLogger($this->logger, $this->levels);
                if (is_array($this->levels)) {
                    $scream = 0;
                    foreach ($this->levels as $type => $log) {
                        $scream |= $type;
                    }
                    $this->levels = $scream;
                }
                $handler->screamAt($this->levels);
            }
            $this->logger = $this->levels = null;
        }
        if ($this->exceptionHandler) {
            $handler = set_exception_handler('var_dump');
            $handler = is_array($handler) ? $handler[0] : null;
            restore_exception_handler();
            if ($handler instanceof ErrorHandler) {
                $h = $handler->setExceptionHandler('var_dump') ?: $this->exceptionHandler;
                $handler->setExceptionHandler($h);
                $handler = is_array($h) ? $h[0] : null;
            }
            if ($handler instanceof AbstractExceptionHandler) {
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
