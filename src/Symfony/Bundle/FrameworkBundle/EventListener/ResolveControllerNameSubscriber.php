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

use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Guarantees that the _controller key is parsed into its final format.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @deprecated since Symfony 4.1
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
