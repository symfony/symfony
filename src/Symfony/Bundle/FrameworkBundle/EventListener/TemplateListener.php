<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\Template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener to convert a template reference to a Response.
 *
 * @author Pierre du Plessis <pdples@gmail.com>
 */
class TemplateListener implements EventSubscriberInterface
{
    private $templating;

    public function __construct(EngineInterface $templating)
    {
        $this->templating = $templating;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::VIEW => array('onView', 128),
        );
    }

    public function onView(GetResponseForControllerResultEvent $event)
    {
        $result = $event->getControllerResult();

        if (!$result instanceof Template) {
            return;
        }

        $response = $this->templating->renderResponse($result->getTemplate(), $result->getParameters());

        if ($statusCode = $result->getStatusCode()) {
            $response->setStatusCode($statusCode);
        }

        if ($headers = $result->getHeaders()) {
            $response->headers->add($headers);
        }

        $event->setResponse($response);
    }
}
