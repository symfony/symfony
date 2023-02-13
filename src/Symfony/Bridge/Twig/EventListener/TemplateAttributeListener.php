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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TemplateAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @return void
     */
    public function onKernelView(ViewEvent $event)
    {
        $parameters = $event->getControllerResult();

        if (!\is_array($parameters ?? [])) {
            return;
        }
        $attribute = $event->getRequest()->attributes->get('_template');

        if (!$attribute instanceof Template && !$attribute = $event->controllerArgumentsEvent?->getAttributes()[Template::class][0] ?? null) {
            return;
        }

        $parameters ??= $this->resolveParameters($event->controllerArgumentsEvent, $attribute->vars);
        $status = 200;

        foreach ($parameters as $k => $v) {
            if (!$v instanceof FormInterface) {
                continue;
            }
            if ($v->isSubmitted() && !$v->isValid()) {
                $status = 422;
            }
            $parameters[$k] = $v->createView();
        }

        $event->setResponse($attribute->stream
            ? new StreamedResponse(fn () => $this->twig->display($attribute->template, $parameters), $status)
            : new Response($this->twig->render($attribute->template, $parameters), $status)
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['onKernelView', -128],
        ];
    }

    private function resolveParameters(ControllerArgumentsEvent $event, ?array $vars): array
    {
        if ([] === $vars) {
            return [];
        }

        $parameters = $event->getNamedArguments();

        if (null !== $vars) {
            $parameters = array_intersect_key($parameters, array_flip($vars));
        }

        return $parameters;
    }
}
