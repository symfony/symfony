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
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Handles the RateLimit attribute on controllers.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
final class RateLimitAttributeListener implements EventSubscriberInterface
{
    /**
     * @param ServiceProviderInterface<RateLimiterFactoryInterface> $limiters
     */
    public function __construct(
        private readonly ServiceProviderInterface $limiters,
    ) {
    }

    /**
     * @param ControllerAttributeEvent<RateLimit> $event
     */
    public function onKernelControllerAttribute(ControllerAttributeEvent $event): void
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

        if (!$rateLimit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(0, $rateLimit->getRetryAfter()->getTimestamp() - time()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS.'.'.RateLimit::class => 'onKernelControllerAttribute',
        ];
    }
}
