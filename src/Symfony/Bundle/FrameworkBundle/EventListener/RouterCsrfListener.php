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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Routing\Generator\UrlOptionsInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Checks that routes with csrf_protect enabled provide the _csrf_token parameter
 * with a correct value.
 *
 * @author Grégoire Passault <g.passault@gmail.com>
 */
class RouterCsrfListener implements EventSubscriberInterface
{
    private $router;
    private $csrfManager;

    /**
     * Constructor.
     *
     * @param Router The router
     * @param CsrfProviderInterface The CSRF provider service
     */
    public function __construct($router, CsrfTokenManagerInterface $csrfManager)
    {
        $this->router = $router;
        $this->csrfManager = $csrfManager;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $attributes = $request->attributes;

        if ($attributes->has('_route')) {
            $route = $attributes->get('_route');
            $generator = $this->router->getGenerator();

            if ($generator instanceof UrlOptionsInterface) {
                $options = $generator->getOptions($route);

                if (isset($options['csrf_protect']) && $options['csrf_protect']) {
                    $query = $request->query;

                    if (!$query->has('_csrf_token') ||
                        !$this->csrfManager->isTokenValid(new CsrfToken($route, $query->get('_csrf_token')))) {
                            throw new BadRequestHttpException('Invalid CSRF token passed');
                    }
                }
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 0)),
        );
    }
}
