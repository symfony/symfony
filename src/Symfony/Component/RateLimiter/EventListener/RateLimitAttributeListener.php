<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\Attribute\RateLimit;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\RuntimeException;

/**
 * Rate limit attribute listener for controller
 *
 * @see https://symfony.com/doc/current/rate_limiter.html
 *
 * @author Raziel Rodrigues <raziel.rodrigues@outlook.pt>
 */
class RateLimitAttributeListener implements EventSubscriberInterface
{
    public function __construct(
        # private readonly AuthorizationCheckerInterface $authChecker,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        /** @var RateLimit[] $attributes */
        if (!\is_array($attributes = $event->getAttributes()[RateLimit::class] ?? null)) {
            return;
        }

        $request = $event->getRequest();
        $arguments = $event->getNamedArguments();

        foreach ($attributes as $attribute) {
            $subject = null;

            if ($subjectRef = $attribute->subject) {
                if (\is_array($subjectRef)) {
                    foreach ($subjectRef as $refKey => $ref) {
                        $subject[\is_string($refKey) ? $refKey : (string) $ref] = $this->getRateLimitSubject($ref, $request, $arguments);
                    }
                } else {
                    $subject = $this->getRateLimitSubject($subjectRef, $request, $arguments);
                }
            }
/*             $accessDecision = new AccessDecision();

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
            } */
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 20]];
    }

    private function getRateLimitSubject(string|Expression|\Closure $subjectRef, Request $request, array $arguments): mixed
    {
        if ($subjectRef instanceof \Closure) {
            return $subjectRef($arguments, $request);
        }

        if ($subjectRef instanceof Expression) {
            $this->expressionLanguage ??= new ExpressionLanguage();

            return $this->expressionLanguage->evaluate($subjectRef, [
                'request' => $request,
                'args' => $arguments,
            ]);
        }

        if (!\array_key_exists($subjectRef, $arguments)) {
            throw new RuntimeException(\sprintf('Could not find the subject "%s" for the #[RateLimit] attribute. Try adding a "$%s" argument to your controller method.', $subjectRef, $subjectRef));
        }

        return $arguments[$subjectRef];
    }
}
