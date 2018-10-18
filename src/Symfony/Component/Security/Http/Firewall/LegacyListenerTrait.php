<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * @deprecated
 *
 * @internal
 */
trait LegacyListenerTrait
{
    /**
     * @deprecated since Symfony 4.3, use __invoke() instead
     */
    public function handle(GetResponseEvent $event)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.3, use __invoke() instead.', __METHOD__), E_USER_DEPRECATED);

        if (!$event instanceof RequestEvent) {
            $event = new class($event) extends RequestEvent {
                private $event;

                public function __construct(GetResponseEvent $event)
                {
                    parent::__construct($event->getKernel(), $event->getRequest(), $event->getRequestType());
                    $this->event = $event;
                }

                public function getResponse()
                {
                    return $this->event->getResponse();
                }

                public function setResponse(Response $response)
                {
                    $this->event->setResponse($response);
                }

                public function hasResponse()
                {
                    return $this->event->hasResponse();
                }
            };
        }

        $this->__invoke($event);
    }
}
