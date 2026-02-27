<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\IsSignatureValid;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles the IsSignatureValid attribute.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
class IsSignatureValidAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        $kernelEvent = $event->kernelEvent;

        if (!$kernelEvent instanceof ControllerArgumentsEvent) {
            return;
        }

        $this->processAttribute($event->attribute, $kernelEvent->getRequest());
    }

    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        foreach ($event->getAttributes(IsSignatureValid::class) as $attribute) {
            $this->processAttribute($attribute, $request);
        }
    }

    private function processAttribute(IsSignatureValid $attribute, Request $request): void
    {
        $methods = array_map('strtoupper', $attribute->methods);
        if ($methods && !\in_array($request->getMethod(), $methods, true)) {
            return;
        }

        $this->uriSigner->verify($request);
    }

    public static function getSubscribedEvents(): array
    {
        if (!class_exists(ControllerAttributesListener::class, false)) {
            return [
                KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 30],
            ];
        }

        return [
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.IsSignatureValid::class => 'onKernelControllerAttribute',
        ];
    }
}
