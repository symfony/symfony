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
use Symfony\Bundle\FrameworkBundle\Templating\TemplatedResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
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

        if (!$result instanceof TemplatedResponseInterface) {
            return;
        }

        $response = $result->getResponse($this->templating);

        if (!$response instanceof Response) {
            $msg = sprintf('The method %s::getResponse() must return a response (%s given).', get_class($result), is_object($response) ? get_class($response) : gettype($response));

            throw new \LogicException($msg);
        }

        $event->setResponse($response);
    }
}
