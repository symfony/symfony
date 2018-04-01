<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\EventListener;

use Symphony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\KernelEvents;

/**
 * Guarantees that the _controller key is parsed into its final format.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @deprecated since Symphony 4.1
 */
class ResolveControllerNameSubscriber implements EventSubscriberInterface
{
    private $parser;

    public function __construct(ControllerNameParser $parser)
    {
        $this->parser = $parser;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $controller = $event->getRequest()->attributes->get('_controller');
        if (is_string($controller) && false === strpos($controller, '::') && 2 === substr_count($controller, ':')) {
            // controller in the a:b:c notation then
            $event->getRequest()->attributes->set('_controller', $this->parser->parse($controller, false));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 24),
        );
    }
}
