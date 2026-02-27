<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\RuntimeException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles the IsGranted attribute on controllers.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class IsGrantedAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
    {
        $kernelEvent = $event->kernelEvent;

        if (!$kernelEvent instanceof ControllerArgumentsEvent) {
            return;
        }

        $this->processAttribute($event->attribute, $kernelEvent);
    }

    /**
     * @internal since Symfony 8.1, use onKernelControllerAttribute() instead
     */
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $attributes = [];
        foreach ($event->getAttributes() as $class => $attributes[]) {
            if (!is_a($class, IsGranted::class, true)) {
                array_pop($attributes);
            }
        }

        if (!$attributes = array_merge(...$attributes)) {
            return;
        }

        foreach ($attributes as $attribute) {
            $this->processAttribute($attribute, $event);
        }
    }

    private function processAttribute(IsGranted $attribute, ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();

        if ($attribute->methods && !\in_array($request->getMethod(), array_map('strtoupper', $attribute->methods), true)) {
            return;
        }

        $subject = null;

        if ($subjectRef = $attribute->subject) {
            if (\is_array($subjectRef)) {
                foreach ($subjectRef as $refKey => $ref) {
                    $subject[\is_string($refKey) ? $refKey : (string) $ref] = $this->getIsGrantedSubject($ref, $event);
                }
            } else {
                $subject = $this->getIsGrantedSubject($subjectRef, $event);
            }
        }
        $accessDecision = new AccessDecision();

        if (!$accessDecision->isGranted = $this->authChecker->isGranted($attribute->attribute, $subject, $accessDecision)) {
            $message = $attribute->message ?: $accessDecision->getMessage();

            if ($statusCode = $attribute->statusCode) {
                throw new HttpException($statusCode, $message, code: $attribute->exceptionCode ?? 0);
            }

            $e = new AccessDeniedException($message, code: $attribute->exceptionCode ?? 403);
            $e->setAttributes([$attribute->attribute]);
            $e->setSubject($subject);
            $e->setAccessDecision($accessDecision);

            throw $e;
        }
    }

    public static function getSubscribedEvents(): array
    {
        if (!class_exists(ControllerAttributesListener::class, false)) {
            return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 20]];
        }

        return [
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.IsGranted::class => 'onKernelControllerAttribute',
        ];
    }

    private function getIsGrantedSubject(string|Expression|\Closure $subjectRef, ControllerArgumentsEvent $event): mixed
    {
        if ($subjectRef instanceof \Closure || $subjectRef instanceof Expression) {
            return $event->evaluate($subjectRef, $this->expressionLanguage);
        }

        $arguments = $event->getNamedArguments();

        if (!\array_key_exists($subjectRef, $arguments)) {
            throw new RuntimeException(\sprintf('Could not find the subject "%s" for the #[IsGranted] attribute. Try adding a "$%s" argument to your controller method.', $subjectRef, $subjectRef));
        }

        return $arguments[$subjectRef];
    }
}
