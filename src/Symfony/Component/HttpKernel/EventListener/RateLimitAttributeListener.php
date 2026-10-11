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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\RateLimiter\AppliedRateLimit;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Handles the RateLimit attribute on controllers.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
final class RateLimitAttributeListener implements EventSubscriberInterface
{
    /** @internal */
    public const RATE_LIMIT_ATTRIBUTE = '_rate_limit';

    /**
     * @var \WeakMap<Request, list<RateLimit>>
     */
    private \WeakMap $consumedBeforeArguments;

    /**
     * @param ServiceProviderInterface<RateLimiterFactoryInterface> $limiters
     * @param ExpressionLanguage|null                               $expressionLanguage The one that evaluates the keys and the conditions, which knows the variables of the expressions it compiled
     */
    public function __construct(
        private readonly ServiceProviderInterface $limiters,
        private ?ExpressionLanguage $expressionLanguage = null,
    ) {
        $this->consumedBeforeArguments = new \WeakMap();
    }

    /**
     * Consumes tokens as soon as the controller is known, or once its arguments are resolved when the key or the condition reads them.
     *
     * @param ControllerAttributeEvent<RateLimit, ControllerEvent|ControllerArgumentsEvent> $event
     */
    public function onKernelControllerAttribute(ControllerAttributeEvent $event, ?string $eventName = null, ?EventDispatcherInterface $dispatcher = null): void
    {
        $request = $event->kernelEvent->getRequest();
        $attribute = $event->attribute;

        if ($event->kernelEvent instanceof ControllerEvent) {
            if ($this->readsArguments($attribute->key) || $this->readsArguments($attribute->if)) {
                return;
            }

            $this->consumedBeforeArguments[$request] = [...$this->consumedBeforeArguments[$request] ?? [], $attribute];
        } elseif (\in_array($attribute, $this->consumedBeforeArguments[$request] ?? [], true)) {
            return;
        }

        if ($attribute->methods && !\in_array($request->getMethod(), $attribute->methods, true)) {
            return;
        }

        if (!\is_bool($if = $event->evaluate($attribute->if))) {
            throw new \TypeError(\sprintf('The value of the "$if" option of the "%s" attribute must evaluate to a boolean, "%s" given.', RateLimit::class, get_debug_type($if)));
        }

        if (!$if) {
            return;
        }

        if (!$this->limiters->has($attribute->limiter)) {
            throw new \InvalidArgumentException(\sprintf('Rate limiter "%s" does not exist. Did you forget to configure it? Available limiters: "%s".', $attribute->limiter, implode('", "', array_keys($this->limiters->getProvidedServices()))));
        }

        if (null === $attribute->key) {
            $key = ($request->getClientIp() ?? 'unknown').'~'.$request->getMethod().'~'.$request->getPathInfo();
        } elseif (!\is_string($key = $event->evaluate($attribute->key))) {
            throw new \TypeError(\sprintf('The value of the "$key" option of the "%s" attribute must evaluate to a string, "%s" given.', RateLimit::class, get_debug_type($key)));
        }

        $rateLimit = $this->limiters->get($attribute->limiter)->create($key)->consume($attribute->tokens);

        $candidate = new AppliedRateLimit($rateLimit, $attribute->tokens, $attribute->limiter, $attribute->exposeHeaders);

        $applied = $request->attributes->get(self::RATE_LIMIT_ATTRIBUTE, []);
        $applied = \is_array($applied) ? $applied : [];
        $applied[] = $candidate;
        $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $applied);

        if (!$rateLimit->isAccepted()) {
            if ($dispatcher && class_exists(RateLimitExceededEvent::class)) {
                $dispatcher->dispatch(new RateLimitExceededEvent($rateLimit, $attribute->limiter, $key));
            }

            throw new TooManyRequestsHttpException(max(0, $rateLimit->getRetryAfter()->getTimestamp() - time()));
        }
    }

    /**
     * Adds the X-RateLimit-* headers to the response.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $applied = $event->getRequest()->attributes->get(self::RATE_LIMIT_ATTRIBUTE, []);

        if (!$event->isMainRequest() || !\is_array($applied) || !($applied = $this->getMostRestrictiveExposedRateLimit($applied))) {
            return;
        }

        $response = $event->getResponse();

        $headers = [
            'X-RateLimit-Limit' => intdiv($applied->rateLimit->getLimit(), $applied->tokens),
            'X-RateLimit-Remaining' => max(0, (int) $applied->getRemainingCalls()),
            'X-RateLimit-Reset' => $applied->rateLimit->getResetAt()->getTimestamp(),
        ];

        foreach (array_keys($headers) as $name) {
            if ($response->headers->has($name)) {
                return;
            }
        }

        $response->setPrivate();
        $response->headers->add($headers);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER.'.'.RateLimit::class => 'onKernelControllerAttribute',
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.RateLimit::class => 'onKernelControllerAttribute',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    private function readsArguments(string|bool|Expression|\Closure|null $value): bool
    {
        if (!$value instanceof Expression) {
            return $value instanceof \Closure;
        }

        try {
            ($this->expressionLanguage ??= new ExpressionLanguage())->lint($value, ['request', 'this'], Parser::IGNORE_UNKNOWN_FUNCTIONS);
        } catch (SyntaxError) {
            return true;
        }

        return false;
    }

    /**
     * @param list<AppliedRateLimit> $applied
     */
    private function getMostRestrictiveExposedRateLimit(array $applied): ?AppliedRateLimit
    {
        // Rejection stops attribute processing, so the rejecting result is last and takes precedence.
        $last = end($applied);
        if ($last instanceof AppliedRateLimit && !$last->rateLimit->isAccepted()) {
            return $last->exposeHeaders && null !== $last->rateLimit->getResetAt() ? $last : null;
        }

        $selected = null;
        foreach ($applied as $candidate) {
            if (!$candidate instanceof AppliedRateLimit || !$candidate->exposeHeaders || null === $candidate->rateLimit->getResetAt()) {
                continue;
            }

            if (!$selected || $candidate->getRemainingCalls() < $selected->getRemainingCalls()) {
                $selected = $candidate;
            }
        }

        return $selected;
    }
}
