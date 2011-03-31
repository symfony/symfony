<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Component\HttpKernel\Debug\ErrorHandler as BaseErrorHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Events;

/**
 * ErrorHandler.
 *
 * @author Martin Hasoň <hason@gmail.com>
 */
class ErrorHandler extends BaseErrorHandler
{
    private $levels = array(
        E_ERROR             => 'Fatal Error',
        E_CORE_ERROR        => 'Fatal Core Error',
        E_COMPILE_ERROR     => 'Fatal Compile Error',
        E_PARSE             => 'Parse Error',
    );

    private $level;

    private $container;

    /**
     * Constructor.
     *
     * @param integer            $level     The level at which the conversion to Exception is done (null to use the error_reporting() value and 0 to disable)
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct($level = null, ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->level = null === $level ? error_reporting() : $level;
        parent::__construct($level);
    }

    public function register()
    {
        parent::register();
        register_shutdown_function(array($this, 'handleShutdown'));
    }

    /**
     * Handles the fatal error to convert it to an exception and create a response.
     */
    public function handleShutdown()
    {
        static $handling;

        if (true === $handling || 0 === $this->level) {
            return false;
        }

        $handling = true;

        $error = error_get_last();
        $level = $error['type'];
        if (error_reporting() & $level && $this->level & $level && isset($this->levels[$level]) && null !== $this->container) {
            $exception = new FatalErrorException($this->levels[$level].': '.$error['message'], 0, $level, $error['file'], $error['line']);

            try {
                $kernel = $this->container->get('kernel');
                $request = $this->container->get('request');
                $type = HttpKernelInterface::MASTER_REQUEST;
                $dispatcher = $this->container->get('event_dispatcher');

                $event = new GetResponseForExceptionEvent($kernel, $request, $type, $exception);
                $dispatcher->dispatch(Events::onCoreException, $event);

                if ($event->hasResponse()) {
                    $response = $event->getResponse();

                    try {
                        $event = new FilterResponseEvent($kernel, $request, $type, $response);
                        $dispatcher->dispatch(Events::onCoreResponse, $event);
                        $response = $event->getResponse();
                    } catch (\Exception $e) {
                    }

                    if (!headers_sent()) {
                        $response->sendHeaders();
                    }
                    $response->sendContent();
                }
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }
}
