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
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
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
    private const RATE_LIMIT_ATTRIBUTE = '_rate_limit';

    /**
     * @param ServiceProviderInterface<RateLimiterFactoryInterface> $limiters
     */
    public function __construct(
        private readonly ServiceProviderInterface $limiters,
    ) {
    }

    /**
     * @param ControllerAttributeEvent<RateLimit, ControllerArgumentsEvent> $event
     */
    public function onKernelControllerAttribute(ControllerAttributeEvent $event, ?string $eventName = null, ?EventDispatcherInterface $dispatcher = null): void
    {
        $request = $event->kernelEvent->getRequest();
        $attribute = $event->attribute;

        if ($attribute->methods && !\in_array($request->getMethod(), $attribute->methods, true)) {
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

        $candidate = $attribute->exposeHeaders && null !== $rateLimit->getResetAt()
            ? new AppliedRateLimit($rateLimit, $attribute->tokens)
            : null;

        if (!$rateLimit->isAccepted()) {
            $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $candidate);

            if ($dispatcher && class_exists(RateLimitExceededEvent::class)) {
                $dispatcher->dispatch(new RateLimitExceededEvent($rateLimit, $attribute->limiter, $key));
            }

            throw new TooManyRequestsHttpException(max(0, $rateLimit->getRetryAfter()->getTimestamp() - time()));
        }

        if ($candidate) {
            /** @var AppliedRateLimit|null $applied */
            $applied = $request->attributes->get(self::RATE_LIMIT_ATTRIBUTE);

            if (!$applied instanceof AppliedRateLimit || $candidate->getRemainingCalls() < $applied->getRemainingCalls()) {
                $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $candidate);
            }
        }
    }

    /**
     * Adds the X-RateLimit-* headers to the response.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $applied = $event->getRequest()->attributes->get(self::RATE_LIMIT_ATTRIBUTE);

        if (!$event->isMainRequest() || !$applied instanceof AppliedRateLimit) {
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
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.RateLimit::class => 'onKernelControllerAttribute',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
