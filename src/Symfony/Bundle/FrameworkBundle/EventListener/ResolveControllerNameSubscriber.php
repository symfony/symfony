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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Guarantees that the _controller key is parsed into its final format.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @method onKernelRequest(RequestEvent $event)
 *
 * @deprecated since Symfony 4.1
 */
class ResolveControllerNameSubscriber implements EventSubscriberInterface
{
    private $parser;

    public function __construct(ControllerNameParser $parser, bool $triggerDeprecation = true)
    {
        if ($triggerDeprecation) {
            @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.1.', __CLASS__), \E_USER_DEPRECATED);
        }

        $this->parser = $parser;
    }

    /**
     * @internal
     */
    public function resolveControllerName(...$args)
    {
        $this->onKernelRequest(...$args);
    }

    public function __call(string $method, array $args)
    {
        if ('onKernelRequest' !== $method && 'onkernelrequest' !== strtolower($method)) {
            throw new \Error(sprintf('Error: Call to undefined method "%s::%s()".', static::class, $method));
        }

        $event = $args[0];

        $controller = $event->getRequest()->attributes->get('_controller');
        if (\is_string($controller) && !str_contains($controller, '::') && 2 === substr_count($controller, ':')) {
            // controller in the a:b:c notation then
            $event->getRequest()->attributes->set('_controller', $parsedNotation = $this->parser->parse($controller, false));

            @trigger_error(sprintf('Referencing controllers with %s is deprecated since Symfony 4.1, use "%s" instead.', $controller, $parsedNotation), \E_USER_DEPRECATED);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['resolveControllerName', 24],
        ];
    }
}
