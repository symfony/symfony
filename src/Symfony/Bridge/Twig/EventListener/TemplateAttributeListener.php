<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\EventListener;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bridge\Twig\TemplateGuesser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Handles the Template annotation for actions.
 *
 * Depends on pre-processing of the ControllerListener.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateAttributeListener implements EventSubscriberInterface
{
    private $templateGuesser;
    private $twig;

    public function __construct(TemplateGuesser $templateGuesser, Environment $twig)
    {
        $this->templateGuesser = $templateGuesser;
        $this->twig = $twig;
    }

    /**
     * Guesses the template name to render and its variables and adds them to
     * the request object.
     */
    public function onKernelController(KernelEvent $event)
    {
        if (!$configuration = $this->getConfiguration($event)) {
            return;
        }

        if (!$configuration instanceof Template) {
            return;
        }

        $request = $event->getRequest();
        $controller = $event->getController();
        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }
        $configuration->setOwner($controller);

        // when no template has been given, try to resolve it based on the controller
        if (null === $configuration->getTemplate()) {
            $configuration->setTemplate($this->templateGuesser->guessTemplateName($controller, $request));
        }
    }

    /**
     * Renders the template and initializes a new response object with the
     * rendered template content.
     */
    public function onKernelView(KernelEvent $event)
    {
        if (!$configuration = $this->getConfiguration($event)) {
            return;
        }

        if (!$configuration instanceof Template) {
            return;
        }

        $request = $event->getRequest();
        $parameters = $event->getControllerResult();
        $owner = $configuration->getOwner();
        list($controller, $action) = $owner;

        // when the annotation declares no default vars and the action returns
        // null, all action method arguments are used as default vars
        if (null === $parameters) {
            $parameters = $this->resolveDefaultParameters($request, $configuration, $controller, $action);
        }

        // attempt to render the actual response
        if ($configuration->isStreamable()) {
            $callback = function () use ($configuration, $parameters) {
                $this->twig->display($configuration->getTemplate(), $parameters);
            };

            $event->setResponse(new StreamedResponse($callback));
        } else {
            $event->setResponse(new Response($this->twig->render($configuration->getTemplate(), $parameters)));
        }

        // make sure the owner (controller+dependencies) is not cached or stored elsewhere
        $configuration->setOwner([]);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', -128],
            KernelEvents::VIEW => 'onKernelView',
        ];
    }

    private function getConfiguration(KernelEvent $event): ?Template
    {
        $request = $event->getRequest();

        if ($configuration = $request->attributes->get('_template')) {
            return $configuration;
        }

        if (!$event instanceof ControllerEvent) {
            return null;
        }

        $controller = $event->getController();

        if (!\is_array($controller) && method_exists($controller, '__invoke')) {
            $controller = [$controller, '__invoke'];
        }

        if (!\is_array($controller)) {
            return null;
        }

        $className = \get_class($controller[0]);
        $object = new \ReflectionClass($className);
        $method = $object->getMethod($controller[1]);

        $configurations = array_map(
            function (\ReflectionAttribute $attribute) {
                return $attribute->newInstance();
            },
            $method->getAttributes(Template::class)
        );

        if (0 === count($configurations)) {
            return null;
        }

        $configuration = $configurations[0];
        $request->attributes->set('_template', $configuration);

        return $configuration;
    }

    private function resolveDefaultParameters(Request $request, Template $template, $controller, $action)
    {
        $parameters = [];
        $arguments = $template->getVars();

        if (0 === \count($arguments)) {
            $r = new \ReflectionObject($controller);

            $arguments = [];
            foreach ($r->getMethod($action)->getParameters() as $param) {
                $arguments[] = $param;
            }
        }

        // fetch the arguments of @Template.vars or everything if desired
        // and assign them to the designated template
        foreach ($arguments as $argument) {
            if ($argument instanceof \ReflectionParameter) {
                $parameters[$name = $argument->getName()] = !$request->attributes->has($name) && $argument->isDefaultValueAvailable() ? $argument->getDefaultValue() : $request->attributes->get($name);
            } else {
                $parameters[$argument] = $request->attributes->get($argument);
            }
        }

        return $parameters;
    }
}
