<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Monolog\Processor\WebProcessor as BaseWebProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * WebProcessor override to read from the HttpFoundation's Request
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class WebProcessor extends BaseWebProcessor
{
    public function __construct()
    {
        // Pass an empty array as the default null value would access $_SERVER
        parent::__construct(array());
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST === $event->getRequestType()) {
            $this->serverData = $event->getRequest()->server->all();
        }
    }
}
