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
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class TemplateAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        if (!$event->kernelEvent instanceof ViewEvent) {
            return;
        }

        if (!$event->kernelEvent->getRequest()->attributes->has('_template')) {
            $event->kernelEvent->getRequest()->attributes->set('_template', $event->attribute);
        }

        $this->onKernelView($event->kernelEvent);
    }

    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function onKernelView(ViewEvent $event): void
    {
        $parameters = $event->getControllerResult();

        if (!\is_array($parameters ?? [])) {
            return;
        }
        $attribute = $event->getRequest()->attributes->get('_template');

        if (!$attribute instanceof Template && !$attribute = $event->{class_exists(ControllerArgumentsMetadata::class, false) ? 'controllerMetadata' : 'controllerArgumentsEvent'}?->getAttributes(Template::class)[0] ?? null) {
            return;
        }

        $parameters ??= $this->resolveParameters($event->{class_exists(ControllerArgumentsMetadata::class, false) ? 'controllerMetadata' : 'controllerArgumentsEvent'}, $attribute->vars);
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
            ? new StreamedResponse(
                null !== $attribute->block
                    ? fn () => $this->twig->load($attribute->template)->displayBlock($attribute->block, $parameters)
                    : fn () => $this->twig->display($attribute->template, $parameters),
                $status)
            : new Response(
                null !== $attribute->block
                    ? $this->twig->load($attribute->template)->renderBlock($attribute->block, $parameters)
                    : $this->twig->render($attribute->template, $parameters),
                $status)
        );
    }

    public static function getSubscribedEvents(): array
    {
        if (!class_exists(ControllerAttributesListener::class, false)) {
            return [
                KernelEvents::VIEW => ['onKernelView', -128],
            ];
        }

        return [
            KernelEvents::VIEW.'.'.Template::class => 'onKernelControllerAttribute',
        ];
    }

    private function resolveParameters(ControllerArgumentsMetadata|ControllerArgumentsEvent $controllerMetadata, ?array $vars): array
    {
        if ([] === $vars) {
            return [];
        }

        $parameters = $controllerMetadata->getNamedArguments();

        if (null !== $vars) {
            $parameters = array_intersect_key($parameters, array_flip($vars));
        }

        return $parameters;
    }
}
