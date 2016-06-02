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
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * WebProcessor override to read from the HttpFoundation's Request.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class WebProcessor extends BaseWebProcessor
{
    public function __construct(array $extraFields = null)
    {
        // Pass an empty array as the default null value would access $_SERVER
        parent::__construct(array(), $extraFields);
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if ($event->isMasterRequest()) {
            $this->serverData = $event->getRequest()->server->all();
            $this->serverData['REMOTE_ADDR'] = $event->getRequest()->getClientIp();
        }
    }
}
